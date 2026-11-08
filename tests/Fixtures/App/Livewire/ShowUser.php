<?php

namespace App\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;

final class ShowUser extends Component
{
    public int $id = 0;

    public ?string $teamId = null;

    public int|string $ownerId = 0;

    #[Locked]
    public int $userId = 0;

    public string $name = '';

    public array $tagId = [];

    protected int $postId = 0;

    public static int $counterId = 0;

    public $untypedId;

    public function __construct(public int $projectId = 0) {}
}
