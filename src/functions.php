<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz;

use Fhaculty\Graph\Graph;
use InvalidArgumentException;
use Iterator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

const WITHOUT_VOLUMES = 1;
const WITHOUT_NETWORKS = 2;
const WITHOUT_PORTS = 4;
const WITHOUT_CONFIGS = 8;
const WITHOUT_SECRETS = 16;

function logger(OutputInterface $output): callable
{
    return function (string $message, int $verbosity = null) use ($output) {
        $output->writeln(sprintf('[%s] %s', date(DATE_ISO8601), $message), $verbosity ?: OutputInterface::VERBOSITY_VERBOSE);
    };
}

function findOverride(string $path): ?string
{
    $inputFileExtension = pathinfo($path, PATHINFO_EXTENSION);
    $overrideFile = dirname($path) . DIRECTORY_SEPARATOR . basename($path, '.' . $inputFileExtension) . '.override.' . $inputFileExtension;

    if (file_exists($overrideFile)) {
        return $overrideFile;
    }

    return null;
}

function findConfigurationFiles(bool $ignoreOverride = false, string ...$paths): array
{
    $files = [];

    foreach ($paths as $path) {
        if (false === file_exists($path)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist', $path));
        }

        $files[] = $path;

        if (false === $ignoreOverride) {
            $override = findOverride($path);

            if (null !== $override) {
                $files[] = $override;
            }
        }
    }

    return $files;
}

function readConfigurations(string ...$paths): array
{
    $configuration = [];

    foreach ($paths as $path) {
        if (false === file_exists($path)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist', $path));
        }

        try {
            $configuration = array_merge_recursive($configuration, Yaml::parse(file_get_contents($path)));
        } catch (ParseException $exception) {
            throw new InvalidArgumentException(sprintf('File "%s" does not contain valid YAML', $path), $exception->getCode(), $exception);
        }
    }

    return $configuration;
}

function createGraph(array $services, array $volumes, array $networks, array $configs, array $secrets, int $flags, string $path): Graph
{
    return makeVerticesAndEdges(new Graph(), $services, $volumes, $networks, $configs, $secrets, $flags, $path);
}

function makeVerticesAndEdges(Graph $graph, array $services, array $volumes, array $networks, array $configs, array $secrets, int $flags, string $path): Graph
{
    if (false === ((bool)($flags & WITHOUT_VOLUMES))) {
        iterator_apply($it = new \ArrayIterator($volumes), fn (Iterator $it) => addVolume(
            $graph,
            $it->key(),
            VolumeKind::VOLUME
        ), [$it]);
    }

    if (false === ((bool)($flags & WITHOUT_NETWORKS))) {
        iterator_apply($it = new \ArrayIterator($networks), fn (Iterator $it) => addNetwork(
            $graph,
            $it->key(),
            $it->current()
        ), [$it]);
    }

    if (false === ((bool)($flags & WITHOUT_CONFIGS))) {
        iterator_apply($it = new \ArrayIterator($configs), fn (Iterator $it) => addConfig($graph, $it->key()), [$it]);
    }

    if (false === ((bool)($flags & WITHOUT_SECRETS))) {
        iterator_apply($it = new \ArrayIterator($secrets), fn (Iterator $it) => addSecret($graph, $it->key()), [$it]);
    }

    iterator_apply($it = new \ArrayIterator($services), fn (Iterator $it) => addService($graph, $it->key()), [$it]);

    foreach ($services as $service => $definition) {
        if (isset($definition['extends'])) {
            if (isset($definition['extends']['file'])) {
                $extendedFile = dirname($path) . DIRECTORY_SEPARATOR . $definition['extends']['file'];
                $configuration = readConfigurations($extendedFile);
                $extendedServices = fetchServices($configuration);
                $extendedVolumes = fetchVolumes($configuration);
                $extendedNetworks = fetchNetworks($configuration);
                $extendedConfigs = fetchNetworks($configuration);
                $extendedSecrets = fetchNetworks($configuration);

                $graph = makeVerticesAndEdges(
                    $graph,
                    $extendedServices,
                    $extendedVolumes,
                    $extendedNetworks,
                    $extendedConfigs,
                    $extendedSecrets,
                    $flags,
                    $path
                );
            }

            addExtendsRelation($graph, $service, $definition['extends']['service']);
        }

        iterator_apply($it = new \ArrayIterator($definition['links'] ?? []), fn (Iterator $it) => addLinkRelation(
            $graph,
            $service,
            ...normalizeLinkMapping($it->current())
        ), [$it]);

        iterator_apply($it = new \ArrayIterator($definition['external_links'] ?? []), fn (Iterator $it) => addExternalLinkRelation(
            $graph,
            $service,
            ...normalizeLinkMapping($it->current())
        ), [$it]);

        iterator_apply($it = new \ArrayIterator($definition['depends_on'] ?? []), fn (Iterator $it) => addDependsRelation(
            $graph,
            $service,
            is_array($it->current()) ? $it->key() : $it->current(),
            $it->current()['condition'] ?? null
        ), [$it]);

        if (false === ((bool)($flags & WITHOUT_VOLUMES))) {
            iterator_apply(new \ArrayIterator($definition['volumes_from'] ?? []), fn (Iterator $it) => addVolumesFromRelation(
                $graph,
                $it->current(),
                $service
            ), [$it]);

            foreach ($definition['volumes'] ?? [] as $volume) {
                $volume = normalizeVolumeMapping($volume, $volumes);
                addVolume($graph, $volume['source'], VolumeKind::BIND);
                addVolumeRelation($graph, $service, $volume);
            }
        }

        if (false === ((bool)($flags & WITHOUT_PORTS))) {
            iterator_apply($it = new \ArrayIterator($definition['ports'] ?? []), fn (Iterator $it) => addPortRelation(
                $graph,
                $service,
                normalizePortMapping($it->current())
            ), [$it]);
        }

        if (false === ((bool)($flags & WITHOUT_NETWORKS))) {
            iterator_apply($it = new \ArrayIterator($definition['networks'] ?? []), fn (Iterator $it) => addNetworkRelation(
                $graph,
                $service,
                is_int($it->key()) ? $it->current() : $it->key(),
                is_int($it->key()) ? [] : $it->current()
            ), [$it]);
        }

        if (false === ((bool)($flags & WITHOUT_CONFIGS))) {
            iterator_apply($it = new \ArrayIterator($definition['configs'] ?? []), fn (Iterator $it) => addConfigRelation(
                $graph,
                $service,
                is_array($it->current()) ? $it->current()['source'] : $it->current(),
                is_array($it->current()) ? $it->current() : []
            ), [$it]);
        }

        if (false === ((bool)($flags & WITHOUT_SECRETS))) {
            iterator_apply($it = new \ArrayIterator($definition['secrets'] ?? []), fn (Iterator $it) => addSecretRelation(
                $graph,
                $service,
                $it->current()
            ), [$it]);
        }
    }

    return $graph;
}

function applyGraphvizStyle(Graph $graph, bool $horizontal, string $background): Graph
{
    $graph = $graph->createGraphClone();
    $graph->setAttribute('graphviz.graph.bgcolor', $background);
    $graph->setAttribute('graphviz.graph.pad', '0.5');
    $graph->setAttribute('graphviz.graph.ratio', 'fill');
    $graph->setAttribute('graphviz.graph.splines', 'true');
    $graph->setAttribute('graphviz.graph.overlap', 'false');

    if (true === $horizontal) {
        $graph->setAttribute('graphviz.graph.rankdir', 'LR');
    }

    return $graph;
}
