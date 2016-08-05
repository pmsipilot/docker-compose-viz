<?php

use function PMSIpilot\DockerComposeViz\fetchVolumes;

require_once __DIR__.'/../vendor/autoload.php';

describe('Fetching volumes', function () {
    describe('from a version 1 configuration', function () {
        it('should always return an empty array', function () {
            $configuration = ['volumes' => ['image' => 'bar']];

            expect(fetchVolumes($configuration))->toBe([]);
        });
    });

    describe('from a version 2 configuration', function () {
        it('should fetch volumes from the dedicated section', function () {
            $configuration = ['version' => 2, 'volumes' => ['foo' => [], 'bar' => []]];

            expect(fetchVolumes($configuration))->toBe($configuration['volumes']);
        });
    });
});
