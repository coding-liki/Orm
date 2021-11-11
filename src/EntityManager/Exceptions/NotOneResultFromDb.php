<?php

namespace CodingLiki\Orm\EntityManager\Exceptions;

use Exception;

class NotOneResultFromDb extends Exception
{
    public function __construct(string $rawQuery)
    {
        parent::__construct('Db returns more than one result for query `' . $rawQuery . '`');
    }
}