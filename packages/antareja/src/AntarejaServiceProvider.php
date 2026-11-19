<?php

namespace Antareja;

use Antareja\Console\SyncRolesCommand;
use Antareja\Support\ModuleRegistry;
use Antareja\Support\RoleBlueprints;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AntarejaServiceProvider extends ServiceProvider
{
    public const VERSION = '0.1.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/antareja.php', 'antareja');
        $this->app->singleton(ModuleRegistry::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'antareja');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([SyncRolesCommand::class]);
        }

        // Menu ANTAREJA di navbar utama (disisipkan oleh patch kecil di layouts/menu.blade.php)
        View::composer('antareja::partials.navbar', function ($view): void {
            $view->with('antareja_modules', $this->app->make(ModuleRegistry::class)->menuFor(Auth::user()));
        });

        $this->registerPermissionLabels();
    }

    /**
     * Label untuk grup permission "ant-*" di editor role LibreNMS, tanpa mengubah lang/en/permissions.php.
     */
    private function registerPermissionLabels(): void
    {
        $this->app->booted(function (): void {
            $titles = [
                'ant-dashboard' => 'ANTAREJA · Dashboard',
                'ant-inventory' => 'ANTAREJA · Device Inventory',
                'ant-discovery' => 'ANTAREJA · Device Discovery',
                'ant-mapping' => 'ANTAREJA · Device Mapping',
                'ant-ipam' => 'ANTAREJA · IP Address Management',
                'ant-cctv' => 'ANTAREJA · CCTV Infrastructure',
                'ant-quality' => 'ANTAREJA · Network Quality',
                'ant-incident' => 'ANTAREJA · Incident Management',
                'ant-sla' => 'ANTAREJA · SLA Management',
                'ant-maintenance' => 'ANTAREJA · Maintenance',
                'ant-report' => 'ANTAREJA · Reports',
                'ant-audit' => 'ANTAREJA · Audit Log',
            ];
            $actions = [
                'technical' => 'Technical dashboard', 'management' => 'Management dashboard',
                'view' => 'View', 'update' => 'Create / edit', 'delete' => 'Delete', 'create' => 'Create',
                'approve' => 'Approve', 'verify' => 'Verify', 'close' => 'Close', 'export' => 'Export',
            ];

            // Pastikan file terjemahan asli sudah dimuat dulu agar tidak tertimpa.
            Lang::get('permissions', [], 'en');

            $lines = [];
            foreach (RoleBlueprints::PERMISSIONS as $group => $permissions) {
                $lines["permissions.$group.title"] = $titles[$group] ?? $group;
                foreach ($permissions as $permission) {
                    $action = substr($permission, strlen($group) + 1);
                    $label = $actions[$action] ?? ucfirst($action);
                    $lines["permissions.$group.$action"] = ['label' => $label, 'description' => $titles[$group] . ' — ' . $label];
                }
            }
            Lang::addLines($lines, 'en');
        });
    }
}
