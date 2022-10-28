DCV_IMAGE_NAME=pmsipilot/docker-compose-viz

COMPOSER ?= composer
COMPOSERFLAGS ?=
DOCKER ?= docker
PHP ?= php

.PHONY: clean docker test unit cs fix-cs

docker: docker.lock

test: vendor unit cs

vendor: vendor/composer/installed.json

unit: vendor
	$(COMPOSER) run ut

cs:
	$(COMPOSER) run cst

fix-cs:
	$(COMPOSER) run cs

clean:
	rm -rf vendor/

docker.lock: Dockerfile bin/entrypoint.sh vendor src/application.php src/functions.php
	$(DOCKER) build -t $(DCV_IMAGE_NAME) --no-cache .
	touch docker.lock

vendor/composer/installed.json: composer.lock
	$(COMPOSER) install --prefer-dist

composer.lock: composer.json
	$(COMPOSER) update $(COMPOSERFLAGS)
