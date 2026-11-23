# PHP 8.5 wie auf dem Plesk, Pakete von packages.sury.org (wie bisher in WSL). Läuft als php-fpm für nginx und
# nimmt über php8.5 (Verknüpfung, siehe compose.yaml) alle Aufrufe von „php8.5 …“ entgegen.
FROM debian:trixie-slim

ARG UID=1000
ARG GID=1000

ENV DEBIAN_FRONTEND=noninteractive \
    LANG=C.UTF-8 \
    TZ=Europe/Berlin

RUN apt-get update \
 && apt-get install -y --no-install-recommends ca-certificates curl \
 && curl -fsSLo /tmp/sury-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
 && dpkg -i /tmp/sury-keyring.deb \
 && rm /tmp/sury-keyring.deb \
 && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ trixie main" \
      > /etc/apt/sources.list.d/sury-php.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends \
      php8.5-cli php8.5-fpm \
      php8.5-bcmath php8.5-curl php8.5-intl php8.5-mbstring php8.5-mysql php8.5-xml php8.5-zip \
      git unzip tzdata \
 && rm -rf /var/lib/apt/lists/*

# Gleiche Benutzer-ID wie in WSL: Dateien aus dem Container (Cache, vendor, Logs) gehören dem eigenen Benutzer
RUN groupadd --gid "$GID" dev \
 && useradd --uid "$UID" --gid "$GID" --create-home --shell /bin/bash dev

COPY php.ini /etc/php/8.5/cli/conf.d/99-hermine-bot.ini
COPY php.ini /etc/php/8.5/fpm/conf.d/99-hermine-bot.ini
COPY php-fpm.conf /etc/php/8.5/fpm/php-fpm.conf

USER dev
CMD ["php-fpm8.5", "--nodaemonize", "--fpm-config", "/etc/php/8.5/fpm/php-fpm.conf"]
