<?php

use Antareja\Http\Controllers\AboutController;
use Antareja\Http\Controllers\AnomalyController;
use Antareja\Http\Controllers\AssistantController;
use Antareja\Http\Controllers\AttachmentController;
use Antareja\Http\Controllers\AuditLogController;
use Antareja\Http\Controllers\BackupController;
use Antareja\Http\Controllers\DeviceAddController;
use Antareja\Http\Controllers\GroupController;
use Antareja\Http\Controllers\NocController;
use Antareja\Http\Controllers\TopologyController;
use Antareja\Http\Controllers\CapacityController;
use Antareja\Http\Controllers\CctvController;
use Antareja\Http\Controllers\CctvLiveController;
use Antareja\Http\Controllers\ChangeController;
use Antareja\Http\Controllers\ConfigController;
use Antareja\Http\Controllers\DiscoveryController;
use Antareja\Http\Controllers\EscalationController;
use Antareja\Http\Controllers\FiberController;
use Antareja\Http\Controllers\HomeController;
use Antareja\Http\Controllers\IncidentController;
use Antareja\Http\Controllers\InventoryController;
use Antareja\Http\Controllers\ManagementController;
use Antareja\Http\Controllers\IpamController;
use Antareja\Http\Controllers\MaintenanceController;
use Antareja\Http\Controllers\MappingController;
use Antareja\Http\Controllers\ProblemController;
use Antareja\Http\Controllers\QualityController;
use Antareja\Http\Controllers\ReportController;
use Antareja\Http\Controllers\SiteController;
use Antareja\Http\Controllers\SetupController;
use Antareja\Http\Controllers\SlaController;
use Antareja\Http\Controllers\TriggerController;
use Antareja\Http\Controllers\WirelessController;
use Antareja\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

