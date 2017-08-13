#!/usr/bin/env sh

set -e

CURRENT_DIR=$(dirname $0)

if [ "$1" = "render" ]
then
    $CURRENT_DIR/dcv "$@"
else
    exec "$@"
fi
