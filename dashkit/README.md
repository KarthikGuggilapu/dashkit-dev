# Dashkit - Laravel Dashboard Package

Dashkit is a Laravel package for bootstrapping and operating an admin dashboard inside a host Laravel application. It provides authentication screens, dashboard pages, settings screens, reusable UI components, generators, upgrade tooling, uninstall tooling, audit logging, and both CLI and browser-based installation flows.

Current package version: `1.5.2`

Supported stack: Laravel 10 / 11 / 12, PHP 8.1+, MySQL / PostgreSQL / SQLite

---

## Table of Contents

1. What Dashkit Is
2. Why It Exists
3. Core Features
4. Package Architecture
5. Requirements
6. Installation Into a Host Project
7. End-to-End Setup Flows
8. GUI Setup Wizard
9. Lifecycle Safety and Recovery Model
10. What Dashkit Changes in Your Project
11. Routes and URL Structure
12. Configuration and Runtime Behavior
13. Generators and Scaffolding Commands
14. Upgrade Flow
15. Uninstall Flow
16. Commands Reference
17. Troubleshooting
18. Pros and Tradeoffs
19. Package Structure
20. License

---

## What Dashkit Is

Dashkit is a package-first dashboard system for Laravel applications. Instead of hand-building an admin shell from scratch, you install Dashkit into a host app and it:

- publishes its config, views, and assets
- wires dashboard routes into the host application
- provides login, password reset, profile, settings, and dashboard screens
- creates preset pages for common dashboard scenarios
- gives you page and module generators for extending the dashboard later
- tracks what it created so upgrade and uninstall flows are safer

Dashkit is not a separate standalone app. It runs inside your Laravel project and uses your app's users, environment, database, and routing context.

---

## Why It Exists

Dashkit exists to reduce the setup cost of building an internal admin UI or dashboard shell in Laravel while still keeping the host project in control.

The package is designed to solve these problems:

- repeated setup of login, layout, sidebar, and default dashboard pages
- inconsistent dashboard scaffolding between projects
- risky manual upgrades and removals of published package files
- hard-to-resume installs after database or environment failures
- need for both terminal-first and browser-first onboarding flows

---

## Core Features

Dashkit currently includes:

- interactive CLI installer
- browser-based GUI setup wizard at `/dashkit-console`
- lifecycle-aware install, resume, reinstall, upgrade, and uninstall behavior
- published config, views, and public assets
- dashboard auth pages and password reset flow
- dashboard home, settings, profile, search, and generated page routes
- preset-based dashboard bootstrapping: `default`, `ecommerce`, `crm`
- page generator, module generator, rename, and delete commands
- audit logging command support
- reusable Blade UI components
- tracked artifact manifest for safer cleanup and rename/delete workflows
- uninstall backups and restore-aware cleanup
- package version and release-check tooling

---

## Package Architecture

At a high level, Dashkit works in four layers.

### 1. Service provider bootstrap

`Dashkit\DashkitServiceProvider` is auto-discovered by Laravel and is responsible for:

- merging `config/dashkit.php`
- loading package views and migrations
- conditionally loading dashboard routes
- conditionally loading setup routes when a setup token exists
- registering all Artisan commands
- registering UI components and widget defaults
- applying stored runtime settings from the database when available

### 2. Host app integration

During install and upgrade, Dashkit publishes and modifies host-app resources such as:

- `config/dashkit.php`
- `resources/views/vendor/dashkit`
- `public/vendor/dashkit`
- generated dashboard page/module files
- `routes/web.php`
- `.env`
- `bootstrap/app.php` when guest redirect integration is required

### 3. Setup and lifecycle engine

The installer and setup wizard use a shared lifecycle-aware engine. Before acting, Dashkit inspects the project for existing traces and decides whether the correct action is install, resume, upgrade, or a manual cleanup path.

### 4. Tracking and recovery

Dashkit stores state under `storage/app/dashkit` so it can:

- resume incomplete installs
- track generated files and routes
- compare upgrade state
- remove only tracked artifacts during uninstall
- expose setup progress inside the GUI wizard

---

## Requirements

