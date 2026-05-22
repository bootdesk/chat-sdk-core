<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Concerns\OpensModals;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use Psr\EventDispatcher\StoppableEventInterface;

class SlashCommandEvent implements StoppableEventInterface
{
    use OpensModals;

    private bool $propagationStopped = false;

    public function __construct(
        public readonly Adapter $adapter,
        public readonly Channel $channel,
        public readonly Thread $thread,
        public readonly Message $message,
        public readonly Author $user,
        public readonly string $command,
        public readonly string $text,
        public readonly mixed $raw = null,
        public readonly ?string $triggerId = null,
        public readonly array $options = [],
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
