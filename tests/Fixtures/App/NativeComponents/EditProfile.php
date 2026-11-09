<?php

namespace App\NativeComponents;

use App\Models\User;

final class EditProfile
{
    public function save(User $user): void
    {
        $user->save();
    }
}
