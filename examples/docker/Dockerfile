# syntax=docker/dockerfile:1
FROM php:8.3-cli AS build
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip && apt-get clean
WORKDIR /build
COPY . .
RUN composer install --no-dev --optimize-autoloader \
 && php vendor/bin/ray-di-compile bootstrap.php /build prod /app/var/di/prod

FROM php:8.3-cli
WORKDIR /app
COPY --from=build /build /app
COPY --from=build /app /app
RUN useradd --system appuser
USER appuser
CMD ["php", "bin/console"]
