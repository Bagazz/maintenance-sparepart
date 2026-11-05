<?php

// Backup otomatis database + foto barang. Semua nilai bisa diubah lewat .env.
return [
    // Folder utama penyimpanan file backup (.zip).
    'folder' => env('BACKUP_FOLDER', storage_path('app/backups')),

    // Salinan kedua (disarankan disk / PC lain, mis. D:\Backup-Sparepart atau \\server\share).
    // Kosong = tidak disalin. Backup di disk yang sama tidak menolong bila disk rusak.
    'salin_ke' => env('BACKUP_COPY_TO'),

    // Backup lebih lama dari ini dihapus otomatis (minimal 3 backup terbaru selalu disimpan).
    'simpan_hari' => (int) env('BACKUP_KEEP_DAYS', 30),

    // Jam backup harian (Windows Task Scheduler, lihat: php artisan backup:jadwalkan).
    'jam' => env('BACKUP_TIME', '12:00'),

    // Lokasi mysqldump / mysql. Kosong = cari otomatis (XAMPP lalu PATH).
    'mysqldump' => env('MYSQLDUMP_PATH'),
    'mysql' => env('MYSQL_PATH'),

    // Peringatan di dashboard admin bila backup terakhir lebih tua dari ini (jam).
    'peringatan_jam' => 48,
];
