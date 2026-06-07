<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Transcript;

use BootDesk\ChatSDK\Core\Contracts\StateAdapter;
use BootDesk\ChatSDK\Core\Contracts\TranscriptsApi as TranscriptsApiContract;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\SentMessage;

class DefaultTranscriptsApi implements TranscriptsApiContract
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
            'direction' => 'incoming',
        ], ['maxLength' => $maxMessages, 'ttlMs' => $ttlMs]);
    }

    public function appendOutgoing(string $userKey, SentMessage $sentMessage, string $text): void
    {
        $maxMessages = $this->config['max_messages'] ?? 100;
        $ttlMs = $this->config['ttl_ms'] ?? 30 * 24 * 60 * 60 * 1000;
        $listKey = "transcripts:{$userKey}";

        $this->state->appendToList($listKey, [
            'id' => $sentMessage->id,
            'text' => $text,
            'authorId' => 'bot',
            'threadId' => $sentMessage->threadId,
            'timestamp' => $sentMessage->timestamp ?? time(),
            'direction' => 'outgoing',
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
