<?php

namespace PMSIpilot\DockerComposeViz;

use Graphp\GraphViz\GraphViz;
use Symfony\Component\Console;

$application = new Console\Application();

$application->register('render')
    ->addArgument('input-file', Console\Input\InputArgument::OPTIONAL, 'Path to a docker compose file', getcwd().DIRECTORY_SEPARATOR.'docker-compose.yml')

    ->addOption('override', null, Console\Input\InputOption::VALUE_REQUIRED, 'Tag of the override file to use', 'override')
    ->addOption('output-file', 'o', Console\Input\InputOption::VALUE_REQUIRED, 'Path to a output file (Only for "dot" and "image" output format)')
    ->addOption('output-format', 'm', Console\Input\InputOption::VALUE_REQUIRED, 'Output format (one of: "dot", "image", "display")', 'display')
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

        if (preg_match('/^#[a-fA-F0-9]{6}|transparent$/', $backgroundColor) === 0) {
            throw new Console\Exception\InvalidArgumentException(sprintf('Invalid background color "%s". It must be a valid hex color or "transparent".', $backgroundColor));
        }

        $inputFile = $input->getArgument('input-file');
        $inputFileExtension = pathinfo($inputFile, PATHINFO_EXTENSION);
        $overrideFile = dirname($inputFile).DIRECTORY_SEPARATOR.basename($inputFile, '.'.$inputFileExtension).'.'.$input->getOption('override').'.'.$inputFileExtension;

        $outputFormat = $input->getOption('output-format');
        $outputFile = $input->getOption('output-file') ?: getcwd().DIRECTORY_SEPARATOR.'docker-compose.'.($outputFormat === 'dot' ? $outputFormat : 'png');
        $onlyServices = $input->getOption('only');

        if (in_array($outputFormat, ['dot', 'image', 'display']) === false) {
            throw new Console\Exception\InvalidArgumentException(sprintf('Invalid output format "%s". It must be one of "dot", "image" or "display".', $outputFormat));
        }

        if ($outputFormat === 'display') {
            if ($input->getOption('force') || $input->getOption('output-file')) {
                $output->writeln('<comment>The following options are ignored with the "display" output format: "--force", "--output-file"</comment>');
            }
        } else {
            if (file_exists($outputFile) === true && $input->getOption('force') === false) {
                throw new Console\Exception\InvalidArgumentException(sprintf('File "%s" already exists. Use the "--force" option to overwrite it.', $outputFile));
            }
        }

        $configuration = readConfiguration($inputFile);
        $configurationVersion = (string) ($configuration['version'] ?? 1);

        if (!$input->getOption('ignore-override') && file_exists($overrideFile)) {
            $override = readConfiguration($overrideFile);
            $overrideVersion = (string) ($override['version'] ?? 1);

            if ($configurationVersion !== $overrideVersion) {
                throw new Console\Exception\LogicException(sprintf('Version mismatch: file "%s" specifies version "%s" but file "%s" uses version "%s"', $inputFile, $configurationVersion, $overrideFile, $overrideVersion));
            }

            $configuration = array_merge_recursive($configuration, $override);
            $configuration['version'] = $configurationVersion;
        }

        $services = fetchServices($configuration);
        $volumes = fetchVolumes($configuration);
        $networks = fetchNetworks($configuration);

        if ([] !== $onlyServices) {
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
        if ($input->getOption('no-volumes') === true) {
            $flags |= WITHOUT_VOLUMES;
        }

        if ($input->getOption('no-networks') === true) {
            $flags |= WITHOUT_NETWORKS;
        }

        if ($input->getOption('no-ports') === true) {
            $flags |= WITHOUT_PORTS;
        }

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
        }
    });

$application->run();
