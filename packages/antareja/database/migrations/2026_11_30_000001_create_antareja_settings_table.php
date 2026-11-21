<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA — pengaturan sederhana (key/value JSON), mis. modul yang dinonaktifkan.
 * Bawaan profil NOC: Inventaris aset, Discovery (verifikasi aset), Fiber Optic, Work Order, Change Request dimatikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        DB::table('ant_settings')->insert([
            'key' => 'modules_disabled',
            'value' => json_encode(['device-inventory', 'device-discovery', 'fiber', 'workorders', 'changes']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_settings');
    }
};
