<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Message;
use Psr\Http\Message\ServerRequestInterface;

interface ConcurrencyHandler
{
    /**
     * Process an incoming message, applying the concurrency strategy.
     *
     * @param  Adapter  $adapter  The platform adapter
     * @param  string  $threadId  The canonical thread ID
     * @param  Message  $message  The incoming message (post-dedup, post-middleware)
     * @param  callable  $processCallback  fn(Adapter, string $threadId, Message, array $skippedMessages, int $totalSinceLastHandler): void
     * @param  ServerRequestInterface|null  $request  The original PSR-7 request (for job serialization)
     */
    public function process(
        Adapter $adapter,
        string $threadId,
        Message $message,
        callable $processCallback,
        ?ServerRequestInterface $request = null,
    ): void;
}
