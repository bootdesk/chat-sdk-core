<?php

namespace BootDesk\ChatSDK\Core\Tests\Conversations;

use BootDesk\ChatSDK\Core\Conversations\Conversation;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\Thread;

class RepeatConversation extends Conversation
{
    public array $log = [];

    public function run(Thread $thread, Message $message): void
    {
        $this->log[] = 'run';
        $this->ask('What color?', 'handleColor');
    }

    public function handleColor(Thread $thread, Message $message): void
    {
        $this->log[] = "color:{$message->text}";
        $this->repeat();
    }
}
