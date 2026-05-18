<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

interface AdapterHasMessagingWindow
{
    public function getMessagingWindowSeconds(): ?int;

    /**
     * Return a stable tracking key for the conversation from a thread ID.
     * Used as the storage key for the last-seen timestamp.
     */
    public function getTrackingKey(string $threadId): string;
}
