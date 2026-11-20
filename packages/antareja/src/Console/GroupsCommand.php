<?php

namespace Antareja\Console;

use Antareja\Services\DeviceGroupSync;
use Illuminate\Console\Command;

class GroupsCommand extends Command
{
    protected $signature = 'antareja:groups';

    protected $description = 'Sinkronkan grup perangkat otomatis (per kategori) ke Device Group LibreNMS';

    public function handle(DeviceGroupSync $sync): int
    {
        $r = $sync->syncAll();
        $this->info("{$r['groups']} grup, {$r['members']} anggota.");

        return 0;
    }
}
