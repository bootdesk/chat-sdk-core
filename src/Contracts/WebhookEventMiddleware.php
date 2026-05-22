<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\WebhookEvent;

interface WebhookEventMiddleware
{
    /**
     * Transform the adapter for a specific webhook event before dispatch.
     *
     * Called once per event in a batched webhook. Return the adapter that
     * should be used to process this event. Useful for multi-tenant setups
     * where different origin IDs (page IDs, account IDs) require different
     * adapter configurations.
     */
    public function handle(WebhookEvent $event, Adapter $adapter): Adapter;
}
