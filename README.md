# WRBN — backend Laravel

Backend WRBN. Porting dari `legacy-node/` (Express + SQLite) ke Laravel 13 +
MySQL, dengan kontrak API yang sama sehingga halaman HTML lama tetap jalan
tanpa diubah.

## Menjalankan secara lokal

```
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8000
```

| Halaman | URL |
| --- | --- |
| Landing + wizard intake | http://127.0.0.1:8000/ |
| Admin | http://127.0.0.1:8000/admin |
| Client (butuh login) | http://127.0.0.1:8000/client.html |

Database lokal memakai XAMPP MariaDB di port `3307`, database `wrbn`.

### Akun demo

| Peran | Email | Password |
| --- | --- | --- |
| Superadmin | `superadmin@wrbn.id` | `SuperAdmin123` |
| Admin operasional | `admin@wrbn.id` | `Admin12345` |
| Support (baca saja) | `support@wrbn.id` | `Support12345` |
| Member | `wahyuganteng@example.com` | `WahyuGanteng123` |

## Alur kerja sehari-hari

Selalu mengembangkan di cabang `dev`, baru digabung ke `main` untuk deploy.

```
git checkout dev
git merge --no-ff feature/nama-fitur

php artisan migrate            # dari laptop, menembak Aiven
php artisan test --compact
vendor/bin/pint --dirty

git checkout main
git merge dev
git push origin main           # Vercel deploy otomatis dari main
```

> Migrasi dijalankan dari laptop, bukan dari Vercel. Vercel tidak bisa
> menjalankan `artisan migrate` karena tidak ada shell persisten.

## Deploy ke Vercel

### 1. Siapkan Aiven

Buat service **Aiven for MySQL** (free tier). Catat dari dashboard:

- host
- port
- nama database
- username
- password

> **Password Aiven tidak boleh ditulis di file mana pun, termasuk commit.**
> Isi lewat dashboard Vercel. Kalau sudah pernah terkirim, segera ganti
> passwordnya di dashboard Aiven.

### 2. Unduh CA certificate

Aiven memakai TLS. Ambil CA-nya, simpan sebagai `certs/ca.pem`:

```
curl -o certs/ca.pem https://<host-aiven>/ca.pem
```

Folder `certs/` di-kecualikan dari `.gitignore` untuk file `.key`, tetapi
`ca.pem` boleh di-commit karena bukan rahasia.

### 3. Buat database kosong

```
mysql -h <host-aiven> -P <port> -u <user> -p
```

```
CREATE DATABASE wrbn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Isi environment variables di Vercel

Buka dashboard Vercel → project → **Settings → Environment Variables**.
Ambil seluruh isi `.env.production.example` sebagai cetak biru, lalu isi
nilainya sendiri. Yang wajib diperhatikan:

| Variabel | Nilai | Alasan |
| --- | --- | --- |
| `APP_KEY` | hasil `php artisan key:generate --show` | |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | dari Aiven | |
| `MYSQL_ATTR_SSL_CA` | path absolut `base_path('certs/ca.pem')` | Aiven butuh TLS |
| `QUEUE_CONNECTION` | `sync` | Vercel tidak punya worker queue; kalau `database`, mirror Google Sheets dan bot tidak akan pernah terkirim |
| `LOG_CHANNEL` | `stderr` | disk Vercel read-only, `storage/logs` tidak bisa ditulis |
| `SESSION_DRIVER` | `database` | session disimpan di Aiven |
| `CACHE_STORE` | `database` | cache disimpan di Aiven |
| `APP_CONFIG_CACHE` dst. | `/tmp/...` | hanya `/tmp` yang bisa ditulis |
| `VIEW_COMPILED_PATH` | `/tmp/views` | sama |

### 5. Deploy

```
npm i -g vercel
vercel link
vercel --prod
```

Atau hubungkan repository GitHub: Vercel akan deploy otomatis setiap push ke
`main`.

### 6. Import data lama (sekali saja)

Dump ada di `backup/wrbn-<tanggal>.sql`. Import dari laptop:

```
mysql -h <host-aiven> -P <port> -u <user> -p wrbn < backup/wrbn-20261011-012111.sql
```

## Catatan deploy

- **File upload disimpan di MySQL** (`consultation_files.contents`,
  `MEDIUMBLOB`, maksimal 10 MB per file), bukan di disk. File lama yang masih
  menunjuk path disk otomatis di-inline saat migration dijalankan.
- **Scheduler tidak dipakai.** Tidak ada `Schedule::` di mana pun, jadi tidak
  perlu cron.
- **Vite tidak dibangun.** `index.html` dan `client.html` tidak merujuk
  `/build/`, jadi tidak perlu langkah build frontend.
- **Aiven free tier bisa power-off** setelah idle. Request pertama setelah
  idle akan lambat atau gagal; cek status service di dashboard Aiven kalau
  aplikasi tiba-tiba error koneksi.

---
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
