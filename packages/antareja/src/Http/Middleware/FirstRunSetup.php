<?php

namespace Antareja\Http\Middleware;

use Antareja\Support\SetupToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selama belum ada user: halaman login (dan beranda) diarahkan ke Setup Awal ANTAREJA.
 * Hanya memeriksa database pada GET /, /login — halaman lain sudah dijaga middleware auth.
 */
class FirstRunSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->is('/', 'login') && SetupToken::needed()) {
            return redirect()->route('antareja.setup');
        }

        return $next($request);
    }
}
