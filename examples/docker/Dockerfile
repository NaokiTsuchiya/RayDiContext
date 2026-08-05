# syntax=docker/dockerfile:1
FROM php:8.3-cli AS build

# php:8.3-cli ships neither composer nor anything that can unpack a Packagist
# download (no zip extension, no unzip, no git) — bring composer in from its
# own image and install unzip.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update && apt-get install -y --no-install-recommends unzip && apt-get clean

WORKDIR /build
COPY . .
RUN composer install --no-dev --optimize-autoloader \
 && php vendor/bin/ray-di-compile bootstrap.php /build prod /app/var/di/prod \
 && php bin/build-check

FROM php:8.3-cli
WORKDIR /app
COPY --from=build /build /app

# The RUN step above compiled straight to the literal runtime path
# /app/var/di/prod, not /build/var/di/prod — a path with no relationship to
# /build, so the COPY above never reaches it. This second COPY is what
# actually brings the compiled scripts into the runtime image; deleting it
# reproduces Ray\Compiler\Exception\ScriptDirNotReadable at container start.
COPY --from=build /app /app

RUN useradd --system appuser
USER appuser
CMD ["php", "bin/console"]
