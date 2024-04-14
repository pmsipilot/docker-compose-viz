<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#networks-top-level-element
 */
function fetchNetworks(array $configuration): array
{
    return $configuration['networks'] ?? [];
}

function getNetworkVertexId(string $name): string
{
    return 'net:'.$name;
}

function addNetwork(Graph $graph, string $name, ?array $definition = null): Vertex
{
    $id = getNetworkVertexId($name);
    $label = $definition['name'] ?? $name;

    if (true === $graph->hasVertex($id)) {
        return $graph->getVertex($id);
    }

    $vertex = $graph->createVertex($id);
    $vertex->setAttribute('docker_compose_viz.type', 'network');
    $vertex->setAttribute('graphviz.label', $label);
    $vertex->setAttribute('graphviz.shape', 'pentagon');

    if (isset($definition['external'])) {
        $vertex->setAttribute('graphviz.color', 'gray');
    }

    return $vertex;
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#networks
 */
function addNetworkRelation(Graph $graph, string $service, string $network, ?array $mapping = null): void
{
    $serviceVertex = $graph->getVertex(getServiceVertexId($service));
    $networkVertex = $graph->getVertex(getNetworkVertexId($network));
    $edge = null;

    if ($serviceVertex->hasEdgeTo($networkVertex)) {
        $edges = $serviceVertex->getEdgesTo($networkVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'network') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($networkVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'network');

    $aliases = $mapping['aliases'] ?? [];

    if (count($aliases) > 0) {
        $edge->setAttribute('graphviz.label', implode(', ', $aliases));
    }
}
