<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Str;

/**
 * Registra en la bitácora los inicios y cierres de sesión, y los intentos
 * fallidos (NOM-024-SSA3-2012: control de acceso al expediente).
 */
class LogAuthenticationEvents
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handleLogin(Login $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('login', $event->user, [], ['remember' => $event->remember], $event->user);
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->audit->log('logout', $event->user, [], [], $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $email = Str::limit(Str::lower((string) ($event->credentials['email'] ?? '')), 120, '');

        $this->audit->log(
            'login_failed',
            $event->user instanceof User ? $event->user : null,
            [],
            ['email' => $email],
        );
    }
}
