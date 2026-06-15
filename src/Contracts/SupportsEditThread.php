<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\ThreadInfo;

interface SupportsEditThread
{
    public function editThread(string $threadId, ThreadInfo $threadInfo): ThreadInfo;
}
