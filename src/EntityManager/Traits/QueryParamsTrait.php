<?php

namespace CodingLiki\Orm\EntityManager\Traits;

trait QueryParamsTrait
{
    private array $usedParamsNames = [];
    private array $usedParams = [];

    public function addParam(string $name, $value): string
    {
        if (!isset($this->usedParamsNames[$name])) {
            $this->usedParamsNames[$name] = 0;
        } else {
            $this->usedParamsNames[$name]++;
        }

        $realName = ":" . $name . $this->usedParamsNames[$name];

        $this->usedParams[$realName] = $value;

        return $realName;
    }

    public function resetParams(): void
    {
        $this->usedParams = [];
        $this->usedParamsNames = [];
    }
}