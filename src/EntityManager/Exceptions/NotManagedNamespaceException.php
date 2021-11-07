<?php

namespace CodingLiki\Orm\EntityManager\Exceptions;

use Exception;

class NotManagedNamespaceException extends Exception
{
    public function __construct(string $modelClass)
    {
        parent::__construct("Class $modelClass is not managed by Entity Manager");
    }
}