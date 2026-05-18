<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface AdapterResolver
{
    public function resolve(string $name, ?ServerRequestInterface $request): ?Adapter;
}
