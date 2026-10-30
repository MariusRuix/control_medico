<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de una cuenta desactivada en su siguiente petición
 * (incluye sesiones abiertas con "recordarme" o con llave de acceso).
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Se compara contra false: un modelo recién creado en memoria aún no trae
        // el valor por defecto de la columna (true).
        if ($user instanceof User && $user->getAttribute('is_active') === false) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Tu cuenta está desactivada. Consulta con el médico responsable.');
        }

        return $next($request);
    }
}
