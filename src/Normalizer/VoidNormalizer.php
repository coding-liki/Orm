<?php

namespace CodingLiki\Orm\Normalizer;

class VoidNormalizer implements StringNormalizerInterface
{

    public function normalize(string $input): string
    {
        return $input;
    }
}