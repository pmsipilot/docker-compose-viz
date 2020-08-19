<?php

namespace PMSIpilot\DockerComposeViz;

use Graphp\GraphViz\GraphViz;
use Symfony\Component\Console;

$application = new Console\Application();

$application->register('render')
    ->addArgument('input-file', Console\Input\InputArgument::OPTIONAL, 'Path to a docker compose file', getcwd().DIRECTORY_SEPARATOR.'docker-compose.yml')

    ->addOption('override', null, Console\Input\InputOption::VALUE_REQUIRED, 'Tag of the override file to use', 'override')
    ->addOption('output-file', 'o', Console\Input\InputOption::VALUE_REQUIRED, 'Path to a output file (Only for "dot" and "image" output format)')
    ->addOption('output-format', 'm', Console\Input\InputOption::VALUE_REQUIRED, 'Output format (one of: "dot", "image", "display", "graphviz")', 'display')
    ->addOption('graphviz-output-format', null, Console\Input\InputOption::VALUE_REQUIRED, 'GraphViz Output format (see `man dot` for details)', 'svg')
    ->addOption('only', null, Console\Input\InputOption::VALUE_IS_ARRAY | Console\Input\InputOption::VALUE_REQUIRED, 'Display a graph only for a given services')

    ->addOption('force', 'f', Console\Input\InputOption::VALUE_NONE, 'Overwrites output file if it already exists')
    ->addOption('no-volumes', null, Console\Input\InputOption::VALUE_NONE, 'Do not display volumes')
    ->addOption('no-networks', null, Console\Input\InputOption::VALUE_NONE, 'Do not display networks')
    ->addOption('no-ports', null, Console\Input\InputOption::VALUE_NONE, 'Do not display ports')
    ->addOption('horizontal', 'r', Console\Input\InputOption::VALUE_NONE, 'Display a horizontal graph')
    ->addOption('ignore-override', null, Console\Input\InputOption::VALUE_NONE, 'Ignore override file')
    ->addOption('background', null, Console\Input\InputOption::VALUE_REQUIRED, 'Set the graph background color', '#ffffff')

    ->setCode(function (Console\Input\InputInterface $input, Console\Output\OutputInterface $output) {
        $backgroundColor = $input->getOption('background');

        if (0 === preg_match('/^#[a-fA-F0-9]{6}|transparent$/', $backgroundColor)) {
            throw new Console\Exception\InvalidArgumentException(sprintf('Invalid background color "%s". It must be a valid hex color or "transparent".', $backgroundColor));
        }

        $logger = logger($output);
        $inputFile = $input->getArgument('input-file');
        $inputFileExtension = pathinfo($inputFile, PATHINFO_EXTENSION);
        $overrideFile = dirname($inputFile).DIRECTORY_SEPARATOR.basename($inputFile, '.'.$inputFileExtension).'.'.$input->getOption('override').'.'.$inputFileExtension;

        $outputFormat = $input->getOption('output-format');
        $outputFile = $input->getOption('output-file') ?: getcwd().DIRECTORY_SEPARATOR.'docker-compose.'.('dot' === $outputFormat ? $outputFormat : 'png');
        $onlyServices = $input->getOption('only');

        if (false === in_array($outputFormat, ['dot', 'image', 'display', 'graphviz'])) {
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

        $logger(sprintf('Reading <comment>configuration</comment> from <info>"%s"</info>', $inputFile));
        $configuration = readConfiguration($inputFile);
        $configurationVersion = (string) ($configuration['version'] ?? 1);

        if (!$input->getOption('ignore-override') && file_exists($overrideFile)) {
            $logger(sprintf('Reading <comment>override</comment> from <info>"%s"</info>', $overrideFile));
            $override = readConfiguration($overrideFile);
            $overrideVersion = (string) ($override['version'] ?? 1);

            if ($configurationVersion !== $overrideVersion) {
                throw new Console\Exception\LogicException(sprintf('Version mismatch: file "%s" specifies version "%s" but file "%s" uses version "%s"', $inputFile, $configurationVersion, $overrideFile, $overrideVersion));
            }

            $configuration = array_merge_recursive($configuration, $override);

            $logger(sprintf('Configuration <comment>version</comment> is <info>"%s"</info>', $configurationVersion), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);
            $configuration['version'] = $configurationVersion;
        }

        $logger('Fetching <comment>services</comment>');
        $services = fetchServices($configuration);
        $logger(sprintf('Found <info>%d</info> <comment>services</comment>', count($services)), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        $logger('Fetching <comment>volumes</comment>');
        $volumes = fetchVolumes($configuration);
        $logger(sprintf('Found <info>%d</info> <comment>volumes</comment>', count($volumes)), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        $logger('Fetching <comment>networks</comment>');
        $networks = fetchNetworks($configuration);
        $logger(sprintf('Found <info>%d</info> <comment>networks</comment>', count($networks)), Console\Output\OutputInterface::VERBOSITY_VERY_VERBOSE);

        if ([] !== $onlyServices) {
            $logger(sprintf('Only <info>%s</info> <comment>services</comment> will be displayed', implode(', ', $onlyServices)));

            $intersect = array_intersect($onlyServices, array_keys($services));

            if ($intersect !== $onlyServices) {
                throw new Console\Exception\InvalidArgumentException(sprintf('The following services do not exist: "%s"', implode('", "', array_diff($onlyServices, $intersect))));
            }

            $services = array_filter(
                $services,
                function ($service) use ($onlyServices) {
                    return in_array($service, $onlyServices);
                },
                ARRAY_FILTER_USE_KEY
            );
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

        $logger('Rendering <comment>graph</comment>');
        $graph = applyGraphvizStyle(
            createGraph($services, $volumes, $networks, $inputFile, $flags),
            $input->getOption('horizontal'),
            $input->getOption('background')
        );

        switch ($outputFormat) {
            case 'dot':
            case 'image':
                $rendererClass = 'Graphp\GraphViz\\'.ucfirst($outputFormat);
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

$application->run();
