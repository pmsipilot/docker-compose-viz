<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz\Tests;

use Fhaculty\Graph\Edge\Directed;
use Fhaculty\Graph\Exception\OutOfBoundsException;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use PHPUnit\Framework\TestCase;

use function PMSIpilot\DockerComposeViz\addDependsRelation;
use function PMSIpilot\DockerComposeViz\addExtendsRelation;
use function PMSIpilot\DockerComposeViz\addExternalLinkRelation;
use function PMSIpilot\DockerComposeViz\addLinkRelation;
use function PMSIpilot\DockerComposeViz\addService;
use function PMSIpilot\DockerComposeViz\addServiceRelation;
use function PMSIpilot\DockerComposeViz\addVolumesFromRelation;
use function PMSIpilot\DockerComposeViz\fetchServices;
use function PMSIpilot\DockerComposeViz\getServiceVertexId;

class ServiceTest extends TestCase
{
    /**
     * @test
     */
    public function emptyDockerComposeConfiguration(): void
    {
        $this->assertEquals([], fetchServices([]));
    }

    /**
     * @test
     */
    public function returnServicesFromDockerComposeConfiguration(): void
    {
        $configuration = [
            'services' => [
                'test-service' => ['external' => true]
            ]
        ];

        $this->assertEquals($configuration['services'], fetchServices($configuration));
    }

    /**
     * @test
     */
    public function generateVertexId(): void
    {
        $service = 'test-service';

        $this->assertEquals('service:'.$service, getServiceVertexId($service));
    }

    /**
     * @test
     */
    public function checkIfVertexAlreadyExistsInGraph(): void
    {
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getServiceVertexId($service))
            ->will($this->returnValue(true));
        $graph->expects($this->once())
            ->method('getVertex')
            ->with(getServiceVertexId($service))
            ->will($this->returnValue(new Vertex($graph, getServiceVertexId($service))));

