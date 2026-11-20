<?php

return [
    'name' => 'ANTAREJA NMS',
    'full_name' => 'Mining ICT Infrastructure Management & Unified Network Monitoring Platform',
    // Alamat ANTAREJA yang dibuka pengguna; dipakai untuk tautan di notifikasi Telegram/email
    'public_url' => env('ANTAREJA_PUBLIC_URL'),
    // Asisten AI opsional (Claude API). Tanpa kunci = fitur AI nonaktif; deteksi anomali tetap jalan offline.
    'ai' => [
        'key' => env('ANTAREJA_AI_KEY'),
        'model' => env('ANTAREJA_AI_MODEL', 'claude-opus-5-5'),
        // Opsional: gateway API perusahaan (kosong = https://api.anthropic.com)
        'base_url' => env('ANTAREJA_AI_BASE_URL'),
        // Samarkan alamat IP & MAC sebelum dikirim ke model
        'redact' => (bool) env('ANTAREJA_AI_REDACT', true),
    ],
    'based_on' => [
        'name' => 'LibreNMS',
        'url' => 'https://www.librenms.org',
        'license' => 'GPL-3.0-or-later',
    ],
];
