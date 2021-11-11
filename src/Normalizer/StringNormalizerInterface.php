<?php
namespace CodingLiki\Orm\Normalizer;

interface StringNormalizerInterface
{
    public function normalize(string $input): string;
}