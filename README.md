# Idan's Designs — Portfolio

A dynamic, creative portfolio built with **Laravel 12**, **Filament 4**, **Livewire 3**, **Tailwind CSS v4**, **Vite 7**, **GSAP**, and **Lenis**.

---

## Runtime Requirements

| Dependency | Minimum | Tested On |
|---|---|---|
| PHP | ^8.4.1 | 8.4.27 |
| Node.js | ^20.19.0 or ≥22.12.0 (required by Vite) | v24.19.0 |
| Composer | ≥ 2.0 | 2.8.5 |
| npm | ≥ 10.0 | 11.17.0 |
| Database | SQLite (default, dev) or MySQL/PostgreSQL (production) | SQLite 3 |

---

## Local Development Setup

```bash
# 1. Clone and enter the directory
git clone <repo-url> portfolio
cd portfolio

# 2. Install PHP dependencies (all including dev)
composer install

# 3. Install Node dependencies (from lockfile)
npm ci

# 4. Configure environment
cp .env.example .env
# Edit .env — set APP_URL, DB_*, GEMINI_API_KEY, GROQ_API_KEY, etc.

# 5. Generate application key
php artisan key:generate

# 6. Run database migrations
php artisan migrate

# 7. Create a symbolic link for public storage
php artisan storage:link

# 8. Seed sample data (optional)
php artisan db:seed

# 9. Build frontend assets
npm run build
```

### Development Server

```bash
# Start all dev processes (PHP server, Vite HMR, queue worker) concurrently
composer dev
# Equivalent to: concurrently "php artisan serve" "npm run dev" "php artisan queue:listen --tries=1"
```

---

## Admin Panel

The Filament admin panel is available at `/admin`.

**Create the first admin user:**

```bash
php artisan app:create-admin
# Prompts for name, email, and password (password is hidden)
```

**Admin email allowlist:**

Set the `ADMIN_EMAILS` environment variable to a comma-separated list of allowed email addresses:

```env
ADMIN_EMAILS="admin@example.com,owner@example.com"
```

After changing `ADMIN_EMAILS` in `.env`, refresh the config cache in production:

```bash
php artisan config:cache
```

---

## Chatbot Feature

The chatbot (powered by Gemini / Groq) can be enabled or disabled in the admin panel under **Site Settings → Chatbot Enabled**. API keys must be configured in `.env`:

```env
GEMINI_API_KEY=your-gemini-key
GROQ_API_KEY=your-groq-key
```

---

## Production Build

```bash
# Install production PHP dependencies only
composer install --no-dev --prefer-dist --optimize-autoloader

# Install Node dependencies from lockfile
npm ci

# Build production frontend assets
npm run build

# Cache configuration, routes, and views
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Web Server Document Root

Point your web server (Apache/Nginx) document root to the `public/` directory, **not** the project root.

### Writable Directories

The following directories must be writable by the web server process:

- `storage/`
- `bootstrap/cache/`

### Upload Limits

For video uploads (up to 50 MB), set in `php.ini`:

```ini
upload_max_filesize = 64M
post_max_size = 64M
```

The Livewire config (`config/livewire.php`) is already set to allow up to 50 MB (`max:51200`).

---

## Packaging a Release

Use the bundled script to build a clean release archive:

```bash
php scripts/package-release.php
```

This produces a ZIP archive and SHA-256 checksum under `dist/`. The `dist/` directory is gitignored. See [DEPLOYMENT.md](DEPLOYMENT.md) for full deployment instructions.

---

## Running Tests

```bash
# Run the full test suite
php artisan test

# Run a specific test file
php artisan test tests/Feature/ChatControllerTest.php

# Run browser runtime checks (requires Node.js and Chrome)
# See tests/Browser/BrowserRuntimeTest.cjs for configuration
node tests/Browser/BrowserRuntimeTest.cjs
```

**Baseline:** 104 tests, 388 assertions (PHP). Browser runtime tests are separate (see `tests/Browser/`).

---

## Security Notes

- **Never commit a real `.env` file.** Use `.env.example` with safe placeholder values.
- Keep `APP_DEBUG=false` in production.
- Rotate `GEMINI_API_KEY` and `GROQ_API_KEY` if either is exposed.
- Run `composer audit` and `npm audit` periodically to check for known vulnerabilities.
- Run `php artisan key:generate` only on first setup. Do **not** regenerate the key on a live deployment as it will invalidate all existing sessions and encrypted data.

---

## License

This project is proprietary software. The Laravel framework is open-sourced under the [MIT license](https://opensource.org/licenses/MIT).
