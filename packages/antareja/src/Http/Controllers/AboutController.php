<?php

namespace Antareja\Http\Controllers;

use Antareja\AntarejaServiceProvider;
use LibreNMS\Util\Version;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AboutController extends Controller
{
    public function __invoke(): View
    {
        return view('antareja::pages.about', [
            'version' => AntarejaServiceProvider::VERSION,
            'librenms_version' => Version::VERSION,
        ]);
    }
}
