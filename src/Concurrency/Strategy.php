<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Concurrency;

enum Strategy: string
{
    case Drop = 'drop';
    case Queue = 'queue';
    case Debounce = 'debounce';
    case Concurrent = 'concurrent';
}