- PHP 8.1 or higher
- Laravel 10, 11, or 12
- MySQL, PostgreSQL, or SQLite
- Node.js and npm

---

## Installation Into a Host Project

### 1. Create or open a Laravel app

```bash
composer create-project laravel/laravel my-app
cd my-app
```

### 2. Add the Dashkit package source

Add the package repository and requirement to the host app `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/KarthikGuggilapu/dashkit-dev.git"
    }
  ],
  "require": {
    "dashkit/dashkit": "dev-dashkit-dev"
  }
}
```

If the GitHub repository is private:

```bash
composer config github-oauth.github.com YOUR_GITHUB_TOKEN
```

### 3. Install dependencies

```bash
composer install
npm install
```

If you are adding Dashkit into an existing project, `composer update` is also acceptable.

### 4. Prepare the Laravel environment

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Prepare database values

You can prefill the database in `.env` or let Dashkit collect it during setup:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

For SQLite, set `DB_CONNECTION=sqlite`. Dashkit can resolve the SQLite path during install.

### 6. Build frontend assets

```bash
npm run build
```

When the installer runs, Dashkit also injects helper Composer scripts such as `dashkit-sync` and `dashkit-update` into the host project.

---

## End-to-End Setup Flows

Dashkit supports two primary install paths.

### CLI setup flow

Run:

```bash
php artisan dashkit:install
```

The CLI flow:

1. inspects the project for existing Dashkit traces
2. decides whether the project is fresh, resumable, upgrade-ready, or leftover-heavy
3. lets you continue in CLI or GUI mode
4. collects app, database, admin, and preset information
5. runs the installer step sequence
6. stores install state and package state for later upgrade/uninstall flows

CLI mode is the recommended path for:

- SSH sessions
- servers
- CI-like scripted usage
- debugging installer internals

### GUI setup flow

Choose GUI mode from `php artisan dashkit:install` when working locally.

If the application is already reachable, Dashkit tries to open the default browser automatically. If not, it prints the exact URL to open manually.

---

## Installer Step Model

Dashkit uses a 7-step guided install engine:

1. `publish_config`
2. `publish_views`
3. `publish_assets`
4. `route_registration`
5. `env_setup`
6. `post_setup`
7. `migrate_seed`

This same step model is used by:

- the CLI installer
- GUI guided install
- resume flow after failure

If the install is interrupted, continue with:

```bash
php artisan dashkit:install --resume
```

If you need to stop intentionally after a known step:

```bash
php artisan dashkit:install --stop-after=env_setup
```

---

## GUI Setup Wizard

The GUI setup wizard is served at `/dashkit-console` while a setup token exists.

### How it is enabled

GUI setup is activated by creating a setup token file at:

`storage/app/dashkit/setup-token.json`

When that file exists, Dashkit registers setup routes and loads the browser wizard. After setup completes, the token is removed and the wizard is no longer intended to remain open.

### What the GUI does

The wizard currently provides:

- a 4-stage form flow: Project, Database, Admin, Review
- test connection support before install
- lifecycle-aware allowed actions: `install`, `resume`, `reinstall`, `upgrade`
- a 7-step progress panel aligned with CLI installer steps
- locked reviewed inputs after guided execution starts
- output for the current step, last completed step, and retry guidance

### Why the setup SQLite database exists

Some host apps use `SESSION_DRIVER=database` before the real application database is ready. To avoid breaking the setup flow, Dashkit temporarily uses:

`storage/app/dashkit/setup.sqlite`

This setup database is used only so the browser installer can function safely before the main connection is ready.

---

## Lifecycle Safety and Recovery Model

Before install, upgrade, and uninstall, Dashkit inspects the host project for traces.

### Trace status values

- `clean`: no traces found
- `partial`: some traces found
- `installed`: a full installation appears to be present

### Lifecycle states

- `fresh`
- `resume-available`
- `ready-to-upgrade`
- `leftovers-detected`

### What Dashkit inspects

Dashkit checks for signals such as:

