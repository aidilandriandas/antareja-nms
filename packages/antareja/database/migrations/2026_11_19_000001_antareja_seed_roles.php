<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Membuat permission "ant-*" dan role ANTAREJA (ict-network, ict-cctv-support, management)
 * secara otomatis saat container pertama kali dijalankan (lnms migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('antareja:roles');
    }

    public function down(): void
    {
        // Role tidak dihapus otomatis agar penugasan user tidak hilang.
    }
};
