<?php

namespace BootDesk\ChatSDK\Core\Conversations;

use BootDesk\ChatSDK\Core\Cards\Card;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\Thread;

abstract class Conversation
{
    protected function ask(
        Thread $thread,
        string|PostableMessage|Card $question,
        string $next,
        array $data = [],
    ): AskResponse {
        $thread->post($question);

        $currentState = ConversationState::get($thread);
        ConversationState::save($thread, [
            'class' => static::class,
            'step' => $next,
            'data' => array_merge(
                $currentState['data'] ?? [],
                $data,
            ),
        ]);

        return new AskResponse($thread);
    }

    protected function say(Thread $thread, string|PostableMessage|Card $text): void
    {
        $thread->post($text);
    }

    protected function pause(string $childClass, Thread $thread, Message $message): void
    {
        $current = ConversationState::get($thread);
        ConversationState::save($thread, [
            'class' => $childClass,
            'step' => 'start',
            'data' => ['_stack' => array_merge(
                $current['data']['_stack'] ?? [],
                [$current],
            )],
        ]);
    }

    protected function end(Thread $thread): void
    {
        $state = ConversationState::get($thread);
        $stack = $state['data']['_stack'] ?? [];

        if (! empty($stack)) {
            $parent = array_pop($stack);
            $parent['data']['_stack'] = $stack;
            ConversationState::save($thread, $parent);
        } else {
            ConversationState::clear($thread);
        }
    }

    abstract public function start(Thread $thread, Message $message): void;
}