- published config
- published vendor views
- published public assets
- generated app views
- installer state files
- progress file
- setup token
- setup SQLite database
- package state file with installed version
- Dashkit route include in `routes/web.php`
- Dashkit redirect changes in `bootstrap/app.php`
- `DASHKIT_*` environment variables

### Manual inspection

```bash
php artisan dashkit:inspect
php artisan dashkit:inspect --json
```

The report includes:

- lifecycle status
- trace status
- detected trace count
- installed version when available
- recommended action
- recommended command

### Decision guide

Use this rule set:

- fresh project: run `php artisan dashkit:install`
- interrupted install: run `php artisan dashkit:install --resume`
- installed project needing updates: run `php artisan dashkit:upgrade`
- leftover/partial project: inspect first, then decide between resume, force install, or uninstall

---

## What Dashkit Changes in Your Project

Dashkit modifies or creates several host-project resources.

### Published package resources

- `config/dashkit.php`
- `resources/views/vendor/dashkit/`
- `public/vendor/dashkit/`

### Generated application resources

- `resources/views/dashkit/pages/`
- `resources/views/dashkit/modules/`
- `app/Models/...` for generated modules
- `app/Http/Controllers/Dashkit/...` for generated modules
- module migrations
- optional seeders such as `database/seeders/DashkitAdminSeeder.php`

### Modified host files

- `routes/web.php`
- `.env`
- `bootstrap/app.php`
- `composer.json`

### Runtime and tracking files

Dashkit stores state under `storage/app/dashkit/` including:

- `package-state.json`
- `install-progress.json`
- `install-state.json`
- `artifacts.json`
- `setup-token.json`
- `setup.sqlite`

### Artifact tracking

Dashkit uses `artifacts.json` to track files and routes it generated. This is important for:

- safer uninstall
- rename/delete operations
- change detection
- avoiding blind cleanup of unrelated user files

---

## Routes and URL Structure

Dashkit route loading is conditional.

- setup routes load only while a setup token exists
- dashboard routes load only when Dashkit is enabled in runtime config

### Setup routes

- `GET /dashkit-console`
- `POST /dashkit-console/test-connection`
- `POST /dashkit-console/install`

### Auth and dashboard routes

Dashkit provides routes such as:

- `/login`
- `/forgot-password`
- `/reset-password/{token}`
- `/logout`
- `/`
- `/{route_prefix}` redirect handling
- `/{route_prefix}/search`
- `/{route_prefix}/profile`
- `/{route_prefix}/settings`
- `/{route_prefix}/{page}`

The default route prefix is `dashboard`, configured by `DASHKIT_ROUTE_PREFIX` or `dashkit.route_prefix`.

---

## Configuration and Runtime Behavior

Dashkit publishes `config/dashkit.php` to the host app.

Important config areas include:

- package branding name
- route prefix and route middleware
- auth guard, login route path, logout path, redirect behavior
- sidebar items
- topbar behavior
- default widgets
- generated pages namespace and path
- installer publish flags

### Runtime settings from the database

When the `dashkit_settings` table exists, Dashkit can apply stored settings at runtime for things such as:

- app name
- locale and timezone
- mail settings
- topbar settings
- sidebar items

This allows package-driven settings screens to influence runtime behavior.

### After config changes

```bash
php artisan config:clear
```

---

## Presets

Dashkit supports three presets.

| Preset | Pages |
|--------|-------|
| `default` | Overview, Reports, Settings, Profile |
| `ecommerce` | Overview, Products, Orders, Customers, Inventory, Reports, Settings, Profile |
| `crm` | Overview, Leads, Contacts, Deals, Activities, Reports, Settings, Profile |

Install directly with a preset:

```bash
php artisan dashkit:install --type=ecommerce
php artisan dashkit:install --type=crm
```

Switch presets later:

```bash
php artisan dashkit:switch-preset ecommerce
php artisan dashkit:switch-preset crm
php artisan dashkit:switch-preset default
```

Switching a preset updates default pages and sidebar wiring without requiring a full reinstall.

---

## Generators and Scaffolding Commands

### Make a page

```bash
php artisan dashkit:make-page analytics "Analytics"
```

This creates a dashboard page view and registers its tracked route/sidebar artifact.

