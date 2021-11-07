<?php

namespace CodingLiki\Orm\Attributes;

use Attribute;

#[Attribute]
class ModelField
{

    public string $modelFieldName = '';

    public function __construct(public ?string $fieldName = null, public bool $ignoreOnInsert = false)
    {
    }
}