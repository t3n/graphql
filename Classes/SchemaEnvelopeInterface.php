<?php

declare(strict_types=1);

namespace t3n\GraphQL;

use GraphQL\Type\Schema;

interface SchemaEnvelopeInterface
{
    public function getSchema(): Schema;
}