### Make a module

```bash
php artisan dashkit:make-module invoice "Invoice"
```

This generates:

- model
- controller
- migration
- view directory and index view
- route entry
- sidebar entry

### Rename generated items

```bash
php artisan dashkit:rename-page reports analytics "Analytics"
php artisan dashkit:rename-module invoice billing "Billing"
```

### Delete generated items

```bash
php artisan dashkit:delete-page analytics
php artisan dashkit:delete-module billing
```

These commands use tracked artifacts so route/sidebar cleanup stays aligned with what Dashkit created.

---

## UI Components

Dashkit includes reusable Blade UI components for:

- buttons
- inputs
- selects
- cards
- alerts
- toast notifications
- tables
- modals
- icons

Available examples include:

- `<x-dashkit::ui.button>`
- `<x-dashkit::ui.input>`
- `<x-dashkit::ui.select>`
- `<x-dashkit::ui.card>`
- `<x-dashkit::ui.alert>`
- `<x-dashkit::ui.toast>`
- `<x-dashkit::ui.table>`
- `<x-dashkit::ui.modal>`
- `<x-dashkit::ui.icon>`

Example:

```blade
<x-dashkit::ui.card title="Orders" description="Reusable Dashkit components in action">
    <x-dashkit::ui.alert tone="info" message="This module uses shared UI primitives." />

    <div class="mt-4 flex gap-2">
        <x-dashkit::ui.button>Primary</x-dashkit::ui.button>
        <x-dashkit::ui.button variant="secondary">Secondary</x-dashkit::ui.button>
    </div>
</x-dashkit::ui.card>
```

---

## Upgrade Flow

Normal upgrade path:

```bash
composer run dashkit-update
```

That flow is intended to:

1. sync package code
2. run `php artisan dashkit:upgrade`

You can also run the upgrade command directly:

```bash
php artisan dashkit:upgrade
php artisan dashkit:upgrade --dry-run
php artisan dashkit:upgrade --force
php artisan dashkit:upgrade --type=crm
```

Upgrade behavior includes:

- lifecycle checks before applying changes
- package version comparison
- fingerprint comparison when version numbers did not change
- publishing config, views, and assets
- ensuring route include and default pages
- running migrations
- clearing config and view caches
- updating stored package state

If Composer-based syncing is stale in a subfolder workflow:

```bash
composer run-script dashkit-sync
php artisan dashkit:upgrade
```

If autoload or package discovery looks stale:

```bash
composer dump-autoload
php artisan package:discover --ansi
```

---

## Uninstall Flow

Dashkit uninstall is designed to be safer than manual file deletion.

Run:

```bash
php artisan dashkit:uninstall
```

Or run non-interactively:

```bash
php artisan dashkit:uninstall --yes
php artisan dashkit:uninstall --yes --backup
```

The uninstall process previews what it will remove, including tracked files and tracked routes.

Uninstall steps include:

1. restore installer-tracked file changes
2. remove `config/dashkit.php`
3. remove published vendor views
4. remove public assets
5. remove generated dashboard pages
6. remove Dashkit route include
7. remove tracked generated files and routes
8. remove default Dashkit routes from `routes/web.php`
9. remove bootstrap/provider modifications
10. remove `DASHKIT_*` environment variables
11. remove Dashkit state and cache files

When backup mode is used, Dashkit stores backups under `storage/app/dashkit/uninstall-backups`.

---

## Commands Reference

### Install, lifecycle, and maintenance

