<?php

namespace Antareja\Http\Controllers;

use Antareja\AntarejaServiceProvider;
use LibreNMS\Util\Version;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __invoke(\Antareja\Support\ModuleRegistry $registry): View
    {
        $modules = $registry->all();

        return view('antareja::pages.about', [
            'modules' => $modules,
            'counts' => $modules->countBy('status'),
            'version' => AntarejaServiceProvider::VERSION,
            'librenms_version' => Version::VERSION,
            'system' => $this->system(),
        ]);
    }

    /** Info sistem (pengganti halaman /about bawaan). Gagal baca satu komponen tidak menggagalkan halaman. */
    private function system(): array
    {
        $v = app(Version::class);
        $rows = ['ANTAREJA NMS' => AntarejaServiceProvider::VERSION, 'Engine monitoring' => Version::VERSION, 'PHP' => PHP_VERSION, 'Laravel' => app()->version()];
        foreach (['Database' => 'databaseServer', 'RRDtool' => 'rrdtool', 'Net-SNMP' => 'netSnmp', 'Python' => 'python', 'Sistem operasi' => 'os'] as $label => $m) {
            try {
                $rows[$label] = $v->{$m}() ?: '—';
            } catch (\Throwable) {
                $rows[$label] = '—';
            }
        }

        return $rows;
    }
}
