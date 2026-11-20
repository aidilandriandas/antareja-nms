<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/**
 * Live view CCTV: permission ant-cctv.live untuk role ANTAREJA.
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
