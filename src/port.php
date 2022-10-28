<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

function getPortVertexId(string $name): string
{
    return 'port:'.$name;
}

function addPort(Graph $graph, array $definition): Vertex
{
    $label = $definition['published'];
    $id = getPortVertexId($label);

    if (isset($definition['host_ip'])) {
        $label = $definition['host_ip'] . ':' . $label;
    }

    if (true === $graph->hasVertex($id)) {
        return $graph->getVertex($id);
    }

    $vertex = $graph->createVertex($id);
    $vertex->setAttribute('docker_compose_viz.type', 'port');
    $vertex->setAttribute('graphviz.label', $label);
    $vertex->setAttribute('graphviz.shape', 'circle');

    if ('udp' === $definition['proto']) {
        $vertex->setAttribute('graphviz.style', 'dashed');
    }

    return $vertex;
}

function addPortRelation(Graph $graph, string $service, array $definition): void
{
    $serviceVertex = $graph->getVertex(getServiceVertexId($service));
    $configVertex = addPort($graph, $definition);
    $edge = null;

    if ($serviceVertex->hasEdgeTo($configVertex)) {
        $edges = $serviceVertex->getEdgesTo($configVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'port') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($configVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'port');
    $edge->setAttribute('graphviz.style', 'solid');

    if (isset($definition['target'])) {
        $edge->setAttribute('graphviz.label', $definition['target']);
    }
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#networks
 */
function normalizePortMapping(string|array $mapping): array
{
    if (is_array($mapping)) {
        return $mapping;
    }

    $parts = explode(':', $mapping);
    $ip = null;
    $published = null;
    $target = null;
    $proto = null;

    if (count($parts) === 1) {
        $target = $parts[0];
        $published = $target;
    } elseif (count($parts) === 2) {
        $published = $parts[0];
        $target = $parts[1];
    } elseif (count($parts) === 3) {
        $ip = $parts[0];
        $published = $parts[1];
        $target = $parts[2];
    }

    $subparts = array_values(array_filter(explode('/', $target)));

    if (isset($subparts[1])) {
        $proto = $subparts[1];
    }

    return [
        'host_ip' => $ip,
        'published' => $published,
        'target' => $target,
        'proto' => $proto,
    ];
}