// Setup Awal: membuat admin pertama (hanya selama belum ada user; dijaga kode setup dari server)
Route::middleware(['web', 'guest'])->group(function (): void {
    Route::get('setup', [SetupController::class, 'show'])->name('antareja.setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware('throttle:10,1')->name('antareja.setup.store');
});

Route::middleware(['web', 'auth'])
    ->prefix('antareja')
    ->name('antareja.')
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('home');
        Route::get('about', AboutController::class)->name('about');

        // Tambah perangkat (nama, grup, site, massal) — hak "device.create" dicek di controller
        Route::get('devices/add', [DeviceAddController::class, 'create'])->name('devices.add');
        Route::post('devices', [DeviceAddController::class, 'store'])->middleware('throttle:20,1')->name('devices.store');

        // Grup perangkat
        Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
        Route::get('groups/{group}', [GroupController::class, 'show'])->whereNumber('group')->name('groups.show');
        Route::post('groups/sync', [GroupController::class, 'sync'])->middleware('throttle:6,1')->name('groups.sync');

        // Peta topologi & layar NOC
        Route::get('topology', [TopologyController::class, 'index'])->name('topology.index');
        Route::get('topology/data', [TopologyController::class, 'data'])->name('topology.data');
        Route::post('topology/positions', [TopologyController::class, 'savePositions'])->name('topology.positions');
        Route::post('topology/positions/reset', [TopologyController::class, 'resetPositions'])->name('topology.positions.reset');
        Route::get('noc', [NocController::class, 'index'])->name('noc');
        Route::get('noc/data', [NocController::class, 'data'])->name('noc.data');

        // Backup & pemulihan NMS (admin)
        Route::get('backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('backup', [BackupController::class, 'run'])->middleware('throttle:6,1')->name('backup.run');
        Route::get('backup/status', [BackupController::class, 'status'])->name('backup.status');
        Route::put('backup/settings', [BackupController::class, 'settings'])->name('backup.settings');
        Route::post('backup/test', [BackupController::class, 'testSftp'])->middleware('throttle:6,1')->name('backup.test');
        Route::get('backup/{backup}/download', [BackupController::class, 'download'])->whereNumber('backup')->name('backup.download');
        Route::delete('backup/{backup}', [BackupController::class, 'destroy'])->whereNumber('backup')->name('backup.destroy');

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

        // Live view & snapshot CCTV (video lewat gateway internal; kredensial NVR tidak pernah ke browser)
        Route::middleware('can:ant-cctv.live')->group(function (): void {
            Route::get('cctv/live', [CctvLiveController::class, 'grid'])->name('cctv.live');
            Route::get('cctv/streams/{stream}/snapshot.jpg', [CctvLiveController::class, 'snapshot'])->middleware('throttle:240,1')->name('cctv.snapshot');
            Route::post('cctv/streams/{stream}/player', [CctvLiveController::class, 'player'])->middleware('throttle:30,1')->name('cctv.player');
            Route::get('cctv/streams/{stream}', [CctvLiveController::class, 'showStream'])->whereNumber('stream')->name('cctv.streams.show');
            Route::get('cctv/streams/{stream}/image/{which}', [CctvLiveController::class, 'image'])->name('cctv.streams.image');
        });
        Route::middleware('can:ant-cctv.update')->group(function (): void {
            Route::get('cctv/streams', [CctvLiveController::class, 'streams'])->name('cctv.streams');
            Route::post('cctv/streams', [CctvLiveController::class, 'storeStream'])->name('cctv.streams.store');
            Route::put('cctv/streams/{stream}', [CctvLiveController::class, 'updateStream'])->name('cctv.streams.update');
            Route::delete('cctv/streams/{stream}', [CctvLiveController::class, 'destroyStream'])->name('cctv.streams.destroy');
            Route::post('cctv/streams/{stream}/check', [CctvLiveController::class, 'check'])->middleware('throttle:20,1')->name('cctv.streams.check');
            Route::post('cctv/streams/{stream}/reference', [CctvLiveController::class, 'reference'])->name('cctv.streams.reference');
            Route::post('cctv/video-credentials', [CctvLiveController::class, 'storeCredential'])->name('cctv.credentials.store');
            Route::delete('cctv/video-credentials/{credential}', [CctvLiveController::class, 'destroyCredential'])->name('cctv.credentials.destroy');
        });

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

        // SLA & service tree
        Route::middleware('can:ant-sla.view')->group(function (): void {
            Route::get('sla', [SlaController::class, 'index'])->name('sla.index');
            Route::get('sla/devices', [SlaController::class, 'devices'])->name('sla.devices');
            Route::get('sla/{service}', [SlaController::class, 'show'])->whereNumber('service')->name('sla.show');
        });
        Route::middleware('can:ant-sla.update')->group(function (): void {
            Route::get('sla/create', [SlaController::class, 'create'])->name('sla.create');
            Route::post('sla', [SlaController::class, 'store'])->name('sla.store');
            Route::get('sla/{service}/edit', [SlaController::class, 'edit'])->name('sla.edit');
            Route::put('sla/{service}', [SlaController::class, 'update'])->name('sla.update');
            Route::delete('sla/{service}', [SlaController::class, 'destroy'])->name('sla.destroy');
        });

        // Trigger expression
        Route::middleware('can:ant-trigger.view')->group(function (): void {
            Route::get('triggers', [TriggerController::class, 'index'])->name('triggers.index');
            Route::get('triggers/{trigger}', [TriggerController::class, 'show'])->whereNumber('trigger')->name('triggers.show');
        });
        Route::middleware('can:ant-trigger.update')->group(function (): void {
            Route::get('triggers/create', [TriggerController::class, 'create'])->name('triggers.create');
            Route::post('triggers', [TriggerController::class, 'store'])->name('triggers.store');
            Route::get('triggers/{trigger}/edit', [TriggerController::class, 'edit'])->name('triggers.edit');
            Route::put('triggers/{trigger}', [TriggerController::class, 'update'])->name('triggers.update');
            Route::delete('triggers/{trigger}', [TriggerController::class, 'destroy'])->name('triggers.destroy');
            Route::post('triggers/{trigger}/evaluate', [TriggerController::class, 'evaluate'])->middleware('throttle:20,1')->name('triggers.evaluate');
        });

        // Dashboard manajemen & laporan
        Route::get('management', ManagementController::class)->middleware('can:ant-dashboard.management')->name('management');
        Route::middleware('can:ant-report.view')->group(function (): void {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
            Route::get('reports/monthly/export', [ReportController::class, 'export'])->middleware('can:ant-report.export')->name('reports.export');
        });

        // Work order
        Route::middleware('can:ant-workorder.view')->group(function (): void {
            Route::get('workorders', [WorkOrderController::class, 'index'])->name('workorders.index');
            Route::get('workorders/{workorder}', [WorkOrderController::class, 'show'])->whereNumber('workorder')->name('workorders.show');
        });
        Route::middleware('can:ant-workorder.update')->group(function (): void {
            Route::get('workorders/create', [WorkOrderController::class, 'create'])->name('workorders.create');
            Route::post('workorders', [WorkOrderController::class, 'store'])->name('workorders.store');
            Route::get('workorders/{workorder}/edit', [WorkOrderController::class, 'edit'])->name('workorders.edit');
            Route::put('workorders/{workorder}', [WorkOrderController::class, 'update'])->name('workorders.update');
            Route::post('workorders/{workorder}/status', [WorkOrderController::class, 'transition'])->name('workorders.transition');
            Route::post('workorders/{workorder}/assign', [WorkOrderController::class, 'assign'])->name('workorders.assign');
            Route::post('workorders/{workorder}/checklist', [WorkOrderController::class, 'checklist'])->name('workorders.checklist');
            Route::post('workorders/{workorder}/materials', [WorkOrderController::class, 'material'])->name('workorders.material');
            Route::delete('workorders/{workorder}/materials/{material}', [WorkOrderController::class, 'removeMaterial'])->whereNumber('material')->name('workorders.material.destroy');
            Route::post('workorders/{workorder}/notes', [WorkOrderController::class, 'note'])->name('workorders.note');
            Route::post('workorders/{workorder}/attachments', [WorkOrderController::class, 'attach'])->name('workorders.attach');
        });

        // Change request
        Route::middleware('can:ant-change.view')->group(function (): void {
            Route::get('changes', [ChangeController::class, 'index'])->name('changes.index');
            Route::get('changes/{change}', [ChangeController::class, 'show'])->whereNumber('change')->name('changes.show');
            // aksi persetujuan dicek per-aksi (ant-change.approve) di controller
            Route::post('changes/{change}/action', [ChangeController::class, 'action'])->name('changes.action');
        });
        Route::middleware('can:ant-change.update')->group(function (): void {
            Route::get('changes/create', [ChangeController::class, 'create'])->name('changes.create');
            Route::post('changes', [ChangeController::class, 'store'])->name('changes.store');
            Route::get('changes/{change}/edit', [ChangeController::class, 'edit'])->name('changes.edit');
            Route::put('changes/{change}', [ChangeController::class, 'update'])->name('changes.update');
        });

        // Backup konfigurasi
        Route::middleware('can:ant-config.view')->group(function (): void {
            Route::get('config', [ConfigController::class, 'index'])->name('config.index');
            Route::get('config/device/{device}', [ConfigController::class, 'device'])->name('config.device');
            Route::get('config/version/{version}/download', [ConfigController::class, 'download'])->name('config.download');
        });
        Route::middleware('can:ant-config.update')->group(function (): void {
            Route::post('config/targets', [ConfigController::class, 'storeTargets'])->name('config.targets.store');
            Route::put('config/device/{device}', [ConfigController::class, 'updateTarget'])->name('config.targets.update');
            Route::delete('config/device/{device}', [ConfigController::class, 'destroyTarget'])->name('config.targets.destroy');
            Route::post('config/device/{device}/collect', [ConfigController::class, 'collect'])->middleware('throttle:10,1')->name('config.collect');
            Route::post('config/device/{device}/acknowledge', [ConfigController::class, 'acknowledge'])->name('config.acknowledge');
            Route::post('config/credentials', [ConfigController::class, 'storeCredential'])->name('config.credentials.store');
            Route::put('config/credentials/{credential}', [ConfigController::class, 'updateCredential'])->name('config.credentials.update');
            Route::delete('config/credentials/{credential}', [ConfigController::class, 'destroyCredential'])->name('config.credentials.destroy');
        });

        // Capacity planning
        Route::middleware('can:ant-capacity.view')->group(function (): void {
            Route::get('capacity', [CapacityController::class, 'index'])->name('capacity.index');
            Route::get('capacity/{item}', [CapacityController::class, 'show'])->whereNumber('item')->name('capacity.show');
        });
        Route::middleware('can:ant-capacity.update')->group(function (): void {
            Route::put('capacity/{item}', [CapacityController::class, 'update'])->name('capacity.update');
            Route::post('capacity/ports', [CapacityController::class, 'addPort'])->name('capacity.ports');
            Route::post('capacity/refresh', [CapacityController::class, 'refresh'])->middleware('throttle:4,1')->name('capacity.refresh');
        });

        // Fiber optic
        Route::middleware('can:ant-fiber.view')->group(function (): void {
            Route::get('fiber', [FiberController::class, 'index'])->name('fiber.index');
            Route::get('fiber/cables/{cable}', [FiberController::class, 'showCable'])->whereNumber('cable')->name('fiber.cables.show');
            Route::get('fiber/odfs/{odf}', [FiberController::class, 'showOdf'])->name('fiber.odfs.show');
            Route::get('fiber/joints/{joint}', [FiberController::class, 'showJoint'])->name('fiber.joints.show');
            Route::get('fiber/trace/{core}', [FiberController::class, 'trace'])->name('fiber.trace');
            Route::get('fiber/port/{port}', [FiberController::class, 'traceForPort'])->whereNumber('port')->name('fiber.port');
        });
        Route::middleware('can:ant-fiber.update')->group(function (): void {
            Route::get('fiber/cables/create', [FiberController::class, 'createCable'])->name('fiber.cables.create');
            Route::post('fiber/cables', [FiberController::class, 'storeCable'])->name('fiber.cables.store');
            Route::get('fiber/cables/{cable}/edit', [FiberController::class, 'editCable'])->name('fiber.cables.edit');
            Route::put('fiber/cables/{cable}', [FiberController::class, 'updateCable'])->name('fiber.cables.update');
            Route::put('fiber/cores/{core}', [FiberController::class, 'updateCore'])->name('fiber.cores.update');
            Route::post('fiber/cores/{core}/tests', [FiberController::class, 'storeTest'])->name('fiber.cores.tests');
            Route::post('fiber/odfs', [FiberController::class, 'storeOdf'])->name('fiber.odfs.store');
            Route::put('fiber/odfs/{odf}', [FiberController::class, 'updateOdf'])->name('fiber.odfs.update');
            Route::put('fiber/odf-ports/{port}', [FiberController::class, 'updateOdfPort'])->name('fiber.odf-ports.update');
            Route::get('fiber/device-ports/{device}', [FiberController::class, 'devicePorts'])->name('fiber.device-ports');
            Route::post('fiber/joints', [FiberController::class, 'storeJoint'])->name('fiber.joints.store');
            Route::put('fiber/joints/{joint}', [FiberController::class, 'updateJoint'])->name('fiber.joints.update');
            Route::post('fiber/joints/{joint}/splices', [FiberController::class, 'storeSplice'])->name('fiber.splices.store');
            Route::delete('fiber/splices/{splice}', [FiberController::class, 'destroySplice'])->name('fiber.splices.destroy');
        });

        // Asisten AI (opsional) & anomali
        Route::get('anomalies', [AnomalyController::class, 'index'])->middleware('can:ant-problem.view')->name('anomalies.index');
        Route::middleware('can:ant-ai.use')->group(function (): void {
            Route::get('assistant', [AssistantController::class, 'index'])->name('assistant.index');
            Route::get('assistant/reports/{report}', [AssistantController::class, 'show'])->name('assistant.show');
            Route::post('assistant/shift', [AssistantController::class, 'shift'])->middleware('throttle:10,1')->name('assistant.shift');
            Route::post('assistant/incidents/{incident}', [AssistantController::class, 'incident'])->middleware(['can:ant-incident.view', 'throttle:10,1'])->name('assistant.incident');
        });

        // Audit log
        Route::get('audit', [AuditLogController::class, 'index'])->middleware('can:ant-audit.view')->name('audit.index');
    });

// Dipanggil Nginx (auth_request) sebelum meneruskan WebSocket video; mengembalikan 204/401/403, bukan redirect login.
Route::middleware('web')->get('antareja/cctv/live-auth', [CctvLiveController::class, 'liveAuth'])->name('antareja.cctv.live-auth');
