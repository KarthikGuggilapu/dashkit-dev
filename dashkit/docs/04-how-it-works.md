# How Dashkit Works

## Lifecycle overview
1. Install package
2. Configure app + DB + admin
3. Access dashboard with auth
4. Generate pages/modules globally
5. Track artifacts
6. Uninstall and clean tracked outputs on demand

## Boot process
- `DashkitServiceProvider` loads:
  - package routes
  - package views (namespace `dashkit`)
  - blade layout component
  - publish groups (`dashkit-config`, `dashkit-views`, `dashkit-assets`)
  - artisan commands (console only)

## Routing model
- Dashboard home route is `/`
- Login route is `/login`
- Content pages use `config('dashkit.route_prefix')` (default `dashboard`) as `/dashboard/<slug>`
- Middleware from `config('dashkit.route_middleware')`
- Auth-protected dashboard pages when `auth.enabled=true`

Key route names:
- `dashkit.home`
- `dashkit.login`
- `dashkit.login.attempt`
- `dashkit.logout`
- `dashkit.page`
- generated: `dashkit.page.<slug>`
- generated modules still use route names like `dashkit.module.<slug>` but URL path is `/dashboard/<slug>`

## UI rendering model
- Main layout: `<x-dashkit-layout>`
- Sidebar links driven from `config('dashkit.sidebar')`
- Topbar supports search/logout by config
- Dashboard page uses widget registry output
- Profile is reached from the topbar profile menu and resolves to `/dashboard/profile`

## Widget model
- Widgets defined in config (`widgets.defaults`)
- `WidgetRegistry` resolves values
- Supports token handlers (e.g. `users_count`)
- Falls back safely when model/value resolution fails

## Generator model
### Page generator
- writes blade to app resources
- appends route into app `routes/web.php`
- records file + route in manifest

### Module generator
- creates model/controller/view/migration in app
- appends route into app `routes/web.php`
- records all outputs in manifest

## Preset model
- Install, upgrade, and switch-preset can apply `default`, `ecommerce`, or `crm`
- Preset controls default pages, sidebar links, and starter dashboard content
- Ecommerce starter pages: products, orders, customers, inventory
- CRM starter pages: leads, contacts, deals, activities

## Uninstall model
- shows full preview before removal
- optional backup mode
- restores installer-tracked changes
- removes generated files/routes recorded in manifest
- clears dashkit tracking files
- preserves provider registration when `dashkit/dashkit` is still required by the project

## Tracking and backup locations
- `storage/app/dashkit/install-state.json`
- `storage/app/dashkit/artifacts.json`
- `storage/app/dashkit/uninstall-backups/`
