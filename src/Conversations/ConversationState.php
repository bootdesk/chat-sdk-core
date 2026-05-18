<?php

namespace BootDesk\ChatSDK\Core\Conversations;

use BootDesk\ChatSDK\Core\Thread;

class ConversationState
{
    public static function save(Thread $thread, array $state): void
    {
        $current = $thread->getState();
        $current['_conversation'] = $state;
        $thread->setState($current);
    }

    public static function get(Thread $thread): array
    {
        return $thread->getState()['_conversation'] ?? [];
    }

    public static function clear(Thread $thread): void
    {
        $current = $thread->getState();
        unset($current['_conversation']);
        $thread->setState($current);
    }
}
