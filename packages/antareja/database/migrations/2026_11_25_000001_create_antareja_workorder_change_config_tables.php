<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA Fase 5a — Work order, change request (RFC), backup konfigurasi perangkat (read-only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->nullable()->unique();
            $table->string('title', 255);
            $table->string('type', 16)->default('normal')->comment('standard, normal, emergency');
            $table->string('risk', 8)->default('medium')->comment('low, medium, high');
            $table->string('status', 16)->default('draft')->index()->comment('draft, submitted, approved, rejected, in_progress, completed, failed, cancelled');
            $table->text('description')->nullable()->comment('alasan & rencana implementasi');
            $table->text('impact')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->text('test_plan')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->timestamp('planned_start')->nullable()->index();
            $table->timestamp('planned_end')->nullable();
            $table->boolean('exclude_from_sla')->default(true);
            $table->unsignedInteger('requested_by')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->foreignId('maintenance_window_id')->nullable()->constrained('ant_maintenance_windows')->nullOnDelete();
            $table->timestamp('implemented_at')->nullable();
            $table->string('result', 16)->nullable()->comment('success, partial, failed, rolled_back');
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->foreign('requested_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_change_devices', function (Blueprint $table): void {
            $table->foreignId('change_id')->constrained('ant_changes')->cascadeOnDelete();
            $table->unsignedInteger('device_id');
            $table->primary(['change_id', 'device_id']);
            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
        });

        Schema::create('ant_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('change_id')->constrained('ant_changes')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('type', 16)->comment('status, note, system');
            $table->text('body')->nullable();
            $table->string('old_value', 32)->nullable();
            $table->string('new_value', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ant_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->nullable()->unique();
            $table->string('title', 255);
            $table->string('type', 16)->comment('corrective, preventive, installation, relocation, inspection, cabling');
            $table->string('priority', 8)->default('normal')->comment('low, normal, high, urgent');
            $table->string('status', 16)->default('open')->index()->comment('open, assigned, in_progress, on_hold, done, verified, cancelled');
            $table->text('description')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('ant_areas')->nullOnDelete();
            $table->unsignedInteger('device_id')->nullable()->index();
            $table->foreignId('incident_id')->nullable()->constrained('ant_incidents')->nullOnDelete();
            $table->foreignId('maintenance_window_id')->nullable()->constrained('ant_maintenance_windows')->nullOnDelete();
            $table->foreignId('change_id')->nullable()->constrained('ant_changes')->nullOnDelete();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->string('team', 128)->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->text('result_notes')->nullable();
            $table->json('checklist')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('assigned_to')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('verified_by')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_work_order_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('ant_work_orders')->cascadeOnDelete();
            $table->string('item', 255);
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 16)->default('pcs');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('ant_work_order_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('ant_work_orders')->cascadeOnDelete();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('type', 16)->comment('status, note, checklist, material, system');
            $table->text('body')->nullable();
            $table->string('old_value', 32)->nullable();
            $table->string('new_value', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ant_config_credentials', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('username', 128);
            $table->text('secret')->comment('password terenkripsi (APP_KEY)');
            $table->unsignedSmallInteger('port')->default(22);
            $table->timestamps();
        });

        Schema::create('ant_config_targets', function (Blueprint $table): void {
            $table->unsignedInteger('device_id')->primary();
            $table->string('profile', 24);
            $table->foreignId('credential_id')->constrained('ant_config_credentials')->restrictOnDelete();
            $table->string('host', 255)->nullable()->comment('kosong = IP/hostname perangkat');
            $table->unsignedSmallInteger('interval_hours')->default(24);
            $table->boolean('enabled')->default(true);
            $table->string('host_key', 128)->nullable()->comment('sidik jari host key SSH (TOFU)');
            $table->string('last_status', 16)->nullable()->comment('ok, unchanged, error, hostkey');
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('last_collected_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
        });

        Schema::create('ant_config_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('device_id');
            $table->timestamp('collected_at')->index();
            $table->char('hash', 64);
            $table->unsignedInteger('size');
            $table->unsignedInteger('line_count');
            $table->longText('content')->comment('konfigurasi terkompresi & terenkripsi');
            $table->unsignedInteger('lines_added')->default(0);
            $table->unsignedInteger('lines_removed')->default(0);
            $table->foreignId('change_id')->nullable()->constrained('ant_changes')->nullOnDelete();
            $table->boolean('unauthorized')->default(false)->index();
            $table->unsignedInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('ack_note', 500)->nullable();

            $table->index(['device_id', 'collected_at']);
            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
            $table->foreign('acknowledged_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['ant_config_versions', 'ant_config_targets', 'ant_config_credentials', 'ant_work_order_logs', 'ant_work_order_materials',
            'ant_work_orders', 'ant_change_logs', 'ant_change_devices', 'ant_changes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
