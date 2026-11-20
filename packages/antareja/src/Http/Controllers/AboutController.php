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
        ]);
    }
}
