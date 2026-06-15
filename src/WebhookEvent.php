<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class WebhookEvent
{
    public const TYPE_MESSAGE = 'message';

    public const TYPE_ACTION = 'action';

    public const TYPE_REACTION = 'reaction';

    public const TYPE_STATUS = 'status';

    public const TYPE_SLASH_COMMAND = 'slash_command';

    public const TYPE_MESSAGE_COST = 'message_cost';

    public const TYPE_UNSUPPORTED = 'unsupported';

    /**
     * @param  'message'|'action'|'reaction'|'status'|'slash_command'|'message_cost'|'unsupported'  $type
     * @param  mixed  $payload  Message for TYPE_MESSAGE, array for all others
     */
    public function __construct(
        public readonly string $type,
        public readonly string $threadId,
        public readonly mixed $payload,
        public readonly ?string $originId = null,
    ) {}
}
