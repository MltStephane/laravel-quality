<?php

namespace App\Livewire;

use Livewire\Attributes\Modelable;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Identifiers bound by wire:model: the browser writes them on purpose.
 */
final class PickRestaurant extends Component
{
    #[Validate('required|exists:restaurants,id')]
    public ?int $restaurantId = null;

    #[Url]
    public ?string $categoryId = null;

    #[Modelable]
    public ?int $patientId = null;

    public ?int $cityId = null;
}
