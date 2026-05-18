<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface HandlesOptionsLoad
{
    /**
     * @return array{actionId: string, query: string, userId: string, raw: mixed}|null
     */
    public function parseOptionsLoad(ServerRequestInterface $request): ?array;

    public function respondToOptionsLoad(?array $options): ?ResponseInterface;
}
