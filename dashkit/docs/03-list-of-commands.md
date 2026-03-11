# Dashkit Commands

## `php artisan dashkit:install`
Installs and configures Dashkit interactively.

Options:
- `--force` Overwrite published files.
- `--resume` Continue from saved install progress.
- `--type=default|ecommerce|crm` Choose dashboard preset.

Does:
- publish package resources
- replace Laravel default `/ -> welcome` route when present
- register Dashkit routes so dashboard home is `/`
- update `.env`
- configure guest redirect
- create/verify DB
- run migrations and seed admin
- create preset-aware default pages and sidebar links
- create installer rollback state

---

## `php artisan dashkit:make-page {name} {title?}`
Creates a global dashboard page and route.

Options:
- `--force` Overwrite existing page.

Generates:
- `resources/views/dashkit/pages/<slug>.blade.php`
- Appends route in `routes/web.php` as `dashkit.page.<slug>` at `/dashboard/<slug>`
- Adds sidebar item in `config/dashkit.php`
- Tracks file/route in artifact manifest

---

## `php artisan dashkit:make-module {name} {title?}`
Creates full global module scaffold.

Options:
- `--force` Overwrite files where applicable.

Generates:
- `app/Models/<Name>.php`
- `app/Http/Controllers/Dashkit/<Name>Controller.php`
- `resources/views/dashkit/modules/<slug>/index.blade.php`
- `database/migrations/*_create_<table>_table.php`
- Appends route in `routes/web.php` as `dashkit.module.<slug>` at `/dashboard/<slug>`
- Adds sidebar item in `config/dashkit.php`
- Tracks all artifacts in manifest

---

## `php artisan dashkit:uninstall`
Shows preview, asks action, and removes Dashkit artifacts.

Options:
- `--yes` Remove immediately (no interactive prompt)
- `--backup` Create backup before removal (use with `--yes` or interactive `yes`)

Interactive choices:
1. `yes` -> backup then reset
2. `no` -> cancel, no changes

Removes:
- published package files
- tracked installer changes
- tracked generated files/routes from Dashkit commands
- Dashkit guest redirect from `bootstrap/app.php`
- `DASHKIT_*` keys from `.env`
- clears artifact manifest

---

## `php artisan dashkit:upgrade`
Upgrades an already installed Dashkit project to the latest package version.

Options:
- `--force` Overwrite published files during upgrade.
- `--dry-run` Preview actions without applying changes.
- `--type=default|ecommerce|crm` Apply a dashboard preset during upgrade.

Does:
- compares installed package version vs current package version
- publishes updated config/views/assets
- ensures preset-specific pages/routes/sidebar exist
- runs migrations and clears config/view cache
- updates package version state in storage

---

## `composer run dashkit-update`
A Composer script automatically added to your `composer.json` during `dashkit:install`.

Runs both update steps in one command:
```bash
composer run dashkit-update
# Equivalent to:
#   composer update dashkit/dashkit
#   php artisan dashkit:upgrade
```

This is the recommended way for any developer to pull and apply new package versions.
No need to remember two separate commands.

---

## `php artisan dashkit:switch-preset {type?}`
Switches the active dashboard preset without running a full upgrade.

Options:
- `type` Optional preset: `default`, `ecommerce`, or `crm`

Does:
- rewrites `config/dashkit.php` sidebar entries for the selected preset
- creates missing preset pages
- appends missing preset page routes in `routes/web.php`
- stores selected preset in package state
- clears config and view cache

---

## Useful supporting commands
```bash
php artisan list | findstr dashkit
php artisan route:list | findstr dashkit
php artisan view:clear
```
