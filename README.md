# 📋 e-Legtra — Legal Tracking System

**e-Legtra** adalah sistem tracking dokumen legal internal untuk **PFI Mega Life Insurance**. Aplikasi ini mengelola siklus hidup kontrak/perjanjian perusahaan, mulai dari pengajuan tiket legal request, proses review oleh tim Legal, hingga manajemen kontrak aktif, reminder otomatis, dan pelaporan.

---

## 📑 Daftar Isi

- [Tech Stack](#-tech-stack)
- [System Requirements](#-system-requirements)
- [Instalasi & Setup](#-instalasi--setup)
- [Konfigurasi Environment](#-konfigurasi-environment)
- [Database](#-database)
- [Fitur Utama](#-fitur-utama)
- [Arsitektur SSO](#-arsitektur-sso-single-sign-on)
- [Panduan Manajemen User](#-panduan-manajemen-user)
- [Role & Permission (RBAC)](#-role--permission-rbac)
- [Alur Kerja (Workflow)](#-alur-kerja-workflow)
- [Artisan Commands](#-artisan-commands)
- [Scheduled Tasks (Cron)](#-scheduled-tasks-cron)
- [Email & Notifikasi](#-email--notifikasi)
- [Deployment Production](#-deployment-production)
- [Struktur Folder](#-struktur-folder)
- [Troubleshooting](#-troubleshooting)

---

## 🛠 Tech Stack

| Layer | Teknologi | Versi |
|-------|-----------|-------|
| Backend | PHP / Laravel Framework | 8.4 / 12.x |
| Frontend | Livewire + Volt (SPA-like) | v3 / v1 |
| UI Components | Flux UI (Free) | v2 |
| CSS | Tailwind CSS | v4 |
| Bundler | Vite | v7 |
| Auth | Laravel Fortify (SSO + NIK login) | v1 |
| Database | MySQL (Oracle-style naming) | 8.x+ |
| Testing | Pest | v4 |
| Code Style | Laravel Pint | v1 |
| Export | Maatwebsite Excel | v3 |
| Queue | Laravel Queue (database driver) | built-in |

---

## 💻 System Requirements

- **PHP** ≥ 8.2 (disarankan 8.4)
- **Composer** ≥ 2.x
- **Node.js** ≥ 18.x + **npm**
- **MySQL** ≥ 8.0
- **Web Server**: Apache/Nginx (atau `php artisan serve` untuk development)
- Extension PHP yang dibutuhkan:
  - `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `zip`

---

## 🚀 Instalasi & Setup

### 1. Clone Repository

```bash
git clone <repository-url> e-legtra
cd e-legtra
```

### 2. Quick Setup (Otomatis)

```bash
composer setup
```

Perintah ini menjalankan:
1. `composer install` — Install PHP dependencies
2. Copy `.env.example` → `.env` (jika belum ada)
3. `php artisan key:generate` — Generate application key
4. `php artisan migrate --force` — Jalankan migrasi database
5. `npm install` — Install Node dependencies
6. `npm run build` — Build frontend assets

### 3. Manual Setup (Step-by-step)

```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Konfigurasi .env (lihat bagian Konfigurasi Environment)

# Migrasi & seed database
php artisan migrate
php artisan db:seed

# Build frontend
npm run build
```

### 4. Jalankan Aplikasi

**Development (semua service sekaligus):**
```bash
composer run dev
```
Perintah ini menjalankan 4 service secara paralel:
- **Server**: `php artisan serve` (http://localhost:8000)
- **Queue**: `php artisan queue:listen`
- **Logs**: `php artisan pail` (real-time log viewer)
- **Vite**: `npm run dev` (hot reload)

**Atau jalankan terpisah:**
```bash
php artisan serve          # Web server
php artisan queue:listen   # Queue worker
npm run dev                # Vite dev server
```

---

## ⚙ Konfigurasi Environment

Salin `.env.example` ke `.env` dan sesuaikan value di bawah:

### Aplikasi

```env
APP_NAME="e-Legtra"
APP_ENV=production           # Ubah ke "production" saat deploy
APP_DEBUG=false               # WAJIB false di production
APP_URL=https://your-domain.com  # URL akses aplikasi
```

### Database (MySQL)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1            # Atau IP server database
DB_PORT=3306
DB_DATABASE=db_legalNew       # Nama database
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Session & Keamanan

```env
SESSION_DRIVER=database       # Session disimpan di database
SESSION_LIFETIME=60           # Timeout 1 jam (dalam menit)
SESSION_ENCRYPT=false
SESSION_DOMAIN=null           # Isi domain jika multi-subdomain
```

### SSO Integration

```env
SSO_LOGOUT_URL=https://sso.pfimegalife.co.id/logout  # URL redirect saat logout
```

> Saat user logout, aplikasi akan redirect ke URL SSO ini. Pastikan URL sesuai dengan SSO kantor.

### Penyimpanan Dokumen Legal

```env
LEGAL_DOCS_ROOT=/path/to/legal-documents  # Root folder dokumen legal di server
FILESYSTEM_DISK=local
```

### Email (SMTP)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@pfimegalife.co.id
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@pfimegalife.co.id"
MAIL_FROM_NAME="${APP_NAME}"
```

### Queue

```env
QUEUE_CONNECTION=database    # Gunakan database untuk reliability
```

### Cache

```env
CACHE_STORE=database
```

---

## 🗄 Database

### Konvensi Penamaan

Aplikasi menggunakan konvensi penamaan **Oracle-style** (uppercase + prefix):

| Konsep | Konvensi | Contoh |
|--------|----------|--------|
| Nama Tabel | `LGL_*` prefix | `LGL_USER`, `LGL_CONTRACT_MASTER` |
| Primary Key | `LGL_ROW_ID` atau custom | `LGL_ROW_ID`, `ROLE_ID`, `CONFIG_ID` |
| Foreign Key | Prefix entity + `_ID` | `CONTR_DIV_ID`, `USER_ROLE_ID` |
| Timestamp | Entity prefix + `_CREATED_DT` / `_UPDATED_DT` | `CONTR_CREATED_DT` |

### Tabel Utama

| Tabel | Model | Deskripsi |
|-------|-------|-----------|
| `LGL_USER` | `User` | Data user dengan NIK, role, divisi, departemen |
| `LGL_CONTRACT_MASTER` | `Contract` | Master kontrak/perjanjian |
| `LGL_TICKET_MASTER` | `Ticket` | Tiket legal request |
| `LGL_ROLE` | `Role` | Role (super-admin, legal, user) |
| `LGL_PERMISSION` | `Permission` | Permission (35+ items) |
| `LGL_ROLE_PERMISSION` | — | Pivot role ↔ permission |
| `LGL_DIVISION` | `Division` | Divisi perusahaan |
| `LGL_DEPARTMENT` | `Department` | Departemen dalam divisi |
| `LGL_NOTIFICATION_MASTER` | `Notification` | Notifikasi in-app |
| `LGL_USER_ADTRL_LOG` | `ActivityLog` | Audit trail (log perubahan) |
| `LGL_SYS_CONFIG` | `Setting` | System settings (key-value) |
| `LGL_FORM_QUESTION` | `FormQuestion` | Dynamic form questions untuk tiket |
| `LGL_TICKET_ANSWER` | `TicketAnswer` | Jawaban tiket (EAV pattern) |
| `LGL_FORM_SECTION` | `FormSection` | Section pada form tiket |
| Lookup tables | `ContractStatus`, `TicketStatus`, `DocumentType`, `ReminderType` | Master data status & tipe |

### Migrasi & Seeding

```bash
# Jalankan semua migrasi
php artisan migrate

# Seed data awal (roles, permissions, settings, divisions)
php artisan db:seed

# Atau seed individual
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=SettingSeeder
php artisan db:seed --class=DivisionSeeder
php artisan db:seed --class=FormSectionSeeder
php artisan db:seed --class=FormQuestionSeeder
```

### Default Users (dari seeder)

| Email | NIK | Role | Password |
|-------|-----|------|----------|
| `admin@example.com` | `1234567` | Super Admin | `password` |
| `legal@example.com` | `12345` | Legal | `password` |

> ⚠️ **WAJIB** ganti password default user setelah deploy ke production!

---

## ✨ Fitur Utama

### 1. Dashboard
- Statistik tiket (submitted, in-process, done, rejected)
- Statistik kontrak (active, expiring soon, expired)
- Tiket terbaru (untuk role user: "My Tickets")
- Widget aging tiket

### 2. Ticketing System
- Buat tiket legal request baru (**dynamic form** — field/section dikonfigurasi via database)
- Lihat detail tiket dan dokumen pendukung
- Workflow status: `Submitted → In Process → Done / Rejected`
- Tracking aging (durasi proses tiket)
- Upload dan preview dokumen

### 3. Contract Management
- Master kontrak terhubung ke tiket asal
- Info: nomor kontrak, pihak, divisi, departemen, PIC, tanggal mulai/berakhir
- Auto-renew flag
- **Traffic light system**: Hijau (aman), Kuning (mendekati expired), Merah (kritis/expired)
- Terminate kontrak dengan alasan
- Link ke folder dokumen (SharePoint/directory share)

### 4. Contract Repository
- Tampilan aset/arsip seluruh kontrak
- Filter berdasarkan status, tipe dokumen, divisi

### 5. Export & Reporting
- Export kontrak ke Excel (`.xlsx`)
- Export tiket ke Excel dengan filter (status, tipe, divisi, tanggal)

### 6. Email Reminder Otomatis
- Reminder otomatis kontrak mendekati expired (configurable: 60, 30, 7 hari)
- Email ke PIC / creator tiket
- CC ke tim Legal dan departemen terkait
- Template email customizable via admin panel

### 7. Notifikasi In-App
- Notifikasi real-time di sidebar
- Tipe: contract_expiring, contract_expired, info, critical
- Mark as read/unread

### 8. Audit Trail
- Semua perubahan pada kontrak tercatat (old values vs new values)
- Log per user, per event (created, updated, deleted, reminder_sent)

### 9. Admin Panel
- **Users**: CRUD user, assign role & divisi/departemen
- **Roles**: Kelola role dan permission per role
- **Settings**: Konfigurasi sistem (reminder threshold, email, dsb)
- **Email Templates**: Edit template email pengingat kontrak
- **Divisions & Departments**: Kelola struktur organisasi

### 10. Autentikasi (SSO)
- Login via **NIK** (bukan email) — autentikasi sepenuhnya ditangani oleh SSO
- Aplikasi **tidak menyimpan/memvalidasi password** — hanya mendaftarkan NIK dan menentukan role
- Two-Factor Authentication (2FA) opsional dengan TOTP
- Session timeout 1 jam
- Logout redirect ke SSO

---

## 🔑 Arsitektur SSO (Single Sign-On)

### Gambaran Umum

Aplikasi e-Legtra **tidak mengelola autentikasi password secara mandiri**. Seluruh proses login/authentication ditangani oleh **SSO (Single Sign-On) kantor**. Aplikasi ini hanya bertugas:

1. **Mendaftarkan NIK** — Agar sistem mengenali user yang boleh masuk
2. **Menentukan Role** — Mengatur akses (Super Admin / Legal / User)
3. **Menentukan Divisi & Departemen** — Untuk keperluan ticketing dan kontrak

### Alur Login (SSO Flow)

```
┌──────────┐     ┌──────────────┐     ┌──────────────┐
│  Browser  │     │  SSO Server   │     │   e-Legtra    │
├──────────┤     ├──────────────┤     ├──────────────┤
│           │     │              │     │              │
│  Akses    │────▶│  Autentikasi │     │              │
│  e-Legtra │     │  (SSO login) │     │              │
│           │     │              │     │              │
│           │     │  SSO OK ✓    │     │              │
│           │     │  Kirim NIK   │────▶│  Cek NIK di  │
│           │     │              │     │  database    │
│           │     │              │     │              │
│           │     │              │     │  NIK ada? ✓  │
│           │     │              │     │  → Login OK  │
│           │     │              │     │              │
│           │     │              │     │  NIK tidak   │
│           │     │              │     │  ada? ✗      │
│           │◀────│              │◀────│  → 403 Error │
│           │     │              │     │  "User not   │
│           │     │              │     │   registered"│
└──────────┘     └──────────────┘     └──────────────┘
```

### Alur Logout

```
User klik "Logout"
     │
     ▼
e-Legtra: Session dihancurkan
     │
     ▼
Redirect ke SSO_LOGOUT_URL (dari .env)
     │
     ▼
SSO Server: Logout session SSO
```

### Detail Teknis

| Komponen | File | Fungsi |
|----------|------|--------|
| Login Request | `app/Http/Requests/Auth/SsoLoginRequest.php` | Validasi: hanya NIK required (tanpa password) |
| Auth Logic | `app/Providers/FortifyServiceProvider.php` | Lookup user by NIK. Jika NIK tidak terdaftar → 403 |
| Logout Response | `app/Http/Responses/SsoLogoutResponse.php` | Redirect ke `SSO_LOGOUT_URL` |
| Logout Action | `app/Livewire/Actions/Logout.php` | Destroy session, redirect ke SSO |
| Login View | `resources/views/livewire/auth/login.blade.php` | Form hanya field NIK (tanpa password) |

### Konfigurasi SSO di `.env`

```env
# URL redirect setelah logout — WAJIB diisi sesuai SSO kantor
SSO_LOGOUT_URL=https://sso.pfimegalife.co.id/logout
```

> ⚠️ **Penting**: Jika `SSO_LOGOUT_URL` tidak diisi, logout akan redirect ke halaman utama aplikasi (bukan ke SSO).

---

## 👤 Panduan Manajemen User

Karena login ditangani oleh SSO, **setiap karyawan yang ingin mengakses e-Legtra harus didaftarkan terlebih dahulu** oleh Super Admin di menu **Admin → Users**.

### Cara Menambah User Baru

1. Login sebagai **Super Admin**
2. Buka menu **Admin → Users** (navigasi sidebar)
3. Klik tombol **"Add User"** (pojok kanan atas)
4. Isi form berikut:

   | Field | Wajib | Keterangan |
   |-------|-------|------------|
   | **Name** | ✅ | Nama lengkap karyawan |
   | **Email** | ✅ | Email kantor (untuk notifikasi & reminder) |
   | **ID / User ID** | ✅ | **NIK karyawan** (angka, maks 10 digit) — ini yang digunakan untuk login via SSO |
   | **Password** | ❌ | Opsional. Karena autentikasi via SSO, password tidak digunakan untuk login. Bisa dikosongkan. |
   | **Role** | ✅ | Pilih role: `Super Admin`, `Legal`, atau `User` |
   | **Division** | ✅ | Pilih divisi karyawan |
   | **Department** | — | Pilih departemen (opsional, muncul setelah divisi dipilih) |

5. Klik **"Save"**

### Cara Edit User

1. Di halaman **Admin → Users**, cari user yang ingin diedit
2. Klik ikon **✏️ (pencil)** di kolom Actions
3. Ubah data yang diperlukan
4. Klik **"Save"**

### Cara Hapus User

1. Di halaman **Admin → Users**, cari user yang ingin dihapus
2. Klik ikon **🗑️ (trash)** di kolom Actions
3. Konfirmasi penghapusan

> ⚠️ Anda tidak bisa menghapus akun sendiri.

### Menentukan Role User

| Role | Kapan Digunakan |
|------|-----------------|
| **Super Admin** | IT admin / system administrator — akses penuh |
| **Legal** | Tim Legal — proses tiket, kelola kontrak, kirim reminder |
| **User** | Karyawan dari departemen lain — hanya buat tiket dan lihat kontrak |

### Checklist Onboarding User Baru

- [ ] Pastikan karyawan sudah terdaftar di sistem SSO kantor
- [ ] Tambahkan user di e-Legtra dengan NIK yang sesuai
- [ ] Assign role yang tepat (Super Admin / Legal / User)
- [ ] Assign divisi dan departemen yang sesuai
- [ ] Informasikan ke karyawan bahwa mereka bisa login via SSO dengan NIK

---

## 🔐 Role & Permission (RBAC)

### Roles

| Role | Slug | Akses |
|------|------|-------|
| **Super Admin** | `super-admin` | Akses penuh ke seluruh sistem |
| **Legal** | `legal` | Kelola tiket & kontrak (proses, reject, complete) |
| **User** | `user` | Buat tiket, lihat tiket sendiri & kontrak |

### Permission Groups

| Group | Permission Code | Deskripsi |
|-------|----------------|-----------|
| **Dashboard** | `dashboard.tickets.view` | Lihat statistik tiket |
| | `dashboard.contracts.view` | Lihat statistik kontrak |
| | `dashboard.my-tickets.view` | Lihat tiket milik sendiri |
| **Tickets** | `tickets.view` | Lihat daftar tiket |
| | `tickets.create` | Buat tiket baru |
| | `tickets.edit` | Edit tiket (Legal only) |
| | `tickets.process` | Proses tiket |
| | `tickets.reject` | Tolak tiket |
| | `tickets.complete` | Selesaikan tiket → buat kontrak |
| **Contracts** | `contracts.view` | Lihat daftar kontrak |
| | `contracts.edit` | Edit kontrak |
| | `contracts.terminate` | Terminate kontrak |
| | `contracts.delete` | Hapus kontrak |
| | `contracts.send_reminder` | Kirim reminder manual |
| **Users** | `users.view`, `users.create`, `users.edit`, `users.delete` | CRUD user |
| **Roles** | `roles.view`, `roles.edit`, `roles.manage` | Kelola role & permission |
| **Divisions** | `divisions.view/create/edit/delete/manage` | Kelola divisi |
| **Departments** | `departments.view/create/edit/delete/manage` | Kelola departemen & CC email |
| **Settings** | `settings.view`, `settings.edit`, `email_templates.edit` | Konfigurasi sistem |
| **Reports** | `reports.view`, `reports.export` | Export laporan Excel |

---

## 🔄 Alur Kerja (Workflow)

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│   User       │     │   Legal Team  │     │   System     │
├─────────────┤     ├──────────────┤     ├─────────────┤
│             │     │              │     │             │
│ Buat Tiket  │────▶│ Review Tiket │     │             │
│ (Submitted) │     │              │     │             │
│             │     │   ┌────┐     │     │             │
│             │     │   │ OK │─────┼────▶│ Buat        │
│             │     │   └────┘     │     │ Kontrak     │
│             │     │   ┌────────┐ │     │ (Active)    │
│             │     │   │ Reject │ │     │             │
│             │     │   └────────┘ │     │             │
│             │     │              │     │             │
│             │     │              │     │ Auto        │
│             │     │              │     │ Reminder    │
│             │     │              │     │ (60/30/7d)  │
│             │     │              │     │             │
│             │     │              │     │ Auto        │
│             │     │              │     │ Expire      │
│             │     │              │     │ Contracts   │
└─────────────┘     └──────────────┘     └─────────────┘
```

### Status Tiket
1. **Submitted** — Tiket baru dibuat oleh user
2. **In Process** — Sedang diproses oleh Legal
3. **Done** — Selesai, kontrak dibuat
4. **Rejected** — Ditolak oleh Legal (dengan alasan)

### Status Kontrak
1. **Draft** — Kontrak belum aktif
2. **Active** — Kontrak aktif berjalan
3. **Expired** — Kontrak sudah lewat tanggal berakhir
4. **Terminated** — Kontrak dihentikan sebelum waktunya

---

## 🖥 Artisan Commands

| Command | Deskripsi |
|---------|-----------|
| `php artisan contracts:send-reminders` | Kirim email reminder kontrak mendekati expired |
| `php artisan contracts:update-expired` | Update status kontrak yang sudah expired |
| `php artisan contracts:expire` | Expire kontrak yang melewati tanggal berakhir |
| `php artisan tickets:recalculate-aging` | Hitung ulang aging/durasi proses tiket |

---

## ⏰ Scheduled Tasks (Cron)

Aplikasi membutuhkan **Laravel Scheduler** di server production. Tambahkan entry berikut ke crontab:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### Tugas Terjadwal

| Jadwal | Command | Deskripsi |
|--------|---------|-----------|
| Setiap hari (midnight) | `contracts:update-expired` | Auto-update status kontrak expired |
| Setiap hari (08:00 WIB*) | `contracts:send-reminders` | Kirim reminder email otomatis |

> \* Waktu pengiriman bisa dikonfigurasi via admin panel (`reminder_send_time` di Settings).

---

## 📧 Email & Notifikasi

### Konfigurasi SMTP

Aplikasi menggunakan SMTP (default: Office 365) untuk mengirim email. Konfigurasi di `.env`:

```env
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=no-reply@pfimegalife.co.id
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
```

### Jenis Email
1. **Contract Expiring Reminder** — Otomatis dikirim H-60, H-30, H-7 sebelum kontrak expired
2. **Dynamic Mail** — Email dengan template customizable dari admin panel

### Penerima Reminder
- **TO**: Creator tiket / PIC kontrak
- **CC**: Email departemen Legal + CC emails departemen terkait

### System Settings (via Admin Panel)

| Key | Default | Deskripsi |
|-----|---------|-----------|
| `app_name` | `PKS Tracking System` | Nama aplikasi |
| `company_name` | `PFI Mega Life` | Nama perusahaan |
| `reminder_email_enabled` | `true` | Aktifkan/nonaktifkan reminder |
| `legal_team_email` | *(configurable)* | Email tim Legal |
| `reminder_send_time` | `08:00` | Jam kirim reminder |
| `reminder_days` | `[60, 30, 7]` | Hari-hari pengiriman reminder sebelum expired |

---

## 🚢 Deployment Production

### Checklist Deployment

- [ ] Set `APP_ENV=production` dan `APP_DEBUG=false`
- [ ] Generate `APP_KEY` baru jika fresh install
- [ ] Konfigurasi database MySQL production
- [ ] Set `APP_URL` sesuai domain
- [ ] Konfigurasi SMTP email
- [ ] Set `SSO_LOGOUT_URL` sesuai SSO kantor
- [ ] Set `LEGAL_DOCS_ROOT` ke path folder dokumen di server
- [ ] Set `SESSION_LIFETIME=60` (1 jam timeout)
- [ ] Ganti password default users
- [ ] Setup crontab untuk scheduler

### Langkah Deploy

```bash
# 1. Clone & install dependencies
git clone <repo> /var/www/e-legtra
cd /var/www/e-legtra
composer install --optimize-autoloader --no-dev
npm install
npm run build

# 2. Setup environment
cp .env.example .env
php artisan key:generate
# Edit .env sesuai konfigurasi production

# 3. Database
php artisan migrate --force
php artisan db:seed

# 4. Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 5. Set permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 6. Setup crontab
crontab -e
# Tambah: * * * * * cd /var/www/e-legtra && php artisan schedule:run >> /dev/null 2>&1

# 7. Setup queue worker (via supervisor)
# Lihat konfigurasi Supervisor di bawah

# 8. Restart web server
sudo systemctl restart nginx  # atau apache
```

### Konfigurasi Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/e-legtra/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

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

### Konfigurasi Supervisor (Queue Worker)

Buat file `/etc/supervisor/conf.d/e-legtra-worker.conf`:

```ini
[program:e-legtra-worker]
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

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start e-legtra-worker:*
```

---

## 📁 Struktur Folder

```
e-legtra/
├── app/
│   ├── Console/Commands/         # Artisan commands (reminders, expire, aging)
│   ├── Exports/                  # Excel export (ContractsExport, TicketsExport)
│   ├── Http/
│   │   ├── Controllers/          # TicketDocumentController (preview/download)
│   │   ├── Middleware/           # CheckPermission middleware
│   │   ├── Requests/            # Form requests (SsoLoginRequest)
│   │   └── Responses/           # SsoLogoutResponse
│   ├── Livewire/Actions/         # Logout action
│   ├── Mail/                     # ContractExpiringMail, DynamicMail
│   ├── Models/                   # 18 Eloquent models
│   ├── Observers/                # TicketObserver (auto ticket number)
│   ├── Providers/                # FortifyServiceProvider (auth config)
│   └── Services/                 # Business logic services
│       ├── ActivityLogService    # Audit trail logging
│       ├── ContractService       # Contract CRUD & business rules
│       ├── EmailTemplateService  # Dynamic email template management
│       ├── LegalDocumentService  # File management for legal docs
│       ├── NotificationService   # In-app notifications
│       └── TicketService         # Ticket workflow & state management
├── config/                       # Laravel config files
├── database/
│   ├── factories/                # Model factories for testing
│   ├── migrations/               # 60 migration files
│   └── seeders/                  # Initial data seeders
├── resources/views/livewire/     # Volt page components
│   ├── admin/                    # Admin: users, roles, settings, email-templates
│   ├── auth/                     # Login, register, 2FA, forgot-password
│   ├── contracts/                # Index, create, show, edit, repository
│   ├── dashboard.blade.php       # Main dashboard
│   ├── departments/              # Department management
│   ├── divisions/                # Division management
│   └── settings/                 # Profile, password, appearance, 2FA
├── routes/
│   ├── web.php                   # All web routes
│   └── console.php               # Scheduled tasks
├── .env                          # Environment configuration
└── .env.example                  # Template environment
```

---

## 🔧 Troubleshooting

### 1. "Vite manifest not found"
```bash
npm run build
```

### 2. Session expired / 419 error
- Pastikan `SESSION_DRIVER=database` dan tabel `LGL_SESSION` ada
- Cek `SESSION_LIFETIME` di `.env` (default: 60 menit)

### 3. Email tidak terkirim
- Cek konfigurasi SMTP di `.env`
- Pastikan queue worker berjalan: `php artisan queue:work`
- Cek log: `storage/logs/laravel.log`

### 4. Permission denied
- Pastikan storage writable: `chmod -R 775 storage bootstrap/cache`
- Pastikan user web server (www-data) memiliki akses

### 5. Reminder tidak berjalan
- Pastikan crontab sudah ditambahkan
- Cek `reminder_email_enabled` = `true` di Settings
- Jalankan manual: `php artisan contracts:send-reminders`

### 6. Clear cache setelah ubah config
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

---

## 📝 Maintenance

### Update Aplikasi
```bash
git pull origin main
composer install --optimize-autoloader --no-dev
npm install && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo supervisorctl restart e-legtra-worker:*
```

### Backup Database
```bash
mysqldump -u root -p db_legalNew > backup_$(date +%Y%m%d_%H%M%S).sql
```

---

> **Dokumen ini dibuat**: 26 Maret 2026  
> **Versi aplikasi**: Laravel 12 + Livewire 3 + Volt 1  
> **Maintainer**: Tim IT PFI Mega Life
