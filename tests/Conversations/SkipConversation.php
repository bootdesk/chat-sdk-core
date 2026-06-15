<?php

namespace BootDesk\ChatSDK\Core\Tests\Conversations;

use BootDesk\ChatSDK\Core\Conversations\Conversation;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\Thread;

class SkipConversation extends Conversation
{
    public array $log = [];

    public function run(Thread $thread, Message $message): void
    {
        $this->log[] = 'run';
        $this->ask('First question?', 'stepOne');
    }

    public function stepOne(Thread $thread, Message $message): void
    {
        $this->log[] = 'stepOne';
        $this->skip('stepThree', $message, ['skipped' => true]);
    }

    public function stepThree(Thread $thread, Message $message): void
    {
        $this->log[] = 'stepThree';
        $this->say('Skipped to three!');
        $this->end();
    }
}
