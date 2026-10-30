<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Altas, cambios, activación y restablecimiento de contraseña de las cuentas
 * del personal. Las cuentas nunca se borran: se desactivan para conservar la
 * autoría de las notas, recetas y la bitácora.
 */
final class UserManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, role: string, password?: string|null}  $data
     * @return array{user: User, password: string|null} password = contraseña temporal generada (si no se indicó una)
     */
    public function create(array $data): array
    {
        $generated = blank($data['password'] ?? null) ? $this->temporaryPassword() : null;

        try {
            $user = DB::transaction(function () use ($data, $generated): User {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $generated ?? (string) $data['password'],
                    'is_active' => true,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
                $user->syncRoles([$data['role']]);

                return $user;
            });
        } catch (Throwable $e) {
            Log::error('No se pudo crear el usuario.', ['email' => $data['email'], 'error' => $e->getMessage()]);

            throw $e;
        }

        $this->audit->log('created', $user, [], [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $data['role'],
        ]);

        return ['user' => $user, 'password' => $generated];
    }

    /**
     * @param  array{name: string, email: string, role: string}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        $currentRole = $this->roleOf($user);
        $newRole = Role::from($data['role']);

        if ($newRole !== $currentRole) {
            if ($user->is($actor)) {
                throw ValidationException::withMessages(['role' => 'No puedes cambiar tu propio rol.']);
            }

            if ($currentRole === Role::Medico) {
                $this->ensureAnotherMedicoRemains($user, 'role');
            }
        }

        $old = ['name' => $user->name, 'email' => $user->email, 'role' => $currentRole?->value];

        try {
            DB::transaction(function () use ($user, $data, $newRole): void {
                $user->fill(['name' => $data['name'], 'email' => $data['email']])->save();
                $user->syncRoles([$newRole->value]);
            });
        } catch (Throwable $e) {
            Log::error('No se pudo actualizar el usuario.', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            throw $e;
        }

        $new = ['name' => $user->name, 'email' => $user->email, 'role' => $newRole->value];
        $changedKeys = array_keys(array_diff_assoc($new, $old));

        if ($changedKeys !== []) {
            $this->audit->log(
                'updated',
                $user,
                array_intersect_key($old, array_flip($changedKeys)),
                array_intersect_key($new, array_flip($changedKeys)),
            );
        }

        return $user;
    }

    public function setActive(User $user, bool $active, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['active' => 'No puedes desactivar tu propia cuenta.']);
        }

        if ($user->is_active === $active) {
            return;
        }

        if (! $active && $this->roleOf($user) === Role::Medico) {
            $this->ensureAnotherMedicoRemains($user, 'active');
        }

        try {
            // Un remember_token nuevo invalida las cookies de "recordarme".
            $user->forceFill(['is_active' => $active, 'remember_token' => Str::random(60)])->save();
        } catch (Throwable $e) {
            Log::error('No se pudo cambiar el estado del usuario.', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            throw $e;
        }

        if (! $active) {
            $this->endSessions($user);
        }

        $this->audit->log($active ? 'activated' : 'deactivated', $user);
    }

    /**
     * @return string|null contraseña temporal generada (null si se indicó una)
     */
    public function resetPassword(User $user, ?string $password, bool $resetSecondFactor, User $actor): ?string
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'password' => 'Cambia tu propia contraseña en Configuración → Seguridad.',
            ]);
        }

        $generated = blank($password) ? $this->temporaryPassword() : null;

        try {
            DB::transaction(function () use ($user, $password, $generated, $resetSecondFactor): void {
                $attributes = [
                    'password' => $generated ?? (string) $password,
                    'remember_token' => Str::random(60),
                ];

                if ($resetSecondFactor) {
                    $attributes += [
                        'two_factor_secret' => null,
                        'two_factor_recovery_codes' => null,
                        'two_factor_confirmed_at' => null,
                    ];
                    $user->passkeys()->delete();
                }

                $user->forceFill($attributes)->save();
            });
        } catch (Throwable $e) {
            Log::error('No se pudo restablecer la contraseña.', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            throw $e;
        }

        $this->endSessions($user);

        $this->audit->log('password_reset', $user, [], ['second_factor_reset' => $resetSecondFactor]);

        return $generated;
    }

    public function roleOf(User $user): ?Role
    {
        $name = $user->getRoleNames()->first();

        return is_string($name) ? Role::tryFrom($name) : null;
    }

    /**
     * Siempre debe quedar al menos un médico activo: es quien administra el sistema.
     */
    private function ensureAnotherMedicoRemains(User $user, string $field): void
    {
        $others = User::role(Role::Medico->value)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                $field => 'Debe quedar al menos un médico activo en el sistema.',
            ]);
        }
    }

    /**
     * Cierra las sesiones abiertas del usuario en otros equipos. Con otros
     * drivers de sesión, el middleware EnsureUserIsActive y el nuevo
     * remember_token cubren el caso.
     */
    private function endSessions(User $user): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        try {
            DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        } catch (Throwable $e) {
            Log::warning('No se pudieron cerrar las sesiones del usuario.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Contraseña temporal fácil de dictar (sin caracteres ambiguos como 0/O o 1/l/I),
     * p. ej. "Kfx7-Pq2m-Wz9r". Cumple la política de producción: mayúsculas,
     * minúsculas, números y símbolo (el guion).
     */
    private function temporaryPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;

        do {
            $groups = [];

            for ($g = 0; $g < 3; $g++) {
                $chunk = '';

                for ($c = 0; $c < 4; $c++) {
                    $chunk .= $alphabet[random_int(0, $max)];
                }

                $groups[] = $chunk;
            }

            $password = implode('-', $groups);
        } while (! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/\d/', $password));

        return $password;
    }
}
