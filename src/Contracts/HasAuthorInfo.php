<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Author;

interface HasAuthorInfo
{
    public function getAuthorInfo(Author $author): Author;
}
