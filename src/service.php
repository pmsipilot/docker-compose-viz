<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#services-top-level-element
 */
function fetchServices(array $configuration): array
{
    return $configuration['services'] ?? [];
}

function getServiceVertexId(string $name): string
{
    return 'service:'.$name;
}

function addService(Graph $graph, string $service, string $type = null): Vertex
{
    $id = getServiceVertexId($service);

    if (true === $graph->hasVertex($id)) {
        return $graph->getVertex($id);
    }

    $vertex = $graph->createVertex($id);
    $vertex->setAttribute('docker_compose_viz.type', 'service');
    $vertex->setAttribute('graphviz.shape', 'component');

    if ('external_service' === $type) {
        $vertex->setAttribute('graphviz.color', 'gray');
    }

    return $vertex;
}

function findServiceVertex(Graph $graph, string $service): Vertex
{
    return $graph->getVertex(getServiceVertexId($service));
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#extends
 */
function addExtendsRelation(Graph $graph, string $service, string $extended): void
{
    $serviceVertex = findServiceVertex($graph, $service);
    $extendedVertex = findServiceVertex($graph, $extended);

    $edge = null;

    if ($serviceVertex->hasEdgeTo($extendedVertex)) {
        $edges = $serviceVertex->getEdgesTo($extendedVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'extends') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($extendedVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'extends');
    $edge->setAttribute('graphviz.dir', 'both');
    $edge->setAttribute('graphviz.arrowhead', 'inv');
    $edge->setAttribute('graphviz.arrowtail', 'dot');
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#links
 */
function addLinkRelation(Graph $graph, string $service, string $linked, ?string $alias = null): void
{
    $serviceVertex = findServiceVertex($graph, $service);
    $linkedVertex = findServiceVertex($graph, $linked);

    $edge = null;

    if ($serviceVertex->hasEdgeTo($linkedVertex)) {
        $edges = $serviceVertex->getEdgesTo($linkedVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'link') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($linkedVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'link');
    $edge->setAttribute('graphviz.style', 'solid');

    if (null !== $alias) {
        $edge->setAttribute('graphviz.label', $alias);
    }
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#external_links
 */
function addExternalLinkRelation(Graph $graph, string $service, string $linked, ?string $alias = null): void
{
    $serviceVertex = findServiceVertex($graph, $service);
    $linkedVertex = findServiceVertex($graph, $linked);

    $edge = null;

    if ($serviceVertex->hasEdgeTo($linkedVertex)) {
        $edges = $serviceVertex->getEdgesTo($linkedVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'external_link') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($linkedVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'external_link');
    $edge->setAttribute('graphviz.style', 'solid');
    $edge->setAttribute('graphviz.color', 'gray');

    if (null !== $alias) {
        $edge->setAttribute('graphviz.label', $alias);
    }
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#depends_on
 */
function addDependsRelation(Graph $graph, string $service, string $dependency, ?string $condition = null): void
{
    $serviceVertex = findServiceVertex($graph, $service);
    $dependencyVertex = findServiceVertex($graph, $dependency);

    $edge = null;

    if ($serviceVertex->hasEdgeTo($dependencyVertex)) {
        $edges = $serviceVertex->getEdgesTo($dependencyVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'depends') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($dependencyVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'depends');
    $edge->setAttribute('graphviz.style', 'dotted');

    if (null !== $condition) {
        if (null !== $edge->getAttribute('graphviz.label')) {
            $label = $edge->getAttribute('graphviz.label');
            $edge->setAttribute('graphviz.label', $label . ' (' . $condition . ')');
        } else {
            $edge->setAttribute('graphviz.label', $condition);
        }
    }
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#volumes_from
 */
function addVolumesFromRelation(Graph $graph, string $service, string $dependency): void
{
    $serviceVertex = findServiceVertex($graph, $service);
    $dependencyVertex = findServiceVertex($graph, $dependency);

    $edge = null;

    if ($serviceVertex->hasEdgeTo($dependencyVertex)) {
        $edges = $serviceVertex->getEdgesTo($dependencyVertex);

        foreach ($edges as $edge) {
            if ($edge->getAttribute('docker_compose_viz.type') === 'volumes_from') {
                break;
            }

            $edge = null;
        }
    }

    if (null === $edge) {
        $edge = $serviceVertex->createEdgeTo($dependencyVertex);
    }

    $edge->setAttribute('docker_compose_viz.type', 'volumes_from');
    $edge->setAttribute('graphviz.style', 'dashed');
}

/**
 * @see https://github.com/compose-spec/compose-spec/blob/master/spec.md#links
 */
function normalizeLinkMapping(string $mapping): array
{
    $parts = explode(':', $mapping);

    return [$parts[0], $parts[1] ?? $parts[0]];
}
