<?php

namespace Antareja;

use Antareja\Console\AnomaliesCommand;
use Antareja\Console\SchedulerStatusCommand;
use Antareja\Console\ArpRefreshCommand;
use Antareja\Console\BackupCommand;
use Antareja\Console\GroupsCommand;
use Antareja\Console\CapacityCommand;
use Antareja\Console\CctvHealthCommand;
use Antareja\Console\ConfigBackupCommand;
use Antareja\Console\IpamSyncCommand;
use Antareja\Console\ProblemsCommand;
use Antareja\Console\QualityMaintenanceCommand;
use Antareja\Console\QualityProbeCommand;
use Antareja\Console\SetupTokenCommand;
use Antareja\Console\SlaCommand;
use Antareja\Console\TriggersCommand;
use Antareja\Console\SuggestMappingsCommand;
use Antareja\Console\SyncInventoryCommand;
use Antareja\Console\SyncRolesCommand;
use Antareja\Http\Middleware\FirstRunSetup;
use Antareja\Listeners\AuthAuditSubscriber;
use Antareja\Plugins\DeviceOverview;
use Antareja\Services\AttachmentService;
use Antareja\Services\InventorySync;
use Antareja\Support\ModuleRegistry;
use Antareja\Support\RoleBlueprints;
use App\Models\Device;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use LibreNMS\Interfaces\Plugins\Hooks\DeviceOverviewHook;
use LibreNMS\Interfaces\Plugins\PluginManagerInterface;

class AntarejaServiceProvider extends ServiceProvider
{
    public const VERSION = '0.9.0';

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

        // Di balik Nginx ANTAREJA (TLS) semua tautan & action form wajib https:// — jangan bergantung pada deteksi
        // header proxy saja; bila gagal, browser memperingatkan "informasi tidak aman" dan login gagal.
        if (str_starts_with((string) config('antareja.public_url'), 'https://') && ! $this->app->runningInConsole()) {
            URL::forceScheme('https');
            $this->app['request']->server->set('HTTPS', 'on');
        }
        $this->app['router']->pushMiddlewareToGroup('web', FirstRunSetup::class);
        $this->app['router']->pushMiddlewareToGroup('web', \Antareja\Http\Middleware\ModuleGate::class);
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'antareja');
        // Komponen design system: <x-antareja::status>, <x-antareja::stat>, <x-antareja::progress>, <x-antareja::alert>
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components', 'antareja');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncRolesCommand::class, SyncInventoryCommand::class, SuggestMappingsCommand::class,
                IpamSyncCommand::class, QualityProbeCommand::class, QualityMaintenanceCommand::class, ProblemsCommand::class,
                SlaCommand::class, TriggersCommand::class, ConfigBackupCommand::class, CapacityCommand::class, AnomaliesCommand::class, CctvHealthCommand::class, SetupTokenCommand::class, BackupCommand::class, GroupsCommand::class, ArpRefreshCommand::class, SchedulerStatusCommand::class,
            ]);
        }

        // Kerangka aplikasi: data menu LibreNMS (pencarian, notifikasi, preferensi) + navigasi ANTAREJA
        View::composer('antareja::shell.chrome', \App\Http\ViewComposers\MenuComposer::class);
        View::composer('antareja::shell.chrome', \Antareja\View\ShellComposer::class);

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

    private function backupTime(): string
    {
        try {
            $t = \Illuminate\Support\Facades\DB::table('ant_backup_settings')->value('schedule_time');
        } catch (\Throwable) {
            $t = null; // tabel belum ada (sebelum migrasi)
        }

        return is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) ? $t : '01:30';
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
            $schedule->command('antareja:problems')->everyMinute()->withoutOverlapping(5)->runInBackground();
            $schedule->command('antareja:sla')->dailyAt('00:35')->withoutOverlapping()->runInBackground();
            $schedule->command('antareja:config-backup')->hourlyAt(41)->withoutOverlapping(120)->runInBackground()->when(fn () => ModuleRegistry::enabled('config'));
            $schedule->command('antareja:capacity')->cron('23 */6 * * *')->withoutOverlapping(120)->runInBackground()->when(fn () => ModuleRegistry::enabled('capacity'));
            $schedule->command('antareja:anomalies')->cron('*/15 * * * *')->withoutOverlapping(14)->runInBackground();
            $schedule->command('antareja:cctv-health')->cron('7,22,37,52 * * * *')->withoutOverlapping(14)->runInBackground();
            // Tabel ARP router/L3 dibaca ulang tiap 30 menit (discovery penuh LibreNMS hanya tiap 6 jam)
            $schedule->command('antareja:arp-refresh')->cron('11,41 * * * *')->withoutOverlapping(25)->runInBackground();
            $schedule->command('antareja:groups')->cron('*/10 * * * *')->withoutOverlapping(9)->runInBackground();
            // Backup NMS harian; jam diambil dari pengaturan backup (bawaan 01:30)
            $schedule->command('antareja:backup --scheduled')->dailyAt($this->backupTime())->withoutOverlapping(360)->runInBackground();
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
                'ant-problem' => 'ANTAREJA · Event & Alert Center',
                'ant-incident' => 'ANTAREJA · Incident Management',
                'ant-escalation' => 'ANTAREJA · Eskalasi & Notifikasi',
                'ant-sla' => 'ANTAREJA · SLA Management',
                'ant-trigger' => 'ANTAREJA · Trigger expression',
                'ant-workorder' => 'ANTAREJA · Work Order',
                'ant-change' => 'ANTAREJA · Change Request',
                'ant-config' => 'ANTAREJA · Backup Konfigurasi',
                'ant-capacity' => 'ANTAREJA · Capacity Planning',
                'ant-fiber' => 'ANTAREJA · Fiber Optic',
                'ant-ai' => 'ANTAREJA · Asisten AI',
                'ant-maintenance' => 'ANTAREJA · Maintenance',
                'ant-report' => 'ANTAREJA · Reports',
                'ant-audit' => 'ANTAREJA · Audit Log',
            ];
            $actions = [
                'technical' => 'Technical dashboard', 'management' => 'Management dashboard',
                'view' => 'View', 'update' => 'Create / edit', 'delete' => 'Delete', 'create' => 'Create',
                'approve' => 'Approve', 'use' => 'Use', 'live' => 'Live view', 'verify' => 'Verify', 'close' => 'Close', 'export' => 'Export', 'acknowledge' => 'Acknowledge',
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
