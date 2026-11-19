<?php

return [
    'name' => 'ANTAREJA NMS',
    'full_name' => 'Mining ICT Infrastructure Management & Unified Network Monitoring Platform',
    // Alamat ANTAREJA yang dibuka pengguna; dipakai untuk tautan di notifikasi Telegram/email
    'public_url' => env('ANTAREJA_PUBLIC_URL'),
    'based_on' => [
        'name' => 'LibreNMS',
        'url' => 'https://www.librenms.org',
        'license' => 'GPL-3.0-or-later',
    ],
];
