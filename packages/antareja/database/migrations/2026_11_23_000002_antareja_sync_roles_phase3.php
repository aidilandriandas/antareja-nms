<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Fase 3: permission Problem & Eskalasi untuk role ANTAREJA.
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
