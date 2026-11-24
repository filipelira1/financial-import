FROM php:8.4-fpm-bookworm

ARG UID=1000
ARG GID=1000

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_mysql redis pcntl zip bcmath intl opcache

# Run PHP as the host user so files written to the bind mount keep the host owner.
RUN groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g "${GID}" www-data \
    && chown -R www-data:www-data /var/www

COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY --chmod=755 entrypoint.sh /usr/local/bin/entrypoint

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/backend
USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
