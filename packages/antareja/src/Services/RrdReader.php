<?php

namespace Antareja\Services;

use LibreNMS\Config;
use LibreNMS\RRD\RrdPath;
use Symfony\Component\Process\Process;

/**
 * Membaca histori RRD yang sudah ditulis poller (lewat rrdcached bila dipakai) — tidak menulis apa pun.
 */
class RrdReader
{
    /**
     * @param  string[]  $nameParts  mis. ['port-id12'] atau ['storage', 'hrstorage', '/data']
     * @return array<int, array<string, ?float>> timestamp → [ds => nilai | null]
     */
    public function fetch(string $hostname, array $nameParts, string $cf, int $resolution, int $start, int $end): array
    {
        $path = (string) RrdPath::make($hostname, implode('-', $nameParts) . '.rrd');
        $cmd = ['rrdtool', 'fetch'];
        if ($daemon = Config::get('rrdcached')) {
            $cmd[] = '--daemon';
            $cmd[] = $daemon;
        }
        array_push($cmd, $path, $cf, '-r', (string) $resolution, '-s', (string) $start, '-e', (string) $end);

        $p = new Process($cmd, null, null, null, 30);
        $p->run();
        if (! $p->isSuccessful()) {
            return [];
        }

        $lines = preg_split('/\r?\n/', trim($p->getOutput()));
        $names = preg_split('/\s+/', trim(array_shift($lines) ?? ''));
        $out = [];
        foreach ($lines as $line) {
            if (! preg_match('/^(\d+):\s+(.*)$/', trim($line), $m)) {
                continue;
            }
            $vals = preg_split('/\s+/', trim($m[2]));
            $row = [];
            foreach ($names as $i => $n) {
                $v = $vals[$i] ?? 'nan';
                $row[$n] = is_numeric($v) ? (float) $v : null;
            }
            $out[(int) $m[1]] = $row;
        }

        return $out;
    }
}
