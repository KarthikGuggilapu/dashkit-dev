# Dashkit — Laravel Dashboard Package

A lightweight dashboard engine for Laravel. Install a fully working admin dashboard — login, sidebar, pages, modules — all through simple Artisan commands.

**Laravel 10 / 11 / 12** · **PHP 8.1+** · **MySQL / PostgreSQL / SQLite**

---

## Table of Contents

1. [Requirements](#requirements)
2. [Project Setup](#project-setup)
3. [Install Dashkit](#install-dashkit)
4. [Presets](#presets)
5. [Updating the Package](#updating-the-package)
6. [Commands Reference](#commands-reference)
7. [Generating Pages & Modules](#generating-pages--modules)
8. [URL Structure](#url-structure)
9. [Configuration](#configuration)
10. [Project Structure](#project-structure)

---

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12
- MySQL, PostgreSQL, or SQLite
- Node.js & npm

---

## Project Setup

**Step 1 — Create a fresh Laravel project**

```bash
composer create-project laravel/laravel my-app
cd my-app
```

**Step 2 — Add the Dashkit package repository**

Open `composer.json` and make the following additions:

**Add to `repositories`:**

```json
"repositories": [
  {
    "type": "package",
    "package": {
      "name": "dashkit/dashkit",
      "version": "dev-dashkit-dev",
      "source": {
        "type": "git",
        "url": "https://github.com/KarthikGuggilapu/dashkit-dev.git",
        "reference": "dashkit-dev"
      },
      "type": "library",
      "require": {
        "php": "^8.2",
        "illuminate/support": "^12.0",
        "illuminate/routing": "^12.0",
        "illuminate/view": "^12.0",
        "illuminate/auth": "^12.0",
        "illuminate/console": "^12.0"
      },
      "autoload": {
        "psr-4": {
          "Dashkit\\\\": "dashkit/src/"
        }
      },
      "extra": {
        "laravel": {
          "providers": [
            "Dashkit\\\\DashkitServiceProvider"
          ]
        }
      }
    }
  }
]
```

**Add to `require`:**

```json
"dashkit/dashkit": "dev-dashkit-dev"
```

That's all you need to add manually. The `config.preferred-install` and `scripts` (`dashkit-sync`, `dashkit-update`) are **injected automatically** into your `composer.json` when you run `php artisan dashkit:install`.

> If the repository is **private**, authenticate first:
> ```bash
> composer config github-oauth.github.com YOUR_GITHUB_TOKEN
> ```

**Step 3 — Install dependencies**

> If composer not installed, install composer with below command
```bash
composer install
```

> If composer installed already, update the composer to get the package
```bash
composer update
```

```bash
npm install
```



**Step 4 — Set up environment**

```bash
cp .env.example .env
php artisan key:generate
```

**Step 5 — Configure your database**

Open `.env` and fill in your database details or skip this for Dashkit installation:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

> **Using SQLite?** Just set `DB_CONNECTION=sqlite` — Dashkit will auto-configure the database file path during install.

**Step 6 — Build assets**

```bash
npm run build
```

---

## Install Dashkit

Run the installer:

```bash
php artisan dashkit:install
```

The installer will guide you through each step interactively:

- Publishes config, views, and assets
- Asks for your app name, database connection, and admin credentials
- Sets up `.env` values (mail settings are skipped — configure them later in `.env` or the dashboard)
- Runs migrations and creates the admin user
- Applies your chosen preset (default, ecommerce, or crm)
- Generates default pages and sidebar navigation
- Adds a `dashkit-update` script to your `composer.json` for easy future updates

**Start the server**

```bash
php artisan serve
```

Visit `http://localhost:8000` — log in with the admin credentials you set during install.

---

## Presets

During install you choose a preset that determines which pages and sidebar links are created:

| Preset | Pages |
|--------|-------|
| `default` | Overview, Reports, Settings, Profile |
| `ecommerce` | Overview, Products, Orders, Customers, Inventory, Reports, Settings, Profile |
| `crm` | Overview, Leads, Contacts, Deals, Activities, Reports, Settings, Profile |

```bash
# Let the installer ask you interactively
php artisan dashkit:install

# Or specify upfront
php artisan dashkit:install --type=ecommerce
php artisan dashkit:install --type=crm
```

**Switch presets any time** (without running a full install):

```bash
php artisan dashkit:switch-preset ecommerce
php artisan dashkit:switch-preset crm
php artisan dashkit:switch-preset default
```

This updates your sidebar and creates any missing pages without deleting existing ones.

---

## Updating the Package

### Standard update

Whenever a new version is available, run:

```bash
composer run dashkit-update
```

This runs two steps in one:
1. Pulls latest commits from GitHub via `dashkit-sync`
2. Applies config, view, and asset updates via `php artisan dashkit:upgrade`

**Your customised views are never overwritten automatically.** Use `--force` only when you want a full UI reset:

```bash
php artisan dashkit:upgrade --force
```

---

### Daily update flow after new Git commits

Whenever package code is updated on branch `dashkit-dev`, run:

```bash
composer run-script dashkit-sync
php artisan dashkit:upgrade --type=crm --force
```

---

### Why not just `composer update`?

With this setup (package manifest inside a subfolder), Composer may show `Nothing to modify` even when new branch commits exist. `dashkit-sync` solves that by directly pulling the latest commits into `vendor/dashkit/dashkit` and refreshing autoload and package discovery.

---

### Troubleshooting

**Sync fails with _source checkout not found_:**

```bash
composer install
composer run-script dashkit-sync
```

**New classes or commands not detected after sync:**

```bash
composer dump-autoload
php artisan package:discover --ansi
```

---

## Commands Reference

| Command | Description |
|---------|-------------|
| `php artisan dashkit:install` | Full install — interactive setup from scratch |
| `php artisan dashkit:install --resume` | Resume an interrupted install |
| `php artisan dashkit:install --type=ecommerce` | Install with a specific preset |
| `php artisan dashkit:install --force` | Overwrite already published files |
| `php artisan dashkit:upgrade` | Apply latest package changes to an installed app |
| `php artisan dashkit:upgrade --dry-run` | Preview what upgrade will change |
| `php artisan dashkit:version` | Show current package version, installed version, and update status |
| `php artisan dashkit:upgrade --type=crm` | Upgrade and switch preset at the same time |
| `php artisan dashkit:switch-preset {type}` | Switch preset without a full upgrade |
| `php artisan dashkit:make-page {name} {title}` | Generate a new dashboard page |
| `php artisan dashkit:make-module {name} {title}` | Generate a new module with model, controller, migration |
| `php artisan dashkit:uninstall` | Remove Dashkit with confirmation prompt |
| `php artisan dashkit:uninstall --yes` | Remove without confirmation |
| `php artisan dashkit:uninstall --backup` | Backup files before removing |
| `composer run dashkit-update` | Pull latest package + run upgrade (one command) |

---

## Generating Pages & Modules

**Create a new page:**

```bash
php artisan dashkit:make-page analytics "Analytics"
```

- Creates `resources/views/dashkit/pages/analytics.blade.php`
- Registers route at `/dashboard/analytics`
- Adds a sidebar entry in `config/dashkit.php`

**Create a module** (page + model + controller + migration):

```bash
php artisan dashkit:make-module invoice "Invoice"
```

- Creates `resources/views/dashkit/modules/invoice/index.blade.php`
- Creates `app/Models/Invoice.php`
- Creates `app/Http/Controllers/Dashkit/InvoiceController.php`
- Creates a migration for the `invoices` table
- Registers route at `/dashboard/invoice`
- Adds a sidebar entry in `config/dashkit.php`

---

## URL Structure

| URL | Page |
|-----|------|
| `/` | Dashboard home (overview) |
| `/login` | Login page |
| `/logout` | Logs out, redirects to `/login` |
| `/dashboard/{slug}` | Any page or module |

---

## Configuration

After install, `config/dashkit.php` is published to your app. Edit it to customise the dashboard:

```php
// config/dashkit.php

'app_name' => 'My Dashboard',

'sidebar' => [
    ['title' => 'Overview',  'slug' => 'overview',  'icon' => 'home'],
    ['title' => 'Analytics', 'slug' => 'analytics', 'icon' => 'bar-chart'],
    ['title' => 'Settings',  'slug' => 'settings',  'icon' => 'settings'],
],

'topbar' => [
    'enabled' => true,
],
```

After editing config, clear the cache:

```bash
php artisan config:clear
```

---

## License

MIT

