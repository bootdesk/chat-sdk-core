<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Author;

interface IdentityResolver
{
    public function resolve(Author $author): ?string;
}
