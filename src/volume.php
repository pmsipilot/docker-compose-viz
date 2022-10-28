<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

enum VolumeKind
{
    case BIND;
    case VOLUME;
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#volumes-top-level-element
 */
function fetchVolumes(array $configuration): array
{
    return $configuration['volumes'] ?? [];
}

function getVolumeVertexId(string $name): string
{
    return 'volume:'.$name;
}

function addVolume(Graph $graph, string $name, ?VolumeKind $type = null): Vertex
{
    $id = getVolumeVertexId($name);
    $label = $name;

    if (true === $graph->hasVertex($id)) {
        return $graph->getVertex($id);
    }

    $vertex = $graph->createVertex($id);
    $vertex->setAttribute('docker_compose_viz.type', 'volume');
    $vertex->setAttribute('graphviz.label', $label);
    $vertex->setAttribute('graphviz.shape', 'pentagon');

    if (VolumeKind::VOLUME === $type) {
        $vertex->setAttribute('graphviz.color', 'blue');
    }

    return $vertex;
}

function addVolumeRelation(Graph $graph, string $service, array $definition): void
{
    $serviceVertex = $graph->getVertex(getServiceVertexId($service));
    $volumeVertex = $graph->getVertex(getVolumeVertexId($definition['source']));
    $edge = null;

    if ($serviceVertex->hasEdgeTo($volumeVertex)) {
        $edges = $serviceVertex->getEdgesTo($volumeVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'volume') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($volumeVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'volume');
    $edge->setAttribute('graphviz.style', 'dashed');

    if (false === ($definition['read_only'] ?? false)) {
        $edge->setAttribute('graphviz.dir', 'both');
    }

    if (isset($definition['target'])) {
        $edge->setAttribute('graphviz.label', $definition['target']);
    }
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#volumes
 */
function normalizeVolumeMapping(string|array $mapping, array $volumes): array
{
    if (is_array($mapping)) {
        return $mapping;
    }

    $parts = explode(':', $mapping);
    $parts[1] = $parts[1] ?? $parts[0];
    $parts[2] = explode(', ', $parts[2] ?? 'rw');

    $type = in_array($parts[0], $volumes, true) ? 'volume' : 'bind';

    return [
        'type' => $type,
        'source' => $parts[0],
        'target' => $parts[1],
        'read_only' => in_array('ro', $parts[2], true),
    ];
}
