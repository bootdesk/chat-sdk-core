<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface HandlesSlackEvents
{
    /**
     * @return array{channelId: string, threadId: string, threadTs: string, userId: string, context: mixed, raw: mixed}|null
     */
    public function parseAssistantThreadStarted(ServerRequestInterface $request): ?array;

    /**
     * @return array{channelId: string, threadId: string, threadTs: string, userId: string, context: mixed, raw: mixed}|null
     */
    public function parseAssistantContextChanged(ServerRequestInterface $request): ?array;

    /**
     * @return array{channelId: string, userId: string, raw: mixed}|null
     */
    public function parseAppHomeOpened(ServerRequestInterface $request): ?array;

    /**
     * @return array{channelId: string, userId: string, inviterId: ?string, raw: mixed}|null
     */
    public function parseMemberJoinedChannel(ServerRequestInterface $request): ?array;
}
