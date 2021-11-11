<?php

namespace CodingLiki\Orm\Exceptions;

use Throwable;

class NotModelFieldException extends \Exception
{
    public function __construct(string $modelClass, string $fieldName)
    {
        parent::__construct("$modelClass.$fieldName is not model field");
    }
}