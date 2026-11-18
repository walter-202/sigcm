# ==============================================================================
# Stage 1: Frontend build (Vite + Tailwind CSS 4)
# ==============================================================================
FROM node:22-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build

# ==============================================================================
# Stage 2: Runtime PHP 8.3-FPM + Nginx (Alpine)
# ==============================================================================
FROM php:8.3-fpm-alpine

# Instalar dependencias del sistema y extensiones PHP requeridas por Laravel 13,
# Filament 5, PostgreSQL (pdo_pgsql) y filament-excel (zip, gd, intl)
RUN apk add --no-cache \
        nginx \
        curl \
        git \
        unzip \
        icu-dev \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        gd \
        bcmath \
        mbstring \
        opcache \
        pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copiar código fuente de la aplicación
COPY . .

# Copiar los assets compilados de Vite desde la etapa frontend
COPY --from=frontend /app/public/build ./public/build

# Instalar dependencias de producción de Composer
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

# Configurar Nginx y el script de arranque
COPY docker/nginx.conf.template /etc/nginx/http.d/default.conf.template
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
