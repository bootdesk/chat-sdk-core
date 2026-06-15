<?php

namespace BootDesk\ChatSDK\Core\Conversations;

use BootDesk\ChatSDK\Core\ActionEvent;
use BootDesk\ChatSDK\Core\Cards\Card;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\ReactionEvent;
use BootDesk\ChatSDK\Core\SlashCommandEvent;
use BootDesk\ChatSDK\Core\Thread;

abstract class Conversation
{
    protected Thread $thread;

    protected Message $currentMessage;

    private bool $skipRequested = false;

    /**
     * Set by ConversationManager before each step call.
     */
    public function initialize(Thread $thread, Message $message): void
    {
        $this->thread = $thread;
        $this->currentMessage = $message;
    }

    /**
     * Post a question and set the next step method.
     * The next user message will be routed to $step.
     */
    protected function ask(
        string|PostableMessage|Card $question,
        string $step,
        array $data = [],
    ): void {
        $this->thread->post($question);

        $currentState = ConversationState::get($this->thread);
        ConversationState::save($this->thread, [
            'class' => static::class,
            'step' => $step,
            'data' => array_merge(
                $currentState['data'] ?? [],
                $data,
            ),
            '_lastQuestion' => $question,
        ]);
    }

    /**
     * Re-ask the last question. Step remains unchanged.
     */
    protected function repeat(?string $message = null): void
    {
        $state = ConversationState::get($this->thread);
        $question = $message ?? ($state['_lastQuestion'] ?? null);
        if ($question !== null) {
            $this->thread->post($question);
        }
    }

    /**
     * Jump to a different step method immediately.
     * The current message is passed to the new step.
     */
    protected function skip(string $step, Message $message, ?array $data = null): void
    {
        $state = ConversationState::get($this->thread);
        ConversationState::save($this->thread, [
            'class' => static::class,
            'step' => $step,
            'data' => $data !== null
                ? array_merge($state['data'] ?? [], $data)
                : ($state['data'] ?? []),
            '_lastQuestion' => $state['_lastQuestion'] ?? null,
        ]);
        $this->skipRequested = true;
    }

    /**
     * Post a message without changing conversation state.
     */
    protected function say(string|PostableMessage|Card $text): void
    {
        $this->thread->post($text);
    }

    /**
     * Replace the current conversation with a new one.
     * No return path — old conversation is gone.
     * Calls $conv->run() immediately.
     */
    protected function startConversation(string $class, Message $message): void
    {
        ConversationState::clear($this->thread);

        $conv = new $class;
        $conv->initialize($this->thread, $message);
        $conv->run($this->thread, $message);
    }

    /**
     * Pause the current conversation and start a child conversation.
     * Stack saves parent state; end() restores and replays parent's last question.
     * Calls $child->run() immediately.
     */
    protected function pause(string $childClass, Message $message): void
    {
        $current = ConversationState::get($this->thread);
        $parentStack = $current['_stack'] ?? [];
        $parentStack[] = $current;

        ConversationState::save($this->thread, [
            'class' => $childClass,
            'step' => 'run',
            'data' => [],
            '_stack' => $parentStack,
        ]);

        $child = new $childClass;
        $child->initialize($this->thread, $message);
        $child->run($this->thread, $message);
    }

    /**
     * End the current conversation step.
     * If a parent conversation exists on the stack, restore it and replay its last question.
     * Otherwise, clear all conversation state.
     */
    protected function end(): void
    {
        $state = ConversationState::get($this->thread);
        $stack = $state['_stack'] ?? [];

        if (! empty($stack)) {
            $parent = array_pop($stack);
            $parent['_stack'] = $stack;
            ConversationState::save($this->thread, $parent);

            if (isset($parent['_lastQuestion'])) {
                $this->thread->post($parent['_lastQuestion']);
            }
        } else {
            ConversationState::clear($this->thread);
        }
    }

    // ─── Non-message event intercepts ─────────────────────────────

    /**
     * Handle an action event during an active conversation.
     * Return true to consume the event (skip normal dispatch),
     * return null to fall through to registered listeners.
     */
    public function onAction(Thread $thread, ActionEvent $action): ?bool
    {
        return null;
    }

    /**
     * Handle a slash command during an active conversation.
     * Return true to consume, null to fall through.
     */
    public function onSlashCommand(Thread $thread, SlashCommandEvent $command): ?bool
    {
        return null;
    }

    /**
     * Handle a reaction during an active conversation.
     * Return true to consume, null to fall through.
     */
    public function onReaction(Thread $thread, ReactionEvent $reaction): ?bool
    {
        return null;
    }

    // ─── Internal (called by ConversationManager) ────────────────

    public function isSkipRequested(): bool
    {
        return $this->skipRequested;
    }

    public function resetSkip(): void
    {
        $this->skipRequested = false;
    }

    /**
     * Entry point called by ConversationManager::start().
     */
    abstract public function run(Thread $thread, Message $message): void;
}
