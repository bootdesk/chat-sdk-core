<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Contracts\StateAdapter;

class TranscriptsApi
{
    public function __construct(
        private readonly StateAdapter $state,
        private readonly array $config = [],
    ) {}

    public function append(string $userKey, Message $message): void
    {
        $maxMessages = $this->config['max_messages'] ?? 100;
        $ttlMs = $this->config['ttl_ms'] ?? 30 * 24 * 60 * 60 * 1000;
        $listKey = "transcripts:{$userKey}";

        $this->state->appendToList($listKey, [
            'id' => $message->id,
            'text' => $message->text,
            'authorId' => $message->author->id,
            'threadId' => $message->threadId,
            'timestamp' => time(),
        ], ['maxLength' => $maxMessages, 'ttlMs' => $ttlMs]);
    }

    public function list(string $userKey): array
    {
        return $this->state->getList("transcripts:{$userKey}");
    }

    public function count(string $userKey): int
    {
        return count($this->list($userKey));
    }

    public function delete(string $userKey): void
    {
        $this->state->delete("transcripts:{$userKey}");
    }
}
