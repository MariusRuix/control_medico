<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UserStatusRequest;
use App\Models\User;
use App\Services\Users\UserDirectory;
use App\Services\Users\UserManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserManager $users) {}

    public function index(Request $request, UserDirectory $directory): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('settings/Users', [
            'users' => $directory->list($actor),
            'roles' => array_map(
                fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
                Role::cases(),
            ),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, role: string, password?: string|null} $data */
        $data = $request->validated();

        $result = $this->users->create($data);

        $this->flashCredentials($result['user'], $result['password']);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Usuario {$result['user']->name} creado."]);

        return to_route('settings.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        /** @var array{name: string, email: string, role: string} $data */
        $data = $request->validated();

        $this->users->update($user, $data, $this->actor($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario actualizado.']);

        return to_route('settings.users.index');
    }

    public function status(UserStatusRequest $request, User $user): RedirectResponse
    {
        $active = $request->boolean('active');

        $this->users->setActive($user, $active, $this->actor($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $active ? "{$user->name} puede volver a entrar." : "{$user->name} ya no puede entrar al sistema.",
        ]);

        return to_route('settings.users.index');
    }

    public function password(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $password = $request->validated('password');

        $generated = $this->users->resetPassword(
            $user,
            is_string($password) ? $password : null,
            $request->boolean('reset_second_factor'),
            $this->actor($request),
        );

        $this->flashCredentials($user, $generated);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Contraseña de {$user->name} restablecida."]);

        return to_route('settings.users.index');
    }

    private function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }

    /**
     * La contraseña temporal solo viaja como dato flash: se muestra una vez y
     * no queda en el historial del navegador.
     */
    private function flashCredentials(User $user, ?string $password): void
    {
        if ($password !== null) {
            Inertia::flash('credentials', ['name' => $user->name, 'email' => $user->email, 'password' => $password]);
        }
    }
}
