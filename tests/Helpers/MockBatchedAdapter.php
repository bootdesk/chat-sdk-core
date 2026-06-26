<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Author;
use BootDesk\ChatSDK\Core\ChannelInfo;
use BootDesk\ChatSDK\Core\Chat;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\FormatConverter;
use BootDesk\ChatSDK\Core\Contracts\HandlesBatchedWebhooks;
use BootDesk\ChatSDK\Core\FetchOptions;
use BootDesk\ChatSDK\Core\FetchResult;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;
use BootDesk\ChatSDK\Core\ThreadInfo;
use BootDesk\ChatSDK\Core\UserInfo;
use BootDesk\ChatSDK\Core\WebhookEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class MockBatchedAdapter implements Adapter, HandlesBatchedWebhooks
{
    public ?FormatConverter $formatConverter = null;

    public bool $initialized = false;

    public bool $disconnected = false;

    /** @var WebhookEvent[] */
    public array $returnedEvents = [];

    public ?ResponseInterface $ackResponse = null;

    public ?ResponseInterface $customResponse = null;

    public function getName(): string
    {
        return 'mock_batched';
    }

    public function getBotUserId(): ?string
    {
        return 'BOT123';
    }

    public function verifyWebhook(ServerRequestInterface $request): ?ResponseInterface
    {
        return $this->ackResponse;
    }

    public function parseWebhook(ServerRequestInterface $request): Message
    {
        $body = (string) $request->getBody();
        $data = json_decode($body, true) ?? [];

        return new Message(
            id: $data['id'] ?? 'fallback',
            threadId: $data['threadId'] ?? 'mock_batched:C',
            author: new Author(id: 'U1'),
            text: $data['text'] ?? 'fallback',
            originId: $data['originId'] ?? null,
        );
    }

    public function parseBatchedWebhook(ServerRequestInterface $request): array
    {
        return $this->returnedEvents;
    }

    public function encodeThreadId(mixed $platformData): string
    {
        return 'mock_batched:'.$platformData['channelId'];
    }

    public function decodeThreadId(string $threadId): mixed
    {
        return ['channelId' => explode(':', $threadId, 2)[1] ?? ''];
    }

    public function channelIdFromThreadId(string $threadId): string
    {
        $parts = explode(':', $threadId, 3);

        return $parts[0].':'.$parts[1];
    }

    public function postMessage(string $threadId, PostableMessage $message): SentMessage
    {
        return new SentMessage('sent', $threadId, (string) time());
    }

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage
    {
        return new SentMessage($messageId, $threadId, (string) time());
    }

    public function deleteMessage(string $threadId, string $messageId): void {}

    public function addReaction(string $threadId, string $messageId, string $emoji): void {}

    public function removeReaction(string $threadId, string $messageId, string $emoji): void {}

    public function startTyping(string $threadId): void {}

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult
    {
        return new FetchResult([]);
    }

    public function fetchThread(string $threadId): ThreadInfo
    {
        return new ThreadInfo($threadId, 'C123');
    }

    public function fetchChannelInfo(string $channelId): ?ChannelInfo
    {
        return new ChannelInfo($channelId, 'test-channel');
    }

    public function getUser(string $userId): ?UserInfo
    {
        return new UserInfo($userId, 'Test User');
    }

    public function openDM(string $userId): ?string
    {
        return 'mock_batched:DM:'.$userId;
    }

    public function getFormatConverter(): ?FormatConverter
    {
        return $this->formatConverter;
    }

    public function setFormatConverter(?FormatConverter $converter): self
    {
        $this->formatConverter = $converter;

        return $this;
    }

    public function initialize(Chat $chat): void
    {
        $this->initialized = true;
    }

    public function disconnect(): void
    {
        $this->disconnected = true;
    }

    public function createResponse(): ?ResponseInterface
    {
        return $this->customResponse;
    }

    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage
    {
        return null;
    }
}
