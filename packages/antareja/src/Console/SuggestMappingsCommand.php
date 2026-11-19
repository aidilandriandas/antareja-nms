<?php

namespace Antareja\Console;

use Antareja\Services\MappingService;
use Illuminate\Console\Command;

class SuggestMappingsCommand extends Command
{
    protected $signature = 'antareja:suggest-mappings';

    protected $description = 'Buat saran device mapping dari LLDP/CDP dan tabel MAC (FDB/ARP) LibreNMS';

    public function handle(MappingService $mapping): int
    {
        $result = $mapping->generateSuggestions();
        $this->info(sprintf('Saran baru: %d dari LLDP/CDP, %d dari FDB/ARP.', $result['lldp'], $result['fdb']));

        return self::SUCCESS;
    }
}
