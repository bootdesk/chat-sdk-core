<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Transcript;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\SentMiddleware;
use BootDesk\ChatSDK\Core\Contracts\StateAdapter;
use BootDesk\ChatSDK\Core\Contracts\TranscriptsApi;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;

class TranscriptSentMiddleware implements SentMiddleware
{
    public function __construct(
        private readonly TranscriptsApi $transcripts,
        private readonly StateAdapter $state,
    ) {}

    public function handle(string $threadId, PostableMessage $message, SentMessage $result, Adapter $adapter, string $operation, callable $next): SentMessage
    {
        $userKey = $this->state->get("transcript_user:{$threadId}");

        if ($userKey !== null) {
            $this->transcripts->appendOutgoing($userKey, $result, $message->getTextContent());
        }

        return $next($threadId, $message, $result, $adapter, $operation);
    }
}
