<?php

use function PMSIpilot\DockerComposeViz\fetchNetworks;

require_once __DIR__.'/../vendor/autoload.php';

describe('Fetching networks', function () {
    describe('from a version 1 configuration', function () {
        it('should always return an empty array', function () {
            $configuration = ['networks' => ['image' => 'bar']];

            expect(fetchNetworks($configuration))->toBe([]);
        });
    });

    describe('from a version 2 configuration', function () {
        it('should fetch networks from the dedicated section', function () {
            $configuration = ['version' => 2, 'networks' => ['foo' => [], 'bar' => []]];

            expect(fetchNetworks($configuration))->toBe($configuration['networks']);
        });
    });
});
