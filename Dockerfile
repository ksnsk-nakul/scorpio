# unrar is not packaged for Alpine (RARLAB's freeware license is what keeps
# it out of official distro repos generally — same reason Debian only ships
# it via non-free — Alpine has no equivalent repo for it at all). RARLAB's
# license explicitly permits freely distributing the *compiled* unrar
# utility "inside of other software packages" (see license.txt in the
# source tarball), so we build it from source here and copy the resulting
# binary into the final image instead of relying on a package manager.
FROM php:8.3-fpm-alpine AS unrar-builder

ARG UNRAR_VERSION=7.2.7

RUN apk add --no-cache build-base curl

WORKDIR /tmp/build
RUN curl -fsSL "https://www.rarlab.com/rar/unrarsrc-${UNRAR_VERSION}.tar.gz" -o unrarsrc.tar.gz \
    && tar xzf unrarsrc.tar.gz \
    && cd unrar \
    && make -j"$(nproc)" \
    && strip unrar

FROM php:8.3-fpm-alpine AS base

# System dependencies
#
# libreoffice-writer: pulls in libreoffice-common (the actual soffice/
# soffice.bin engine) plus only the Writer document-format filters, so
# Doc/Docx/ODT -> PDF conversion works without the calc/impress/draw apps
# or the ~150 language packs the full `libreoffice` meta-package installs.
# libstdc++: runtime dependency for the unrar binary built in the
# unrar-builder stage (g++-compiled, dynamically linked).
RUN apk add --no-cache \
    nginx \
    nodejs \
    npm \
    sqlite \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    zip \
    unzip \
    git \
    curl \
    libreoffice-writer \
    libstdc++

# Bundle the unrar binary compiled in the unrar-builder stage above.
COPY --from=unrar-builder /tmp/build/unrar/unrar /usr/local/bin/unrar
RUN chmod +x /usr/local/bin/unrar

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql fileinfo gd zip pcntl bcmath opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application
COPY . .

# Install PHP dependencies (no dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node dependencies and build assets
RUN npm ci --ignore-scripts && npm run build && rm -rf node_modules

# Nginx config
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Storage permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Startup script
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
