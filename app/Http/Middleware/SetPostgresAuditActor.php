<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SetPostgresAuditActor
{
    /**
     * Propaga el ID del usuario autenticado hacia PostgreSQL 17 para auditoría en audit_events.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $connection = DB::connection();
        $shouldReset = false;

        if ($connection->getDriverName() === 'pgsql' && Auth::check()) {
            $connection->statement("SELECT set_config('app.current_user_id', ?, false)", [(string) Auth::id()]);
            $shouldReset = true;
        }

        try {
            return $next($request);
        } finally {
            if ($shouldReset) {
                try {
                    $connection->statement('RESET app.current_user_id');
                } catch (Throwable) {
                    // Evita ocultar excepciones de dominio cuando la transacción quedó abortada.
                }
            }
        }
    }
}
