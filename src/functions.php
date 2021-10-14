<?php

namespace PMSIpilot\DockerComposeViz;

use Fhaculty\Graph\Edge;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use InvalidArgumentException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

const WITHOUT_VOLUMES = 1;
const WITHOUT_NETWORKS = 2;
const WITHOUT_PORTS = 4;

/**
 * @internal
 */
function logger(OutputInterface $output): callable
{
    return function (string $message, int $verbosity = null) use ($output) {
        $output->writeln(sprintf('[%s] %s', date(DATE_ISO8601), $message), $verbosity ?: OutputInterface::VERBOSITY_VERBOSE);
    };
}

/**
 * @public
 *
 * @param string $path Path to a YAML file
 */
function readConfiguration(string $path): array
{
    if (false === file_exists($path)) {
        throw new InvalidArgumentException(sprintf('File "%s" does not exist', $path));
    }

    try {
        return Yaml::parse(file_get_contents($path));
    } catch (ParseException $exception) {
        throw new InvalidArgumentException(sprintf('File "%s" does not contain valid YAML', $path), $exception->getCode(), $exception);
    }
}

/**
 * @public
 *
 * @param array $configuration Docker compose (version 1 or 2) configuration
 *
 * @return array List of service definitions exctracted from the configuration
 */
function fetchServices(array $configuration): array
{
    if (false === isset($configuration['version']) || 1 === (int) $configuration['version']) {
        return $configuration;
    }

    return $configuration['services'] ?? [];
}

/**
 * @public
 *
 * @param array $configuration Docker compose (version 1 or 2) configuration
 *
 * @return array List of service definitions exctracted from the configuration
 */
function fetchVolumes(array $configuration): array
{
    if (false === isset($configuration['version']) || 1 === (int) $configuration['version']) {
        return [];
    }

    return $configuration['volumes'] ?? [];
}

/**
 * @public
 *
 * @param array $configuration Docker compose (version 1 or 2) configuration
 *
 * @return array List of service definitions exctracted from the configuration
 */
function fetchNetworks(array $configuration): array
{
    if (false === isset($configuration['version']) || 1 === (int) $configuration['version']) {
        return [];
    }

    return $configuration['networks'] ?? [];
}

/**
 * @public
 *
 * @param array  $services    Docker compose service definitions
 * @param array  $volumes     Docker compose volume definitions
 * @param array  $networks    Docker compose network definitions
 * @param bool   $withVolumes Create vertices and edges for volumes
 * @param string $path        Path of the current docker-compose configuration file
 *
 * @return Graph The complete graph for the given list of services
 */
function createGraph(array $services, array $volumes, array $networks, string $path, int $flags): Graph
{
    return makeVerticesAndEdges(new Graph(), $services, $volumes, $networks, $path, $flags);
}

/**
 * @public
 *
 * @param Graph  $graph      Input graph
 * @param bool   $horizontal Display a horizontal graph
 * @param string $horizontal Background color (any hex color or 'transparent')
 *
 * @return Graph A copy of the input graph with style attributes
 */
function applyGraphvizStyle(Graph $graph, bool $horizontal, string $background): Graph
{
    $graph = $graph->createGraphClone();
    $graph->setAttribute('graphviz.graph.bgcolor', $background);
    $graph->setAttribute('graphviz.graph.pad', '0.5');
    $graph->setAttribute('graphviz.graph.ratio', 'fill');

    if (true === $horizontal) {
        $graph->setAttribute('graphviz.graph.rankdir', 'LR');
    }

    foreach ($graph->getVertices() as $vertex) {
        switch ($vertex->getAttribute('docker_compose.type')) {
            case 'service':
                $vertex->setAttribute('graphviz.shape', 'component');
                break;

            case 'external_service':
                $vertex->setAttribute('graphviz.shape', 'component');
                $vertex->setAttribute('graphviz.color', 'gray');
                break;

            case 'volume':
                $vertex->setAttribute('graphviz.shape', 'folder');
                break;

            case 'network':
                $vertex->setAttribute('graphviz.shape', 'pentagon');
                break;

            case 'external_network':
                $vertex->setAttribute('graphviz.shape', 'pentagon');
                $vertex->setAttribute('graphviz.color', 'gray');
                break;

            case 'port':
                $vertex->setAttribute('graphviz.shape', 'circle');

                if ('udp' === ($proto = $vertex->getAttribute('docker_compose.proto'))) {
                    $vertex->setAttribute('graphviz.style', 'dashed');
                }
                break;
        }
    }

    foreach ($graph->getEdges() as $edge) {
        switch ($edge->getAttribute('docker_compose.type')) {
            case 'ports':
            case 'links':
                $edge->setAttribute('graphviz.style', 'solid');
                break;

            case 'external_links':
                $edge->setAttribute('graphviz.style', 'solid');
                $edge->setAttribute('graphviz.color', 'gray');
                break;

            case 'volumes_from':
            case 'volumes':
                $edge->setAttribute('graphviz.style', 'dashed');
                break;

            case 'depends_on':
                $edge->setAttribute('graphviz.style', 'dotted');
                break;

            case 'extends':
                $edge->setAttribute('graphviz.dir', 'both');
                $edge->setAttribute('graphviz.arrowhead', 'inv');
                $edge->setAttribute('graphviz.arrowtail', 'dot');
                break;
        }

        if (null !== ($alias = $edge->getAttribute('docker_compose.alias'))) {
            $edge->setAttribute('graphviz.label', $alias);

            if (null !== $edge->getAttribute('docker_compose.condition')) {
                $edge->setAttribute('graphviz.fontsize', '10');
            }
        }

        if ($edge->getAttribute('docker_compose.bidir')) {
            $edge->setAttribute('graphviz.dir', 'both');
        }
    }

    return $graph;
}

