<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface HandlesModals
{
    /**
     * @return array{callbackId: string, viewId: string, values: array, userId: string, contextId: ?string, raw: mixed}|null
     */
    public function parseModalSubmit(ServerRequestInterface $request): ?array;

    /**
     * @return array{callbackId: string, viewId: string, userId: string, contextId: ?string, raw: mixed}|null
     */
    public function parseModalClose(ServerRequestInterface $request): ?array;
}
