<?php

namespace Antareja;

use Antareja\Console\IpamSyncCommand;
use Antareja\Console\QualityMaintenanceCommand;
use Antareja\Console\QualityProbeCommand;
use Antareja\Console\SuggestMappingsCommand;
use Antareja\Console\SyncInventoryCommand;
use Antareja\Console\SyncRolesCommand;
use Antareja\Listeners\AuthAuditSubscriber;
use Antareja\Plugins\DeviceOverview;
use Antareja\Services\AttachmentService;
use Antareja\Services\InventorySync;
use Antareja\Support\ModuleRegistry;
use Antareja\Support\RoleBlueprints;
use App\Models\Device;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\DeviceOverviewHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

class AntarejaServiceProvider extends ServiceProvider
{
    public const VERSION = '0.3.0';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/antareja.php', 'antareja');
        $this->app->singleton(ModuleRegistry::class);

        // Disk untuk evidence: /data/antareja di container (ikut volume & backup)
        $this->app['config']->set('filesystems.disks.' . AttachmentService::DISK, [
            'driver' => 'local',
            'root' => AttachmentService::root(),
            'throw' => false,
        ]);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'antareja');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncRolesCommand::class, SyncInventoryCommand::class, SuggestMappingsCommand::class,
                IpamSyncCommand::class, QualityProbeCommand::class, QualityMaintenanceCommand::class,
            ]);
        }

        View::composer('antareja::partials.navbar', function ($view): void {
            $view->with('antareja_modules', $this->app->make(ModuleRegistry::class)->menuFor(Auth::user()));
        });

        Event::subscribe(AuthAuditSubscriber::class);

        // Device baru di LibreNMS (discovery / tambah manual) langsung masuk antrean verifikasi
        Device::created(function (Device $device): void {
            try {
                $this->app->make(InventorySync::class)->ensure($device);
            } catch (\Throwable $e) {
                Log::error('ANTAREJA: gagal membuat detail inventaris: ' . $e->getMessage());
            }
        });

        $this->registerSchedule();
        $this->registerPluginHooks();
        $this->registerPermissionLabels();
    }

    private function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            // Jaring pengaman bila ada device yang dibuat tanpa event Eloquent
            $schedule->command('antareja:sync-inventory')->everyFifteenMinutes()->withoutOverlapping()->runInBackground();
            $schedule->command('antareja:suggest-mappings')->hourlyAt(17)->withoutOverlapping()->runInBackground();
            $schedule->command('antareja:ipam-sync')->everyFifteenMinutes()->withoutOverlapping()->runInBackground();
            $schedule->command('antareja:quality-probe')->everyFiveMinutes()->withoutOverlapping(10)->runInBackground();
            $schedule->command('antareja:quality-maintenance')->dailyAt('00:20')->withoutOverlapping()->runInBackground();
        });
    }

    private function registerPluginHooks(): void
    {
        $this->app->booted(function (): void {
            try {
                $this->app->make(PluginManagerInterface::class)
                    ->publishHook('antareja', DeviceOverviewHook::class, DeviceOverview::class);
            } catch (\Throwable $e) {
                Log::error('ANTAREJA: gagal mendaftarkan hook device overview: ' . $e->getMessage());
            }
        });
    }

    /**
     * Label untuk grup permission "ant-*" di editor role LibreNMS, tanpa mengubah lang/en/permissions.php.
     */
    private function registerPermissionLabels(): void
    {
        $this->app->booted(function (): void {
            $titles = [
                'ant-dashboard' => 'ANTAREJA · Dashboard',
                'ant-site' => 'ANTAREJA · Site & Area',
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
                    $lines["permissions.$group.$action"] = ['label' => $label, 'description' => ($titles[$group] ?? $group) . ' — ' . $label];
                }
            }
            Lang::addLines($lines, 'en');
        });
    }
}
