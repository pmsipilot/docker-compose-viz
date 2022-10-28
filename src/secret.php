<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#secrets-top-level-element
 */
function fetchSecrets(array $configuration): array
{
    return $configuration['secrets'] ?? [];
}

function getSecretVertexId(string|int $name): string
{
    return 'secret:'.$name;
}

function addSecret(Graph $graph, string $name): Vertex
{
    $id = getSecretVertexId($name);

    if (true === $graph->hasVertex($id)) {
        return $graph->getVertex($id);
    }

    $vertex = $graph->createVertex($id);
    $vertex->setAttribute('docker_compose_viz.type', 'secret');
    $vertex->setAttribute('graphviz.shape', 'hexagon');

    return $vertex;
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#secrets
 */
function addSecretRelation(Graph $graph, string $service, string|array $mapping): void
{
    $serviceVertex = $graph->getVertex(getServiceVertexId($service));

    if (is_string($mapping)) {
        $configVertex = $graph->getVertex(getSecretVertexId($mapping));
    } else {
        $configVertex = $graph->getVertex(getSecretVertexId($mapping['source']));
    }

    $edge = null;

    if ($serviceVertex->hasEdgeTo($configVertex)) {
        $edges = $serviceVertex->getEdgesTo($configVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'secret') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($configVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'secret');

    if (isset($mapping['target'])) {
        $edge->setAttribute('graphviz.label', $mapping['target']);
    }
}
