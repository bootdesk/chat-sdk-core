<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\SentMessage;

interface TranscriptsApi
{
    public function append(string $userKey, Message $message): void;

    public function appendOutgoing(string $userKey, SentMessage $sentMessage, string $text): void;

    public function list(string $userKey): array;

    public function count(string $userKey): int;

    public function delete(string $userKey): void;
}
