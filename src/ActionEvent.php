<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Concerns\OpensModals;

class ActionEvent
{
    use OpensModals;

    public function __construct(
        public readonly string $actionId,
        public readonly ?string $value,
        public readonly string $messageId,
        public readonly ?string $triggerId,
        public readonly Thread $thread,
        public readonly Author $user,
        public readonly mixed $raw = null,
    ) {}
}
