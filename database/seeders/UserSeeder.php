<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([Role::Medico, Role::Recepcion] as $role) {
            /** @var array{name: string|null, email: string|null, password: string|null} $data */
            $data = config("medical.seed_users.{$role->value}");

            if (blank($data['email'])) {
                $this->command->warn("Sin SEED_".strtoupper($role->value)."_EMAIL en .env: no se creó el usuario {$role->label()}.");

                continue;
            }

            $user = User::query()->where('email', Str::lower($data['email']))->first();

            if ($user === null) {
                $password = filled($data['password']) ? $data['password'] : Str::password(16);

                $user = User::query()->create([
                    'name' => $data['name'] ?? $role->label(),
                    'email' => Str::lower($data['email']),
                    'password' => $password,
                    'is_active' => true,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                if (blank($data['password'])) {
                    $this->command->warn("Contraseña temporal de {$user->email}: {$password}  (cámbiala al entrar)");
                } else {
                    $this->command->info("Usuario {$role->label()} creado: {$user->email}");
                }
            }

            $user->syncRoles([$role->value]);
        }
    }
}
