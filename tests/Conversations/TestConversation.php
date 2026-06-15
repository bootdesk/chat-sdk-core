<?php

namespace BootDesk\ChatSDK\Core\Tests\Conversations;

use BootDesk\ChatSDK\Core\Conversations\Conversation;
use BootDesk\ChatSDK\Core\Conversations\ConversationState;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\Thread;

class TestConversation extends Conversation
{
    public array $log = [];

    public function run(Thread $thread, Message $message): void
    {
        $this->log[] = 'run';
        $this->ask('What is your name?', 'askEmail');
    }

    public function askEmail(Thread $thread, Message $message): void
    {
        $this->log[] = "name:{$message->text}";
        $this->ask("Hi {$message->text}! What is your email?", 'confirm', ['name' => $message->text]);
    }

    public function confirm(Thread $thread, Message $message): void
    {
        $state = ConversationState::get($thread);
        $this->log[] = "email:{$message->text}";
        $this->log[] = 'data:'.json_encode($state['data']);
        $this->say('Done!');
        $this->end();
    }
}
