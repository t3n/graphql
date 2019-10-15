<?php

declare(strict_types=1);

namespace t3n\GraphQL;

interface ResolverGeneratorInterface
{
    /**
     * Should return a map with this structure:
     *
     * return [
     *    ['typeName' => \Resolver\Class\Name]
     * ];
     *
     * @return mixed[]
     */
    public function generate(): array;
}
