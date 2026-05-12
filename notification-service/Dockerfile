FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        supervisor \
        rabbitmq-c-dev \
        linux-headers \
        $PHPIZE_DEPS \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && docker-php-ext-install pdo opcache \
    && apk del $PHPIZE_DEPS linux-headers \
    && mkdir -p /var/log/supervisor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && php bin/console cache:warmup --env=prod 2>/dev/null || true

COPY docker/supervisord.conf /etc/supervisord.conf

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]

