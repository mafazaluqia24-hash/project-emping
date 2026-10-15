# Blueprint Website Pemesanan UMKM Emping (Revisi)

> Revisi menutup 7 kelemahan hasil analisis blueprint awal. File ini ditulis ulang dari ringkasan analisis,
> karena berkas blueprint asli tidak tersedia saat revisi; **gabungkan** dengan bagian asli Anda (tujuan, profil
> pengguna, teknologi) bila ada yang belum tercakup di sini.

## 1. Masalah & Tujuan
Pembatalan mendadak membuat bahan dan tenaga yang sudah disiapkan terbuang. Solusi: konfirmasi penjual,
batas waktu pembatalan yang tegas, dan DP untuk pesanan besar.

## 2. Aktor
| Aktor | Peran |
|---|---|
| Pelanggan | Memesan, membayar DP, melacak, membatalkan sebelum batas waktu |
| Penjual/Admin | Menerima/menolak, verifikasi DP, mengatur produksi |
| Kurir | **Di luar MVP** (hanya status "Siap Dikirim/Diambil") |

## 3. Alur & Status
### 3.1 Enum status tetap (sumber tunggal: `src/constants.php`)
`pesanan_dibuat -> menunggu_konfirmasi -> [menunggu_verifikasi_dp] -> terkonfirmasi -> diproduksi -> siap_kirim -> selesai`
Cabang: `ditolak_penjual`, `dibatalkan_pelanggan`.

### 3.2 Pembayaran & DP (perbaikan #1)
1. Total > Rp 200.000 -> DP 30% (angka dapat diatur admin).
2. Penjual menerima -> status `menunggu_verifikasi_dp`; pelanggan transfer dan mengirim bukti (no. referensi/catatan).
3. Admin memverifikasi (`lunas`) atau menolak bukti (pelanggan kirim ulang).
4. **Produksi diblokir** sampai DP `lunas`.
5. Pembatalan sebelum batas waktu -> DP dikembalikan penuh.

### 3.3 Penolakan penjual (perbaikan #2)
Alasan wajib -> pelanggan menerima notifikasi + alasan -> status `ditolak_penjual` -> stok reserve dilepas -> DP dikembalikan penuh.

## 4. Data (perbaikan #6)
Tabel: `settings`, `users`, `products(stok, stok_reserved)`, `orders(status, status_pembayaran, dp_required, dp_amount,
waktu_konfirmasi, batas_waktu_pembatalan, needs_escalation, stock_committed)`, `order_items` (snapshot harga),
`order_status_logs` (audit), **`payments`** (baru). Lihat `schema.sql`.

## 5. Sitemap
Beranda · Pesan (katalog, keranjang, checkout) · Pesanan Saya (detail, timeline, countdown, batal, upload DP) ·
Admin (login PIN, dashboard, pengaturan). Kurir: tidak ada panel (di luar MVP) (perbaikan #4).

## 6. UI / Wireframe (perbaikan #7)
```
PESANAN SAYA                                   Dashboard Admin
+--------------------------------------+       +------------------------------------------------+
| EPG-20261009-AB12CD   [Terkonfirmasi]|       | Perlu konfirmasi 3 | DP 1 | Eskalasi 1 | Rp ...  |
| (o)--(o)--(o)--( )--( )--( )         |       | [Semua][Menunggu][DP][Produksi]...             |
| Batas pembatalan   01:42:10          |       | Kode | Pemesan | Total | Status | Aksi          |
| DP wajib Rp 78.000 [kirim bukti]     |       | ...  | ...     | ...   | ...    | [Terima][Tolak]|
| Timeline: dibuat > diterima > ...    |       | (baris merah = eskalasi >6 jam)                |
| [Batalkan Pesanan]  [WhatsApp]       |       +------------------------------------------------+
+--------------------------------------+
```
Catatan keamanan: seluruh timestamp dan hitung mundur dihitung **di server**; klien hanya menampilkan.

## 7. Teknologi
PHP native + MySQL (XAMPP) untuk MVP; jalur skala: Laravel/Express + Next.js. Git + GitHub + Actions untuk CI.

## 8. Aturan Bisnis
1. Batas pembatalan dihitung sejak **konfirmasi**, bukan sejak pesanan dibuat; default 2 jam, diatur admin (1-24 jam).
2. Pesanan > Rp 200.000 wajib DP; produksi diblokir sampai DP terverifikasi.
3. **Reserve stok** (perbaikan #3): di-reserve saat dibuat, dipotong permanen saat diterima, dilepas/dikembalikan saat batal/tolak.
4. Pembatalan hanya pada `menunggu_konfirmasi`, `menunggu_verifikasi_dp`, `terkonfirmasi` dan sebelum batas waktu; setelah itu hubungi penjual.
5. **Eskalasi berjenjang, bukan batal otomatis** (perbaikan #5): >6 jam tanpa respons ditandai di dashboard admin; pelanggan mendapat pengingat H-30 menit sebelum batas pembatalan.
6. Setiap perubahan status dicatat di audit log.
7. Validasi dan transisi status ditegakkan server.

## 9. Roadmap
- **Fase 1**: katalog, checkout, konfirmasi penjual, batas pembatalan, DP manual, timeline.
- **Fase 2**: notifikasi WhatsApp otomatis, upload gambar bukti, laporan.
- **Fase 3**: payment gateway, kurir, akun pelanggan.
