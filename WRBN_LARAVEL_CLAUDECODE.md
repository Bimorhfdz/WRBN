# WRBN → Laravel: migrasi, setup lokal, dan fitur notulen

Dokumen ini instruksi untuk Claude Code. Baca seluruhnya dulu, kerjakan berurutan per bagian, commit per bagian. Kalau ada yang ambigu atau butuh kredensial, berhenti dan tanya, jangan menebak.

## 0. Konteks

Project sekarang (folder `wrbnfix/`):
- Backend: **Node.js + Express 5**, DB **SQLite** (`better-sqlite3`), auth JWT di cookie httpOnly.
- Frontend: **HTML + vanilla JS statis** di `public/` (`index.html`, `client.html`, `admin.html`, `admin-users.html`, `velaris.js`, `config.js`). Frontend memanggil `/api/*` dengan `fetch(..., {credentials:'include'})` dan membaca `data.message` untuk pesan error.
- Docker + volume untuk SQLite.

Target:
- Backend diganti **Laravel** (MySQL/MariaDB di `127.0.0.1:3307`).
- **Frontend HTML tidak ditulis ulang.** Kontrak API (path, method, bentuk JSON, pesan error Bahasa Indonesia) dipertahankan persis supaya HTML lama jalan tanpa perubahan. Perubahan frontend hanya untuk fitur baru di bagian 8.
- Kode Node lama dipindah ke `legacy-node/` sebagai referensi (jangan dihapus sampai bagian 9 lolos).

Aturan kerja:
- Edit terarah, jangan regenerasi file besar tanpa alasan.
- Setelah tiap bagian: jalankan tes, lalu commit (`git commit -m "bagian N: ..."`). Kalau belum ada git repo, `git init` dulu.
- Semua string pesan error/sukses salin **persis** dari controller Node di `legacy-node/backend/controllers/*` (frontend menampilkannya langsung).

---

## 1. Setup environment di PC (Windows, PowerShell)

Tujuan: project bisa dijalankan di PC ini. Cek dulu, install hanya yang kurang.

1. Cek versi:
   ```powershell
   php -v ; composer -V ; node -v ; git --version
   ```
   - PHP minimal **8.2** dengan ekstensi: `pdo_mysql, mbstring, openssl, fileinfo, zip, curl, ctype, tokenizer, xml`. Cek: `php -m`. Kalau ada yang kurang, aktifkan di `php.ini` (hapus `;` di depan `extension=...`) dan beri tahu saya file `php.ini` mana yang diubah.
   - Kalau PHP/Composer belum ada, jangan install diam-diam: tampilkan opsi (XAMPP/Laragon/installer resmi) dan tanya saya dulu.
   - Node hanya dibutuhkan kalau nanti memakai Vite. Untuk project ini **tidak wajib**.
2. Cek database di port 3307:
   ```powershell
   mysql -h 127.0.0.1 -P 3307 -u root -p -e "SELECT VERSION();"
   ```
   - Kalau `mysql` tidak ada di PATH, cari `mysql.exe` (mis. folder XAMPP/Laragon `bin`) dan pakai path lengkap.
   - **Tanya saya username dan password MySQL** kalau koneksi dengan `root` tanpa password gagal. Jangan menyimpan password di file yang di-commit.
