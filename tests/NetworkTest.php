<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz\Tests;

use Fhaculty\Graph\Edge\Directed;
use Fhaculty\Graph\Exception\OutOfBoundsException;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use PHPUnit\Framework\TestCase;

use function PMSIpilot\DockerComposeViz\addNetwork;
use function PMSIpilot\DockerComposeViz\addNetworkRelation;
use function PMSIpilot\DockerComposeViz\fetchNetworks;
use function PMSIpilot\DockerComposeViz\getNetworkVertexId;
use function PMSIpilot\DockerComposeViz\getServiceVertexId;

class NetworkTest extends TestCase
{
    /**
     * @test
     */
    public function emptyDockerComposeConfiguration(): void
    {
        $this->assertEquals([], fetchNetworks([]));
    }

    /**
     * @test
     */
    public function returnNetworksFromDockerComposeConfiguration(): void
    {
        $configuration = [
            'networks' => [
                'test-net' => ['external' => true]
            ]
        ];

        $this->assertEquals($configuration['networks'], fetchNetworks($configuration));
    }

    /**
     * @test
     */
    public function generateVertexId(): void
    {
        $network = 'test-net';

        $this->assertEquals('net:'.$network, getNetworkVertexId($network));
    }

    /**
     * @test
     */
    public function checkIfVertexAlreadyExistsInGraph(): void
    {
        $network = 'test-net';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getNetworkVertexId($network))
            ->will($this->returnValue(true));
        $graph->expects($this->once())
            ->method('getVertex')
            ->with(getNetworkVertexId($network))
            ->will($this->returnValue(new Vertex($graph, getNetworkVertexId($network))));

        addNetwork($graph, $network);
    }

    /**
     * @test
     */
    public function addVertexIfNotExistInGraph(): void
    {
        $network = 'test-net';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getNetworkVertexId($network))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getNetworkVertexId($network))
            ->will($this->returnValue(new Vertex($graph, getNetworkVertexId($network))));

        addNetwork($graph, $network);
    }

    /**
     * @test
     */
    public function setVertexAttributesWhenAdding(): void
    {
        $network = 'test-net';
        $graph = $this->createMock(Graph::class);
        $vertex = $this->createMock(Vertex::class);
        $vertex->expects($this->exactly(3))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('network')],
                [$this->equalTo('graphviz.label'), $this->equalTo($network)],
                [$this->equalTo('graphviz.shape'), $this->equalTo('pentagon')],
            );
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getNetworkVertexId($network))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getNetworkVertexId($network))
            ->will($this->returnValue($vertex));

        addNetwork($graph, $network);
    }

    /**
     * @test
     */
    public function setVertexLabelAttributeFromName(): void
    {
        $network = 'test-net';
        $definition = ['name' => 'test-net-name'];
        $graph = $this->createMock(Graph::class);
        $vertex = $this->createMock(Vertex::class);
        $vertex->expects($this->exactly(3))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('network')],
                [$this->equalTo('graphviz.label'), $this->equalTo($definition['name'])],
                [$this->equalTo('graphviz.shape'), $this->equalTo('pentagon')],
            );
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getNetworkVertexId($network))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getNetworkVertexId($network))
            ->will($this->returnValue($vertex));

        addNetwork($graph, $network, $definition);
    }

    /**
     * @test
     */
    public function addRelationWithUnknownService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        addNetworkRelation(new Graph(), 'unknown', 'test-net');
    }

    /**
     * @test
     */
    public function addRelationWithUnknownNetwork(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addNetworkRelation(new Graph(), $service, 'unknown');
    }

    /**
     * @test
     */
    public function checkIfEdgeOfExpectedTypeAlreadyExistsInGraph(): void
    {
        $network = 'test-net';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $networkVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getNetworkVertexId($network))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($networkVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($networkVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->never()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('network'));

        addNetworkRelation($graph, $service, $network);
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistWithExpectedTypeInGraph(): void
    {
        $network = 'test-net';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $networkVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getNetworkVertexId($network))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($networkVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($networkVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('unknown'));

        addNetworkRelation($graph, $service, $network);
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistInGraph(): void
    {
        $network = 'test-net';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $networkVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getNetworkVertexId($network))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($networkVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));

        addNetworkRelation($graph, $service, $network);
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAdding(): void
    {
        $network = 'test-net';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $networkVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getNetworkVertexId($network))]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($networkVertex),
                )
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->once())
            ->method('setAttribute')
            ->with($this->equalTo('docker_compose_viz.type'), $this->equalTo('network'));

        addNetworkRelation($graph, $service, $network);
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAddingWithMapping(): void
    {
        $network = 'test-net';
        $service = 'test-service';
        $mapping = ['aliases' => ['foo', 'bar']];
        $graph = $this->createMock(Graph::class);
        $networkVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getNetworkVertexId($network))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($networkVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($networkVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->exactly(2))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('network')],
                [$this->equalTo('graphviz.label'), $this->equalTo(implode(', ', $mapping['aliases']))],
            );

        addNetworkRelation($graph, $service, $network, $mapping);
    }
}