        addService($graph, $service);
    }

    /**
     * @test
     */
    public function addVertexIfNotExistInGraph(): void
    {
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getServiceVertexId($service))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getServiceVertexId($service))
            ->will($this->returnValue(new Vertex($graph, getServiceVertexId($service))));

        addService($graph, $service);
    }

    /**
     * @test
     */
    public function setVertexAttributesWhenAdding(): void
    {
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $vertex = $this->createMock(Vertex::class);
        $vertex->expects($this->exactly(2))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('service')],
                [$this->equalTo('graphviz.shape'), $this->equalTo('component')],
            );
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getServiceVertexId($service))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getServiceVertexId($service))
            ->will($this->returnValue($vertex));

        addService($graph, $service);
    }

    /**
     * @test
     */
    public function addExtendsRelationWithUnknownSourceService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addExtendsRelation(new Graph(), 'unknown', $service);
    }

    /**
     * @test
     */
    public function addExtendsRelationWithUnknownTargetService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addExtendsRelation(new Graph(), $service, 'unknown');
    }

    /**
     * @test
     */
    public function addLinkRelationWithUnknownSourceService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addLinkRelation(new Graph(), 'unknown', $service);
    }

    /**
     * @test
     */
    public function addLinkRelationWithUnknownTargetService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addLinkRelation(new Graph(), $service, 'unknown');
    }

    /**
     * @test
     */
    public function addExternalLinkRelationWithUnknownSourceService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addExternalLinkRelation(new Graph(), 'unknown', $service);
    }

    /**
     * @test
     */
    public function addExternalLinkRelationWithUnknownTargetService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addExternalLinkRelation(new Graph(), $service, 'unknown');
    }

    /**
     * @test
     */
    public function addDependsRelationWithUnknownSourceService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addDependsRelation(new Graph(), 'unknown', $service);
    }

    /**
     * @test
     */
    public function addDependsRelationWithUnknownTargetService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addDependsRelation(new Graph(), $service, 'unknown');
    }

    /**
     * @test
     */
    public function addVolumesFromRelationWithUnknownSourceService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addVolumesFromRelation(new Graph(), 'unknown', $service);
    }

    /**
     * @test
     */
    public function addVolumesFromRelationWithUnknownTargetService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addVolumesFromRelation(new Graph(), $service, 'unknown');
    }

    /**
     * @test
     * @dataProvider checkIfEdgeOfExpectedTypeAlreadyExistsInGraphProvider
     */
    public function checkIfEdgeOfExpectedTypeAlreadyExistsInGraph(string $type, callable $function): void
    {
        $source = 'source-service';
        $target = 'target-service';
        $graph = $this->createMock(Graph::class);
        $sourceVertex = $this->createMock(Vertex::class);
        $targetVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($source))],
                [$this->equalTo(getServiceVertexId($target))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($sourceVertex),
                    $this->returnValue($targetVertex),
                ),
            );
        $sourceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($targetVertex))
            ->will($this->returnValue(true));
        $sourceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($targetVertex))
            ->will($this->returnValue([$relation]));
        $sourceVertex->expects(($this->never()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($targetVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue($type));

        $function($graph, $source, $target);
    }

    private function checkIfEdgeOfExpectedTypeAlreadyExistsInGraphProvider(): array
    {
        return [
            'extends' => ['extends', 'PMSIpilot\DockerComposeViz\addExtendsRelation'],
            'link' => ['link', 'PMSIpilot\DockerComposeViz\addLinkRelation'],
            'external_link' => ['external_link', 'PMSIpilot\DockerComposeViz\addExternalLinkRelation'],
            'depends' => ['depends', 'PMSIpilot\DockerComposeViz\addDependsRelation'],
            'volumes_from' => ['volumes_from', 'PMSIpilot\DockerComposeViz\addVolumesFromRelation'],
        ];
    }

    /**
     * @test
     * @dataProvider addEdgeIfNotExistsProvider
     */
    public function addEdgeIfNotExistWithExpectedTypeInGraph(callable $function): void
    {
        $source = 'source-service';
        $target = 'target-service';
        $graph = $this->createMock(Graph::class);
        $sourceVertex = $this->createMock(Vertex::class);
        $targetVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($source))],
                [$this->equalTo(getServiceVertexId($target))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($sourceVertex),
                    $this->returnValue($targetVertex),
                ),
            );
        $sourceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($targetVertex))
            ->will($this->returnValue(true));
        $sourceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($targetVertex))
            ->will($this->returnValue([$relation]));
        $sourceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($targetVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('unknown'));

        $function($graph, $source, $target);
    }

    /**
     * @test
     * @dataProvider addEdgeIfNotExistsProvider
     */
    public function addEdgeIfNotExistInGraph(callable $function): void
    {
        $source = 'source-service';
        $target = 'target-service';
        $graph = $this->createMock(Graph::class);
        $sourceVertex = $this->createMock(Vertex::class);
        $targetVertex = $this->createMock(Vertex::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($source))],
                [$this->equalTo(getServiceVertexId($target))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($sourceVertex),
                    $this->returnValue($targetVertex),
                ),
            );
        $sourceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($targetVertex))
            ->will($this->returnValue(false));
        $sourceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($targetVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));

        $function($graph, $source, $target);
    }

    private function addEdgeIfNotExistsProvider(): array
    {
        return [
            'extends' => ['PMSIpilot\DockerComposeViz\addExtendsRelation'],
            'link' => ['PMSIpilot\DockerComposeViz\addLinkRelation'],
            'external_link' => ['PMSIpilot\DockerComposeViz\addExternalLinkRelation'],
            'depends' => ['PMSIpilot\DockerComposeViz\addDependsRelation'],
            'volumes_from' => ['PMSIpilot\DockerComposeViz\addVolumesFromRelation'],
        ];
    }

    /**
     * @test
     * @dataProvider setEdgeAttributesWhenAddingProvider
     */
    public function setEdgeAttributesWhenAdding(callable $function, array ...$setAttributesCalls): void
    {
        $source = 'source-service';
        $target = 'target-service';
        $graph = $this->createMock(Graph::class);
        $sourceVertex = $this->createMock(Vertex::class);
        $targetVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($source))],
                [$this->equalTo(getServiceVertexId($target))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($sourceVertex),
                    $this->returnValue($targetVertex),
                ),
            );
        $sourceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($targetVertex))
            ->will($this->returnValue(false));
        $sourceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($targetVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->exactly(count($setAttributesCalls)))
            ->method('setAttribute')
            ->withConsecutive(...$setAttributesCalls);

        $function($graph, $source, $target);
    }

    private function setEdgeAttributesWhenAddingProvider(): array
    {
        return [
            'extends' => [
                'PMSIpilot\DockerComposeViz\addExtendsRelation',
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('extends')],
                [$this->equalTo('graphviz.dir'), $this->equalTo('both')],
                [$this->equalTo('graphviz.arrowhead'), $this->equalTo('inv')],
                [$this->equalTo('graphviz.arrowtail'), $this->equalTo('dot')],
            ],
            'link' => [
                'PMSIpilot\DockerComposeViz\addLinkRelation',
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('link')],
                [$this->equalTo('graphviz.style'), $this->equalTo('solid')],
            ],
            'external_link' => [
                'PMSIpilot\DockerComposeViz\addExternalLinkRelation',
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('external_link')],
                [$this->equalTo('graphviz.style'), $this->equalTo('solid')],
                [$this->equalTo('graphviz.color'), $this->equalTo('gray')],
            ],
            'depends' => [
                'PMSIpilot\DockerComposeViz\addDependsRelation',
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('depends')],
                [$this->equalTo('graphviz.style'), $this->equalTo('dotted')],
            ],
            'volumes_from' => [
                'PMSIpilot\DockerComposeViz\addVolumesFromRelation',
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('volumes_from')],
                [$this->equalTo('graphviz.style'), $this->equalTo('dashed')],
            ],
        ];
    }
}
