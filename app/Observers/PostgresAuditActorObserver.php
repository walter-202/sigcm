<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class PostgresAuditActorObserver
{
    public function creating(Model $model): void
    {
        $this->syncActor($model);
    }

    public function updating(Model $model): void
    {
        $this->syncActor($model);
    }

    public function deleting(Model $model): void
    {
        $this->syncActor($model);
    }

    private function syncActor(Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        $connection = $model->getConnection();
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            $isLocal = $connection->transactionLevel() > 0;
            $connection->statement(
                "SELECT set_config('app.current_user_id', ?, ?)",
                [(string) Auth::id(), $isLocal]
            );
        } catch (Throwable) {
            // No interrumpe si la sesión actual no permite ajustar la configuración.
        }
    }
}
