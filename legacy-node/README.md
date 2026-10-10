# WRBN Statistical Consulting

Backend + Client Area + Admin Web untuk WRBN.

## Struktur
- `/` — public website
- `/client.html` — Client Area
- `/admin.html` — Admin Web
- `/admin-users.html` — pengelolaan akun client
- `/api/*` — backend API
- `data/wrbn.sqlite` — database lokal/server
- `backend/uploads/` — file client

## 1. Setup lokal
```bash
npm install
copy .env.example .env
npm run db:admin
npm start
```
Buka `http://localhost:3000`.

> Catatan: `npm start` sekarang otomatis menyiapkan schema database. `npm run db:init` tetap tersedia untuk migrasi/seed manual.

### Admin pertama
Isi `.env`:
```env
ADMIN_EMAIL=admin@domainmu.com
ADMIN_PASSWORD=password-admin-yang-kuat
```
Lalu:
```bash
npm run db:admin
```
Login di `/admin.html` memakai email + password tersebut. Password disimpan sebagai bcrypt hash, bukan plaintext.

## 2. API / integrasi eksternal
Semua secret API diletakkan di `.env`, bukan di HTML/JS:
```env
GOOGLE_SHEETS_WEBHOOK_URL=
PAYMENT_PROVIDER=dummy
PAYMENT_API_URL=
PAYMENT_API_KEY=
```
Biarkan kosong saat belum dipakai. Untuk gateway pembayaran sungguhan, implementasikan adapter di `backend/services/payment-service.js`.

`public/config.js` hanya untuk nilai public seperti `API_BASE_URL`. Jangan pernah menaruh secret API key di file public.

## 3. Database online
Versi ini menggunakan SQLite. SQLite tetap bisa dipakai online **kalau backend dijalankan di server/VPS/container dengan persistent disk**. Set lokasi database production melalui:
```env
DATABASE_PATH=/var/lib/wrbn/data/wrbn.sqlite
UPLOAD_DIR=/var/lib/wrbn/uploads
```
Pastikan folder tersebut berada di persistent volume dan dibackup. Jangan memakai filesystem ephemeral untuk database/file upload.

### Docker
```bash
cp .env.production.example .env
# isi domain, secret, dan akun admin di .env
docker compose up -d --build
```
Docker memakai volume `wrbn_data` untuk `/data`, sehingga database dan upload tidak hilang ketika container dibuat ulang.

Jika nanti pindah ke PostgreSQL/MySQL/managed database, layer database perlu dimigrasikan; jangan hanya mengganti `DATABASE_PATH` menjadi URL.

## 4. Production checklist
- `NODE_ENV=production`
- `FRONTEND_ORIGIN=https://domainmu.com`
- `JWT_SECRET` dan `ADMIN_JWT_SECRET` random panjang
- HTTPS aktif
- SQLite + upload directory di persistent volume
- backup database berkala
- jangan commit `.env`
- ganti `PAYMENT_PROVIDER=dummy` hanya setelah adapter + webhook gateway benar-benar siap
- rate limit tetap aktif

## 5. Arsitektur
Public/Client/Admin memakai satu backend dan satu database. Admin mengubah project melalui `/api/admin/*`; perubahan tersebut otomatis terlihat di Client Area karena membaca data yang sama.
