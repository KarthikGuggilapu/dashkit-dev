# Dashkit Setup Guide

## Prerequisites
- PHP 8.2+
- Laravel 12+
- Working database server (MySQL/PostgreSQL/SQLite)

## 1) Require package

### Standard install
```bash
composer require dashkit/dashkit
```

### Local path repository (monorepo/dev)
Add this to root `composer.json`:
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/dashkit",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "dashkit/dashkit": "*"
  }
}
```
Then run:
```bash
composer update dashkit/dashkit
```

## 2) Run installer
```bash
php artisan dashkit:install
```

Examples:
```bash
php artisan dashkit:install --type=default
php artisan dashkit:install --type=ecommerce
php artisan dashkit:install --type=crm
```

Installer asks for:
- app name
- DB connection details
- default admin name/email/password
- dashboard preset if `--type` is not provided

Installer performs:
- publish config/views/assets
- route include setup
- removes default Laravel welcome route for `/` when present
- `.env` update
- guest redirect setup to Dashkit login
- DB creation verification
- migrations + admin seeding
- preset-aware default page generation
- preset-aware sidebar setup

## 3) Optional maintenance
```bash
composer dump-autoload
php artisan package:discover
php artisan optimize:clear
```

## 4) Login and use dashboard
- Open: `/login`
- Login with seeded admin credentials from install prompt
- Open: `/`

Generated pages open under:
- `/dashboard/<slug>`

Examples:
- `/dashboard/reports`
- `/dashboard/settings`
- `/dashboard/products`
- `/dashboard/leads`

## 5) Configure package
Edit `config/dashkit.php` (published copy in app config):
- `route_prefix`
- `route_middleware`
- `auth` settings
- `sidebar`
- `widgets`
- generated page path/namespace

## Common commands after setup
```bash
php artisan dashkit:make-page analytics "Analytics"
php artisan dashkit:make-module inventory "Inventory"
php artisan dashkit:switch-preset ecommerce
```
