<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA — live view CCTV (lewat gateway video internal) & cek kesehatan gambar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_video_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('username', 128);
            $table->text('secret')->comment('password terenkripsi (APP_KEY); pakai akun viewer/operator NVR');
            $table->timestamps();
        });

        Schema::create('ant_cctv_streams', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('device_id')->unique()->comment('kamera');
            $table->unsignedInteger('nvr_device_id')->nullable()->comment('NVR sumber stream (kosong = langsung ke kamera)');
            $table->string('host', 255)->comment('IP/host NVR atau kamera');
            $table->unsignedSmallInteger('rtsp_port')->default(554);
            $table->unsignedSmallInteger('channel')->default(1)->comment('nomor channel kamera di NVR (1 untuk kamera langsung)');
            $table->string('rtsp_path', 255)->nullable()->comment('kosong = format Hikvision /Streaming/Channels/{channel}0{1|2}');
            $table->foreignId('credential_id')->constrained('ant_video_credentials')->restrictOnDelete();
            $table->boolean('enabled')->default(true);
            $table->boolean('health_check')->default(true);
            $table->string('health', 16)->nullable()->index()->comment('ok, dark, blank, frozen, blur, moved, error');
            $table->string('health_detail', 255)->nullable();
            $table->json('metrics')->nullable();
            $table->json('reference_metrics')->nullable();
            $table->timestamp('reference_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('health_changed_at')->nullable();
            $table->unsignedSmallInteger('frozen_count')->default(0);
            $table->timestamps();

            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
            $table->foreign('nvr_device_id')->references('device_id')->on('devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_cctv_streams');
        Schema::dropIfExists('ant_video_credentials');
    }
};
