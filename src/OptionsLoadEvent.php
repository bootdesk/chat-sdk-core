<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use Psr\EventDispatcher\StoppableEventInterface;

class OptionsLoadEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(
        public readonly Adapter $adapter,
        public readonly string $actionId,
        public readonly string $query,
        public readonly Author $user,
        public readonly mixed $raw = null,
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
