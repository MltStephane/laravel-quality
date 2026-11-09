<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\PlainObject;
use Livewire\Component;

final class ListUsers extends Component
{
    public ?User $user = null;

    public function render(): void
    {
        User::query()->where('active', true)->get();
        User::find(1);
        User::where('active', true)->first();
        User::whereKey([1, 2])->get();
        User::all();
        User::with('posts')->paginate();
        User::latest()->first();

        // Not reported: reads through a relation of a loaded model, a static call on another class.
        $this->user?->posts()->get();
        PlainObject::find();
    }
}
