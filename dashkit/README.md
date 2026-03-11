# Dashkit Laravel Package

Dashkit is a lightweight Laravel dashboard engine that can install, scaffold, and clean up dashboard resources globally in your app.

## Docs Index

See `docs/README.md` for the complete documentation map.

Direct links:

1. `docs/01-full-package-explanation.md`
2. `docs/02-how-to-setup.md`
3. `docs/03-list-of-commands.md`
4. `docs/04-how-it-works.md`

## Quick Start

```bash
composer require dashkit/dashkit
php artisan dashkit:install
php artisan dashkit:make-page analytics "Analytics"
```

## Main Commands

```bash
php artisan dashkit:install
php artisan dashkit:make-page <name> [title]
php artisan dashkit:make-module <name> [title]
php artisan dashkit:uninstall [--yes] [--backup]
```
