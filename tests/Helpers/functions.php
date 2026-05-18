<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Author;
use BootDesk\ChatSDK\Core\Message;

function createTestMessage(
    string $id = 'msg_1',
    string $threadId = 'mock:C123:1234',
    string $text = 'hello',
    ?Author $author = null,
    bool $isMention = false,
    bool $isDM = false,
): Message {
    return new Message(
        id: $id,
        threadId: $threadId,
        author: $author ?? new Author(id: 'U123', name: 'Test User'),
        text: $text,
        isMention: $isMention,
        isDM: $isDM,
    );
}
