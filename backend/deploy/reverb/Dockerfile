# Stand-alone Reverb WebSocket server (Pusher protocol). It shares NOTHING with the app except REVERB_APP_ID/KEY/SECRET:
# laravel/reverb 1.x pins Guzzle 7, the app runs Guzzle 8, so the server lives in its own throw-away Laravel skeleton built here.
# Build:  docker build -t 3alraf-reverb deploy/reverb   Run: see docker-compose.yml (service "reverb"), put TLS/wss in front (nginx: proxy /app and /apps to :8080).
FROM php:8.3-cli-alpine
RUN apk add --no-cache git unzip libstdc++ && docker-php-ext-install pcntl sockets
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /srv/reverb
RUN composer create-project laravel/laravel . --no-interaction --prefer-dist && composer require laravel/reverb --no-interaction && php artisan reverb:install --no-interaction || true
ENV APP_ENV=production BROADCAST_CONNECTION=reverb REVERB_SERVER_HOST=0.0.0.0 REVERB_SERVER_PORT=8080
EXPOSE 8080
CMD ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8080"]
