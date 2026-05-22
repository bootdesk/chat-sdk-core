<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use Psr\EventDispatcher\StoppableEventInterface;

class ModalCloseEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly string $callbackId,
        public readonly Author $user,
        public readonly mixed $raw = null,
        public readonly ?string $viewId = null,
        public readonly ?Channel $relatedChannel = null,
        public readonly ?Thread $relatedThread = null,
        public readonly ?Message $relatedMessage = null,
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
