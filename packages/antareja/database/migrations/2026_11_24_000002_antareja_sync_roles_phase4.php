<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Fase 4: permission SLA, Trigger & Laporan untuk role ANTAREJA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('antareja:roles');
    }

    public function down(): void
    {
    }
};
