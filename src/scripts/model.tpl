<?php

namespace {{namespace}};

use CodingLiki\Orm\Attributes\ModelField;
use CodingLiki\Orm\BaseModel;
{{otherUses}}

class {{class}} extends BaseModel
{
    protected static ?string $queryClass = {{queryClass}}::class;
    protected ?string $tableName = '{{tableName}}';{{fields}}

    public static function find(): {{queryClass}}
    {
        return (new {{queryClass}})->find(static::class);
    }
    {{gettersAndSetters}}
}