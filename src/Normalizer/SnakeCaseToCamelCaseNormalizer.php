<?php
namespace CodingLiki\Orm\Normalizer;

class SnakeCaseToCamelCaseNormalizer implements StringNormalizerInterface
{
    public function normalize(string $input): string
    {
        return str_replace('_', '', ucwords($input, '_'));
    }
}