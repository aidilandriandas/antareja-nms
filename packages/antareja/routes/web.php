<?php

use Antareja\Http\Controllers\AboutController;
use Antareja\Http\Controllers\AttachmentController;
use Antareja\Http\Controllers\AuditLogController;
use Antareja\Http\Controllers\CctvController;
use Antareja\Http\Controllers\DiscoveryController;
use Antareja\Http\Controllers\EscalationController;
use Antareja\Http\Controllers\HomeController;
use Antareja\Http\Controllers\IncidentController;
use Antareja\Http\Controllers\InventoryController;
use Antareja\Http\Controllers\IpamController;
use Antareja\Http\Controllers\MaintenanceController;
use Antareja\Http\Controllers\MappingController;
use Antareja\Http\Controllers\ProblemController;
use Antareja\Http\Controllers\QualityController;
use Antareja\Http\Controllers\SiteController;
use Antareja\Http\Controllers\WirelessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('antareja')
    ->name('antareja.')
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('home');
        Route::get('about', AboutController::class)->name('about');

        // Site & Area
        Route::middleware('can:ant-site.view')->group(function (): void {
            Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
            Route::get('sites/{site}', [SiteController::class, 'show'])->whereNumber('site')->name('sites.show');
        });
        Route::middleware('can:ant-site.update')->group(function (): void {
            Route::get('sites/create', [SiteController::class, 'create'])->name('sites.create');
            Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
            Route::get('sites/{site}/edit', [SiteController::class, 'edit'])->name('sites.edit');
            Route::put('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
            Route::delete('sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
            Route::post('sites/{site}/areas', [SiteController::class, 'storeArea'])->name('sites.areas.store');
            Route::delete('sites/{site}/areas/{area}', [SiteController::class, 'destroyArea'])->scopeBindings()->name('sites.areas.destroy');
        });

        // Device Inventory
        Route::middleware('can:ant-inventory.view')->group(function (): void {
            Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
            Route::get('inventory/{device}', [InventoryController::class, 'show'])->name('inventory.show');
            Route::get('attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
            Route::get('api/devices/{device}/ports', [MappingController::class, 'ports'])->name('api.device-ports');
        });
        Route::middleware('can:ant-inventory.update')->group(function (): void {
            Route::get('inventory/{device}/edit', [InventoryController::class, 'edit'])->name('inventory.edit');
            Route::put('inventory/{device}', [InventoryController::class, 'update'])->name('inventory.update');
            Route::post('inventory/{device}/attachments', [AttachmentController::class, 'store'])->name('inventory.attachments.store');
            Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        });

        // Device Discovery & verifikasi
        Route::middleware('can:ant-discovery.view')->group(function (): void {
            Route::get('discovery', [DiscoveryController::class, 'index'])->name('discovery.index');
        });
        Route::middleware('can:ant-discovery.approve')->group(function (): void {
            Route::post('discovery/{device}/verify', [DiscoveryController::class, 'verify'])->name('discovery.verify');
            Route::post('discovery/{device}/reject', [DiscoveryController::class, 'reject'])->name('discovery.reject');
            Route::post('discovery/{device}/retire', [DiscoveryController::class, 'retire'])->name('discovery.retire');
            Route::post('discovery/{device}/restore', [DiscoveryController::class, 'restore'])->name('discovery.restore');
            Route::post('discovery/bulk', [DiscoveryController::class, 'bulk'])->name('discovery.bulk');
            Route::post('discovery/sync', [DiscoveryController::class, 'sync'])->name('discovery.sync');
        });

        // Device Mapping
        Route::middleware('can:ant-mapping.view')->group(function (): void {
            Route::get('mapping', [MappingController::class, 'index'])->name('mapping.index');
        });
        Route::middleware('can:ant-mapping.update')->group(function (): void {
            Route::get('mapping/create', [MappingController::class, 'create'])->name('mapping.create');
            Route::post('mapping', [MappingController::class, 'store'])->name('mapping.store');
            Route::delete('mapping/{link}', [MappingController::class, 'destroy'])->name('mapping.destroy');
            Route::post('mapping/suggest', [MappingController::class, 'suggest'])->name('mapping.suggest');
        });
        Route::middleware('can:ant-mapping.verify')->group(function (): void {
            Route::post('mapping/{link}/verify', [MappingController::class, 'verify'])->name('mapping.verify');
            Route::post('mapping/{link}/reject', [MappingController::class, 'reject'])->name('mapping.reject');
        });

        // IP Address Management
        Route::middleware('can:ant-ipam.view')->group(function (): void {
            Route::get('ipam', [IpamController::class, 'index'])->name('ipam.index');
            Route::get('ipam/subnets/{subnet}', [IpamController::class, 'show'])->whereNumber('subnet')->name('ipam.subnets.show');
            Route::get('ipam/addresses/{ip}', [IpamController::class, 'editAddress'])->name('ipam.addresses.edit');
            Route::get('ipam/conflicts', [IpamController::class, 'conflicts'])->name('ipam.conflicts');
            Route::get('ipam/vlans', [IpamController::class, 'vlans'])->name('ipam.vlans');
        });
        Route::middleware('can:ant-ipam.update')->group(function (): void {
            Route::get('ipam/subnets/create', [IpamController::class, 'create'])->name('ipam.subnets.create');
            Route::post('ipam/subnets', [IpamController::class, 'store'])->name('ipam.subnets.store');
            Route::get('ipam/subnets/{subnet}/edit', [IpamController::class, 'edit'])->name('ipam.subnets.edit');
            Route::put('ipam/subnets/{subnet}', [IpamController::class, 'update'])->name('ipam.subnets.update');
            Route::delete('ipam/subnets/{subnet}', [IpamController::class, 'destroy'])->name('ipam.subnets.destroy');
            Route::post('ipam/subnets/{subnet}/allocate', [IpamController::class, 'allocate'])->name('ipam.subnets.allocate');
            Route::put('ipam/addresses/{ip}', [IpamController::class, 'updateAddress'])->name('ipam.addresses.update');
            Route::delete('ipam/addresses/{ip}', [IpamController::class, 'releaseAddress'])->name('ipam.addresses.release');
            Route::get('ipam/import', [IpamController::class, 'importForm'])->name('ipam.import');
            Route::post('ipam/import', [IpamController::class, 'import'])->name('ipam.import.store');
            Route::post('ipam/sync', [IpamController::class, 'sync'])->name('ipam.sync');
            Route::post('ipam/vlans', [IpamController::class, 'storeVlan'])->name('ipam.vlans.store');
            Route::delete('ipam/vlans/{vlan}', [IpamController::class, 'destroyVlan'])->name('ipam.vlans.destroy');
        });

        // Network Quality
        Route::middleware('can:ant-quality.view')->group(function (): void {
            Route::get('quality', [QualityController::class, 'index'])->name('quality.index');
            Route::get('quality/targets/{target}', [QualityController::class, 'show'])->whereNumber('target')->name('quality.show');
        });
        Route::middleware('can:ant-quality.update')->group(function (): void {
            Route::get('quality/targets/create', [QualityController::class, 'create'])->name('quality.create');
            Route::post('quality/targets', [QualityController::class, 'store'])->name('quality.store');
            Route::get('quality/targets/{target}/edit', [QualityController::class, 'edit'])->name('quality.edit');
            Route::put('quality/targets/{target}', [QualityController::class, 'update'])->name('quality.update');
            Route::delete('quality/targets/{target}', [QualityController::class, 'destroy'])->name('quality.destroy');
            Route::post('quality/targets/{target}/measure', [QualityController::class, 'measure'])->middleware('throttle:10,1')->name('quality.measure');
            Route::get('quality/bulk', [QualityController::class, 'bulkForm'])->name('quality.bulk');
            Route::post('quality/bulk', [QualityController::class, 'bulkStore'])->name('quality.bulk.store');
        });

        // Wireless & CCTV
        Route::get('wireless', [WirelessController::class, 'index'])->middleware('can:ant-inventory.view')->name('wireless.index');
        Route::get('cctv', [CctvController::class, 'index'])->middleware('can:ant-cctv.view')->name('cctv.index');

        // Event & Alert Center (problem)
        Route::middleware('can:ant-problem.view')->group(function (): void {
            Route::get('problems', [ProblemController::class, 'index'])->name('problems.index');
            Route::get('problems/{problem}', [ProblemController::class, 'show'])->whereNumber('problem')->name('problems.show');
        });
        Route::middleware('can:ant-problem.acknowledge')->group(function (): void {
            Route::post('problems/{problem}/acknowledge', [ProblemController::class, 'acknowledge'])->name('problems.acknowledge');
            Route::post('problems/acknowledge', [ProblemController::class, 'bulkAcknowledge'])->name('problems.bulk-acknowledge');
            Route::post('problems/sync', [ProblemController::class, 'sync'])->middleware('throttle:6,1')->name('problems.sync');
        });
        Route::middleware('can:ant-incident.create')->group(function (): void {
            Route::post('problems/{problem}/incident', [ProblemController::class, 'createIncident'])->name('problems.incident');
            Route::post('problems/{problem}/link', [ProblemController::class, 'linkIncident'])->name('problems.link');
        });

        // Incident Management
        Route::middleware('can:ant-incident.view')->group(function (): void {
            Route::get('incidents', [IncidentController::class, 'index'])->name('incidents.index');
            Route::get('incidents/{incident}', [IncidentController::class, 'show'])->whereNumber('incident')->name('incidents.show');
        });
        Route::middleware('can:ant-incident.create')->group(function (): void {
            Route::get('incidents/create', [IncidentController::class, 'create'])->name('incidents.create');
            Route::post('incidents', [IncidentController::class, 'store'])->name('incidents.store');
        });
        Route::middleware('can:ant-incident.update')->group(function (): void {
            Route::put('incidents/{incident}', [IncidentController::class, 'update'])->name('incidents.update');
            Route::post('incidents/{incident}/transition', [IncidentController::class, 'transition'])->name('incidents.transition');
            Route::post('incidents/{incident}/notes', [IncidentController::class, 'note'])->name('incidents.note');
            Route::post('incidents/{incident}/assign', [IncidentController::class, 'assign'])->name('incidents.assign');
            Route::post('incidents/{incident}/attachments', [IncidentController::class, 'attach'])->name('incidents.attach');
        });

        // Eskalasi & notifikasi
        Route::get('escalation', [EscalationController::class, 'index'])->middleware('can:ant-escalation.view')->name('escalation.index');
        Route::middleware('can:ant-escalation.update')->group(function (): void {
            Route::get('escalation/create', [EscalationController::class, 'create'])->name('escalation.create');
            Route::post('escalation', [EscalationController::class, 'store'])->name('escalation.store');
            Route::get('escalation/{policy}/edit', [EscalationController::class, 'edit'])->name('escalation.edit');
            Route::put('escalation/{policy}', [EscalationController::class, 'update'])->name('escalation.update');
            Route::delete('escalation/{policy}', [EscalationController::class, 'destroy'])->name('escalation.destroy');
            Route::post('escalation/{policy}/test', [EscalationController::class, 'test'])->middleware('throttle:6,1')->name('escalation.test');
        });

        // Maintenance
        Route::middleware('can:ant-maintenance.view')->group(function (): void {
            Route::get('maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
            Route::get('maintenance/{window}', [MaintenanceController::class, 'show'])->whereNumber('window')->name('maintenance.show');
        });
        Route::middleware('can:ant-maintenance.update')->group(function (): void {
            Route::get('maintenance/create', [MaintenanceController::class, 'create'])->name('maintenance.create');
            Route::post('maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
            Route::get('maintenance/{window}/edit', [MaintenanceController::class, 'edit'])->name('maintenance.edit');
            Route::put('maintenance/{window}', [MaintenanceController::class, 'update'])->name('maintenance.update');
            Route::post('maintenance/{window}/complete', [MaintenanceController::class, 'complete'])->name('maintenance.complete');
            Route::post('maintenance/{window}/cancel', [MaintenanceController::class, 'cancel'])->name('maintenance.cancel');
            Route::post('maintenance/{window}/attachments', [MaintenanceController::class, 'attach'])->name('maintenance.attach');
        });

        // Audit log
        Route::get('audit', [AuditLogController::class, 'index'])->middleware('can:ant-audit.view')->name('audit.index');
    });
