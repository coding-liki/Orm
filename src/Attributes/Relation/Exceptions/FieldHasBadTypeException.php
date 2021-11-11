<?php

namespace CodingLiki\Orm\Attributes\Relation\Exceptions;

use Throwable;

class FieldHasBadTypeException extends \Exception
{
    public function __construct(string $fieldName, string $fieldClass, string $needType)
    {
        parent::__construct("Field `$fieldName` in class `$fieldClass` is not of $needType type");
    }
}