<?php

namespace Antareja\Http\Middleware;

use Antareja\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman modul yang dimatikan admin tidak bisa dibuka. Tautan lama tetap aman:
 *  - Inventaris: /antareja/inventory/{id}[/edit] → Pengaturan Perangkat; daftar inventaris → daftar perangkat.
 *  - Modul lain: kembali ke Pusat Operasi dengan pesan.
 */
class ModuleGate
{
    public function __construct(private readonly ModuleRegistry $modules)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');
        if (! str_starts_with($path, 'antareja/') || ModuleRegistry::disabled() === []) {
            return $next($request);
        }

        foreach ($this->modules->all() as $m) {
            if ($m['path'] === null || ModuleRegistry::enabled($m['key']) || ! str_starts_with($m['path'], 'antareja/')) {
                continue;
            }
            $prefix = trim($m['path'], '/');
            if ($path !== $prefix && ! str_starts_with($path, $prefix . '/')) {
                continue;
            }
            if (! $request->isMethod('GET')) {
                abort(404);
            }
            if ($m['key'] === 'device-inventory') {
                if (preg_match('#^antareja/inventory/(\d+)(/edit)?$#', $path, $x)) {
                    return redirect()->route('antareja.devices.edit', (int) $x[1]);
                }

                return redirect()->route('devices');
            }

            return redirect()->route('antareja.home')
                ->with('antareja_warning', "Modul {$m['label']} sedang dinonaktifkan. Admin dapat menyalakannya di Administrasi → Modul Aktif.");
        }

        return $next($request);
    }
}
