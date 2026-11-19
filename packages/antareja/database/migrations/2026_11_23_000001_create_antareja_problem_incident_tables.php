<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA Fase 3 — Problem (ala Zabbix), korelasi event, incident, eskalasi, maintenance.
 * Event asli tetap tersimpan sebagai "sumber" problem dengan referensi ke ID aslinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->nullable()->unique();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('severity', 16)->index()->comment('critical, high, medium, low');
            $table->string('status', 16)->default('open')->index()->comment('open, acknowledged, in_progress, pending, resolved, closed');
            $table->unsignedInteger('device_id')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->unsignedInteger('assigned_to')->nullable();
            $table->string('pic_name', 128)->nullable();
            $table->string('source', 16)->default('manual')->comment('manual, problem, auto');
            $table->timestamp('started_at')->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('corrective_action')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedBigInteger('escalation_policy_id')->nullable();
            $table->smallInteger('last_escalation_step')->default(-1);
            $table->boolean('resolved_notified')->default(false);
            $table->timestamps();

            $table->foreign('device_id')->references('device_id')->on('devices')->nullOnDelete();
            $table->foreign('assigned_to')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_incident_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained('ant_incidents')->cascadeOnDelete();
            $table->string('type', 16)->comment('note, status, assignment, escalation, link, system');
            $table->text('body')->nullable();
            $table->string('old_value', 64)->nullable();
            $table->string('new_value', 64)->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('ant_problems', function (Blueprint $table): void {
            $table->id();
            $table->string('correlation_key', 160)->index();
            $table->string('class', 24)->index()->comment('device_down, port_down, quality, ip_conflict, alert');
            $table->string('severity', 16)->index()->comment('information, warning, average, high, disaster');
            $table->string('title', 255);
            $table->text('detail')->nullable();
            $table->unsignedInteger('device_id')->nullable();
            $table->unsignedInteger('port_id')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->string('status', 16)->default('problem')->index()->comment('problem, resolved');
            $table->timestamp('started_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedInteger('acknowledged_by')->nullable();
            $table->string('ack_message', 255)->nullable();
            $table->string('suppressed_reason', 16)->nullable()->comment('parent, maintenance');
            $table->foreignId('parent_problem_id')->nullable()->constrained('ant_problems')->nullOnDelete();
            $table->foreignId('incident_id')->nullable()->constrained('ant_incidents')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'correlation_key']);
            $table->foreign('device_id')->references('device_id')->on('devices')->nullOnDelete();
            $table->foreign('port_id')->references('port_id')->on('ports')->nullOnDelete();
            $table->foreign('acknowledged_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_problem_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('problem_id')->constrained('ant_problems')->cascadeOnDelete();
            $table->string('source', 24)->comment('device, port, quality, ipam, librenms_alert');
            $table->string('source_ref', 64)->comment('ID asli di sumbernya (device_id, port_id, alerts.id, target id, IP)');
            $table->string('label', 255);
            $table->boolean('active')->default(true)->index();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('cleared_at')->nullable();

            $table->index(['source', 'source_ref', 'active']);
        });

        Schema::create('ant_problem_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('problem_id')->constrained('ant_problems')->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('message', 255);
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ant_escalation_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->string('min_severity', 16)->default('high')->comment('severity incident minimum: low, medium, high, critical');
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->boolean('auto_create_incident')->default(true);
            $table->unsignedSmallInteger('auto_create_after_minutes')->default(5);
            $table->boolean('notify_resolved')->default(true);
            $table->json('steps')->comment('[{"after_minutes":0,"transport_ids":[1,2]}]');
            $table->timestamps();
        });

        Schema::create('ant_maintenance_windows', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 255);
            $table->string('type', 16)->comment('preventive, corrective, change');
            $table->string('status', 16)->default('planned')->index()->comment('planned, in_progress, completed, cancelled');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->text('description')->nullable();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->unsignedInteger('pic_user_id')->nullable();
            $table->string('pic_name', 128)->nullable();
            $table->boolean('exclude_from_sla')->default(true);
            $table->unsignedInteger('alert_schedule_id')->nullable()->comment('alert_schedule LibreNMS yang menekan alert');
            $table->text('work_done')->nullable();
            $table->string('result', 16)->nullable()->comment('success, partial, failed');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('pic_user_id')->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_maintenance_devices', function (Blueprint $table): void {
            $table->foreignId('window_id')->constrained('ant_maintenance_windows')->cascadeOnDelete();
            $table->unsignedInteger('device_id');
            $table->primary(['window_id', 'device_id']);
            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_maintenance_devices');
        Schema::dropIfExists('ant_maintenance_windows');
        Schema::dropIfExists('ant_escalation_policies');
        Schema::dropIfExists('ant_problem_events');
        Schema::dropIfExists('ant_problem_sources');
        Schema::dropIfExists('ant_problems');
        Schema::dropIfExists('ant_incident_notes');
        Schema::dropIfExists('ant_incidents');
    }
};