/**
 * @internal
 *
 * @param Graph $graph       Input graph
 * @param array $services    Docker compose service definitions
 * @param array $volumes     Docker compose volume definitions
 * @param array $networks    Docker compose network definitions
 * @param bool  $withVolumes Create vertices and edges for volumes
 *
 * @return Graph A copy of the input graph with vertices and edges for services
 */
function makeVerticesAndEdges(Graph $graph, array $services, array $volumes, array $networks, string $path, int $flags): Graph
{
    if (false === ((bool) ($flags & WITHOUT_VOLUMES))) {
        foreach (array_keys($volumes) as $volume) {
            addVolume($graph, 'named: '.$volume);
        }
    }

    if (false === ((bool) ($flags & WITHOUT_NETWORKS))) {
        foreach ($networks as $network => $definition) {
            addNetwork(
                $graph,
                'net: '.$network,
                isset($definition['external']) && true === $definition['external'] ? 'external_network' : 'network'
            );
        }
    }

    foreach ($services as $service => $definition) {
        addService($graph, $service);

        if (isset($definition['extends'])) {
            if (isset($definition['extends']['file'])) {
                $configuration = readConfiguration(dirname($path).DIRECTORY_SEPARATOR.$definition['extends']['file']);
                $extendedServices = fetchServices($configuration);
                $extendedVolumes = fetchVolumes($configuration);
                $extendedNetworks = fetchNetworks($configuration);

                $graph = makeVerticesAndEdges($graph, $extendedServices, $extendedVolumes, $extendedNetworks, dirname($path).DIRECTORY_SEPARATOR.$definition['extends']['file'], $flags);
            }

            addRelation(
                addService($graph, $definition['extends']['service']),
                $graph->getVertex($service),
                'extends'
            );
        }

        $serviceLinks = [];

        foreach ($definition['links'] ?? [] as $link) {
            list($target, $alias) = explodeMapping($link);

            $serviceLinks[$alias] = $target;
        }

        foreach ($serviceLinks as $alias => $target) {
            addRelation(
                addService($graph, $target),
                $graph->getVertex($service),
                'links',
                $alias !== $target ? $alias : null
            );
        }

        foreach ($definition['external_links'] ?? [] as $link) {
            list($target, $alias) = explodeMapping($link);

            addRelation(
                addService($graph, $target, 'external_service'),
                $graph->getVertex($service),
                'external_links',
                $alias !== $target ? $alias : null
            );
        }

        foreach ($definition['depends_on'] ?? [] as $key => $dependency) {
            addRelation(
                $graph->getVertex($service),
                addService($graph, is_array($dependency) ? $key : $dependency),
                'depends_on',
                is_array($dependency) && isset($dependency['condition']) ? $dependency['condition'] : null,
                false,
                is_array($dependency) && isset($dependency['condition'])
            );
        }

        foreach ($definition['volumes_from'] ?? [] as $source) {
            addRelation(
                addService($graph, $source),
                $graph->getVertex($service),
                'volumes_from'
            );
        }

        if (false === ((bool) ($flags & WITHOUT_VOLUMES))) {
            $serviceVolumes = [];

            foreach ($definition['volumes'] ?? [] as $volume) {
                if (is_array($volume)) {
                    $host = $volume['source'];
                    $container = $volume['target'];
                    $attr = !empty($volume['read-only']) ? 'ro' : '';
                } else {
                    list($host, $container, $attr) = explodeVolumeMapping($volume);
                }

                $serviceVolumes[$container] = [$host, $attr];
            }

            foreach ($serviceVolumes as $container => $volume) {
                list($host, $attr) = $volume;

                if ('.' !== $host[0] && DIRECTORY_SEPARATOR !== $host[0]) {
                    $host = 'named: '.$host;
                }

                addRelation(
                    addVolume($graph, $host),
                    $graph->getVertex($service),
                    'volumes',
                    $host !== $container ? $container : null,
                    'ro' !== $attr
                );
            }
        }

        if (false === ((bool) ($flags & WITHOUT_PORTS))) {
            foreach ($definition['ports'] ?? [] as $port) {
                list($target, $host, $container, $proto) = explodePortMapping($port);

                addRelation(
                    addPort($graph, (int) $host, $proto, $target),
                    $graph->getVertex($service),
                    'ports',
                    $host !== $container ? $container : null
                );
            }
        }

        if (false === ((bool) ($flags & WITHOUT_NETWORKS))) {
            foreach ($definition['networks'] ?? [] as $network => $config) {
                $network = is_int($network) ? $config : $network;
                $config = is_int($network) ? [] : $config;
                $aliases = $config['aliases'] ?? [];

                addRelation(
                    $graph->getVertex($service),
                    addNetwork($graph, 'net: '.$network),
                    'networks',
                    count($aliases) > 0 ? implode(', ', $aliases) : null
                );
            }
        }
    }

    return $graph;
}

