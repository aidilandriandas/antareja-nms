<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Fase 1: tambah permission ant-site.* ke role ANTAREJA dan isi detail inventaris untuk device yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('antareja:roles');
        Artisan::call('antareja:sync-inventory');
    }

    public function down(): void
    {
    }
};
