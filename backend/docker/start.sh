#!/bin/sh
set -eu

if [ ! -f .env ]; then
  cp .env.example .env
fi

if [ "${SKIP_COMPOSER_INSTALL:-0}" != "1" ]; then
  composer install --no-interaction --prefer-dist
fi

if [ -z "${APP_KEY:-}" ]; then
  php artisan key:generate --force
fi

until php -r '
try {
    $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT"), getenv("DB_DATABASE"));
    new PDO($dsn, getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
'; do
  echo "Waiting for MySQL..."
  sleep 2
done

php artisan migrate --force

php artisan serve --host=0.0.0.0 --port=8000