<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;

final class EditUser extends Component
{
    public ?User $user = null;

    public function save(): void
    {
        User::query()->where('active', true)->get();
        User::create(['name' => 'Ada']);
        User::firstOrCreate(['name' => 'Ada']);
        User::destroy(1);
        $this->user?->save();
        $this->user?->posts()->create(['title' => 'Draft']);
        User::query()->where('active', false)->update(['active' => true]);
        $this->user?->forceDelete();

        // Same method name on something that is not Eloquent.
        $this->save();
    }
}
