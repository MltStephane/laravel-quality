<?php

namespace Livewire\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Url
{
    public function __construct(mixed ...$arguments) {}
}
