<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz\Tests;

use Fhaculty\Graph\Edge\Directed;
use Fhaculty\Graph\Exception\OutOfBoundsException;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use PHPUnit\Framework\TestCase;

use function PMSIpilot\DockerComposeViz\addVolume;
use function PMSIpilot\DockerComposeViz\addVolumeRelation;
use function PMSIpilot\DockerComposeViz\fetchVolumes;
use function PMSIpilot\DockerComposeViz\getServiceVertexId;
use function PMSIpilot\DockerComposeViz\getVolumeVertexId;

class VolumeTest extends TestCase
{
    /**
     * @test
     */
    public function emptyDockerComposeConfiguration(): void
    {
        $this->assertEquals([], fetchVolumes([]));
    }

    /**
     * @test
     */
    public function returnVolumesFromDockerComposeConfiguration(): void
    {
        $configuration = [
            'volumes' => [
                'test-volume' => ['external' => true]
            ]
        ];

        $this->assertEquals($configuration['volumes'], fetchVolumes($configuration));
    }

    /**
     * @test
     */
    public function generateVertexId(): void
    {
        $volume = 'test-volume';

        $this->assertEquals('volume:'.$volume, getVolumeVertexId($volume));
    }

    /**
     * @test
     */
    public function checkIfVertexAlreadyExistsInGraph(): void
    {
        $volume = 'test-volume';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getVolumeVertexId($volume))
            ->will($this->returnValue(true));
        $graph->expects($this->once())
            ->method('getVertex')
            ->with(getVolumeVertexId($volume))
            ->will($this->returnValue(new Vertex($graph, getVolumeVertexId($volume))));

        addVolume($graph, $volume);
    }

    /**
     * @test
     */
    public function addVertexIfNotExistInGraph(): void
    {
        $volume = 'test-volume';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getVolumeVertexId($volume))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getVolumeVertexId($volume))
            ->will($this->returnValue(new Vertex($graph, getVolumeVertexId($volume))));

        addVolume($graph, $volume);
    }

    /**
     * @test
     */
    public function setVertexAttributesWhenAdding(): void
    {
        $volume = 'test-volume';
        $graph = $this->createMock(Graph::class);
        $vertex = $this->createMock(Vertex::class);
        $vertex->expects($this->exactly(3))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('volume')],
                [$this->equalTo('graphviz.label'), $this->equalTo($volume)],
                [$this->equalTo('graphviz.shape'), $this->equalTo('pentagon')],
            );
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getVolumeVertexId($volume))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getVolumeVertexId($volume))
            ->will($this->returnValue($vertex));

        addVolume($graph, $volume);
    }

    /**
     * @test
     */
    public function addRelationWithUnknownService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        addVolumeRelation(new Graph(), 'unknown', ['source' => 'test-volume']);
    }

    /**
     * @test
     */
    public function addRelationWithUnknownVolume(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addVolumeRelation(new Graph(), $service, ['source' => 'test-volume']);
    }

    /**
     * @test
     */
    public function checkIfEdgeOfExpectedTypeAlreadyExistsInGraph(): void
    {
        $volume = 'test-volume';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $volumeVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getVolumeVertexId($volume))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($volumeVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($volumeVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->never()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('volume'));

        addVolumeRelation($graph, $service, ['source' => 'test-volume']);
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistWithExpectedTypeInGraph(): void
    {
        $volume = 'test-volume';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $volumeVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getVolumeVertexId($volume))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($volumeVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($volumeVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('unknown'));

        addVolumeRelation($graph, $service, ['source' => 'test-volume']);
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistInGraph(): void
    {
        $volume = 'test-volume';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $volumeVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getVolumeVertexId($volume))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($volumeVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));

        addVolumeRelation($graph, $service, ['source' => 'test-volume']);
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAdding(): void
    {
        $volume = 'test-volume';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $volumeVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getVolumeVertexId($volume))]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($volumeVertex),
                )
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->exactly(3))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('volume')],
                [$this->equalTo('graphviz.style'), $this->equalTo('dashed')],
                [$this->equalTo('graphviz.dir'), $this->equalTo('both')],
            );

        addVolumeRelation($graph, $service, ['source' => 'test-volume']);
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAddingWithDefinition(): void
    {
        $volume = 'test-volume';
        $service = 'test-service';
        $definition = ['source' => $volume, 'target' => '/target'];
        $graph = $this->createMock(Graph::class);
        $volumeVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getVolumeVertexId($volume))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($volumeVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($volumeVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->exactly(4))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('volume')],
                [$this->equalTo('graphviz.style'), 'dashed'],
                [$this->equalTo('graphviz.dir'), $this->equalTo('both')],
                [$this->equalTo('graphviz.label'), $definition['target']],
            );

        addVolumeRelation($graph, $service, $definition);
    }
}