3. Buat database:
   ```sql
   CREATE DATABASE IF NOT EXISTS wrbn CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. `.env` Laravel (nanti di bagian 2) harus berisi:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3307
   DB_DATABASE=wrbn
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   (isi password sesuai jawaban saya). Pastikan `.env` ada di `.gitignore`.

---

## 2. Scaffold Laravel

1. Buat project Laravel stabil terbaru yang didukung PHP terpasang (minimal Laravel 12):
   ```powershell
   composer create-project laravel/laravel wrbn-laravel
   ```
   Lalu pindahkan isinya ke root folder project (atau kerjakan di `wrbn-laravel/` dan bilang ke saya folder mana yang dipakai). Tanpa starter kit (tidak pakai Breeze/Jetstream/Sanctum).
2. Struktur akhir:
   ```
   app/ bootstrap/ config/ database/ routes/ storage/ ...   (Laravel)
   public/            -> index.php + asset statis: index.html, admin.html, admin-users.html, velaris.js, config.js
   resources/pages/   -> client.html   (SENGAJA di luar public/, supaya bisa digate auth)
   legacy-node/       -> seluruh kode Node lama
   ```
3. Salin dari `legacy-node/public/`: `index.html`, `admin.html`, `admin-users.html`, `velaris.js`, `config.js` → `public/`. Salin `client.html` → `resources/pages/client.html`.
4. `.env`: `APP_NAME=WRBN`, `APP_LOCALE=id`, `APP_TIMEZONE=UTC`, `SESSION_DRIVER=database` (atau `file`), `SESSION_LIFETIME=10080`, `SESSION_SAME_SITE=lax`, `SESSION_SECURE_COOKIE=true` hanya di production. `php artisan key:generate`.
5. Pastikan tidak ada secret di `public/`.

---

## 3. Database (migrations)

Buat migration untuk semua tabel di `legacy-node/backend/models/schema.sql`. Ketentuan:
- Primary key **string UUID** (Node memakai `crypto.randomUUID()`): pakai `$table->uuid('id')->primary()` + trait `HasUuids` di model. Foreign key `foreignUuid()` dengan perilaku `onDelete` sama persis seperti schema (CASCADE / RESTRICT / SET NULL).
- Kolom waktu yang berasal dari input user dan dikirim balik apa adanya (`scheduled_at`, `current_scheduled_at`, `requested_scheduled_at`) → `string(80)`, jangan `datetime` (hindari masalah timezone). `created_at`, `updated_at`, `paid_at` → `timestamp`, tetap diserialisasi ISO 8601 UTC di JSON.
- CHECK constraint status: validasi di level aplikasi + `enum` di migration (`payments.status`: pending/paid/failed/expired; `consultations.status`: submitted/reviewing/in_progress/completed/cancelled; `reschedule_requests` & `upgrade_requests`: pending/approved/rejected).
- Tabel: `admins`, `users`, `services`, `payments`, `consultations` (termasuk `project_code` unique, `meeting_url`, `consultant_name`, `contact_person`), `project_updates`, `reschedule_requests`, `upgrade_requests`, `consultation_files`, `contact_messages`, `reviews`. Index sama seperti schema.
- Model Eloquent dengan `$guarded=[]` atau `$fillable` lengkap, `$hidden=['password_hash']`. Karena kolom password bernama `password_hash`, override `getAuthPassword()` di model `User` dan `Admin`.
- `$timestamps` Laravel pakai `created_at/updated_at` — cocok dengan skema.
- Seeder `ServiceSeeder` (idempotent via `updateOrCreate` by `slug`), ambil teks deskripsi persis dari `legacy-node/backend/utils/init-db.js`:
  - `basic` — Basic — 199000 — "Rp199K mulai"
  - `vip` — VIP — 399000 — "Rp399K mulai"
  - `custom` — Custom — 199000 — "Rp199K+"
  - category `package`, `active=1`.
- Command `php artisan wrbn:create-admin` menggantikan `utils/create-admin.js`: baca `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_RESET_PASSWORD` dari env, hash dengan `Hash::make`. Buat juga command pengganti `list-users.js`, `delete-user.js`, `backup-db.js` (backup → `mysqldump` ke `storage/backups/`, atau tampilkan instruksi kalau `mysqldump` tidak ada).
- (Opsional) command `wrbn:import-sqlite {path}` untuk memindahkan data dari `wrbn.sqlite` lama. Hash bcrypt dari bcryptjs kompatibel dengan `Hash::check`. Kerjakan hanya kalau saya minta.

---

## 4. API: port semua endpoint (kontrak harus identik)

Daftarkan di `routes/web.php` di dalam `Route::prefix('api')` (bukan `routes/api.php`) supaya session/cookie jalan, dan kecualikan `api/*` dari CSRF di `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['api/*']);
})
```
(Frontend tidak mengirim CSRF token; perlindungan lewat cookie `SameSite=Lax` + same-origin. Tulis catatan ini di README.)

### User / publik
| Method | Path | Catatan |
|---|---|---|
| GET | `/api/health` | `{ok:true,service:'wrbn-backend',database:'ok'}`, 503 kalau DB error |
| POST | `/api/auth/register` | validasi: nama ≥2, email valid, password ≥8, email unik (409 "Email sudah terdaftar."). Login otomatis, 201 `{user}` |
| POST | `/api/auth/login` | 401 "Email atau password salah." |
| POST | `/api/auth/logout` | `{message:'Logout berhasil.'}` |
| GET | `/api/auth/me` | auth, `{user}` |
| GET | `/api/services` | `{services:[{id,slug,name,category,description,price,priceLabel}]}` (perhatikan **`priceLabel` camelCase**), hanya `active=1`, urut sesuai insert |
| POST/GET | `/api/payments`, GET `/api/payments/{id}`, POST `/api/payments/{id}/dummy-pay` | auth; logika dari `payment-controller.js` + `payment-service.js` (adapter Dummy/External) |
| GET | `/api/client/dashboard` | auth; `{user, stats:{paidPayments,pendingPayments,consultations}, latestConsultations}` |
| POST/GET | `/api/consultations`, GET `/{id}`, PATCH `/{id}` (hanya `status:'cancelled'`, selain itu 403 "Status project dikelola oleh konsultan."), POST `/{id}/reschedule`, POST `/{id}/upgrade`, POST/GET `/{id}/files` | auth; owner-only |
| POST | `/api/contact` | publik, 201 `{message:'Brief berhasil diterima.'}` |
| GET/POST | `/api/reviews` | GET publik (30 terbaru, tambah field `initials`), POST auth |

### Admin (guard terpisah)
`POST /api/admin/login`, `POST /api/admin/logout`, `GET /api/admin/session`, lalu (middleware admin): `GET dashboard`, `GET users`, `DELETE users` (body `{ids:[]}`), `GET projects`, `GET projects/{id}`, `PATCH projects/{id}`, `POST projects/{id}/progress`, `GET requests`, `PATCH reschedules/{id}`, `PATCH upgrades/{id}`, `GET payments`, `PATCH payments/{id}`, `GET files/{id}/download`, `GET messages`.

### Aturan yang wajib sama persis
- **Bentuk response** salin dari controller Node: nama key snake_case dari kolom DB (mis. `service_name`, `price_label`, `latest_update_title`, `user_name`, `user_email`), kecuali response upload file: `{file:{id,originalName,mimeType,sizeBytes,createdAt}}` dan `priceLabel` di `/api/services`. Gunakan query builder/`DB::select` dengan alias yang sama seperti SQL Node, atau API Resource yang menghasilkan key identik. Cocokkan dengan pemakaian di `client.html`, `admin.html`, `index.html`.
- **Error format**: selalu `{message:'...'}` dengan status yang sama (400/401/403/404/409/503). Jangan pakai format 422 `errors` bawaan Laravel: lakukan validasi manual atau tambahkan exception handler untuk path `api/*` yang mengubah `ValidationException` menjadi `400 {message:<pesan pertama>}`. Handler yang sama juga mengubah 401/404/500 menjadi JSON `{message}`.
- **Project code**: `WRBN-YYYYMMDD-XXXXXX` (tanggal dari `created_at` UTC, 6 karakter pertama UUID tanpa tanda `-`, uppercase).
- **Pembuatan project**: wajib ada payment `paid` untuk service itu dan belum dipakai project lain (403 "Paket belum dibayar..." / 409 "Pembayaran ini sudah dipakai..."); insert project + `project_updates` "Project diterima" dalam satu DB transaction. Status awal `submitted`.
- **Payment**: harga diambil dari `services.price` (bukan dari client); `provider_reference` `DUMMY-{id}`; setelah create/pay panggil mirror Google Sheets (fire-and-forget, error ditelan, hanya jalan kalau `GOOGLE_SHEETS_WEBHOOK_URL` terisi) — pakai `Http::async()` atau `dispatch()->afterResponse()`.
- **Reschedule/Upgrade**: satu `pending` per project (409), tolak kalau project `cancelled/completed`, upgrade ke paket sama → 400. Admin approve/reject: update + insert `project_updates` dalam satu transaction (lihat `admin-controller.js`).
- **Upload file**: ekstensi + mime whitelist persis seperti `middleware/upload.js` (pdf, csv, xlsx, xls, doc, docx, txt, png, jpg, jpeg, zip), maks `MAX_FILE_SIZE_MB` (default 10), disimpan dengan nama acak di `storage/app/private/uploads` (disk privat, bukan `public`), download hanya lewat endpoint. Di versi Node hanya admin yang bisa download (`GET /api/admin/files/{id}/download`); pertahankan itu.
- **Hapus user (admin)**: hapus user + file fisik milik project-nya, response `{message:'N akun berhasil dihapus.',deleted:[emails]}`.
- **Rate limit** (`RateLimiter::for` + middleware `throttle`): `/api/auth/*` 30 per 15 menit, `/api/admin/login` 15 per 15 menit, seluruh `/api/*` 120 per menit.
- **Cookie/session**: login user pakai guard `web` (session), admin pakai guard `admin` (provider model `Admin`, session key terpisah). Sesi admin kedaluwarsa 8 jam (simpan `admin_login_at` di session dan cek di middleware; kalau lewat → 401 "Sesi admin tidak valid atau sudah berakhir."). Cookie `httpOnly`, `SameSite=Lax`, `secure` di production. Pesan 401: user "Authentication diperlukan.", admin "Admin belum login.".
- **Production guard**: saat `APP_ENV=production`, tolak start jika `APP_KEY` kosong atau (nanti, bagian 5) `PAYMENT_PROVIDER=dummy` tanpa `ALLOW_DUMMY_PAY=true`.
- Security headers: pakai middleware yang menambahkan `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`; hilangkan `X-Powered-By` (`expose_php=Off` / header remove).

---

## 5. Perbaikan keamanan (WAJIB sebelum fitur lain)

Masalah di versi Node: `POST /api/payments/{id}/dummy-pay` bisa dipakai user manapun untuk menandai payment-nya `paid` tanpa bayar, dan `.env.production.example` default `PAYMENT_PROVIDER=dummy`.

1. Di `config/wrbn.php` tambahkan `allow_dummy_pay` (`env('ALLOW_DUMMY_PAY', false)`), `payment_provider`, `payment_api_url`, `payment_api_key`, `sheets_webhook_url`.
2. `dummyPay`: kalau `app()->isProduction()` dan `!config('wrbn.allow_dummy_pay')` → 403 `{message:'Dummy payment dinonaktifkan.'}`.
3. Di `AppServiceProvider::boot()`: kalau production dan provider `dummy` tanpa `ALLOW_DUMMY_PAY=true` → throw exception saat boot.
4. Tambahkan variabel ini ke `.env.example` dan `.env.production.example`.

---

## 6. Serving halaman

`routes/web.php` (di luar prefix `api`):
- `GET /` → `response()->file(public_path('index.html'))`.
- `GET /client.html` → middleware auth user; kalau belum login **redirect ke `/`** (bukan JSON), kalau sudah → `response()->file(resource_path('pages/client.html'))`. (`client.html` tidak boleh ada di `public/`.)
- `admin.html` dan `admin-users.html` tetap statis di `public/` (login ada di dalam halaman, sama seperti versi Node).
- Fallback: GET non-`api/*` yang tidak ketemu → `index.html` (perilaku Node), tapi `api/*` yang tidak ketemu → JSON 404.
- `config.js` biarkan `API_BASE_URL:''` (same-origin).

---

## 7. Menjalankan & verifikasi lokal

```powershell
composer install
copy .env.example .env      # lalu isi DB sesuai bagian 1
php artisan key:generate
php artisan migrate --seed
php artisan wrbn:create-admin
php artisan serve           # http://127.0.0.1:8000
```
Set `ADMIN_EMAIL` & `ADMIN_PASSWORD` di `.env` sebelum `wrbn:create-admin`. Kalau port 8000 dipakai, `php artisan serve --port=8080`.

Smoke test manual (tulis hasilnya ringkas di akhir):
1. `GET /api/health` → ok.
2. Register user → login → `GET /api/auth/me`.
3. `GET /api/services` → 3 paket, ada `priceLabel`.
4. Buat payment → dummy-pay (di local) → buat project → project_code berformat benar.
5. Upload file `.pdf` OK, `.exe` ditolak ("Tipe file tidak diizinkan.").
6. Login di `/admin.html` → dashboard, ubah status project, kirim progress; cek muncul di `client.html`.
7. Reschedule & upgrade request → approve dari admin.
8. `/client.html` tanpa login → redirect ke `/`.

Tulis juga **feature test** (PHPUnit/Pest) untuk: gating payment→project, payment tidak bisa dipakai dua kali, dummy-pay 403 di production, format project code, whitelist file upload.

---

## 8. Fitur dari notulen (kerjakan setelah bagian 1–7 lolos)

Flow: `client_intake → client_liaison → koordinator → pendukung → analisis_utama → qc → finalisasi_dokumen → done`.

### 8A. Workflow pipeline
- Migration: kolom `consultations.workflow_stage` (default `client_intake`); tabel `project_assignments` (`id, consultation_id, role enum(client_liaison, koordinator, pendukung, analis_utama, qc, finalisasi), assignee_name, assignee_contact, timestamps`); tabel `stage_history` (`id, consultation_id, from_stage, to_stage, note, created_at`). FK cascade.
- Class `App\Support\Workflow`: daftar stage berurut, label Indonesia, `statusForStage()` (`client_intake→submitted`; `client_liaison|koordinator→reviewing`; `pendukung|analisis_utama|qc|finalisasi_dokumen→in_progress`; `done→completed`), `canAdvance($from,$to)` (maju 1 tahap, atau mundur ke tahap sebelumnya, mis. QC gagal → analisis_utama).
- Admin API: `PATCH /api/admin/projects/{id}/stage` `{stage,note}` (transaction: update stage+status, insert `stage_history`, insert `project_updates`; jangan ubah project `cancelled`); `PUT /api/admin/projects/{id}/assignments`. `GET projects/{id}` ikut mengirim `assignments` dan `stageHistory`. Sinkronkan `consultant_name` ← analis_utama dan `contact_person` ← client_liaison.
- UI admin (modal detail project): dropdown tahap valid, catatan, form penugasan 6 peran, riwayat tahap. UI client: stepper 7 tahap read-only; hanya Client Liaison yang tampil sebagai kontak (jangan tampilkan nama pendukung/QC).

### 8B. Dua pintu
- Plan 1 (General): siapa pun register dan bayar per project (perilaku sekarang), `users.access_plan='general'`.
- Plan 2 (Member): tabel `member_emails` (`id, email unique lowercase, plan_label, note, expires_at nullable, timestamps`) + `users.access_plan` (default `general`).
  - Register & login: hitung ulang `access_plan` dari `member_emails` (belum expired). Email dihapus dari whitelist → user kembali `general` pada login berikutnya; **user dan project tidak dihapus**.
  - Member boleh membuat project tanpa payment `paid` (`payment_id` null), batas `MEMBER_MAX_ACTIVE=3` project aktif (409 kalau lewat; ubah angka di config). **Konfirmasi dengan saya dulu** apakah ini sesuai maksud "langganan"; kalau tidak, lewati aturan ini.
  - Admin API: `GET/POST/DELETE /api/admin/members` (POST mendukung banyak email sekaligus dipisah baris/koma; DELETE hanya menghapus whitelist).
  - UI admin halaman "Members": tambah email massal; tabel dengan checkbox; **mencentang email langsung menghapusnya** + toast "Undo" 5 detik; juga "Pilih semua" + hapus massal dengan konfirmasi.

### 8C. Wizard intake 3 layer (pengganti GForm)
- Ganti modal booking di `public/index.html` menjadi wizard 3 layer (indikator 1/3–3/3, tombol Kembali/Lanjut, state di variabel JS). Modal Custom tetap; hasilnya masuk `notes`.
  - Layer 1 General: nama, email (dari akun), WhatsApp, judul, deskripsi kebutuhan, jenis kebutuhan, deadline (opsional). **Jadwal dibuat opsional.**
  - Layer 2 Biodata + Paket + Payment: umur (13–100), status, institusi, jenjang/jabatan, kota; pilih paket; payment (general) atau dilewati dengan label "Akses member aktif" (member).
  - Layer 3 CP: tampilkan project_code, nama CP, tombol "Chat CP via WhatsApp" (`wa.me` dengan pesan terisi kode project), tombol "Buka Client Area".
- Backend: tabel `user_profiles` (`user_id pk, age, occupation, institution, level, city, referral, timestamps`), `GET/PUT /api/client/profile`; kolom baru `consultations.need_type`, `consultations.deadline`; kirim juga ke payload Google Sheets (`umur, institusi, status, needType, deadline, projectCode, accessPlan`); biodata tampil di detail project admin.
- Kalau create project gagal setelah pembayaran, tampilkan pesan jelas dan arahkan ke Client Area (form manual di `client.html` tetap sebagai fallback).

### 8D. CP general + bot
- Env: `CP_GENERAL_NAME`, `CP_GENERAL_WHATSAPP` (format `62...`), `BOT_PROVIDER` (`none|link|webhook`, default `link`), `BOT_API_URL`, `BOT_API_KEY`.
- Tabel `contact_persons` (`id, name, whatsapp, is_general, active, timestamps`), seed CP general dari env. `GET /api/contact-person` publik (tanpa secret); CRUD admin; saat project dibuat tanpa assignment `client_liaison`, otomatis assign CP general.
- `App\Services\Bot\BotService` (interface) + `LinkBot`, `WebhookBot`, `NoopBot`, dipilih lewat config seperti payment adapter. `notify($event,$payload)` dipanggil lewat queue/`afterResponse` pada event: project dibuat, pindah tahap, progress baru, reschedule/upgrade diputuskan. Error provider tidak boleh menggagalkan request. Jangan hardcode provider WhatsApp; dokumentasikan format payload di README.
- Ganti nomor placeholder `+62 812-0000-0000` di `index.html` dengan data dari `/api/contact-person` (fallback ke placeholder bila gagal).

### 8E. Perapihan (opsional)
1. Custom package: payment status "menunggu quote" + `quoted_amount` yang diisi admin sebelum bisa `paid`.
2. Upgrade approved → buat payment `pending` sebesar selisih harga.
3. Hapus user: peringatan di UI bahwa riwayat payment ikut hilang, atau ganti ke soft delete.
4. README: arsitektur Laravel, cara setup, alur 2 pintu, tahapan pipeline, env baru, format payload bot.

---

## 9. Definition of done
- `php artisan migrate:fresh --seed` jalan di MySQL `127.0.0.1:3307`; `php artisan serve` menampilkan website dan semua halaman lama berfungsi tanpa error di console browser.
- Smoke test bagian 7 lolos dan feature test hijau (`php artisan test`).
- Tidak ada secret di `public/` atau ter-commit; `.env.example` dan `.env.production.example` lengkap.
- `legacy-node/` boleh dihapus hanya setelah saya konfirmasi.
- Ringkasan akhir: daftar file yang dibuat/diubah per bagian, perintah menjalankan, dan hal yang masih butuh keputusan saya.
