<?php

namespace BootDesk\ChatSDK\Core\Tests\Conversations;

use BootDesk\ChatSDK\Core\ActionEvent;
use BootDesk\ChatSDK\Core\Conversations\Conversation;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\Thread;

class NoInterceptConversation extends Conversation
{
    public bool $actionFallthrough = false;

    public function run(Thread $thread, Message $message): void
    {
        $this->ask('Question?', 'step');
    }

    public function step(Thread $thread, Message $message): void {}

    public function onAction(Thread $thread, ActionEvent $action): ?bool
    {
        return null;
    }
}