| Command | Description |
|---------|-------------|
| `php artisan dashkit:install` | Start a new install with CLI or GUI selection |
| `php artisan dashkit:install --resume` | Continue an interrupted install |
| `php artisan dashkit:install --force` | Reinstall intentionally and overwrite published files |
| `php artisan dashkit:install --type=crm` | Install with a specific preset |
| `php artisan dashkit:install --stop-after=env_setup` | Stop after a specific step |
| `php artisan dashkit:inspect` | Inspect lifecycle and traces |
| `php artisan dashkit:inspect --json` | Return trace report as JSON |
| `php artisan dashkit:upgrade` | Upgrade an installed Dashkit project |
| `php artisan dashkit:upgrade --dry-run` | Preview upgrade work only |
| `php artisan dashkit:upgrade --force` | Force upgrade behavior and overwrite published files |
| `php artisan dashkit:uninstall` | Uninstall with confirmation |
| `php artisan dashkit:uninstall --yes` | Uninstall without prompt |
| `php artisan dashkit:uninstall --backup` | Keep a backup before uninstall |
| `php artisan dashkit:version` | Show package and installed versions |
| `php artisan dashkit:version --json` | Show versions as JSON |
| `php artisan dashkit:release-check` | Recommend a version bump for package changes |
| `composer run dashkit-update` | Sync and upgrade in one command |

### Presets, generators, and cleanup

| Command | Description |
|---------|-------------|
| `php artisan dashkit:switch-preset {type}` | Switch the default preset |
| `php artisan dashkit:make-page {name} {title?}` | Generate a dashboard page |
| `php artisan dashkit:make-module {name} {title?}` | Generate a dashboard module |
| `php artisan dashkit:rename-page {from} {to} {title?}` | Rename a generated page and keep tracking aligned |
| `php artisan dashkit:rename-module {from} {to} {title?}` | Rename a generated module and keep tracking aligned |
| `php artisan dashkit:delete-page {name}` | Delete a generated page |
| `php artisan dashkit:delete-module {module}` | Delete a generated module |
| `php artisan dashkit:audit` | View Dashkit audit log events |

### Audit command filters

`dashkit:audit` supports filters such as:

- `--action=`
- `--actor=`
- `--target=`
- `--from=`
- `--to=`
- `--limit=`

---

## Troubleshooting

### GUI wizard does not open automatically

If the browser does not open automatically, start your Laravel app first and then open the printed `/dashkit-console?...` URL manually.

### GUI route returns 404

The setup wizard only exists while `storage/app/dashkit/setup-token.json` exists. If the token was removed or setup completed, the route is not supposed to remain available.

### Database-backed sessions break setup before the main DB exists

Dashkit works around this by using `storage/app/dashkit/setup.sqlite` during GUI setup. If setup still fails, verify the storage path is writable.

### Install stopped midway

Use:

```bash
php artisan dashkit:install --resume
```

If the project state is confused, inspect first:

```bash
php artisan dashkit:inspect
```

### Upgrade says the project is clean or partial

That means Dashkit does not trust the current state as a valid installed package state. Inspect traces first, then choose between upgrade, force install, or uninstall.

### Published views are not matching package views

Remember that published views in `resources/views/vendor/dashkit` override package views. If you changed the package copy but the app still renders old markup, check the published override first.

### Commands or classes are not discovered after sync

Run:

```bash
composer dump-autoload
php artisan package:discover --ansi
```

---

## Pros and Tradeoffs

### Advantages

- very fast dashboard bootstrap inside a standard Laravel project
- both CLI and GUI onboarding paths
- lifecycle-aware install, upgrade, and uninstall flows
- safer artifact tracking than ad hoc file publishing alone
- page/module generation reduces repetitive dashboard work
- package settings can influence runtime behavior

### Tradeoffs

- host-project files are modified, so package ownership must be understood clearly
- published vendor views can drift from package views over time
- setup and uninstall rely on tracking files under `storage/app/dashkit`
- upgrade behavior is safer than blind publishing, but still requires discipline when the host app has customized generated artifacts
- the GUI setup flow is primarily optimized for local/developer environments, not headless production deployment

---

## Package Structure

Key package directories:

- `config/`
- `database/migrations/`
- `resources/views/auth/`
- `resources/views/components/`
- `resources/views/setup/`
- `routes/web.php`
- `routes/setup.php`
- `src/Commands/`
- `src/Http/Controllers/`
- `src/Support/`

Important command classes include:

- install, inspect, upgrade, uninstall
- version and release-check
- preset switch
- page/module make, rename, and delete
- audit log inspection

Important migrations include:

- `dashkit_settings`
- `dashkit_user_preferences`
- `dashkit_audit_logs`

---

## License

MIT
