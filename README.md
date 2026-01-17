# Online Casino

Symfony 6.4 project.

## Requirements

- PHP 8.1+
- Composer
- MariaDB (or compatible MySQL server)

## Setup

```bash
composer install
```

Create `.env.local` and set your database connection:

```bash
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/app?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
```

You can start from the provided example:

```bash
cp .env.example .env.local
```

Run migrations:

```bash
php bin/console doctrine:migrations:migrate
```

## Run locally

```bash
php -S 127.0.0.1:8000 -t public
```

Then open http://127.0.0.1:8000.

php bin/console asset-map:compile

## Tests

```bash
vendor/bin/phpunit
```

