# Panduan Deployment e-Legtra (Production)

Dokumen ini ditujukan untuk **Tim Infrastructure / DevOps** sebagai panduan step-by-step deployment aplikasi **e-Legtra** ke environment Production.

## 1. Spesifikasi Server & Requirements

Pastikan Virtual Machine / Server Production memiliki komponen berikut:

*   **OS:** Linux (Ubuntu 22.04 LTS atau 24.04 LTS direkomendasikan)
*   **Web Server:** Nginx (disarankan) atau Apache
*   **PHP:** Versi **8.4** (dengan ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip`)
*   **Node.js:** Versi **18.x** atau **20.x** & npm (hanya dibutuhkan pada saat *build* aset frontend)
*   **Composer:** Versi 2.x
*   **Database:** MySQL versi 8.x+ (berada di server yang sama atau terpisah)
*   **Process Monitor:** `supervisor` (untuk worker antrian/queue)

## 2. Persiapan Folder & Source Code

1.  Arahkan ke folder web root:
    ```bash
    cd /var/www
    ```
2.  Clone / Upload source code ke folder aplikasi:
    ```bash
    git clone <repository_url> e-legtra
    cd e-legtra
    ```
    *(Atau ekstrak file zip source code dari pipeline relesase ke folder `/var/www/e-legtra`)*

## 3. Instalasi Dependencies & Build

Jalankan perintah berikut di dalam direktori `e-legtra` server:

```bash
# 1. Install dependencies PHP tanpa package development
composer install --optimize-autoloader --no-dev

# 2. Install dependencies Node.js
npm install

# 3. Build aset frontend (TailwindCSS, JS, Flux UI) untuk production
npm run build
```
*(Catatan: Setelah `npm run build` sukses, proses Node.js tidak lagi berjalan di background karena Laravel menggunakan aset statis yang sudah ter-build).*

## 4. Konfigurasi Environment (`.env`)

Aplikasi Laravel **membutuhkan** file konfigurasi environment bernama `.env`.

1. Salin dari `.env.example`:
    ```bash
    cp .env.example .env
    ```
2. Generate Application Key (Hanya dilakukan 1x di awal deployment):
    ```bash
    php artisan key:generate
    ```
3. Edit pengaturan production di dalam file `.env`:
    ```bash
    nano .env
    ```
    Pastikan parameter berikut terisi dengan benar (terutama untuk Production):

    ```env
    # --- PENGATURAN UMUM APLIKASI ---
    APP_NAME="e-Legtra"
    APP_ENV=production        # HARUS production
    APP_DEBUG=false           # HARUS false (Penting untuk keamanan)
    APP_URL=https://elegtra.pfimegalife.co.id  # URL publik aplikasi

    # --- PENGATURAN DATABASE ---
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1         # IP Host Database
    DB_PORT=3306              # Port Database
    DB_DATABASE=db_legalNew   # Nama Database untuk e-Legtra
    DB_USERNAME=root          # User Database
    DB_PASSWORD=secret        # Password Database

    # --- SINGLE SIGN ON (SSO) ---
    SSO_LOGOUT_URL="https://sso.pfimegalife.co.id/logout" # URL redirect saat user di e-Legtra klik 'logout'

    # --- SESSION CONFIG ---
    SESSION_DRIVER=database
    SESSION_LIFETIME=60       # Durasi session dalam menit (1 jam)
    SESSION_SECURE_COOKIE=true # Aktifkan jika menggunakan HTTPS
    SESSION_SAME_SITE=lax

    # --- PENYIMPANAN DOKUMEN ---
    FILESYSTEM_DISK=local
    # Tentukan root path absolut jika dokumen fisik disimpan di luar folder Laravel
    LEGAL_DOCS_ROOT="/mnt/share/legal-documents" # Ganti dengan storage directory server
    
    # --- QUEUE & CACHE ---
    QUEUE_CONNECTION=database
    CACHE_STORE=database

    # --- EMAIL (SMTP) UNTUK REMINDER ---
    MAIL_MAILER=smtp
    MAIL_HOST=smtp.office365.com
    MAIL_PORT=587
    MAIL_USERNAME=no-reply@pfimegalife.co.id
    MAIL_PASSWORD=your_mail_password
    MAIL_ENCRYPTION=tls
    MAIL_FROM_ADDRESS="no-reply@pfimegalife.co.id"
    MAIL_FROM_NAME="${APP_NAME}"
    ```

## 5. Migrasi Database (Setup Schema)

Setelah koneksi database di `.env` sudah benar, buat tabel-tabel sistem:

```bash
# Menjalankan migrasi tabel
php artisan migrate --force

# Menambahkan data bawaan (Master status, Menu, Template, dsb)
php artisan db:seed --force
```
*(Flag `--force` wajib digunakan saat script mendeteksi `APP_ENV=production`)*

## 6. Optimasi Cache Laravel

Untuk performa maksimal di environment production:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```
*(Catatan: Setiap kali melakukan perubahan file `.env`, Anda **wajib** menjalankan `php artisan config:cache` ulang agar perubahannya dibaca).*

## 7. Pengaturan Hak Akses (Permissions)

Web server `nginx` atau `apache2` rata-rata menggunakan user/group `www-data`. Berikan izin write akses ke direktori yang membutuhkan:

```bash
# Berikan ownership kepada web server (asumsi Ubuntu/Debian nginx user: www-data)
sudo chown -R www-data:www-data /var/www/e-legtra

# Berikan file permissions folder storage dan cache
sudo find /var/www/e-legtra -type f -exec chmod 644 {} \;
sudo find /var/www/e-legtra -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/e-legtra/storage
sudo chmod -R 775 /var/www/e-legtra/bootstrap/cache
```

## 8. Konfigurasi Web Server (Nginx)

Buat file konfigurasi Virtual Host Nginx baru untuk e-Legtra:

```bash
sudo nano /etc/nginx/sites-available/elegtra.conf
```
Contoh isi file `elegtra.conf` (disesuaikan dengan domain/ssl sertifikat Anda):

```nginx
server {
    listen 80;
    listen 443 ssl;
    server_name elegtra.pfimegalife.co.id;
    
    # SSL config (sesuaikan dengan cert path yang Anda miliki)
    # ssl_certificate     /etc/ssl/certs/elegtra.crt;
    # ssl_certificate_key /etc/ssl/private/elegtra.key;

    # Dokumen root backend wajib diarahkan ke folder /public
    root /var/www/e-legtra/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    # Redirect semua request ke logic index.php Laravel
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    # Konfigurasi socket fpm PHP 8.4
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan konfigurasi dan restart nginx:
```bash
sudo ln -s /etc/nginx/sites-available/elegtra.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

## 9. Menjalankan Queue Worker (Supervisor)

Aplikasi memiliki task antrian di background (seperti mengirim Email massal, dll). Kita perlu `supervisor` menjaga service `queue:work` selalu berjalan.

1. Install supervisor (jika belum): `sudo apt install supervisor`
2. Buat file konfigurasi worker:
    ```bash
    sudo nano /etc/supervisor/conf.d/elegtra-worker.conf
    ```
3. Isi konfigurasi:
    ```ini
    [program:elegtra-worker]
    process_name=%(program_name)s_%(process_num)02d
    command=php /var/www/e-legtra/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
    autostart=true
    autorestart=true
    stopasgroup=true
    killasgroup=true
    user=www-data
    numprocs=2
    redirect_stderr=true
    stdout_logfile=/var/www/e-legtra/storage/logs/worker.log
    stopwaitsecs=3600
    ```
4. Update dan Start:
    ```bash
    sudo supervisorctl reread
    sudo supervisorctl update
    sudo supervisorctl start elegtra-worker:*
    ```

## 10. Konfigurasi Cron (Task Scheduler)

e-Legtra memiliki fitur Reminder otomatis (harian) & Update Aging Tiket yang berjalan di background. Agar fitur ini berjalan, tambahkan 1 baris Crontab berikut:

```bash
sudo crontab -u www-data -e
```
*(Gunakan crontab milik user www-data untuk keamanan)*

Tambahkan di baris paling bawah:
```bash
* * * * * cd /var/www/e-legtra && php artisan schedule:run >> /dev/null 2>&1
```

---

## ✅ Post-Deployment Checks

1. Akses web `https://elegtra.pfimegalife.co.id` dari browser.
2. Pastikan Anda dialihkan langsung dengan fitur SSO-nya.
3. Login pertama kali dengan default user admin atau user dev tim Anda. (User PFI harus didaftarkan di table `LGL_USER` menggunakan NIK dengan SSO). Pastikan proses migrasi/seeder di langkah 5 berhasil.
4. Coba melakukan upload file/dokumen pada aplikasi dan pastikan tidak ada error *permission denied*. Storage config `/mnt/share/legal-documents` harus bisa dibaca & ditulis (R/W) oleh user `www-data` OS Linux.
