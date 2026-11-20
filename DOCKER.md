# Panduan Penggunaan Docker untuk Portfolio

Projek ini telah dimigrasikan sepenuhnya daripada MAMP kepada Docker untuk persekitaran pembangunan tempatan (local development).

---

## 1. Perkhidmatan & Port

- **Aplikasi Web**: [http://localhost:8080](http://localhost:8080)
- **Panel Admin Filament**: [http://localhost:8080/admin](http://localhost:8080/admin)
- **Vite HMR (Dev Server)**: [http://localhost:5173](http://localhost:5173) (atau port tersuai melalui `VITE_PORT`)
- **Pangkalan Data MySQL (Host Gateway)**: `127.0.0.1:3307` (terikat ke localhost untuk keselamatan)
  - Database: `portfolio_db`
  - App User: `portfolio_app`
  - Root User: `root` (kata laluan wajib ditetapkan dalam `.env` melalui `DB_ROOT_PASSWORD`)
  - MySQL Engine: `5.7.44`

> [!NOTE]
> Perubahan kata laluan `DB_ROOT_PASSWORD` dalam fail `.env` hanya berkuat kuasa untuk pemasangan volume baharu. Sekiranya container telah dimulakan sebelum ini, password root pada volume sedia ada kekal seperti yang ditetapkan semasa penciptaan awal volume.

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
# Menjalankan arahan artisan am:
docker compose exec laravel.test php artisan <command>

# Contoh semakan status migration:
docker compose exec laravel.test php artisan migrate:status

# Kosongkan cache (penting sebelum ujian):
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

> [!WARNING]
> Laravel test suite menggunakan pangkalan data SQLite in-memory (`phpunit.xml`). Sekiranya anda menjalankan `php artisan optimize`, konfigurasi MySQL akan dicache dan menyebabkan ujian mencuba berhubung ke MySQL. Sentiasa jalankan `docker compose exec laravel.test php artisan optimize:clear` semasa sesi pembangunan dan ujian.

---

## 4. Prestasi & Pengoptimuman (Performance)

Persekitaran Docker telah dioptimumkan untuk prestasi pantas pada Windows (mengurangkan kelewatan bind mount):

1. **OPcache CLI Diaktifkan** (`docker/8.4/php.ini`):
   - `opcache.enable_cli = 1` memastikan `artisan serve` (CLI SAPI) memanfaatkan cache kod bait.
   - Saiz memori `opcache.memory_consumption = 256MB` dan fail dipercepatkan sehingga `30,000`.
   - `realpath_cache_size = 16M` untuk mengurangkan panggilan sistem `stat()` pada folder bind mount.

2. **Xdebug Dinyahaktifkan Automatik**:
   - Apabila `SAIL_XDEBUG_MODE=off` (lalai), skrip `start-container` melumpuhkan fail konfigurasi Xdebug untuk mengelakkan beban hook fungsi PHP yang berat.

3. **Multi-Worker PHP CLI Server**:
   - `PHP_CLI_SERVER_WORKERS=4` membolehkan pelayan pembangunan mengendalikan beberapa permintaan serentak (aset statik, AJAX, livewire).

---

## 5. Lokasi Data & Persistent Volumes

Pangkalan data MySQL disimpan pada Docker named volume tersendiri untuk mengelakkan sebarang pertembungan atau kehilangan data:
- **Nama Volume**: `portfolio-mysql-data` (dipaparkan sebagai `portfolio_portfolio-mysql-data` di peringkat engine)
- **Lokasi Fail Media / Upload**: `storage/app/public` (dipautkan ke `public/storage` secara symlink)

---

## 6. Backup & Restore Database Docker (Binary-Safe)

> [!IMPORTANT]
> Jangan gunakan operator pengalihan PowerShell `> backup.sql` secara terus kerana PowerShell mengubah format penulisan fail (UTF-16/BOM) yang boleh merosakkan format SQL. Gunakan pendekatan container-internal dump dan `docker cp` di bawah:

### Membuat Backup Pangkalan Data Docker
```powershell
# 1. Hasilkan dump di dalam kontena secara selamat:
docker compose exec -T mysql sh -c 'mysqldump -u portfolio_app -p"$MYSQL_PASSWORD" --single-transaction --routines --triggers portfolio_db > /tmp/backup_docker.sql'

# 2. Salin fail dump keluar ke direktori semasa:
docker cp $(docker compose ps -q mysql):/tmp/backup_docker.sql ./backup_docker.sql

# 3. Bersihkan fail sementara di dalam kontena:
docker compose exec -T mysql rm /tmp/backup_docker.sql
```

### Memulihkan (Restore) Pangkalan Data Docker
```powershell
# 1. Salin fail backup ke dalam kontena:
docker cp ./backup_docker.sql $(docker compose ps -q mysql):/tmp/restore.sql

# 2. Laksanakan import di dalam kontena:
docker compose exec -T mysql sh -c 'mysql -u portfolio_app -p"$MYSQL_PASSWORD" portfolio_db < /tmp/restore.sql'

# 3. Bersihkan fail sementara di dalam kontena:
docker compose exec -T mysql rm /tmp/restore.sql
```

---

## 7. Penyelarasan Port Vite & HMR

Vite secara automatik menyelaraskan port kontena, port hos, dan port HMR:
- **Port Lalai**: `5173`
- **Tukar ke Port Tersuai (contoh: 5273)**:
  Tetapkan di dalam `.env`:
  ```ini
  VITE_PORT=5273
  ```
  Kemudian mulakan semula kontena jika port forwarding berubah:
  ```bash
  docker compose up -d
  docker compose exec laravel.test npm run dev
  ```

---

## 8. Panduan Setup di PC Lain Selepas Git Clone / Git Pull

Apabila anda atau pembangun lain memuat turun projek ini di komputer lain, ikuti langkah berikut:

### Prasyarat di PC Baharu:
- **Docker Desktop** (atau Docker Engine + Docker Compose v2) telah dipasang dan berjalan.
- **Git** telah dipasang.
- *(Tidak perlu pasang PHP, MySQL, Composer, atau Node.js pada komputer hos).*

### Langkah Pelaksanaan:

1. **Clone Repository pada Branch Docker**:
   ```bash
   git clone -b codex/portfolio-fixes-phase1-4 https://github.com/npmrundevv-gif/portfolio.git
   cd portfolio
   ```
   *(Atau jika telah clone pada branch `main`, tukar ke branch Docker: `git checkout codex/portfolio-fixes-phase1-4`).*

2. **Sediakan Fail `.env`**:
   Salin templat yang telah diselaraskan untuk Docker:
   ```bash
   cp .env.example .env
   ```

3. **Pasang Dependensi Composer Sebelum Memulakan Aplikasi**:
   ```bash
   docker compose run --rm --no-deps laravel.test composer install
   ```
   *(Nota: Sekiranya anda menjalankan `docker compose up -d` terus, skrip `start-container` juga telah diprogramkan untuk mengesan ketiadaan `vendor/autoload.php` dan menjalankan `composer install` secara automatik).*

4. **Mulakan Kontena Docker**:
   ```bash
   docker compose up -d
   ```

5. **Pasang Dependensi Node & Pautan Storan**:
   ```bash
   docker compose exec laravel.test npm install
   docker compose exec laravel.test php artisan storage:link
   ```

6. **Konfigurasi Kunci Keselamatan (`APP_KEY`)**:
   - **Pemasangan Baharu (Fresh Install)**:
     ```bash
     docker compose exec laravel.test php artisan key:generate
     ```
   - **Migrasi / Pemasangan Bersama Data Sedia Ada (Existing Data / MAMP Migration)**:
     **JANGAN** jalankan `key:generate`. Kekalkan nilai `APP_KEY` asal daripada fail `.env.backup` atau `.env` komputer asal supaya data sesi, kuki, dan maklumat terenkripsi sedia ada kekal serasi dan boleh dinyahsulit.

7. **Sediakan Pangkalan Data**:
   - **Pilihan A (Struktur Skema Bersih + Seeder)**:
     ```bash
     docker compose exec laravel.test php artisan migrate --seed
     ```
   - **Pilihan B (Data Sebenar MAMP / Backup SQL)**:
     Gunakan kaedah binary-safe untuk mengimport:
     ```powershell
     docker cp ./portfolio_db_backup.sql $(docker compose ps -q mysql):/tmp/import.sql
     docker compose exec -T mysql sh -c 'mysql -u portfolio_app -p"$MYSQL_PASSWORD" portfolio_db < /tmp/import.sql'
     docker compose exec -T mysql rm /tmp/import.sql
     ```

8. **Bina Aset Frontend atau Jalankan Mod Dev**:
   ```bash
   # Mod pembangunan (Vite HMR):
   docker compose exec laravel.test npm run dev

   # Atau binaan produksi:
   docker compose exec laravel.test npm run build
   ```

Aplikasi kini sedia diakses di [http://localhost:8080](http://localhost:8080).

---

## 9. Prosedur Rollback ke MAMP

Sekiranya perlu kembali menggunakan persekitaran MAMP pada bila-bila masa:

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
