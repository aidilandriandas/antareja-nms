<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA Fase 2 — Network Quality (Quality Probe, pengganti SmokePing).
 * Sampel 5 menit disimpan 30 hari; ringkasan harian disimpan jangka panjang untuk laporan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_quality_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->string('host', 255)->comment('IP atau hostname yang di-ping');
            $table->unsignedInteger('device_id')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->string('group_name', 64)->nullable()->index()->comment('mis. WAN, PTP Backhaul, Antar-site');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('pings')->default(20);
            $table->unsignedSmallInteger('latency_warn_ms')->default(100);
            $table->unsignedSmallInteger('latency_crit_ms')->default(250);
            $table->decimal('loss_warn_pct', 5, 2)->default(5);
            $table->decimal('loss_crit_pct', 5, 2)->default(20);
            $table->unsignedSmallInteger('jitter_warn_ms')->default(30);
            $table->boolean('enabled')->default(true);
            $table->string('last_status', 16)->nullable()->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('device_id')->on('devices')->nullOnDelete();
        });

        Schema::create('ant_quality_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('target_id')->constrained('ant_quality_targets')->cascadeOnDelete();
            $table->timestamp('measured_at');
            $table->unsignedTinyInteger('sent');
            $table->unsignedTinyInteger('received');
            $table->decimal('loss_pct', 5, 2);
            $table->float('min_ms')->nullable();
            $table->float('p10_ms')->nullable();
            $table->float('p25_ms')->nullable();
            $table->float('median_ms')->nullable();
            $table->float('avg_ms')->nullable();
            $table->float('p75_ms')->nullable();
            $table->float('p90_ms')->nullable();
            $table->float('max_ms')->nullable();
            $table->float('jitter_ms')->nullable()->comment('Rata-rata selisih RTT berurutan (RFC 3550 style)');
            $table->float('stddev_ms')->nullable();

            $table->index(['target_id', 'measured_at']);
            $table->index('measured_at');
        });

        Schema::create('ant_quality_daily', function (Blueprint $table): void {
            $table->foreignId('target_id')->constrained('ant_quality_targets')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedSmallInteger('samples');
            $table->decimal('avg_loss_pct', 5, 2);
            $table->decimal('max_loss_pct', 5, 2);
            $table->unsignedSmallInteger('samples_with_loss');
            $table->float('avg_median_ms')->nullable();
            $table->float('p95_median_ms')->nullable();
            $table->float('max_ms')->nullable();
            $table->float('avg_jitter_ms')->nullable();
            $table->unsignedSmallInteger('samples_down')->comment('sampel dengan loss 100%');

            $table->primary(['target_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_quality_daily');
        Schema::dropIfExists('ant_quality_samples');
        Schema::dropIfExists('ant_quality_targets');
    }
};
