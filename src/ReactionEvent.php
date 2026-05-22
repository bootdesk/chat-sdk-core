<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use Psr\EventDispatcher\StoppableEventInterface;

class ReactionEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $emoji,
        public readonly string $messageId,
        public readonly Thread $thread,
        public readonly Author $user,
        public readonly bool $added = true,
        public readonly string $rawEmoji = '',
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
