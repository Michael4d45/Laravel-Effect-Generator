<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Tokens;

use Laravel\Surveyor\Analyzed\PropertyResult;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;

class PublicPropertyToken
{
    public function __construct(
        public PropertyResult $property,
        public null|TypeNode $phpDocType = null,
    ) {}
}
