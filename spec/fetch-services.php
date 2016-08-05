<?php

use function PMSIpilot\DockerComposeViz\fetchServices;

require_once __DIR__.'/../vendor/autoload.php';

describe('Fetching services', function () {
    describe('from a version 1 configuration', function () {
        it('should fetch services from top-level keys', function () {
            $configuration = ['foo' => ['image' => 'bar'], 'baz' => ['build' => '.']];

            expect(fetchServices($configuration))->toBe($configuration);
        });
    });

    describe('from a version 2 configuration', function () {
        it('should fetch services from the dedicated section', function () {
            $configuration = ['version' => 2, 'services' => ['foo' => ['image' => 'bar'], 'baz' => ['build' => '.']]];

            expect(fetchServices($configuration))->toBe($configuration['services']);
        });
    });
});
