<?php
/** Validasi input sisi server. Mengembalikan daftar pesan error (kosong = valid). */
declare(strict_types=1);

function validate_create_order(array $in): array
{
    $e = [];
    foreach (['nama_pemesan' => 'Nama', 'no_hp' => 'Nomor HP', 'alamat' => 'Alamat'] as $f => $label) {
        if (!isset($in[$f]) || !is_string($in[$f]) || trim($in[$f]) === '') {
            $e[] = "{$label} wajib diisi";
        }
    }
    if (isset($in['nama_pemesan']) && is_string($in['nama_pemesan']) && mb_strlen($in['nama_pemesan']) > 100) {
        $e[] = 'Nama maksimal 100 karakter';
    }
    if (isset($in['no_hp']) && is_string($in['no_hp']) && !preg_match('/^62\d{8,13}$/', normalize_phone($in['no_hp']))) {
        $e[] = 'Nomor HP tidak valid (contoh: 08123456789)';
    }
    if (empty($in['items']) || !is_array($in['items'])) {
        $e[] = 'Minimal 1 item pesanan';
    } else {
        foreach ($in['items'] as $i => $it) {
            if (!is_array($it) || !isset($it['product_id']) || !ctype_digit((string)$it['product_id'])) {
                $e[] = "Item #" . ($i + 1) . ': produk tidak valid';
            }
            if (!is_array($it) || !isset($it['jumlah']) || !ctype_digit((string)$it['jumlah']) || (int)$it['jumlah'] < 1 || (int)$it['jumlah'] > 1000) {
                $e[] = "Item #" . ($i + 1) . ': jumlah harus 1-1000';
            }
        }
    }
    return $e;
}

function validate_payment_upload(array $in): array
{
    $e = [];
    if (empty($in['code']) || !is_string($in['code'])) {
        $e[] = 'Kode pesanan wajib diisi';
    }
    if (empty($in['no_hp']) || !is_string($in['no_hp'])) {
        $e[] = 'Nomor HP wajib diisi';
    }
    if (trim((string)($in['no_referensi'] ?? '')) === '' && trim((string)($in['bukti_catatan'] ?? '')) === '') {
        $e[] = 'Isi nomor referensi atau catatan transfer';
    }
    return $e;
}

function validate_settings(array $in): array
{
    $e = [];
    if (isset($in['cancel_window_hours']) && (!is_numeric($in['cancel_window_hours']) || (int)$in['cancel_window_hours'] < 1 || (int)$in['cancel_window_hours'] > 24)) {
        $e[] = 'Batas pembatalan harus 1-24 jam';
    }
    if (isset($in['dp_threshold']) && (!is_numeric($in['dp_threshold']) || (int)$in['dp_threshold'] < 0)) {
        $e[] = 'Ambang DP tidak boleh negatif';
    }
    if (isset($in['dp_percent']) && (!is_numeric($in['dp_percent']) || (int)$in['dp_percent'] < 1 || (int)$in['dp_percent'] > 100)) {
        $e[] = 'Persentase DP harus 1-100';
    }
    if (isset($in['phone_seller']) && !preg_match('/^62\d{8,13}$/', normalize_phone((string)$in['phone_seller']))) {
        $e[] = 'Nomor WhatsApp penjual tidak valid';
    }
    if (!empty($in['new_pin']) && !preg_match('/^\d{4,12}$/', (string)$in['new_pin'])) {
        $e[] = 'PIN baru harus 4-12 digit angka';
    }
    return $e;
}
