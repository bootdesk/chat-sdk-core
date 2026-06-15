<?php

namespace BootDesk\ChatSDK\Core\Tests\Conversations;

use BootDesk\ChatSDK\Core\ActionEvent;
use BootDesk\ChatSDK\Core\Conversations\Conversation;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\ReactionEvent;
use BootDesk\ChatSDK\Core\SlashCommandEvent;
use BootDesk\ChatSDK\Core\Thread;

class ActionAwareConversation extends Conversation
{
    public bool $actionHandled = false;

    public bool $slashHandled = false;

    public bool $reactionHandled = false;

    public array $log = [];

    public function run(Thread $thread, Message $message): void
    {
        $this->log[] = 'run';
        $this->ask('Pick one', 'handleChoice');
    }

    public function handleChoice(Thread $thread, Message $message): void
    {
        $this->log[] = "choice:{$message->text}";
    }

    public function onAction(Thread $thread, ActionEvent $action): ?bool
    {
        $this->actionHandled = true;

        return true;
    }

    public function onSlashCommand(Thread $thread, SlashCommandEvent $command): ?bool
    {
        $this->slashHandled = true;

        return true;
    }

    public function onReaction(Thread $thread, ReactionEvent $reaction): ?bool
    {
        $this->reactionHandled = true;

        return true;
    }
}
