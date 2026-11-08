<?php

namespace App\Actions;

use App\Models\User;

final class CreateUser
{
    public function handle(string $name): User
    {
        $user = User::query()->create(['name' => $name]);
        $user->save();

        return $user;
    }
}
