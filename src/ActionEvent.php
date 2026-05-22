<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Concerns\OpensModals;
use Psr\EventDispatcher\StoppableEventInterface;

class ActionEvent implements StoppableEventInterface
{
    use OpensModals;

    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $actionId,
        public readonly ?string $value,
        public readonly string $messageId,
        public readonly ?string $triggerId,
        public readonly Thread $thread,
        public readonly Author $user,
        public readonly mixed $raw = null,
        public readonly ?string $originId = null,
    ) {}

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
