<?php

namespace Antareja\Http\Controllers;

use Antareja\Services\AuditLogger;
use Antareja\Support\SetupToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Setup Awal ANTAREJA: membuat admin pertama lewat browser (pengganti wizard instalasi LibreNMS).
 * Database, migrasi, dan konfigurasi sudah disiapkan otomatis oleh container, jadi yang tersisa hanya akun admin.
 */
class SetupController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (! SetupToken::needed()) {
            return redirect()->route('login');
        }
        SetupToken::current(); // pastikan kode sudah ada di server

        return view('antareja::setup.index');
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        if (! SetupToken::needed()) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:32'],
            'realname' => ['required', 'string', 'max:64'],
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'email' => ['nullable', 'email', 'max:128'],
            'password' => ['required', 'string', 'min:10', 'max:128', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'confirmed'],
        ], [
            'required' => 'Kolom :attribute wajib diisi.',
            'max' => 'Kolom :attribute terlalu panjang (maksimal :max karakter).',
            'username.min' => 'Username minimal 3 karakter.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan tanda minus.',
            'email.email' => 'Format email tidak valid.',
            'password.min' => 'Password minimal 10 karakter.',
            'password.regex' => 'Password harus berisi huruf dan angka.',
            'password.confirmed' => 'Ulangi password tidak sama.',
        ], [
            'token' => 'kode setup', 'realname' => 'nama lengkap', 'username' => 'username',
            'email' => 'email', 'password' => 'password',
        ]);

        if (! SetupToken::verify($data['token'])) {
            $audit->log('setup_failed', 'user', 'Setup awal: kode setup salah', username: mb_substr($data['username'], 0, 64));

            throw ValidationException::withMessages(['token' => 'Kode setup salah. Lihat kode di server: ./antareja.sh setup-token']);
        }

        // Kunci agar dua permintaan bersamaan tidak sama-sama membuat admin "pertama"
        $user = Cache::lock('antareja:first-run-setup', 10)->block(5, function () use ($data): ?User {
            if (! SetupToken::needed()) {
                return null;
            }
            $user = new User([
                'username' => $data['username'],
                'realname' => $data['realname'],
                'email' => $data['email'] ?? null,
                'descr' => 'Admin (Setup Awal)',
                'auth_type' => 'mysql',
            ]);
            $user->setPassword($data['password']);
            $user->save();
            $user->assignRole('admin');
            $user->save();

            return $user;
        });

        if (! $user) {
            return redirect()->route('login');
        }

        SetupToken::clear();
        Auth::login($user);
        $request->session()->regenerate();
        $audit->log('setup', $user, 'Setup awal selesai: admin pertama dibuat', user: $user);

        return redirect()->route('antareja.home')
            ->with('antareja_success', 'Selamat datang di ANTAREJA NMS. Langkah berikutnya: tambahkan Site & Area, lalu perangkat.');
    }
}
