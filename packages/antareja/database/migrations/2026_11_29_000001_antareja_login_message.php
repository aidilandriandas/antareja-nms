<?php

use App\Facades\LibrenmsConfig;
use Illuminate\Database\Migrations\Migration;

/**
 * Pesan di halaman login dalam bahasa Indonesia. Hanya mengganti pesan bawaan LibreNMS (pesan kustom tidak disentuh).
 */
return new class extends Migration
{
    private const DEFAULT_EN = 'Unauthorised access or use shall render the user liable to criminal and/or civil prosecution.';

    public function up(): void
    {
        $current = LibrenmsConfig::get('login_message');
        if ($current === null || $current === '' || $current === self::DEFAULT_EN) {
            LibrenmsConfig::persist('login_message', 'Akses atau penggunaan tanpa izin dilarang dan dapat dikenai sanksi pidana maupun perdata.');
        }
    }

    public function down(): void
    {
    }
};
