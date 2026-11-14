# syntax=docker/dockerfile:1
# Build context: ./bimilyoncu/server   |   ek context "docker": ./Docker
# Dockerfile'a özel ignore: Docker/bimilyoncu/Dockerfile.dockerignore

############ 1) Vite asset build ############
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json ./
RUN --mount=type=cache,target=/root/.npm npm install --no-audit --no-fund --fetch-retries=5 --fetch-timeout=120000
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

############ 2) Composer bağımlılıkları ############
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache COMPOSER_CACHE_DIR=/tmp/composer-cache composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

############ 3) Runtime (PHP 8.3 + Apache) ############
FROM php:8.3-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql bcmath intl zip gd exif opcache pcntl  && a2enmod rewrite headers remoteip  && rm -rf /var/lib/apt/lists/*

COPY --from=docker common/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY --from=docker common/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --from=docker --chmod=755 common/entrypoint.sh /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build


EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
