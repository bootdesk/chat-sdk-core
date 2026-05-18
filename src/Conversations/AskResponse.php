<?php

namespace BootDesk\ChatSDK\Core\Conversations;

use BootDesk\ChatSDK\Core\Thread;

class AskResponse
{
    public function __construct(
        private readonly Thread $thread,
    ) {}

    public function repeat(
        string $message,
        int $maxAttempts = 3,
        ?string $onMaxReached = null,
    ): self {
        $state = ConversationState::get($this->thread);
        $state['_repeat'] = [
            'message' => $message,
            'maxAttempts' => $maxAttempts,
            'attempts' => 0,
            'onMaxReached' => $onMaxReached,
        ];
        ConversationState::save($this->thread, $state);

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $state = ConversationState::get($this->thread);
        $state['timeoutAt'] = time() + $seconds;
        ConversationState::save($this->thread, $state);

        return $this;
    }
}
