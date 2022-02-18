FROM composer:2

ARG UID
ARG GID

ENV UID=${UID}
ENV GID=${GID}

WORKDIR /var/www/html

RUN composer require jenssegers/mongodb