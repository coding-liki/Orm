<?php

namespace CodingLiki\Orm\Attributes\Relation\Exceptions;

class NotModelClassTypeException extends \Exception
{

    public function __construct(string $fieldName, string $fieldClass)
    {
        parent::__construct("Field `$fieldName` in class `$fieldClass` is not of BaseModel type");
    }
}