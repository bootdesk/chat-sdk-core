<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\AdapterResolver;
use Psr\Http\Message\ServerRequestInterface;

class MockAdapterResolver implements AdapterResolver
{
    public function resolve(string $name, ?ServerRequestInterface $request): ?Adapter
    {
        return new MockAdapter;
    }
}
