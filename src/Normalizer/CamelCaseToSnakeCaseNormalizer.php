<?php
namespace CodingLiki\Orm\Normalizer;

class CamelCaseToSnakeCaseNormalizer implements StringNormalizerInterface
{
    public function normalize(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}