<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Al desactivar un usuario se revocan todos sus tokens de API.
     */
    public function updated(User $user): void
    {
        if ($user->wasChanged('estado') && ! $user->estado) {
            $user->tokens()->delete();
        }
    }
}