/**
 * @internal
 *
 * @param Graph  $graph   Input graph
 * @param string $service Service name
 * @param string $type    Service type
 *
 * @return Vertex
 */
function addService(Graph $graph, string $service, string $type = null)
{
    if (true === $graph->hasVertex($service)) {
        return $graph->getVertex($service);
    }

    $vertex = $graph->createVertex($service);
    $vertex->setAttribute('docker_compose.type', $type ?: 'service');

    return $vertex;
}

/**
 * @internal
 *
 * @param Graph       $graph Input graph
 * @param int         $port  Port number
 * @param string|null $proto Protocol
 *
 * @return Vertex
 */
function addPort(Graph $graph, int $port, string $proto = null, string $target = null)
{
    $target = $target ? $target.':' : null;

    if (true === $graph->hasVertex($target.$port)) {
        return $graph->getVertex($target.$port);
    }

    $vertex = $graph->createVertex($target.$port);
    $vertex->setAttribute('docker_compose.type', 'port');
    $vertex->setAttribute('docker_compose.proto', $proto ?: 'tcp');

    return $vertex;
}

/**
 * @internal
 *
 * @param Graph  $graph Input graph
 * @param string $path  Path
 *
 * @return Vertex
 */
function addVolume(Graph $graph, string $path)
{
    if (true === $graph->hasVertex($path)) {
        return $graph->getVertex($path);
    }

    $vertex = $graph->createVertex($path);
    $vertex->setAttribute('docker_compose.type', 'volume');

    return $vertex;
}

/**
 * @internal
 *
 * @param Graph  $graph Input graph
 * @param string $name  Name of the network
 * @param string $type  Network type
 *
 * @return Vertex
 */
function addNetwork(Graph $graph, string $name, string $type = null)
{
    if (true === $graph->hasVertex($name)) {
        return $graph->getVertex($name);
    }

    $vertex = $graph->createVertex($name);
    $vertex->setAttribute('docker_compose.type', $type ?: 'network');

    return $vertex;
}

/**
 * @internal
 *
 * @param Vertex      $from          Source vertex
 * @param Vertex      $to            Destination vertex
 * @param string      $type          Type of the relation (one of "links", "volumes_from", "depends_on", "ports");
 * @param string|null $alias         Alias associated to the linked element
 * @param bool|null   $bidirectional Biderectional or not
 * @param bool|null   $condition     Wether the alias represents a condition or not
 */
function addRelation(Vertex $from, Vertex $to, string $type, string $alias = null, bool $bidirectional = false, bool $condition = false): Edge\Directed
{
    $edge = null;

    if ($from->hasEdgeTo($to)) {
        $edges = $from->getEdgesTo($to);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose.type') === $type) {
                break;
            }
        }
    }

    if (null === $edge) {
        $edge = $from->createEdgeTo($to);
    }

    $edge->setAttribute('docker_compose.type', $type);

    if (null !== $alias) {
        $edge->setAttribute('docker_compose.alias', $alias);
    }

    if (true === $condition) {
        $edge->setAttribute('docker_compose.condition', true);
    }

    $edge->setAttribute('docker_compose.bidir', $bidirectional);

    return $edge;
}

/**
 * @internal
 *
 * @param string $mapping A docker mapping (<from>[:<to>])
 *
 * @return array An 2 or 3 items array containing the parts of the mapping.
 *               If the mapping does not specify a second part, the first one will be repeated
 */
function explodeMapping($mapping): array
{
    $parts = explode(':', $mapping);
    $parts[1] = $parts[1] ?? $parts[0];

    return [$parts[0], $parts[1]];
}

/**
 * @internal
 *
 * @param string $mapping A docker mapping (<from>[:<to>])
 *
 * @return array An 2 or 3 items array containing the parts of the mapping.
 *               If the mapping does not specify a second part, the first one will be repeated
 */
function explodeVolumeMapping($mapping): array
{
    $parts = explode(':', $mapping);
    $parts[1] = $parts[1] ?? $parts[0];

    return [$parts[0], $parts[1], $parts[2] ?? null];
}

/**
 * @internal
 *
 * @param string $mapping A docker mapping (<from>[:<to>])
 *
 * @return array An 2 or 3 items array containing the parts of the mapping.
 *               If the mapping does not specify a second part, the first one will be repeated
 */
function explodePortMapping($mapping): array
{
    $parts = explode(':', $mapping);

    if (count($parts) < 3) {
        $target = null;
        $host = $parts[0];
        $container = $parts[1] ?? $parts[0];
    } else {
        $target = $parts[0];
        $host = $parts[1];
        $container = $parts[2];
    }

    $subparts = array_values(array_filter(explode('/', $container)));

    return [$target, $host, $subparts[0], $subparts[1] ?? null];
}
