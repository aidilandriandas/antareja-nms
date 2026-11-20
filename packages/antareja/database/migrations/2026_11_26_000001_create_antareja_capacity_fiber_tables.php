<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA Fase 5b — Capacity planning (dari histori RRD poller) & manajemen fiber optic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_capacity_items', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 16)->comment('port, storage, subnet');
            $table->unsignedBigInteger('ref_id')->comment('port_id / storage_id / ant_subnets.id');
            $table->unsignedInteger('device_id')->nullable()->index();
            $table->string('label', 255);
            $table->double('capacity')->nullable()->comment('bps / byte / jumlah IP');
            $table->unsignedTinyInteger('warn_pct')->default(70);
            $table->unsignedTinyInteger('crit_pct')->default(90);
            $table->boolean('enabled')->default(true);
            $table->boolean('auto')->default(true)->comment('ditambahkan otomatis');
            $table->double('current_pct')->nullable()->comment('puncak harian terakhir');
            $table->double('trend_pct_per_day')->nullable();
            $table->date('forecast_warn_on')->nullable();
            $table->date('forecast_crit_on')->nullable();
            $table->unsignedSmallInteger('days_of_data')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
            $table->unique(['type', 'ref_id']);
        });

        Schema::create('ant_capacity_daily', function (Blueprint $table): void {
            $table->foreignId('item_id')->constrained('ant_capacity_items')->cascadeOnDelete();
            $table->date('day');
            $table->double('avg_pct');
            $table->double('peak_pct')->comment('p95 dari data 5 menit, atau maksimum harian untuk histori lama');
            $table->double('avg_in')->nullable()->comment('port: bps masuk');
            $table->double('avg_out')->nullable()->comment('port: bps keluar');
            $table->string('peak_method', 8)->default('p95');
            $table->primary(['item_id', 'day']);
        });

        Schema::create('ant_fiber_odfs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('ant_areas')->nullOnDelete();
            $table->string('rack', 64)->nullable();
            $table->unsignedSmallInteger('port_count');
            $table->string('connector', 8)->default('SC')->comment('SC, LC, FC, ST');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ant_fiber_joints', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('type', 16)->default('closure')->comment('closure, odp, splitter, handhole');
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('location', 255)->nullable()->comment('patokan: tiang, KM jalan hauling, dll');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ant_fiber_cables', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 255);
            $table->string('mode', 2)->default('sm')->comment('sm, mm');
            $table->string('fiber_type', 16)->nullable()->comment('G.652D, G.657A, OM3, …');
            $table->unsignedSmallInteger('core_count');
            $table->unsignedSmallInteger('cores_per_tube')->default(12);
            $table->unsignedInteger('length_m')->nullable();
            $table->string('installation', 16)->default('aerial')->comment('aerial, duct, buried, indoor');
            $table->string('a_type', 8)->comment('odf, joint');
            $table->unsignedBigInteger('a_id');
            $table->string('b_type', 8);
            $table->unsignedBigInteger('b_id');
            $table->string('status', 16)->default('active')->comment('planned, active, damaged, retired');
            $table->date('installed_on')->nullable();
            $table->string('vendor', 128)->nullable();
            $table->text('route')->nullable();
            $table->timestamps();
            $table->index(['a_type', 'a_id']);
            $table->index(['b_type', 'b_id']);
        });

        Schema::create('ant_fiber_cores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cable_id')->constrained('ant_fiber_cables')->cascadeOnDelete();
            $table->unsignedSmallInteger('core_no');
            $table->string('status', 16)->default('free')->comment('free, used, reserved, broken');
            $table->string('service', 255)->nullable()->comment('layanan yang dibawa');
            $table->double('loss_db')->nullable()->comment('loss terukur terakhir');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['cable_id', 'core_no']);
        });

        Schema::create('ant_fiber_odf_ports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('odf_id')->constrained('ant_fiber_odfs')->cascadeOnDelete();
            $table->unsignedSmallInteger('port_no');
            $table->foreignId('core_id')->nullable()->unique()->constrained('ant_fiber_cores')->nullOnDelete();
            $table->unsignedInteger('device_id')->nullable();
            $table->unsignedInteger('port_id')->nullable()->index()->comment('port perangkat yang di-patch ke port ODF ini');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
            $table->unique(['odf_id', 'port_no']);
            $table->foreign('device_id')->references('device_id')->on('devices')->nullOnDelete();
        });

        Schema::create('ant_fiber_splices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('joint_id')->constrained('ant_fiber_joints')->cascadeOnDelete();
            $table->foreignId('core_a_id')->unique()->constrained('ant_fiber_cores')->cascadeOnDelete();
            $table->foreignId('core_b_id')->unique()->constrained('ant_fiber_cores')->cascadeOnDelete();
            $table->double('loss_db')->nullable();
            $table->timestamps();
        });

        Schema::create('ant_fiber_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('core_id')->constrained('ant_fiber_cores')->cascadeOnDelete();
            $table->timestamp('tested_at');
            $table->string('method', 8)->default('otdr')->comment('otdr, opm (power meter)');
            $table->unsignedSmallInteger('wavelength_nm')->default(1310);
            $table->double('length_m')->nullable();
            $table->double('total_loss_db')->nullable();
            $table->string('result', 8)->default('pass')->comment('pass, fail');
            $table->text('events')->nullable()->comment('event OTDR: jarak & jenis');
            $table->unsignedInteger('tested_by')->nullable();
            $table->timestamps();
            $table->foreign('tested_by')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (['ant_fiber_tests', 'ant_fiber_splices', 'ant_fiber_odf_ports', 'ant_fiber_cores', 'ant_fiber_cables', 'ant_fiber_joints',
            'ant_fiber_odfs', 'ant_capacity_daily', 'ant_capacity_items'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
