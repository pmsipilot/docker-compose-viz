<?php

use function PMSIpilot\DockerComposeViz\readConfiguration;

require_once __DIR__.'/../vendor/autoload.php';

describe('Reading configuration', function () {
    it('should check if file exists', function () {
        expect(function () {
            readConfiguration(uniqid());
        })
            ->toThrow(new InvalidArgumentException());
    });

    it('should parse YAML and return an array', function () {
        expect(readConfiguration(__DIR__.'/fixtures/read-configuration/valid.yml'))
            ->toBe(['version' => 2, 'services' => ['foo' => ['image' => 'bar']]]);
    });

    it('should report if YAML is invalid', function () {
        expect(function () {
            readConfiguration(__DIR__.'/fixtures/read-configuration/invalid.yml');
        })
            ->toThrow(new InvalidArgumentException());
    });
});
