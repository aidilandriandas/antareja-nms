<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA — ping cepat tiap menit untuk Layar NOC (deteksi down lebih cepat dari polling 5 menit, panel latensi)
 * dan linimasa kejadian (down/up, reboot, flapping, latensi tinggi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_device_pings', function (Blueprint $table): void {
            $table->unsignedInteger('device_id')->primary();
            $table->string('state', 8)->default('up')->comment('up, down');
            $table->float('rtt_ms')->nullable()->comment('rata-rata RTT pengukuran terakhir');
            $table->unsignedTinyInteger('loss')->default(0)->comment('% loss pengukuran terakhir');
            $table->unsignedSmallInteger('fail_streak')->default(0);
            $table->timestamp('last_ok_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('state_changed_at')->nullable();
            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
        });

        Schema::create('ant_device_ping_samples', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('device_id');
            $table->timestamp('at')->index();
            $table->float('rtt_ms')->nullable();
            $table->unsignedTinyInteger('loss')->default(0);
            $table->index(['device_id', 'at']);
        });

        Schema::create('ant_noc_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('device_id')->nullable()->index();
            $table->string('kind', 16)->index()->comment('down, up, reboot, flapping, latency, port');
            $table->string('message', 255);
            $table->unsignedInteger('duration_s')->nullable()->comment('untuk up: lama down sebelumnya');
            $table->string('source_ref', 64)->nullable()->comment('mis. eventlog:<id> agar tidak dobel');
            $table->timestamp('at')->index();
            $table->unique('source_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_noc_events');
        Schema::dropIfExists('ant_device_ping_samples');
        Schema::dropIfExists('ant_device_pings');
    }
};
