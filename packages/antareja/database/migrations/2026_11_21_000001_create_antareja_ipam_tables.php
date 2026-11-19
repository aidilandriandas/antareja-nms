<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ANTAREJA Fase 2 — IPAM. Alamat disimpan biner (VARBINARY 16) agar siap IPv4 & IPv6.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ant_vlans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->unsignedSmallInteger('vlan_number');
            $table->string('name', 64);
            $table->string('purpose', 16)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'vlan_number']);
        });

        Schema::create('ant_subnets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('family');
            $table->binary('network', 16);
            $table->unsignedTinyInteger('prefix');
            $table->string('cidr', 49)->unique();
            $table->string('name', 128);
            $table->string('purpose', 16)->nullable()->index();
            $table->foreignId('site_id')->nullable()->constrained('ant_sites')->nullOnDelete();
            $table->foreignId('vlan_id')->nullable()->constrained('ant_vlans')->nullOnDelete();
            $table->binary('gateway', 16)->nullable();
            $table->binary('dhcp_start', 16)->nullable();
            $table->binary('dhcp_end', 16)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['family', 'prefix']);
        });

        Schema::create('ant_ip_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subnet_id')->nullable()->constrained('ant_subnets')->nullOnDelete();
            $table->unsignedTinyInteger('family');
            $table->binary('address', 16);
            $table->string('address_text', 45)->unique();
            $table->string('status', 16)->index()->comment('used, static, reserved, dhcp');
            $table->string('hostname', 128)->nullable();
            $table->string('description', 255)->nullable();
            $table->string('mac_address', 17)->nullable()->index();
            $table->unsignedInteger('device_id')->nullable();
            $table->unsignedInteger('port_id')->nullable();
            $table->string('source', 16)->default('manual')->comment('manual, interface, arp, device');
            $table->boolean('conflict')->default(false)->index();
            $table->string('conflict_detail', 255)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->unsignedInteger('assigned_by')->nullable();
            $table->timestamps();

            $table->index('address');
            $table->foreign('device_id')->references('device_id')->on('devices')->nullOnDelete();
            $table->foreign('port_id')->references('port_id')->on('ports')->nullOnDelete();
            $table->foreign('assigned_by')->references('user_id')->on('users')->nullOnDelete();
        });

        Schema::create('ant_ip_history', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ip_address_id')->nullable()->index();
            $table->string('address_text', 45)->index();
            $table->string('event', 16);
            $table->string('description', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ant_ip_history');
        Schema::dropIfExists('ant_ip_addresses');
        Schema::dropIfExists('ant_subnets');
        Schema::dropIfExists('ant_vlans');
    }
};
