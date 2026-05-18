<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class MessageContext
{
    private bool $skipped = false;

    /** @var Message[] */
    public readonly array $skippedMessages;

    public readonly int $totalSinceLastHandler;

    public function __construct(
        public readonly Thread $thread,
        public readonly Message $message,
        public readonly ?TranscriptsApi $transcripts = null,
        array $skippedMessages = [],
        int $totalSinceLastHandler = 1,
    ) {
        $this->skippedMessages = $skippedMessages;
        $this->totalSinceLastHandler = $totalSinceLastHandler;
    }

    public function skip(): void
    {
        $this->skipped = true;
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    public function setState(array $state): void
    {
        $this->thread->setState($state);
    }

    public function getState(): array
    {
        return $this->thread->getState();
    }
}
