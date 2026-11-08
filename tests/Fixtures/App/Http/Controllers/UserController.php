<?php

namespace App\Http\Controllers;

use App\Models\User;

final class UserController
{
    public function destroy(User $user): void
    {
        $user->delete();
        User::upsert([['name' => 'Ada']], ['name']);
    }
}
