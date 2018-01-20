#!/bin/bash

export REPO=pmsipilot/docker-compose-viz
export TAG=`if [ "$TRAVIS_BRANCH" == "master" ]; then echo "latest"; else echo $TRAVIS_BRANCH ; fi`

git reset --hard
git clean -dfx
composer install --no-dev --prefer-dist --classmap-authoritative

docker login -u $DOCKER_USER -p $DOCKER_PASS
docker build -f Dockerfile -t $REPO:$TAG .
docker push $REPO
