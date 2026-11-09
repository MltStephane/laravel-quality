<?php

namespace Livewire\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Validate
{
    public function __construct(mixed ...$arguments) {}
}
