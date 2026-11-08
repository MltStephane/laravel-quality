<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

final class RoleChecks
{
    public function check(User $user, string $role): void
    {
        $user->hasRole('admin');
        $user->hasAnyRole([Role::Admin->value, 'editor']);
        $user->assignRole('editor', Role::Admin->value);

        $user->hasRole(Role::Admin->value);
        $user->hasAnyRole([Role::Admin->value, Role::Editor->value]);
        $user->assignRole($role);
    }
}
