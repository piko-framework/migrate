# Piko Migrate

A small command-line tool for database migrations, built on top of [`dakujem/migrun`](https://github.com/dakujem/migrun).

`piko/migrate` provides a simple wrapper around Migrun to run, rollback, and inspect migrations from your terminal.

## Features

- Lightweight CLI for migrations
- Built on reliable migration orchestration from `dakujem/migrun`
- Supports:
  - applying pending migrations
  - rolling back applied migrations
  - displaying migration status
- Configurable migrations directory

## Requirements

- PHP 8+
- A PDO-compatible database (MySQL, SQLite, PostgreSQL, etc.)
- Composer

## Installation

### As a dependency

```bash
composer require piko/migrate
```

Then use the generated binary:

```bash
./vendor/bin/migrate --help
```

### For local development

```bash
composer install
php ./bin/migrate --help
```

## Configuration

The CLI reads database settings from environment variables:

- `DSN`
- `DB_USERNAME` (optional depending on DSN)
- `DB_PASSWORD` (optional depending on DSN)

You can also create an `env.php` file in your working directory. If present, it will be loaded automatically.

Example `env.php`:

```php
<?php

return [
    'DSN' => 'mysql:host=127.0.0.1;dbname=test;charset=utf8mb4',
    'DB_USERNAME' => 'my_user',
    'DB_PASSWORD' => 'my_password',
];
```

## Migration files

By default, migrations are loaded from:

```text
./migrations
```

You can override this directory with `-p` / `--path`.

A migration file should return an object exposing `up(PDO $db)` and `down(PDO $db)` methods.

Example:

```php
<?php

return new class {
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(120) NOT NULL)');
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE users');
    }
};
```

## CLI usage

```bash
migrate [options] <command>
```

### Global options

- `-h, --help` Show help
- `-p, --path` Path to migration directory (default: `./migrations`)

### Commands

#### Run pending migrations

```bash
migrate run
```

#### Roll back migrations

Roll back the latest migration:

```bash
migrate rollback
```

Roll back multiple migrations:

```bash
migrate rollback -s 3
```

#### Show status

```bash
migrate status
```

The status output includes:

- `up`: already applied
- `down`: pending
- `MISSING`: applied in history but migration file is no longer present

## Development

Run tests:

```bash
composer test
```

Run static analysis and coding style checks:

```bash
composer lint
```
