<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Events;

use BootDesk\ChatSDK\Core\MessageContext;
use Psr\EventDispatcher\StoppableEventInterface;

final readonly class MentionEvent implements StoppableEventInterface
{
    public function __construct(
        public MessageContext $context,
    ) {}

    public function isPropagationStopped(): bool
    {
        return $this->context->isSkipped();
    }
}
