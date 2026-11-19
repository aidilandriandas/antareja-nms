<?php

use Antareja\Http\Controllers\AboutController;
use Antareja\Http\Controllers\AttachmentController;
use Antareja\Http\Controllers\AuditLogController;
use Antareja\Http\Controllers\DiscoveryController;
use Antareja\Http\Controllers\HomeController;
use Antareja\Http\Controllers\InventoryController;
use Antareja\Http\Controllers\MappingController;
use Antareja\Http\Controllers\SiteController;
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

        // Audit log
        Route::get('audit', [AuditLogController::class, 'index'])->middleware('can:ant-audit.view')->name('audit.index');
    });
