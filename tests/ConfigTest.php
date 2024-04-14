<?php

declare(strict_types=1);

namespace PMSIpilot\DockerComposeViz\Tests;

use Fhaculty\Graph\Edge\Directed;
use Fhaculty\Graph\Exception\OutOfBoundsException;
use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use PHPUnit\Framework\TestCase;

use function PMSIpilot\DockerComposeViz\addConfig;
use function PMSIpilot\DockerComposeViz\addConfigRelation;
use function PMSIpilot\DockerComposeViz\fetchConfigs;
use function PMSIpilot\DockerComposeViz\getConfigVertexId;
use function PMSIpilot\DockerComposeViz\getServiceVertexId;

class ConfigTest extends TestCase
{
    /**
     * @test
     */
    public function emptyDockerComposeConfiguration(): void
    {
        $this->assertEquals([], fetchConfigs([]));
    }

    /**
     * @test
     */
    public function returnConfigurationsFromDockerComposeConfiguration(): void
    {
        $configuration = [
            'configs' => [
                'test-config' => ['external' => true]
            ]
        ];

        $this->assertEquals($configuration['configs'], fetchConfigs($configuration));
    }

    /**
     * @test
     */
    public function generateVertexId(): void
    {
        $config = 'test-config';

        $this->assertEquals('config:'.$config, getConfigVertexId($config));
    }

    /**
     * @test
     */
    public function checkIfVertexAlreadyExistsInGraph(): void
    {
        $config = 'test-config';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getConfigVertexId($config))
            ->will($this->returnValue(true));
        $graph->expects($this->once())
            ->method('getVertex')
            ->with(getConfigVertexId($config))
            ->will($this->returnValue(new Vertex($graph, getConfigVertexId($config))));

        addConfig($graph, $config);
    }

    /**
     * @test
     */
    public function addVertexIfNotExistInGraph(): void
    {
        $config = 'test-config';
        $graph = $this->createMock(Graph::class);
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getConfigVertexId($config))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getConfigVertexId($config))
            ->will($this->returnValue(new Vertex($graph, getConfigVertexId($config))));

        addConfig($graph, $config);
    }

    /**
     * @test
     */
    public function setVertexAttributesWhenAdding(): void
    {
        $config = 'test-config';
        $graph = $this->createMock(Graph::class);
        $vertex = $this->createMock(Vertex::class);
        $vertex->expects($this->exactly(3))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('config')],
                [$this->equalTo('graphviz.label'), $this->equalTo($config)],
                [$this->equalTo('graphviz.shape'), $this->equalTo('note')],
            );
        $graph->expects($this->once())
            ->method('hasVertex')
            ->with(getConfigVertexId($config))
            ->will($this->returnValue(false));
        $graph->expects($this->once())
            ->method('createVertex')
            ->with(getConfigVertexId($config))
            ->will($this->returnValue($vertex));

        addConfig($graph, $config);
    }

    /**
     * @test
     */
    public function addRelationWithUnknownService(): void
    {
        $this->expectException(OutOfBoundsException::class);

        addConfigRelation(new Graph(), 'unknown', 'test-config');
    }

    /**
     * @test
     */
    public function addRelationWithUnknownConfiguration(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $service = 'test-service';
        $graph = new Graph();
        $graph->createVertex($service);

        addConfigRelation(new Graph(), $service, 'unknown');
    }

    /**
     * @test
     */
    public function checkIfEdgeOfExpectedTypeAlreadyExistsInGraph(): void
    {
        $config = 'test-config';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $configVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getConfigVertexId($config))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($configVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($configVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->never()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('config'));

        addConfigRelation($graph, $service, $config);
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistWithExpectedTypeInGraph(): void
    {
        $config = 'test-config';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $configVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getConfigVertexId($config))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($configVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue(true));
        $serviceVertex->expects($this->once())
            ->method('getEdgesTo')
            ->with($this->equalTo($configVertex))
            ->will($this->returnValue([$relation]));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));
        $relation->expects($this->once())
            ->method('getAttribute')
            ->with($this->equalTo('docker_compose_viz.type'))
            ->will($this->returnValue('unknown'));

        addConfigRelation($graph, $service, $config);
    }

    /**
     * @test
     */
    public function addEdgeIfNotExistInGraph(): void
    {
        $config = 'test-config';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $configVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getConfigVertexId($config))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($configVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue($this->createMock(Directed::class)));

        addConfigRelation($graph, $service, $config);
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAdding(): void
    {
        $config = 'test-config';
        $service = 'test-service';
        $graph = $this->createMock(Graph::class);
        $configVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getConfigVertexId($config))]
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($configVertex),
                )
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->once())
            ->method('setAttribute')
            ->with($this->equalTo('docker_compose_viz.type'), $this->equalTo('config'));

        addConfigRelation($graph, $service, $config);
    }

    /**
     * @test
     */
    public function setEdgeAttributesWhenAddingWithMapping(): void
    {
        $config = 'test-config';
        $service = 'test-service';
        $mapping = ['target' => 'config-target'];
        $graph = $this->createMock(Graph::class);
        $configVertex = $this->createMock(Vertex::class);
        $serviceVertex = $this->createMock(Vertex::class);
        $relation = $this->createMock(Directed::class);
        $graph->expects($this->exactly(2))
            ->method('getVertex')
            ->withConsecutive(
                [$this->equalTo(getServiceVertexId($service))],
                [$this->equalTo(getConfigVertexId($config))],
            )
            ->will(
                $this->onConsecutiveCalls(
                    $this->returnValue($serviceVertex),
                    $this->returnValue($configVertex),
                ),
            );
        $serviceVertex->expects($this->once())
            ->method('hasEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue(false));
        $serviceVertex->expects(($this->once()))
            ->method('createEdgeTo')
            ->with($this->identicalTo($configVertex))
            ->will($this->returnValue($relation));
        $relation->expects($this->exactly(2))
            ->method('setAttribute')
            ->withConsecutive(
                [$this->equalTo('docker_compose_viz.type'), $this->equalTo('config')],
                [$this->equalTo('graphviz.label'), $this->equalTo($mapping['target'])],
            );

        addConfigRelation($graph, $service, $config, $mapping);
    }
}
