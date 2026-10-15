# Emping UMKM - Website Pemesanan dengan Konfirmasi & Batas Pembatalan

Aplikasi PHP native + MySQL/MariaDB (XAMPP). Masalah yang diselesaikan: pembatalan mendadak yang merugikan
produsen emping. Alur inti: **pesan -> konfirmasi penjual -> (DP) -> batas pembatalan -> produksi**.

## Menjalankan di XAMPP (Windows)

1. Ekstrak proyek ke `D:\xampp\htdocs\emping` (sehingga ada `D:\xampp\htdocs\emping\schema.sql`).
2. Buka **XAMPP Control Panel**, Start **Apache** dan **MySQL**.
3. Impor database (PowerShell):
   ```powershell
   cd D:\xampp\htdocs\emping
   Get-Content schema.sql -Raw | & "D:\xampp\mysql\bin\mysql.exe" -u root
   ```
   (atau lewat phpMyAdmin: tab *Import* -> pilih `schema.sql`). Skema aman dijalankan ulang.
4. Cek `config/config.php` (default XAMPP: user `root`, password kosong, db `emping_umkm`).
   Jika belum ada: salin dari `config/config.sample.php`.
5. Buka **http://localhost/emping**. Admin: menu *Admin*, PIN awal **1234** (segera ganti di *Pengaturan*).

## Arsitektur & sinkronisasi data

```
schema.sql          -> bentuk data (satu-satunya definisi tabel)
src/constants.php   -> status, label, warna, aksi admin yang sah (SUMBER TUNGGAL)
src/functions.php   -> aturan bisnis (stok, batas waktu, transisi, envelope API)
api/*.php           -> endpoint JSON, semua memakai envelope {ok,data,meta} / {ok:false,error}
public/index.php    -> menyuntik constants ke JS (window.APP_CONFIG)
public/js/main.js   -> UI membaca APP_CONFIG; tidak ada string status buatan sendiri
```

- **Satu sumber status**: tombol aksi admin digambar dari `admin_actions`, dan backend menolak transisi di luar peta itu.
- **Satu sumber waktu**: batas pembatalan dihitung dengan `NOW()` MySQL; API mengirim `seconds_left`. Jam browser tidak dipercaya.
- **Server menegakkan aturan**: tombol "Batalkan" yang nonaktif hanyalah tampilan; `orders_cancel.php` memeriksa ulang.
- **Audit trail**: setiap perubahan status ditulis ke `order_status_logs` (timeline pelanggan, notifikasi, dan log admin).

## Aturan bisnis

| Aturan | Perilaku |
|---|---|
| Reserve stok | Saat pesanan dibuat stok di-*reserve*; dipotong permanen saat diterima; dilepas/dikembalikan saat batal atau ditolak |
| Konfirmasi | Batas pembatalan = waktu konfirmasi + `cancel_window_hours` (default 2 jam, diatur admin) |
| DP | Total > Rp 200.000 wajib DP 30%. Produksi **diblokir** sampai DP terverifikasi |
| Penolakan | Alasan wajib, stok dilepas, DP dikembalikan penuh |
| Pembatalan | Boleh sebelum produksi dan sebelum batas waktu; setelah lewat tombol nonaktif + arahan WhatsApp |
| Eskalasi | Pesanan tanpa respons > 6 jam **ditandai** di admin (tidak dibatalkan otomatis); pelanggan dapat pengingat H-30 menit |
| Kurir | Di luar MVP (hanya status "Siap Dikirim/Diambil") |

## Endpoint

| Endpoint | Metode | Akses |
|---|---|---|
| `api/products.php` | GET | publik |
| `api/orders_create.php` | POST | publik |
| `api/orders_track.php` | POST `{code,no_hp}` | pemilik pesanan |
| `api/orders_cancel.php` | POST `{code,no_hp}` | pemilik pesanan |
| `api/payments_upload.php` | POST | pemilik pesanan |
| `api/notifications.php` | GET (admin) / POST (pelanggan) | sesuai konteks |
| `api/login.php`, `logout.php`, `me.php` | POST/POST/GET | - |
| `api/admin_dashboard.php` | GET | admin |
| `api/admin.php` | POST `{action,order_id,alasan?}` + header `X-CSRF-Token` | admin |
| `api/admin_settings.php` | GET / POST | admin |

## Keamanan

- Query memakai prepared statement; output UI di-escape; admin memakai sesi + token CSRF + pembatasan percobaan PIN.
- Pelacakan pesanan membutuhkan kode **dan** nomor HP.
- `config/config.php` masuk `.gitignore`. **Ganti PIN default** dan set `debug => false` di produksi.
- `.htaccess` memblokir akses langsung ke `config/`, `src/`, `tests/`, dan `*.sql`.

## Tes & CI

```bash
php -S 127.0.0.1:8080 -t .          # terminal 1
php tests/api_smoke.php http://127.0.0.1:8080   # terminal 2 (butuh DB baru)
```
GitHub Actions (`.github/workflows/ci.yml`) menjalankan `php -l`, `node --check`, lalu smoke test dengan service MySQL.

## Publikasi ke GitHub

```powershell
gh auth login
cd D:\xampp\htdocs\emping
git init -b main
git add .
git commit -m "feat: MVP pemesanan emping"
gh repo create emping-umkm --private --source=. --push
```
