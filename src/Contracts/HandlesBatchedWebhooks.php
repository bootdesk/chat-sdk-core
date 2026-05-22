<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\WebhookEvent;
use Psr\Http\Message\ServerRequestInterface;

interface HandlesBatchedWebhooks
{
    /**
     * Parse ALL events from a potentially-batched webhook payload.
     *
     * Unlike the individual parseXxx() methods which return on the first match,
     * this method iterates every entry/event and returns all of them for the
     * Chat to dispatch individually.
     *
     * @return WebhookEvent[]
     */
    public function parseBatchedWebhook(ServerRequestInterface $request): array;
}
