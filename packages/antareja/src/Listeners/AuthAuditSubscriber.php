<?php

namespace Antareja\Listeners;

use Antareja\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * Catat login, logout, dan login gagal ke audit log ANTAREJA.
 */
class AuthAuditSubscriber
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handleLogin(Login $event): void
    {
        $this->audit->log('login', $event->user, 'Login berhasil', user: $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            $this->audit->log('logout', $event->user, 'Logout', user: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $username = (string) ($event->credentials['username'] ?? $event->credentials['email'] ?? '');
        $this->audit->log('login_failed', 'user', 'Login gagal untuk "' . mb_substr($username, 0, 64) . '"', username: mb_substr($username, 0, 64));
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }
}
