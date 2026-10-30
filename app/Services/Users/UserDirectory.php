<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Listado de cuentas del personal para la pantalla de usuarios.
 */
final class UserDirectory
{
    public function __construct(private readonly UserManager $users) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(User $actor): array
    {
        $users = User::query()
            ->with('roles:id,name')
            ->withCount('passkeys')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $lastActivity = $this->lastActivity($users->modelKeys());

        return $users->map(function (User $user) use ($actor, $lastActivity): array {
            $role = $this->users->roleOf($user);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role?->value,
                'role_label' => $role?->label() ?? 'Sin rol',
                'is_active' => $user->is_active,
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'passkeys_count' => (int) $user->getAttribute('passkeys_count'),
                'last_activity_at' => $lastActivity[$user->id] ?? null,
                'created_at' => $user->created_at?->toIso8601String(),
                'is_self' => $user->is($actor),
            ];
        })->values()->all();
    }

    /**
     * Última actividad registrada en la tabla de sesiones (solo con SESSION_DRIVER=database).
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    private function lastActivity(array $ids): array
    {
        if (config('session.driver') !== 'database' || $ids === []) {
            return [];
        }

        try {
            return DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->whereIn('user_id', $ids)
                ->groupBy('user_id')
                ->selectRaw('user_id, MAX(last_activity) as last_activity')
                ->pluck('last_activity', 'user_id')
                ->map(fn (mixed $ts): string => Carbon::createFromTimestamp((int) $ts)->toIso8601String())
                ->all();
        } catch (Throwable $e) {
            Log::warning('No se pudo leer la actividad de las sesiones.', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
