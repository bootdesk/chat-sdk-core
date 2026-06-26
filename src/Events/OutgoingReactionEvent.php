<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Events;

use Psr\EventDispatcher\StoppableEventInterface;

final readonly class OutgoingReactionEvent implements StoppableEventInterface
{
    public function __construct(
        public string $threadId,
        public string $messageId,
        public string $emoji,
        public bool $added = true,
        public string $rawEmoji = '',
    ) {}

    public function isPropagationStopped(): bool
    {
        return false;
    }
}
