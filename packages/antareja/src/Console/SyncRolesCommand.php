<?php

namespace Antareja\Console;

use Antareja\Support\RoleBlueprints;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncRolesCommand extends Command
{
    protected $signature = 'antareja:roles {--reset : Hapus permission tambahan pada role ANTAREJA yang tidak ada di blueprint}';

    protected $description = 'Membuat / menyinkronkan permission dan role awal ANTAREJA NMS';

    public function handle(): int
    {
        foreach (RoleBlueprints::allAntarejaPermissions() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RoleBlueprints::ROLES as $name => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            $role = Role::findOrCreate($name, 'web');
            if ($this->option('reset')) {
                $role->syncPermissions($permissions);
            } else {
                $role->givePermissionTo($permissions);
            }
            $this->info(sprintf('Role %-18s (%s): %d permission', $name, RoleBlueprints::LABELS[$name], count($permissions)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('ICT Admin memakai role bawaan "admin".');

        return self::SUCCESS;
    }
}
