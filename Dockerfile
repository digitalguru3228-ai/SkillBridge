FROM php:8.2-apache

# Install required PHP extensions and Apache modules
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libzip-dev \
    && docker-php-ext-install pdo_mysql curl zip \
    && a2dismod mpm_event mpm_worker mpm_prefork || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && if [ -d /var/www/html/uploads ]; then chmod -R 775 /var/www/html/uploads; fi

EXPOSE 80