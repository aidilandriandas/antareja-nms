<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA Fase 1: site/area, detail inventaris, device mapping, evidence, audit log.
 * Tabel LibreNMS tidak diubah; relasi memakai foreign key ke devices / ports / users / locations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_sites', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 128);
            $table->string('type', 32)->index();
            $table->text('description')->nullable();
            $table->string('address', 255)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->unsignedInteger('location_id')->nullable()->comment('locations.id LibreNMS (sysLocation)');
            $table->unsignedInteger('pic_user_id')->nullable();
            $table->string('pic_name', 128)->nullable();
            $table->string('pic_phone', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
            $table->foreign('pic_user_id')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->constrained('ant_sites')->cascadeOnDelete();
            $table->string('name', 128);
            $table->string('code', 32)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });

        Schema::create('ant_device_details', function (Blueprint $table): void {
            $table->unsignedInteger('device_id')->primary();
            $table->string('category', 32)->nullable()->index();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('ant_areas')->nullOnDelete();
            $table->string('department', 128)->nullable()->index();
            $table->string('installation_location', 255)->nullable()->comment('Rak / tiang / panel / ruangan');
            $table->string('rack', 64)->nullable();
            $table->string('serial_number', 128)->nullable()->index();
            $table->string('asset_tag', 64)->nullable()->index();
            $table->string('model', 128)->nullable();
            $table->string('mac_address', 32)->nullable()->index();
            $table->string('management_protocol', 16)->nullable();
            $table->unsignedInteger('pic_user_id')->nullable();
            $table->string('pic_name', 128)->nullable();
            $table->string('pic_phone', 64)->nullable();
            $table->date('installed_at')->nullable();
            $table->date('warranty_until')->nullable();
            $table->text('warranty_info')->nullable();
            $table->string('lifecycle_status', 16)->default('operational');
            $table->string('verification_status', 16)->default('pending')->index();
            $table->string('suggested_category', 32)->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejected_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
            $table->foreign('pic_user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_device_links', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('a_device_id')->comment('Sisi A = upstream / parent');
            $table->unsignedInteger('a_port_id')->nullable();
            $table->unsignedInteger('b_device_id')->comment('Sisi B = downstream / child');
            $table->unsignedInteger('b_port_id')->nullable();
            $table->string('link_type', 16)->index();
            $table->string('status', 16)->default('suggested')->index();
            $table->string('source', 16)->default('manual');
            $table->string('source_ref', 64)->nullable();
            $table->string('evidence_text', 255)->nullable()->comment('Alasan saran, mis. "LLDP: Gi0/1 -> eth0"');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'source_ref']);
            $table->index(['a_device_id', 'b_device_id']);
            $table->foreign('a_device_id')->references('device_id')->on('devices')->cascadeOnDelete();
            $table->foreign('b_device_id')->references('device_id')->on('devices')->cascadeOnDelete();
            $table->foreign('a_port_id')->references('port_id')->on('ports')->nullOnDelete();
            $table->foreign('b_port_id')->references('port_id')->on('ports')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('attachable_type', 64);
            $table->string('attachable_id', 64);
            $table->string('disk', 32);
            $table->string('path', 255);
            $table->string('original_name', 255);
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->string('description', 255)->nullable();
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->foreign('uploaded_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->string('username', 255)->nullable();
            $table->string('event', 32)->index();
            $table->string('auditable_type', 64)->nullable();
            $table->string('auditable_id', 64)->nullable();
            $table->string('description', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_audit_logs');
        Schema::dropIfExists('ant_attachments');
        Schema::dropIfExists('ant_device_links');
        Schema::dropIfExists('ant_device_details');
        Schema::dropIfExists('ant_areas');
        Schema::dropIfExists('ant_sites');
    }
};
