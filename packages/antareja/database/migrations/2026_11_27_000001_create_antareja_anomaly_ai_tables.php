<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA Fase 5c — deteksi anomali statistik (offline) & riwayat keluaran asisten AI (opsional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_anomalies', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 24)->comment('latency, loss, traffic_drop, traffic_spike, flapping');
            $table->string('ref', 64)->comment('quality:{id}, capacity:{id}, device:{id}');
            $table->unsignedInteger('device_id')->nullable()->index();
            $table->string('label', 255);
            $table->double('value')->nullable();
            $table->double('baseline')->nullable();
            $table->double('score')->nullable()->comment('robust z-score');
            $table->string('unit', 8)->nullable();
            $table->string('status', 8)->default('open')->index()->comment('open, closed');
            $table->timestamp('detected_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('closed_at')->nullable();
            $table->index(['kind', 'ref', 'status']);
        });

        Schema::create('ant_ai_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16)->comment('incident, shift');
            $table->unsignedBigInteger('subject_id')->nullable()->comment('incident id');
            $table->string('title', 255);
            $table->longText('content');
            $table->longText('context')->comment('data yang dikirim ke model (untuk transparansi)');
            $table->string('model', 64);
            $table->boolean('fallback')->default(false);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['kind', 'subject_id']);
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_ai_reports');
        Schema::dropIfExists('ant_anomalies');
    }
};
