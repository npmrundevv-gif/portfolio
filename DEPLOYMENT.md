# Deployment Guide

Step-by-step instructions to deploy this Laravel + Filament portfolio site from either a source clone or a packaged release archive.

---

## Prerequisites

| Requirement | Minimum | Notes |
|---|---|---|
| PHP | 8.4.1+ | Extensions: mbstring, openssl, pdo, tokenizer, xml, ctype, json, bcmath, fileinfo |
| Composer | 2.x | Required to install PHP dependencies on the server |
| Node.js + npm | 20.19.0+ or 22.12.0+ | Only needed if building assets on the server |
| MySQL | 8.0+ (or MariaDB 10.6+) | SQLite is for local development only |
| Web server | Nginx or Apache | Document root **must** point to `public/` |
| HTTPS | Required in production | Used for secure session cookies and content integrity |

---

## Option A — Deploy from Source Clone

### 1. Install PHP dependencies (production only, no dev tools)

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

### 2. Install and build front-end assets

```bash
npm ci
npm run build
```

### 3. Configure environment

```bash
cp .env.example .env
```

Edit `.env` and fill in **all** values:

| Variable | Description |
|---|---|
| `APP_NAME` | Your site name |
| `APP_ENV` | **Must be `production`** |
| `APP_DEBUG` | **Must be `false`** |
| `APP_URL` | Your production URL (e.g. `https://example.com`) |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Database credentials |
| `ADMIN_EMAILS` | Comma-separated list of emails allowed into the admin panel |
| `GEMINI_API_KEY` | Primary Gemini API key |
| `GEMINI_API_KEYS` | Comma-separated additional Gemini keys (optional, for key rotation) |
| `GROQ_API_KEY` | Primary Groq API key |
| `GROQ_API_KEYS` | Comma-separated additional Groq keys (optional, for key rotation) |
| `SESSION_SECURE_COOKIE` | `true` (required for HTTPS deployments) |

Then generate the application key (first install only — **do not regenerate on a live deployment**):

```bash
php artisan key:generate
```

### 4. Run database migrations

```bash
php artisan migrate --force
```

### 5. Create the storage symlink

Uploaded hero images, work thumbnails, tech-stack icons, case-study images, and favicons are served from `storage/app/public`. Without this symlink they will **404**:

```bash
php artisan storage:link
```

### 6. Set correct file permissions

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

Adjust `www-data` to match your web server user.

### 7. Cache configuration for performance

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> **Note:** After changing `ADMIN_EMAILS` or any other `.env` variable on a live server, re-run `php artisan config:cache` for the change to take effect.

### 8. Create the admin user

```bash
php artisan app:create-admin
```

Prompts for name, email, and password (password input is hidden). The email **must** match one listed in `ADMIN_EMAILS`.

### 9. Configure web server

Point your web server's document root to the `public/` directory.

**Example Nginx config:**

```nginx
server {
    listen 443 ssl http2;
    server_name example.com;
    root /path/to/portfolio/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## Option B — Deploy from Packaged Release Archive

A release ZIP can be generated locally using:

```bash
php scripts/package-release.php
```

This produces `dist/portfolio-release-vX.Y.Z-<timestamp>.zip` and a matching `.sha256` checksum file.

**The release archive does NOT include `vendor/`.** Run `composer install --no-dev` on the deployment server after extracting.

The archive includes an empty `storage/app/public/` directory and leaves `public/storage` absent. Run `php artisan storage:link` after extraction to create the symlink for that server. If an older ZIP has left a regular `public/storage` directory, check its contents and move it aside before creating the symlink.

### Steps

```bash
# 1. Verify archive integrity
sha256sum -c portfolio-release-vX.Y.Z-<timestamp>.zip.sha256

# 2. Extract to target directory
unzip portfolio-release-vX.Y.Z-<timestamp>.zip -d /var/www/portfolio

# 3. Install PHP dependencies (server must have Composer)
cd /var/www/portfolio
composer install --no-dev --prefer-dist --optimize-autoloader

# 4. Configure .env, run migrations, storage:link, permissions, and cache
# (follow steps 3–9 from Option A)
```

---

## Upload Limits (Video Support)

The application allows video uploads up to **50 MB**. Configure `php.ini` on the server:

```ini
upload_max_filesize = 64M
post_max_size = 64M
```

The Livewire config (`config/livewire.php`) has `max:51200` already set.

---

## Chatbot Feature

The chatbot is enabled or disabled in **Admin → Site Settings → Chatbot Enabled**. It requires valid API keys in `.env`. If the keys are missing or the chatbot is disabled, the chat widget is hidden automatically.

---

## Security Notes

> **Rotate any API keys before go-live** if they were ever committed to version control or shared outside the team. The Gemini and Groq keys should be generated fresh from their respective dashboards (Google AI Studio, Groq Console) and placed in `.env` on the server only.

- Keep `APP_DEBUG=false` in production. Debug mode exposes stack traces and environment values publicly.
- Use HTTPS. Set `SESSION_SECURE_COOKIE=true` in `.env`.
- Run `composer audit` after each dependency update to check for known CVEs.
- Run `npm audit` after each `npm install` or `npm update`.

---

## Rollback Procedure

### Code rollback

If deploying from source control:

```bash
git checkout <previous-tag-or-commit>
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If deploying from an archive, re-deploy the previous release ZIP.

### Database rollback

```bash
# Roll back the most recent migration batch
php artisan migrate:rollback

# Roll back to a specific step count
php artisan migrate:rollback --step=2
```

> **Important:** Always take a database backup before running migrations or rollbacks on production. Code rollback and database rollback are independent operations — confirm your target migration state before running either.

---

## Post-Deployment Verification

```bash
# Confirm routes load cleanly
php artisan route:list

# Confirm no API keys in public assets
grep -rn "AIzaSy\|gsk_" public/

# Verify /register returns 404
curl -s -o /dev/null -w "%{http_code}" https://your-domain.com/register
# Expected: 404

# Verify admin panel requires login
# Navigate to https://your-domain.com/admin — only ADMIN_EMAILS users can access
```

---

## Items Requiring Owner Action Before Production

| Item | Action |
|---|---|
| Gemini / Groq API keys | Rotate keys if previously exposed |
| Composer security advisories | Run `composer update` after vetting symfony patch versions |
| npm DOMPurify CVE | Update `dompurify` to ≥3.4.17 when authorized |
| Database backup plan | Configure automated daily backups on the server |
| HTTPS certificate | Provision via Let's Encrypt or hosting provider |
