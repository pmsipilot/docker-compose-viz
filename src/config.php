<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#configs
 */
function fetchConfigs(array $configuration): array
{
    return $configuration['configs'] ?? [];
}

function getConfigVertexId(string $name): string
{
    return 'config:'.$name;
}

function addConfig(Graph $graph, string $name): Vertex
{
    $id = getConfigVertexId($name);
    $label = $name;

    if (true === $graph->hasVertex($id)) {
        return $graph->getVertex($id);
    }

    $vertex = $graph->createVertex($id);
    $vertex->setAttribute('docker_compose_viz.type', 'config');
    $vertex->setAttribute('graphviz.label', $label);
    $vertex->setAttribute('graphviz.shape', 'note');

    return $vertex;
}

function findConfigVertex(Graph $graph, string $config): Vertex
{
    return $graph->getVertex(getConfigVertexId($config));
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#configs
 */
function addConfigRelation(Graph $graph, string $service, string $config, ?array $mapping = null): void
{
    $serviceVertex = $graph->getVertex(getServiceVertexId($service));
    $configVertex = findConfigVertex($graph, $config);
    $edge = null;

    if ($serviceVertex->hasEdgeTo($configVertex)) {
        $edges = $serviceVertex->getEdgesTo($configVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'config') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($configVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'config');

    if (isset($mapping['target'])) {
        $edge->setAttribute('graphviz.label', $mapping['target']);
    }
}
