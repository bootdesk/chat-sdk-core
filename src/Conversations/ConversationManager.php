<?php

namespace BootDesk\ChatSDK\Core\Conversations;

use BootDesk\ChatSDK\Core\ActionEvent;
use BootDesk\ChatSDK\Core\Author;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\ReactionEvent;
use BootDesk\ChatSDK\Core\SlashCommandEvent;
use BootDesk\ChatSDK\Core\Thread;
use Psr\Log\LoggerInterface;

class ConversationManager
{
    /** @var callable(string): Conversation */
    private $factory;

    private ?Message $pendingSyntheticMessage = null;

    public function __construct(
        private readonly ?LoggerInterface $logger = null,
        ?callable $factory = null,
    ) {
        $this->factory = $factory ?? fn (string $class): Conversation => new $class;
    }

    /**
     * Return a synthetic message from an action fallthrough, if one was stored
     * during the last interceptAction call. Clears the stored message.
     */
    public function consumePendingSyntheticMessage(): ?Message
    {
        $msg = $this->pendingSyntheticMessage;
        $this->pendingSyntheticMessage = null;

        return $msg;
    }

    /**
     * Start a new conversation. Clears existing state and calls run() immediately.
     */
    public function start(string $class, Thread $thread, Message $message): void
    {
        if (! is_subclass_of($class, Conversation::class)) {
            throw new \InvalidArgumentException("{$class} must extend Conversation");
        }

        ConversationState::clear($thread);

        $conv = ($this->factory)($class);
        $conv->initialize($thread, $message);
        $conv->run($thread, $message);
    }

    /**
     * Intercept an incoming message for an active conversation.
     * Returns true if the message was consumed by the conversation,
     * false if no active conversation exists.
     */
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

        $conv = ($this->factory)($class);
        $conv->initialize($thread, $message);

        $depth = 0;

        do {
            $conv->resetSkip();
            $conv->$step($thread, $message);

            if ($conv->isSkipRequested()) {
                $state = ConversationState::get($thread);
                $step = $state['step'] ?? '';
                $depth++;

                continue;
            }

            break;
        } while ($depth < 10);

        if ($depth >= 10) {
            $this->logger?->warning('Conversation skip chain exceeded max depth', ['thread' => $thread->id]);
            ConversationState::clear($thread);
        }

        return true;
    }

    /**
     * Intercept an action event during an active conversation.
     * Returns true if the conversation consumed the event.
     */
    public function interceptAction(Thread $thread, ActionEvent $action): bool
    {
        return $this->interceptEvent($thread, $action, 'onAction');
    }

    /**
     * Intercept a slash command during an active conversation.
     * Returns true if the conversation consumed the event.
     */
    public function interceptSlashCommand(Thread $thread, SlashCommandEvent $command): bool
    {
        return $this->interceptEvent($thread, $command, 'onSlashCommand');
    }

    /**
     * Intercept a reaction during an active conversation.
     * Returns true if the conversation consumed the event.
     */
    public function interceptReaction(Thread $thread, ReactionEvent $reaction): bool
    {
        return $this->interceptEvent($thread, $reaction, 'onReaction');
    }

    /**
     * Generic non-message event intercept.
     */
    private function interceptEvent(Thread $thread, object $event, string $method): bool
    {
        $convState = ConversationState::get($thread);

        if ($convState === [] || ! isset($convState['step'])) {
            return false;
        }

        $class = $convState['class'];

        if (! class_exists($class) || ! is_subclass_of($class, Conversation::class)) {
            return false;
        }

        $conv = ($this->factory)($class);
        $conv->initialize($thread, new Message(
            id: '',
            threadId: $thread->id,
            author: new Author(id: ''),
            text: '',
        ));

        $result = $conv->$method($thread, $event);

        if ($result === true) {
            return true;
        }

        // Not consumed by conversation — for action events, store a synthetic
        // message so the caller can route it through the message pipeline.
        // This lets card button clicks work with ask() while still running
        // middlewares (ReceivingMiddleware, transcript, etc.).
        if ($event instanceof ActionEvent) {
            $this->pendingSyntheticMessage = new Message(
                id: 'action_'.uniqid(),
                threadId: $thread->id,
                author: new Author(id: $event->user->id),
                text: $event->triggerId ?? $event->actionId,
                raw: $event->raw,
            );

            $this->pendingSyntheticMessage->extras['action_value'] = $event->value;
            $this->pendingSyntheticMessage->extras['action_id'] = $event->actionId;
        }

        return false;
    }

    /**
     * Clear any active conversation state for the given thread.
     */
    public function clear(Thread $thread): void
    {
        ConversationState::clear($thread);
    }
}
