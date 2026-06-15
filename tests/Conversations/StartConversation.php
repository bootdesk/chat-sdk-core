<?php

namespace BootDesk\ChatSDK\Core\Tests\Conversations;

use BootDesk\ChatSDK\Core\Conversations\Conversation;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\Thread;

class StartConversation extends Conversation
{
    public array $log = [];

    public function run(Thread $thread, Message $message): void
    {
        $this->log[] = 'outer-run';
        $this->startConversation(InnerConversation::class, $message);
    }
}
