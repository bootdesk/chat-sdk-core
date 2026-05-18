<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface HandlesActions
{
    /**
     * @return array{actionId: string, value: ?string, threadId: string, messageId: string, userId: string, isBot: bool, triggerId: ?string, raw: mixed, callbackQueryId: ?string}|null
     */
    public function parseAction(ServerRequestInterface $request): ?array;

    /**
     * Acknowledge the action to the platform (e.g. Telegram answerCallbackQuery).
     */
    public function acknowledgeAction(?string $callbackQueryId): ?ResponseInterface;
}
