DCV_IMAGE_NAME=pmsipilot/docker-compose-viz

COMPOSER ?= composer
COMPOSERFLAGS ?=
DOCKER ?= docker
PHP ?= php

.PHONY: clean docker test

docker: docker.lock

test: vendor
	$(PHP) bin/kahlan --pattern='*.php' --reporter=verbose

clean:
	rm -rf vendor/

docker.lock: Dockerfile vendor
	$(COMPOSER) dump-autoload --classmap-authoritative
	$(DOCKER) build -t $(DCV_IMAGE_NAME) .
	touch docker.lock

ifndef COMPOSERFLAGS
vendor: composer.lock
	$(COMPOSER) install --prefer-dist
else
vendor: composer.lock
	$(COMPOSER) update $(COMPOSERFLAGS)
endif

composer.lock: composer.json
	$(COMPOSER) update $(COMPOSERFLAGS)
