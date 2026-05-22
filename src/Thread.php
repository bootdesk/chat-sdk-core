<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\StateAdapter;

class Thread
{
    public function __construct(
        public readonly string $id,
        public readonly Chat $chat,
        public readonly Adapter $adapter,
        private readonly StateAdapter $state,
    ) {}

    public function post(string|PostableMessage|Cards\Card $message): SentMessage
    {
        $postable = $this->normalizePostable($message);
        $postable = $this->runSendingMiddleware($postable, 'post');

        if (! $postable instanceof PostableMessage) {
            return new SentMessage(id: '', threadId: $this->id);
        }

        return $this->adapter->postMessage($this->id, $postable);
    }

    public function edit(string $messageId, string|PostableMessage $message): SentMessage
    {
        $postable = $this->normalizePostable($message);
        $postable = $this->runSendingMiddleware($postable, 'edit');

        if (! $postable instanceof PostableMessage) {
            return new SentMessage(id: $messageId, threadId: $this->id);
        }

        return $this->adapter->editMessage($this->id, $messageId, $postable);
    }

    public function delete(string $messageId): void
    {
        $this->adapter->deleteMessage($this->id, $messageId);
    }

    public function subscribe(): void
    {
        $this->state->subscribe($this->id);
    }

    public function unsubscribe(): void
    {
        $this->state->unsubscribe($this->id);
    }

    public function isSubscribed(): bool
    {
        return $this->state->isSubscribed($this->id);
    }

    public function startTyping(): void
    {
        $this->adapter->startTyping($this->id);
    }

    public function addReaction(string $messageId, string $emoji): void
    {
        $this->adapter->addReaction($this->id, $messageId, $emoji);
    }

    public function removeReaction(string $messageId, string $emoji): void
    {
        $this->adapter->removeReaction($this->id, $messageId, $emoji);
    }

    public function postEphemeral(string $userId, string|PostableMessage $message): void
    {
        $postable = $this->normalizePostable($message);
        $postable = $this->runSendingMiddleware($postable, 'postEphemeral');

        if (! $postable instanceof PostableMessage) {
            return;
        }

        // Ephemeral messages are adapter-specific (e.g., Slack ephemeral).
        // Posting as a regular message if the adapter doesn't support it.
        try {
            $this->adapter->postMessage($this->id, $postable);
        } catch (\Throwable) {
            // Silently fail for unsupported operations
        }
    }

    public function getState(): array
    {
        $key = "thread-state:{$this->id}";
        $state = $this->state->get($key);

        return is_array($state) ? $state : [];
    }

    public function setState(array $state): void
    {
        $key = "thread-state:{$this->id}";
        $this->state->set($key, $state, 30 * 24 * 60 * 60 * 1000);
    }

    public function fetchMessages(?FetchOptions $options = null): FetchResult
    {
        return $this->adapter->fetchMessages(
            threadId: $this->id,
            options: $options
        );
    }

    private function normalizePostable(string|PostableMessage|Cards\Card $message): PostableMessage
    {
        if ($message instanceof PostableMessage) {
            return $message;
        }

        if ($message instanceof Cards\Card) {
            return PostableMessage::card($message);
        }

        return PostableMessage::text($message);
    }

    private function runSendingMiddleware(PostableMessage $message, string $operation): ?PostableMessage
    {
        return $this->chat->getMiddleware()->processSending(
            threadId: $this->id,
            message: $message,
            adapter: $this->adapter,
            operation: $operation,
            handler: fn ($tid, PostableMessage $msg, $adapter, $op): PostableMessage => $msg
        );
    }
}
