FROM php:7.1-alpine

RUN apk update && \
    apk add graphviz ttf-dejavu && \
    rm -rf \
        /var/cache/apk/* \
        /tmp/*

COPY bin/    /dcv/bin
COPY src/    /dcv/src
COPY vendor/ /dcv/vendor

RUN chmod +x /dcv/bin/dcv

RUN addgroup dcv && \
    adduser -D -G dcv -s /bin/bash -g "docker-compose-viz" -h /input dcv

USER dcv
VOLUME /input
WORKDIR /input

ENTRYPOINT ["/dcv/bin/entrypoint.sh"]
CMD ["render", "-m", "image", "-f"]
