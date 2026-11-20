<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA Fase 4 — Service tree & SLA, trigger expression ala Zabbix.
 * Downtime dihitung dari device_outages LibreNMS (tidak diduplikasi); hanya hasil bulanan yang disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('ant_services')->nullOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->string('calc', 8)->default('all')->comment('all = down bila satu anggota down; any = down bila semua down; min = down bila anggota up < min_up');
            $table->unsignedSmallInteger('min_up')->nullable();
            $table->decimal('sla_target', 6, 3)->default(99.5);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('ant_service_devices', function (Blueprint $table): void {
            $table->foreignId('service_id')->constrained('ant_services')->cascadeOnDelete();
            $table->unsignedInteger('device_id');
            $table->primary(['service_id', 'device_id']);
            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
        });

        Schema::create('ant_sla_monthly', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('ant_services')->cascadeOnDelete();
            $table->date('month');
            $table->unsignedInteger('period_seconds');
            $table->unsignedInteger('downtime_seconds');
            $table->unsignedInteger('excluded_seconds')->comment('downtime dalam jendela maintenance yang dikecualikan');
            $table->unsignedInteger('outages');
            $table->decimal('availability', 9, 5);
            $table->decimal('sla_target', 6, 3);
            $table->boolean('met');
            $table->timestamp('computed_at');
            $table->unique(['service_id', 'month']);
        });

        Schema::create('ant_triggers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->string('source', 16)->comment('quality, port, sensor, wireless, device');
            $table->unsignedBigInteger('ref_id')->comment('ID item di sumbernya');
            $table->unsignedInteger('device_id')->nullable()->index();
            $table->string('metric', 32);
            $table->string('function', 8)->default('avg')->comment('last, avg, min, max');
            $table->unsignedSmallInteger('window_minutes')->default(15);
            $table->string('operator', 2);
            $table->double('threshold');
            $table->double('recovery_threshold')->nullable()->comment('histeresis: pulih bila kondisi dengan nilai ini tidak lagi terpenuhi');
            $table->string('severity', 16)->default('average');
            $table->boolean('enabled')->default(true);
            $table->string('state', 8)->default('unknown')->index()->comment('ok, problem, unknown');
            $table->double('last_value')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('state_changed_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('ant_trigger_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trigger_id')->constrained('ant_triggers')->cascadeOnDelete();
            $table->timestamp('measured_at');
            $table->double('value');
            $table->index(['trigger_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_trigger_samples');
        Schema::dropIfExists('ant_triggers');
        Schema::dropIfExists('ant_sla_monthly');
        Schema::dropIfExists('ant_service_devices');
        Schema::dropIfExists('ant_services');
    }
};
