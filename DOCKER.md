# Panduan Penggunaan Docker untuk Portfolio

Projek ini telah dimigrasikan sepenuhnya daripada MAMP kepada Docker untuk persekitaran pembangunan tempatan (local development).

---

## 1. Perkhidmatan & Port

- **Aplikasi Web**: [http://localhost:8080](http://localhost:8080)
- **Panel Admin Filament**: [http://localhost:8080/admin](http://localhost:8080/admin)
- **Vite HMR (Dev Server)**: [http://localhost:5173](http://localhost:5173)
- **Pangkalan Data MySQL (Host Gateway)**: `127.0.0.1:3307`
  - Database: `portfolio_db`
  - App User: `portfolio_app`
  - MySQL Engine: `5.7.44`

---

## 2. Arahan Start / Stop Docker

### Memulakan Container
```bash
docker compose up -d
```

### Menghentikan Container
```bash
docker compose stop
```

### Menghentikan dan Menurunkan Container (Data kekal pada Volume)
```bash
docker compose down
```

### Memeriksa Status Container
```bash
docker compose ps
```

---

## 3. Menjalankan Artisan, Composer, Node/NPM & Ujian

Semua arahan boleh dijalankan terus melalui servis `laravel.test` di dalam container tanpa memerlukan PHP atau Composer pada Windows host:

### Artisan
```bash
# Menjalankan arahan artisan am
docker compose exec laravel.test php artisan <command>

# Contoh semakan migration:
docker compose exec laravel.test php artisan migrate:status

# Kosongkan cache:
docker compose exec laravel.test php artisan optimize:clear
```

### Composer
```bash
docker compose exec laravel.test composer install
docker compose exec laravel.test composer check-platform-reqs
```

### NPM & Vite
```bash
# Mod Pembangunan (Vite Hot Module Replacement):
docker compose exec laravel.test npm run dev

# Membina Aset Produksi:
docker compose exec laravel.test npm run build
```

### Menjalankan Ujian (Testing)
```bash
# Ujian PHPUnit / Feature Tests:
docker compose exec laravel.test php artisan test

# Ujian Frontend:
docker compose exec laravel.test npm run test:chat-scroll
```

---

## 4. Lokasi Data & Persistent Volumes

Pangkalan data MySQL disimpan pada Docker named volume tersendiri untuk mengelakkan sebarang pertembungan atau kehilangan data:
- **Nama Volume**: `portfolio-mysql-data` (dipaparkan sebagai `portfolio_portfolio-mysql-data` di peringkat engine)
- **Lokasi Fail Media / Upload**: `storage/app/public` (dipautkan ke `public/storage` secara symlink)

---

## 5. Backup & Restore Database Docker

### Membuat Backup Pangkalan Data Docker
```powershell
# Menggunakan akaun aplikasi:
docker compose exec -T mysql mysqldump -u portfolio_app -p portfolio_db > backup_docker.sql

# Atau tanpa prompt password melalui variabel persekitaran:
$pass = (Get-Content .env | Select-String '^DB_PASSWORD=(.*)$').Matches.Groups[1].Value.Trim()
docker compose exec -T -e MYSQL_PWD=$pass mysql mysqldump -u portfolio_app portfolio_db > backup_docker.sql
```

### Memulihkan (Restore) Pangkalan Data Docker
```powershell
$pass = (Get-Content .env | Select-String '^DB_PASSWORD=(.*)$').Matches.Groups[1].Value.Trim()
cmd.exe /c "docker compose exec -T -e MYSQL_PWD=$pass mysql mysql -u portfolio_app portfolio_db < backup_docker.sql"
```

---

## 6. Prosedur Rollback ke MAMP

Sekiranya perlu kembali menggunakan persekitaran MAMP:

1. **Hentikan Container Docker**:
   ```bash
   docker compose down
   ```

2. **Pulihkan Tetapan `.env` MAMP**:
   Salin semula fail backup `.env.backup` yang disimpan di luar git repository:
   ```powershell
   Copy-Item "C:\MAMP\backups\portfolio_20261119\.env.backup" ".env" -Force
   ```
   *(Konfigurasi DB_HOST akan kembali ke 127.0.0.1, DB_PORT 3306, DB_USERNAME root, DB_PASSWORD root).*

3. **Pastikan Servis MAMP Berjalan**:
   Buka aplikasi MAMP dan mulakan servis Apache serta MySQL.

4. **Pangkalan Data MAMP**:
   Pangkalan data asal `portfolio_db` di dalam MySQL MAMP tidak pernah disentuh, diubah, atau dipadam sepanjang proses migrasi.

---

## 7. Panduan Setup di PC Lain Selepas Git Clone / Git Pull

Apabila anda atau pembangun lain memuat turun projek ini di komputer lain, ikuti langkah mudah berikut:

### Prasyarat di PC Baharu:
- **Docker Desktop** (atau Docker Engine + Docker Compose v2) telah dipasang dan berjalan.
- **Git** telah dipasang.
- *(Tidak perlu pasang PHP, MySQL, Composer, atau Node.js pada komputer hos).*

### Langkah Pelaksanaan:

1. **Clone atau Pull Repository**:
   ```bash
   git clone https://github.com/npmrundevv-gif/portfolio.git
   cd portfolio
   ```

2. **Sediakan Fail `.env`**:
   Salin templat yang telah diselaraskan untuk Docker:
   ```bash
   cp .env.example .env
   ```
   *(Atau salin terus fail `.env` sedia ada jika anda mahu mengekalkan APP_KEY dan API Keys yang sama).*

3. **Mulakan Kontena Docker**:
   ```bash
   docker compose up -d
   ```
   *(Docker akan membina imej PHP 8.4 + Node 24 dan memulakan kontena MySQL 5.7 secara automatik).*

4. **Pasang Dependensi & Sediakan Pautan Storan**:
   ```bash
   docker compose exec laravel.test composer install
   docker compose exec laravel.test npm install
   docker compose exec laravel.test php artisan key:generate
   docker compose exec laravel.test php artisan storage:link
   ```

5. **Sediakan Pangkalan Data**:
   - **Pilihan A (Struktur Skema Bersih + Seeder)**:
     ```bash
     docker compose exec laravel.test php artisan migrate --seed
     ```
   - **Pilihan B (Data Sebenar MAMP)**:
     Import salinan fail backup SQL yang anda miliki:
     ```bash
     # Windows PowerShell:
     $pass = (Get-Content .env | Select-String '^DB_PASSWORD=(.*)$').Matches.Groups[1].Value.Trim()
     cmd.exe /c "docker compose exec -T -e MYSQL_PWD=$pass mysql mysql -u portfolio_app portfolio_db < portfolio_db_backup.sql"
     ```

6. **Bina Aset Frontend atau Jalankan Mod Dev**:
   ```bash
   # Mod pembangunan (Vite HMR):
   docker compose exec laravel.test npm run dev

   # Atau binaan produksi:
   docker compose exec laravel.test npm run build
   ```

Aplikasi kini sedia diakses di `http://localhost:8080`.

