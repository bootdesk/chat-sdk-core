<?php

namespace BootDesk\ChatSDK\Core\Conversations;

use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\Thread;
use Psr\Log\LoggerInterface;

class ConversationManager
{
    /** @var callable(string): Conversation */
    private $factory;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        ?callable $factory = null,
    ) {
        $this->factory = $factory ?? fn (string $class): Conversation => new $class;
    }

    public function start(string $class, Thread $thread, Message $message): void
    {
        if (! is_subclass_of($class, Conversation::class)) {
            throw new \InvalidArgumentException("{$class} must extend Conversation");
        }

        ConversationState::clear($thread);

        $conv = ($this->factory)($class);
        $conv->start($thread, $message);
    }

    public function intercept(Thread $thread, Message $message): bool
    {
        $convState = ConversationState::get($thread);

        if ($convState === [] || ! isset($convState['step'])) {
            return false;
        }

        $class = $convState['class'];
        $step = $convState['step'];

        if (! class_exists($class) || ! is_subclass_of($class, Conversation::class)) {
            ConversationState::clear($thread);

            return false;
        }

        if (isset($convState['timeoutAt']) && time() > $convState['timeoutAt']) {
            $this->logger?->info('Conversation timed out', ['thread' => $thread->id]);
            ConversationState::clear($thread);

            return false;
        }

        if (isset($convState['_repeat'])) {
            $repeat = $convState['_repeat'];
            $repeat['attempts']++;
            $convState['_repeat'] = $repeat;
            ConversationState::save($thread, $convState);

            if ($repeat['attempts'] >= $repeat['maxAttempts']) {
                if ($repeat['onMaxReached'] !== null) {
                    $conv = ($this->factory)($class);
                    $conv->{$repeat['onMaxReached']}($thread, $message);
                } else {
                    ConversationState::clear($thread);
                }

                return true;
            }

            $thread->post($repeat['message']);

            return true;
        }

        $conv = ($this->factory)($class);
        $conv->$step($thread, $message);

        return true;
    }

    public function clear(Thread $thread): void
    {
        ConversationState::clear($thread);
    }
}
