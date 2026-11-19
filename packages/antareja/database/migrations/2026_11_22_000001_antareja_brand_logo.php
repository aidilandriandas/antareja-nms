<?php

use App\Facades\LibrenmsConfig;
use Illuminate\Database\Migrations\Migration;

/**
 * Ganti logo sementara (segitiga, v0.1–0.3) dengan logo resmi ANTAREJA.
 * Hanya mengubah nilai yang masih menunjuk ke logo sementara, agar logo kustom pengguna tidak tertimpa.
 */
return new class extends Migration
{
    private const OLD = [
        'title_image' => ['images/antareja/logo.svg', 'images/antareja/logo.svg?v=1', 'images/antareja/antareja-mark-64.png'],
        'favicon' => ['images/antareja/favicon.svg'],
    ];

    private const NEW = [
        'title_image' => 'images/antareja/antareja-mark-256.png',
        'favicon' => 'images/antareja/favicon-32.png',
    ];

    public function up(): void
    {
        foreach (self::NEW as $key => $value) {
            $current = LibrenmsConfig::get($key);
            if ($current === null || $current === '' || in_array($current, self::OLD[$key], true)) {
                LibrenmsConfig::persist($key, $value);
            }
        }
    }

    public function down(): void
    {
    }
};
