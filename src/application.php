<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz;

use Graphp\GraphViz\GraphViz;
use Symfony\Component\Console;

$application = new Console\Application();

$application->register('render')
    ->addArgument('input-file', Console\Input\InputArgument::OPTIONAL | Console\Input\InputArgument::IS_ARRAY, 'Path to a docker compose file', [getcwd() . DIRECTORY_SEPARATOR . 'docker-compose.yml'])
    ->addOption('output-file', 'o', Console\Input\InputOption::VALUE_REQUIRED, 'Path to a output file (Only for "dot" and "image" output format)')
    ->addOption('output-format', 'm', Console\Input\InputOption::VALUE_REQUIRED, 'Output format (one of: "dot", "image", "display", "graphviz")', 'display')
    ->addOption('graphviz-output-format', null, Console\Input\InputOption::VALUE_REQUIRED, 'GraphViz Output format (see `man dot` for details)', 'svg')
    ->addOption('include', null, Console\Input\InputOption::VALUE_IS_ARRAY | Console\Input\InputOption::VALUE_REQUIRED, 'Display a graph only for given services')
    ->addOption('exclude', null, Console\Input\InputOption::VALUE_IS_ARRAY | Console\Input\InputOption::VALUE_REQUIRED, 'Display a graph without the given services')
    ->addOption('force', 'f', Console\Input\InputOption::VALUE_NONE, 'Overwrites output file if it already exists')
    ->addOption('no-volumes', null, Console\Input\InputOption::VALUE_NONE, 'Do not display volumes')
    ->addOption('no-networks', null, Console\Input\InputOption::VALUE_NONE, 'Do not display networks')
    ->addOption('no-ports', null, Console\Input\InputOption::VALUE_NONE, 'Do not display ports')
    ->addOption('no-secrets', null, Console\Input\InputOption::VALUE_NONE, 'Do not display secrets')
    ->addOption('no-configs', null, Console\Input\InputOption::VALUE_NONE, 'Do not display configs')
    ->addOption('horizontal', 'r', Console\Input\InputOption::VALUE_NONE, 'Display a horizontal graph')
    ->addOption('ignore-override', null, Console\Input\InputOption::VALUE_NONE, 'Ignore override file')
    ->addOption('background', null, Console\Input\InputOption::VALUE_REQUIRED, 'Set the graph background color', '#ffffff')
    ->setCode(function (Console\Input\InputInterface $input, Console\Output\OutputInterface $output) {
        $backgroundColor = $input->getOption('background');

        if (0 === preg_match('/^#[a-fA-F0-9]{6}|transparent$/', $backgroundColor)) {
            throw new Console\Exception\InvalidArgumentException(sprintf('Invalid background color "%s". It must be a valid hex color or "transparent".', $backgroundColor));
        }

        $logger = logger($output);
        $inputFiles = $input->getArgument('input-file');


        $outputFormat = $input->getOption('output-format');
        $outputFile = $input->getOption('output-file') ?: getcwd() . DIRECTORY_SEPARATOR . 'docker-compose.' . ('dot' === $outputFormat ? $outputFormat : 'png');
        $includeServices = $input->getOption('include');
        $excludeServices = $input->getOption('exclude');

        if (false === in_array($outputFormat, ['dot', 'image', 'display', 'graphviz'], true)) {
            throw new Console\Exception\InvalidArgumentException(sprintf('Invalid output format "%s". It must be one of "dot", "image" or "display".', $outputFormat));
        }

        if ('display' === $outputFormat) {
            if ($input->getOption('force') || $input->getOption('output-file')) {
                $output->writeln('<comment>The following options are ignored with the "display" output format: "--force", "--output-file"</comment>');
            }
        } else {
            if (true === file_exists($outputFile) && false === $input->getOption('force')) {
                throw new Console\Exception\InvalidArgumentException(sprintf('File "%s" already exists. Use the "--force" option to overwrite it.', $outputFile));
            }
        }

        $files = findConfigurationFiles($input->getOption('ignore-override'), ...$inputFiles);
        $logger(sprintf('Reading <comment>configuration</comment> from <info>"%s"</info>', implode(', ', $files)));
        $configuration = readConfigurations(...$files);

        $logger('Fetching <comment>services</comment>');
        $services = fetchServices($configuration);
        $logger(sprintf('Found <info>%d</info> <comment>services</comment>', count($services)), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        $logger('Fetching <comment>volumes</comment>');
        $volumes = fetchVolumes($configuration);
        $logger(sprintf('Found <info>%d</info> <comment>volumes</comment>', count($volumes)), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        $logger('Fetching <comment>networks</comment>');
        $networks = fetchNetworks($configuration);
        $logger(sprintf('Found <info>%d</info> <comment>networks</comment>', count($networks)), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        $logger('Fetching <comment>configs</comment>');
        $configs = fetchConfigs($configuration);
        $logger(sprintf('Found <info>%d</info> <comment>configs</comment>', count($configs)), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        $logger('Fetching <comment>secrets</comment>');
        $secrets = fetchSecrets($configuration);
        $logger(sprintf('Found <info>%d</info> <comment>secrets</comment>', count($secrets)), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        if ([] !== $includeServices) {
            $logger(sprintf('Only <info>%s</info> <comment>services</comment> will be displayed', implode(', ', $includeServices)));
        }

        if ([] !== $excludeServices) {
            $logger(sprintf('Services <info>%s</info> will not be displayed', implode(', ', $excludeServices)));
        }

        $flags = 0;
        if (true === $input->getOption('no-volumes')) {
            $logger('<comment>Volumes</comment> will not be displayed');

            $flags |= WITHOUT_VOLUMES;
        }

        if (true === $input->getOption('no-networks')) {
            $logger('<comment>Networks</comment> will not be displayed');

            $flags |= WITHOUT_NETWORKS;
        }

        if (true === $input->getOption('no-ports')) {
            $logger('<comment>Ports</comment> will not be displayed');

            $flags |= WITHOUT_PORTS;
        }

        if (true === $input->getOption('no-secrets')) {
            $logger('<comment>Secrets</comment> will not be displayed');

            $flags |= WITHOUT_SECRETS;
        }

        if (true === $input->getOption('no-configs')) {
            $logger('<comment>Configs</comment> will not be displayed');

            $flags |= WITHOUT_CONFIGS;
        }

        $logger('Rendering <comment>graph</comment>');
        $graph = applyGraphvizStyle(
            createGraph($services, $volumes, $networks, $configs, $secrets, $flags, $inputFiles[0]),
            $input->getOption('horizontal'),
            $input->getOption('background')
        );

        if ([] !== $includeServices) {
            foreach ($graph->getVertices() as $vertex) {
                if ($vertex->getAttribute('docker_compose_viz.type') !== 'service') {
                    continue;
                }

                if (!in_array($vertex->getId(), $includeServices, true)) {
                    $vertex->destroy();
                }
            }
        }

        if ([] !== $excludeServices) {
            foreach ($graph->getVertices() as $vertex) {
                if ($vertex->getAttribute('docker_compose_viz.type') !== 'service') {
                    continue;
                }

                if (in_array($vertex->getId(), $includeServices, true)) {
                    $vertex->destroy();
                }
            }
        }

        switch ($outputFormat) {
            case 'dot':
            case 'image':
                $rendererClass = 'Graphp\GraphViz\\' . ucfirst($outputFormat);
                $renderer = new $rendererClass();

                file_put_contents($outputFile, $renderer->getOutput($graph));
                break;

            case 'display':
                $renderer = new GraphViz();
                $renderer->display($graph);
                break;

            case 'graphviz':
                $renderer = new GraphViz();
                $format = $input->getOption('graphviz-output-format');

                file_put_contents($outputFile, $renderer->setFormat($format)->createImageData($graph));
                break;
        }
    });

return $application;
