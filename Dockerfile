FROM php:7.4-alpine as builder

COPY composer.json /dcv/composer.json
COPY composer.lock /dcv/composer.lock

WORKDIR /dcv

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    php composer.phar install --prefer-dist

FROM php:7.4-alpine

RUN apk update && \
    apk add graphviz ttf-dejavu && \
    rm -rf \
        /var/cache/apk/* \
        /tmp/*

COPY bin/    /dcv/bin
COPY src/    /dcv/src
COPY --from=builder /dcv/vendor /dcv/vendor

RUN chmod +x /dcv/bin/dcv

RUN addgroup dcv && \
    adduser -D -G dcv -s /bin/bash -g "docker-compose-viz" -h /input dcv

USER dcv
VOLUME /input
WORKDIR /input

ENTRYPOINT ["/dcv/bin/entrypoint.sh"]
CMD ["render", "-m", "image", "-f"]
