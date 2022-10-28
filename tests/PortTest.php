<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz\Tests;

use Fhaculty\Graph\Edge\Directed;
use Fhaculty\Graph\Exception\OutOfBoundsException;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use PHPUnit\Framework\TestCase;

use function PMSIpilot\DockerComposeViz\addPort;
use function PMSIpilot\DockerComposeViz\addPortRelation;
use function PMSIpilot\DockerComposeViz\fetchPorts;
use function PMSIpilot\DockerComposeViz\getPortVertexId;
use function PMSIpilot\DockerComposeViz\getServiceVertexId;
use function PMSIpilot\DockerComposeViz\normalizePortMapping;

class PortTest extends TestCase
{
    /**
     * @test
     */
    public function generateVertexId(): void
    {
        $port = '443';

        $this->assertEquals('port:'.$port, getPortVertexId($port));
    }

    /**
     * @test
     */
    public function checkIfVertexAlreadyExistsInGraph(): void
    {
        $port = '443';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getPortVertexId($port))
            ->will($this->returnValue(true));
        $graph->expects($this->once())
            ->method('getVertex')
            ->with(getPortVertexId($port))
            ->will($this->returnValue(new Vertex($graph, getPortVertexId($port))));

        addPort($graph, normalizePortMapping($port));
    }

    /**
     * @test
     */
    public function addVertexIfNotExistInGraph(): void
    {
        $port = '443';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getPortVertexId($port))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getPortVertexId($port))
            ->will($this->returnValue(new Vertex($graph, getPortVertexId($port))));

        addPort($graph, normalizePortMapping($port));
    }

    /**
     * @test
     */
    public function setVertexAttributesWhenAdding(): void
    {
        $port = '443';
        $graph = $this->createMock(Graph::class);
        $vertex = $this->createMock(Vertex::class);
        $vertex->expects($this->exactly(3))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('port')],
                [$this->equalTo('graphviz.label'), $this->equalTo($port)],
                [$this->equalTo('graphviz.shape'), $this->equalTo('circle')],
            );
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getPortVertexId($port))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getPortVertexId($port))
            ->will($this->returnValue($vertex));

        addPort($graph, normalizePortMapping($port));
    }

    /**
     * @test
     */
    public function addRelationWithUnknownService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        addPortRelation(new Graph(), 'unknown', normalizePortMapping('443'));
    }

    /**
     * @test
     */
    public function addRelationWithUnknownPort(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addPortRelation(new Graph(), $service, normalizePortMapping('443'));
    }

    /**
     * @test
     */
    public function checkIfEdgeOfExpectedTypeAlreadyExistsInGraph(): void
    {
        $port = '443';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $portVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with($this->equalTo(getPortVertexId($port)))
            ->will($this->returnValue(true));
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getPortVertexId($port))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($portVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($portVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($portVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->never()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($portVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('port'));

        addPortRelation($graph, $service, normalizePortMapping($port));
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistWithExpectedTypeInGraph(): void
    {
        $port = '443';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $portVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with($this->equalTo(getPortVertexId($port)))
            ->will($this->returnValue(true));
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getPortVertexId($port))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($portVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($portVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($portVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($portVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('unknown'));

        addPortRelation($graph, $service, normalizePortMapping($port));
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistInGraph(): void
    {
        $port = '443';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $portVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with($this->equalTo(getPortVertexId($port)))
            ->will($this->returnValue(true));
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getPortVertexId($port))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($portVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($portVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($portVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));

        addPortRelation($graph, $service, normalizePortMapping($port));
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAdding(): void
    {
        $port = '443';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $portVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with($this->equalTo(getPortVertexId($port)))
            ->will($this->returnValue(true));
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getPortVertexId($port))]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($portVertex),
                )
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($portVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($portVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->exactly(3))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('port')],
                [$this->equalTo('graphviz.style'), $this->equalTo('solid')],
                [$this->equalTo('graphviz.label'), $this->equalTo($port)],
            );

        addPortRelation($graph, $service, normalizePortMapping($port));
    }
}
