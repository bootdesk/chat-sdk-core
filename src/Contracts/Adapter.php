<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\ChannelInfo;
use BootDesk\ChatSDK\Core\Chat;
use BootDesk\ChatSDK\Core\FetchOptions;
use BootDesk\ChatSDK\Core\FetchResult;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\SentMessage;
use BootDesk\ChatSDK\Core\ThreadInfo;
use BootDesk\ChatSDK\Core\UserInfo;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface Adapter
{
    public function getName(): string;

    public function getBotUserId(): ?string;

    public function verifyWebhook(ServerRequestInterface $request): ?ResponseInterface;

    public function parseWebhook(ServerRequestInterface $request): Message;

    public function encodeThreadId(mixed $platformData): string;

    public function decodeThreadId(string $threadId): mixed;

    public function channelIdFromThreadId(string $threadId): string;

    public function postMessage(string $threadId, PostableMessage $message): SentMessage;

    public function editMessage(string $threadId, string $messageId, PostableMessage $message): SentMessage;

    public function deleteMessage(string $threadId, string $messageId): void;

    public function addReaction(string $threadId, string $messageId, string $emoji): void;

    public function removeReaction(string $threadId, string $messageId, string $emoji): void;

    public function startTyping(string $threadId): void;

    public function fetchMessages(string $threadId, ?FetchOptions $options = null): FetchResult;

    public function fetchThread(string $threadId): ThreadInfo;

    public function fetchChannelInfo(string $channelId): ?ChannelInfo;

    public function getUser(string $userId): ?UserInfo;

    public function openDM(string $userId): ?string;

    public function getFormatConverter(): ?FormatConverter;

    public function initialize(Chat $chat): void;

    public function disconnect(): void;

    public function stream(string $threadId, iterable $textStream, array $options = []): ?SentMessage;

    public function createResponse(): ?ResponseInterface;
}
