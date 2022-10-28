<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz\Tests;

use Fhaculty\Graph\Edge\Directed;
use Fhaculty\Graph\Exception\OutOfBoundsException;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use PHPUnit\Framework\TestCase;

use function PMSIpilot\DockerComposeViz\addSecret;
use function PMSIpilot\DockerComposeViz\addSecretRelation;
use function PMSIpilot\DockerComposeViz\fetchSecrets;
use function PMSIpilot\DockerComposeViz\getSecretVertexId;
use function PMSIpilot\DockerComposeViz\getServiceVertexId;

class SecretTest extends TestCase
{
    /**
     * @test
     */
    public function emptyDockerComposeSecreturation(): void
    {
        $this->assertEquals([], fetchSecrets([]));
    }

    /**
     * @test
     */
    public function returnSecreturationsFromDockerComposeSecreturation(): void
    {
        $secreturation = [
            'secrets' => [
                'test-secret' => ['external' => true]
            ]
        ];

        $this->assertEquals($secreturation['secrets'], fetchSecrets($secreturation));
    }

    /**
     * @test
     */
    public function generateVertexId(): void
    {
        $secret = 'test-secret';

        $this->assertEquals('secret:'.$secret, getSecretVertexId($secret));
    }

    /**
     * @test
     */
    public function checkIfVertexAlreadyExistsInGraph(): void
    {
        $secret = 'test-secret';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getSecretVertexId($secret))
            ->will($this->returnValue(true));
        $graph->expects($this->once())
            ->method('getVertex')
            ->with(getSecretVertexId($secret))
            ->will($this->returnValue(new Vertex($graph, getSecretVertexId($secret))));

        addSecret($graph, $secret);
    }

    /**
     * @test
     */
    public function addVertexIfNotExistInGraph(): void
    {
        $secret = 'test-secret';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getSecretVertexId($secret))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getSecretVertexId($secret))
            ->will($this->returnValue(new Vertex($graph, getSecretVertexId($secret))));

        addSecret($graph, $secret);
    }

    /**
     * @test
     */
    public function setVertexAttributesWhenAdding(): void
    {
        $secret = 'test-secret';
        $graph = $this->createMock(Graph::class);
        $vertex = $this->createMock(Vertex::class);
        $vertex->expects($this->exactly(2))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('secret')],
                [$this->equalTo('graphviz.shape'), $this->equalTo('hexagon')],
            );
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getSecretVertexId($secret))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getSecretVertexId($secret))
            ->will($this->returnValue($vertex));

        addSecret($graph, $secret);
    }

    /**
     * @test
     */
    public function addRelationWithUnknownService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        addSecretRelation(new Graph(), 'unknown', 'test-secret');
    }

    /**
     * @test
     */
    public function addRelationWithUnknownSecreturation(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addSecretRelation(new Graph(), $service, 'unknown');
    }

    /**
     * @test
     */
    public function checkIfEdgeOfExpectedTypeAlreadyExistsInGraph(): void
    {
        $secret = 'test-secret';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $secretVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getSecretVertexId($secret))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($secretVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($secretVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->never()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('secret'));

        addSecretRelation($graph, $service, $secret);
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistWithExpectedTypeInGraph(): void
    {
        $secret = 'test-secret';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $secretVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getSecretVertexId($secret))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($secretVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($secretVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('unknown'));

        addSecretRelation($graph, $service, $secret);
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistInGraph(): void
    {
        $secret = 'test-secret';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $secretVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getSecretVertexId($secret))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($secretVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));

        addSecretRelation($graph, $service, $secret);
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAdding(): void
    {
        $secret = 'test-secret';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $secretVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getSecretVertexId($secret))]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($secretVertex),
                )
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->once())
            ->method('setAttribute')
            ->with($this->equalTo('docker_compose_viz.type'), $this->equalTo('secret'));

        addSecretRelation($graph, $service, $secret);
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAddingWithMapping(): void
    {
        $secret = 'test-secret';
        $service = 'test-service';
        $mapping = ['source' => $secret, 'target' => 'secret-target'];
        $graph = $this->createMock(Graph::class);
        $secretVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getSecretVertexId($secret))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($secretVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($secretVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->exactly(2))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('secret')],
                [$this->equalTo('graphviz.label'), $this->equalTo($mapping['target'])],
            );

        addSecretRelation($graph, $service, $mapping);
    }
}
