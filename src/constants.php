<?php
/**
 * SINGLE SOURCE OF TRUTH: status, aksi admin, label, warna.
 * Dipakai backend (validasi transisi) dan diekspor ke JS lewat public/index.php
 * sebagai window.APP_CONFIG -> UI tidak punya string status sendiri.
 */
declare(strict_types=1);

return [
    'status' => [
        'pesanan_dibuat'         => ['label' => 'Pesanan Dibuat',          'color' => '#6b7280', 'description' => 'Pesanan tercatat.'],
        'menunggu_konfirmasi'    => ['label' => 'Menunggu Konfirmasi',     'color' => '#d97706', 'description' => 'Menunggu penjual menerima atau menolak.'],
        'menunggu_verifikasi_dp' => ['label' => 'Menunggu Verifikasi DP',  'color' => '#7c3aed', 'description' => 'Pesanan diterima, DP perlu dibayar dan diverifikasi.'],
        'terkonfirmasi'          => ['label' => 'Terkonfirmasi',           'color' => '#2563eb', 'description' => 'Siap diproduksi. Pembatalan masih mungkin sebelum batas waktu.'],
        'diproduksi'             => ['label' => 'Sedang Diproduksi',       'color' => '#0891b2', 'description' => 'Produksi dimulai, tidak dapat dibatalkan.'],
        'siap_kirim'             => ['label' => 'Siap Dikirim/Diambil',    'color' => '#65a30d', 'description' => 'Pesanan siap diambil atau dikirim.'],
        'selesai'                => ['label' => 'Selesai',                 'color' => '#16a34a', 'description' => 'Transaksi selesai.'],
        'dibatalkan_pelanggan'   => ['label' => 'Dibatalkan Pelanggan',    'color' => '#dc2626', 'description' => 'Dibatalkan pelanggan sebelum batas waktu.'],
        'ditolak_penjual'        => ['label' => 'Ditolak Penjual',         'color' => '#b91c1c', 'description' => 'Ditolak penjual. DP (jika ada) dikembalikan penuh.'],
    ],

    // Urutan alur utama untuk stepper (cabang batal/tolak ditampilkan terpisah)
    'main_flow' => ['menunggu_konfirmasi', 'menunggu_verifikasi_dp', 'terkonfirmasi', 'diproduksi', 'siap_kirim', 'selesai'],

    'payment_status' => [
        'belum_dibayar'       => ['label' => 'Belum Dibayar',        'color' => '#6b7280'],
        'menunggu_verifikasi' => ['label' => 'Menunggu Verifikasi',  'color' => '#d97706'],
        'lunas'               => ['label' => 'Lunas',                'color' => '#16a34a'],
        'dikembalikan'        => ['label' => 'Dikembalikan',         'color' => '#2563eb'],
        'hangus'              => ['label' => 'Hangus',               'color' => '#dc2626'],
    ],

    // Status yang boleh dibatalkan pelanggan. 'deadline' = wajib sebelum batas_waktu_pembatalan.
    'customer_cancel' => [
        'menunggu_konfirmasi'    => ['deadline' => false],
        'menunggu_verifikasi_dp' => ['deadline' => true],
        'terkonfirmasi'          => ['deadline' => true],
    ],

    // Aksi admin yang sah per status (backend menegakkan, UI menggambar tombol dari sini)
    'admin_actions' => [
        'menunggu_konfirmasi'    => ['confirm', 'reject'],
        'menunggu_verifikasi_dp' => ['verify_dp', 'reject_dp'],
        'terkonfirmasi'          => ['start_production'],
        'diproduksi'             => ['ready'],
        'siap_kirim'             => ['complete'],
    ],
    'action_labels' => [
        'confirm'          => 'Terima',
        'reject'           => 'Tolak',
        'verify_dp'        => 'Verifikasi DP',
        'reject_dp'        => 'Tolak Bukti DP',
        'start_production' => 'Mulai Produksi',
        'ready'            => 'Siap Kirim',
        'complete'         => 'Selesai',
    ],
    // Aksi yang memerlukan alasan teks
    'action_needs_reason' => ['reject', 'reject_dp'],

    'reminder_seconds' => 1800, // pengingat H-30 menit sebelum batas pembatalan
];
