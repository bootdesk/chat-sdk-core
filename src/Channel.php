<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Contracts\Adapter;

class Channel
{
    public function __construct(
        public readonly string $id,
        public readonly Adapter $adapter,
    ) {}

    public function post(string|PostableMessage $message): SentMessage
    {
        $postable = $message instanceof PostableMessage
            ? $message
            : PostableMessage::text($message);

        return $this->adapter->postMessage($this->id, $postable);
    }

    public function fetchMetadata(): ?ChannelInfo
    {
        return $this->adapter->fetchChannelInfo($this->id);
    }
}
