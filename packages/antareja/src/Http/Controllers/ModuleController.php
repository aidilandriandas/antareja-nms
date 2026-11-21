<?php

namespace Antareja\Http\Controllers;

use Antareja\Services\AuditLogger;
use Antareja\Support\ModuleRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Admin menyalakan / mematikan modul opsional. Data modul yang dimatikan tetap tersimpan. */
class ModuleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.update');

        return view('antareja::modules.index', [
            'modules' => ModuleRegistry::TOGGLEABLE,
            'disabled' => ModuleRegistry::disabled(),
            'noc' => ModuleRegistry::NOC_DISABLED,
        ]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('settings.update');
        $enabled = (array) $request->input('enabled', []);
        if ($request->input('preset') === 'noc') {
            $disabled = ModuleRegistry::NOC_DISABLED;
        } elseif ($request->input('preset') === 'all') {
            $disabled = [];
        } else {
            $disabled = array_values(array_diff(array_keys(ModuleRegistry::TOGGLEABLE), $enabled));
        }
        $before = ModuleRegistry::disabled();
        ModuleRegistry::setDisabled($disabled);
        $audit->log('updated', 'modules', 'Modul aktif diubah', ['disabled' => $before], ['disabled' => $disabled]);

        return back()->with('antareja_success', 'Pengaturan modul disimpan. Menu di samping sudah menyesuaikan.');
    }
}
