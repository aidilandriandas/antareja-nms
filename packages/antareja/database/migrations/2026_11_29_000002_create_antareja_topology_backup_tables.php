<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA — tata letak peta topologi (dipakai bersama semua user & layar NOC) dan backup NMS dari web.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_topology_positions', function (Blueprint $table): void {
            $table->unsignedInteger('device_id')->primary();
            $table->integer('x');
            $table->integer('y');
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
        });

        Schema::create('ant_backup_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('schedule_enabled')->default(true);
            $table->string('schedule_time', 5)->default('01:30');
            $table->unsignedSmallInteger('keep_count')->default(14)->comment('jumlah backup lokal yang disimpan');
            $table->boolean('include_rrd')->default(true)->comment('sertakan grafik historis (RRD)');
            $table->boolean('sftp_enabled')->default(false);
            $table->string('sftp_host', 255)->nullable();
            $table->unsignedSmallInteger('sftp_port')->default(22);
            $table->string('sftp_username', 128)->nullable();
            $table->text('sftp_secret')->nullable()->comment('password / private key terenkripsi (APP_KEY)');
            $table->string('sftp_path', 255)->nullable();
            $table->string('sftp_host_key', 128)->nullable()->comment('fingerprint SHA256 host key (TOFU)');
            $table->unsignedSmallInteger('sftp_keep_count')->default(30);
            $table->timestamps();
        });

        Schema::create('ant_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('filename', 128)->unique();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->string('status', 16)->default('running')->index()->comment('running, ok, failed');
            $table->string('trigger', 16)->default('manual')->comment('manual, schedule');
            $table->boolean('include_rrd')->default(true);
            $table->string('offsite_status', 16)->nullable()->comment('ok, failed, skipped');
            $table->string('message', 500)->nullable();
            $table->json('summary')->nullable()->comment('jumlah perangkat, ukuran bagian, dll.');
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_backups');
        Schema::dropIfExists('ant_backup_settings');
        Schema::dropIfExists('ant_topology_positions');
    }
};
