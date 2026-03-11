# Sharing & Distributing the Dashkit Package

This guide covers 3 ways to share the Dashkit package with another developer or publish it publicly.
Choose the option that fits your situation.

---

## Option 1 — Share the Full Laravel Project via Git (Easiest, Best for Testing)

The entire Laravel app including `packages/dashkit/` lives in one Git repository.
The other developer just clones the project and everything works out of the box.

**When to use:** You want a second developer to test the package quickly on their machine.

### Steps for You (Package Author)

**1. Initialize Git in your project (if not done yet)**

```bash
cd e:\xampp\htdocs\dashkit-dev
git init
git add .
git commit -m "Initial commit"
```

**2. Create a repository on GitHub**

- Go to [https://github.com/new](https://github.com/new)
- Set repository name (e.g. `dashkit-dev`)
- Choose **Private** (recommended while testing)
- Do NOT initialize with README (you already have one)
- Click **Create repository**

**3. Push your code**

```bash
git remote add origin https://github.com/YOUR_USERNAME/dashkit-dev.git
git branch -M main
git push -u origin main
```

### Steps for the Other Developer

**1. Clone the project**

```bash
git clone https://github.com/YOUR_USERNAME/dashkit-dev.git
cd dashkit-dev
```

**2. Install dependencies**

```bash
composer install
npm install
```

**3. Set up environment**

```bash
cp .env.example .env
php artisan key:generate
```

**4. Set up database**
Edit `.env` and set your DB credentials, then:

```bash
php artisan migrate
```

**5. Install Dashkit**

```bash
php artisan dashkit:install
```

**6. Start the server**

```bash
php artisan serve
```

> Visit `http://localhost:8000` — the dashboard is ready.

---

## Option 2 — Separate Package Git Repo + VCS Repository (Proper Package Distribution)

The `packages/dashkit/` folder is pushed as its **own standalone Git repository**.
Other developers add it to their Laravel projects manually via Composer.

**When to use:** You want to distribute the package to other Laravel projects without bundling the full test app.

### Steps for You (Package Author)

**1. Create a new repository on GitHub for the package only**

- Go to [https://github.com/new](https://github.com/new)
- Name it `dashkit` (repo will be `github.com/YOUR_USERNAME/dashkit`)
- Choose **Public** or **Private**
- Do NOT initialize with README

**2. Initialize Git inside the package folder**

```bash
cd e:\xampp\htdocs\dashkit-dev\packages\dashkit
git init
git add .
git commit -m "Initial release v1.2.0"
```

**3. Tag a version (Composer uses Git tags)**

```bash
git tag v1.2.0
```

**4. Push to GitHub**

```bash
git remote add origin https://github.com/YOUR_USERNAME/dashkit.git
git branch -M main
git push -u origin main
git push origin v1.2.0
```

### Steps for the Other Developer

**1. Create a fresh Laravel project**

```bash
composer create-project laravel/laravel my-app
cd my-app
```

**2. Add the package repository to `composer.json`**

Open `composer.json` and add a `repositories` block:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/YOUR_USERNAME/dashkit"
        }
    ],
    "require": {
        "php": "^8.2",
        "dashkit/dashkit": "^1.2",
        "laravel/framework": "^12.0"
    }
}
```

**3. Install the package**

```bash
composer require dashkit/dashkit
```

> If the repo is **private**, the developer needs to authenticate with a GitHub personal access token.
> Run `composer config github-oauth.github.com TOKEN_HERE` before installing.

**4. Install Dashkit**

```bash
php artisan dashkit:install
php artisan serve
```

> During install, Dashkit automatically adds a `dashkit-update` script to the app's `composer.json`.

**5. Getting future updates from the package author**

Whenever the package author pushes new code to GitHub, the other developer just runs:

```bash
composer run dashkit-update
```

This pulls the latest code from GitHub and applies any upgrade steps automatically — no need to remember two separate commands.

---

## Option 3 — Publish on Packagist (Public, Production-Ready)

The package repo (from Option 2) is submitted to [packagist.org](https://packagist.org).
Anyone in the world can install it with a simple `composer require`.

**When to use:** The package is stable and you want it publicly available like any other Composer package.

### Prerequisites

- You must have completed **Option 2** first (package has its own GitHub repo with a version tag).
- The GitHub repo must be **Public**.

### Steps for You (Package Author)

**1. Sign in to Packagist**

- Go to [https://packagist.org](https://packagist.org)
- Sign in with your GitHub account

**2. Submit the package**

- Click **Submit** in the top menu
- Enter your GitHub repository URL: `https://github.com/YOUR_USERNAME/dashkit`
- Click **Check** — Packagist will read your `composer.json` and confirm the package name (`dashkit/dashkit`)
- Click **Submit**

**3. Set up auto-update webhook (so Packagist updates when you push)**

- On the Packagist package page, copy the **API Token**
- Go to your GitHub repo → **Settings → Webhooks → Add webhook**
- Payload URL: `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
- Content type: `application/json`
- Secret: your Packagist API Token
- Save

**4. Push a new release when you have updates**

```bash
git tag v1.3.0
git push origin v1.3.0
```

Packagist will pick it up automatically via the webhook.

### Steps for the Other Developer

No special setup needed. Just run:

```bash
composer require dashkit/dashkit
php artisan dashkit:install
```

> `dashkit:install` automatically adds the `dashkit-update` script to `composer.json`.
> To get future updates: `composer run dashkit-update`

---

## Quick Comparison

| | Option 1 | Option 2 | Option 3 |
|---|---|---|---|
| **Setup effort** | Minimal | Medium | High (one-time) |
| **Best for** | Testing with 1–2 devs | Small team / private use | Public release |
| **Requires separate package repo** | No | Yes | Yes (must be public) |
| **Other dev needs full app?** | Yes | No | No |
| **Install command** | `git clone` | `composer require` (with VCS) | `composer require` |
| **Versioning via Git tags** | Not needed | Required | Required |

---

## Recommended Path

```
Testing phase       →  Option 1 (share full project via Git)
Team/private use    →  Option 2 (separate package repo, VCS in composer.json)
Public release      →  Option 3 (Packagist)
```

Start with Option 1 now, migrate to Option 2 when the package is stable enough to stand alone.
