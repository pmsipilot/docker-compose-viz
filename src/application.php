<?php

namespace PMSIpilot\DockerComposeViz;

use Graphp\GraphViz\GraphViz;
use Symfony\Component\Console;
use function PMSIpilot\DockerComposeViz\readConfiguration;
use function PMSIpilot\DockerComposeViz\fetchServices;
use function PMSIpilot\DockerComposeViz\fetchVolumes;
use function PMSIpilot\DockerComposeViz\fetchNetworks;
use function PMSIpilot\DockerComposeViz\createGraph;
use function PMSIpilot\DockerComposeViz\applyGraphvizStyle;

$application = new Console\Application();

$application->register('render')
    ->addArgument('input-file', Console\Input\InputArgument::OPTIONAL, 'Path to a docker compose file', getcwd().DIRECTORY_SEPARATOR.'docker-compose.yml')

    ->addOption('output-file', 'o', Console\Input\InputOption::VALUE_REQUIRED, 'Path to a output file (Only for "dot" and "image" output format)')
    ->addOption('output-format', 'm', Console\Input\InputOption::VALUE_REQUIRED, 'Output format (one of: "dot", "image", "display")', 'display')
    ->addOption('only', null, Console\Input\InputOption::VALUE_IS_ARRAY | Console\Input\InputOption::VALUE_REQUIRED, 'Display a graph only for a given services')

    ->addOption('force', 'f', Console\Input\InputOption::VALUE_NONE, 'Overwrites output file if it already exists')
    ->addOption('no-volumes', null, Console\Input\InputOption::VALUE_NONE, 'Do not display volumes')
    ->addOption('horizontal', 'r', Console\Input\InputOption::VALUE_NONE, 'Display a horizontal graph')

    ->setCode(function (Console\Input\InputInterface $input, Console\Output\OutputInterface $output) {
        $inputFile = $input->getArgument('input-file');
        $outputFormat = $input->getOption('output-format');
        $outputFile = $input->getOption('output-file') ?: getcwd().DIRECTORY_SEPARATOR.'docker-compose.'.($outputFormat === 'dot' ? $outputFormat : 'png');
        $onlyServices = $input->getOption('only');

        if (in_array($outputFormat, ['dot', 'image', 'display']) === false) {
            throw new Console\Exception\InvalidArgumentException(sprintf('Invalid output format "%s". It must be one of "dot", "png" or "display".', $outputFormat));
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

        $graph = applyGraphvizStyle(
            createGraph($services, $volumes, $networks, $input->getOption('no-volumes') === false),
            $input->getOption('horizontal')
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
