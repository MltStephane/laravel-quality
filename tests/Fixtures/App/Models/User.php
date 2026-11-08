<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class User extends Model
{
    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @param  string|array<int, string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        return $roles !== [];
    }

    /**
     * @param  string|array<int, string>  $roles
     */
    public function hasAnyRole(string|array $roles): bool
    {
        return $roles !== [];
    }

    public function assignRole(string ...$roles): self
    {
        return $this;
    }
}
