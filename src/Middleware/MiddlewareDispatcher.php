<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Middleware;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\ReceivingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\SendingMiddleware;
use BootDesk\ChatSDK\Core\Contracts\WebhookEventMiddleware;
use BootDesk\ChatSDK\Core\Contracts\WebhookMiddleware;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\PostableMessage;
use BootDesk\ChatSDK\Core\WebhookEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MiddlewareDispatcher
{
    /** @var array{webhook: array, receiving: array, sending: array, webhook_event: array} */
    private array $middlewares = [
        'webhook' => [],
        'receiving' => [],
        'sending' => [],
        'webhook_event' => [],
    ];

    public function addWebhook(WebhookMiddleware $middleware): void
    {
        $this->middlewares['webhook'][] = $middleware;
    }

    public function addReceiving(ReceivingMiddleware $middleware): void
    {
        $this->middlewares['receiving'][] = $middleware;
    }

    public function addSending(SendingMiddleware $middleware): void
    {
        $this->middlewares['sending'][] = $middleware;
    }

    public function addWebhookEvent(WebhookEventMiddleware $middleware): void
    {
        $this->middlewares['webhook_event'][] = $middleware;
    }

    /**
     * @param  'webhook'|'receiving'|'sending'|'webhook_event'  $type
     */
    public function getMiddlewares(string $type): array
    {
        return $this->middlewares[$type];
    }

    /**
     * @param  callable(WebhookEvent, Adapter): Adapter  $handler
     */
    public function processWebhookEvent(WebhookEvent $event, Adapter $adapter, callable $handler): Adapter
    {
        $middlewares = $this->middlewares['webhook_event'];

        if ($middlewares === []) {
            return $handler($event, $adapter);
        }

        $current = $adapter;

        foreach ($middlewares as $middleware) {
            $current = $middleware->handle($event, $current);
        }

        return $handler($event, $current);
    }

    /**
     * @param  callable(ServerRequestInterface): ResponseInterface  $handler
     */
    public function processWebhook(ServerRequestInterface $request, callable $handler): ResponseInterface
    {
        return $this->process('webhook', $request, $handler);
    }

    /**
     * @param  callable(?Message, Adapter): ?Message  $handler
     */
    public function processReceiving(?Message $message, Adapter $adapter, callable $handler): ?Message
    {
        $result = $this->process('receiving', [$message, $adapter], $handler);

        return $result instanceof Message ? $result : null;
    }

    /**
     * @param  callable(string, PostableMessage, Adapter, string): ?PostableMessage  $handler
     */
    public function processSending(string $threadId, PostableMessage $message, Adapter $adapter, string $operation, callable $handler): ?PostableMessage
    {
        return $this->process('sending', [$threadId, $message, $adapter, $operation], $handler);
    }

    /**
     * @param  'webhook'|'receiving'|'sending'  $type
     */
    private function process(string $type, mixed $context, callable $handler): mixed
    {
        $middlewares = $this->middlewares[$type];

        if ($middlewares === []) {
            return $type === 'webhook' ? $handler($context) : $handler(...$context);
        }

        return $this->buildPipeline($middlewares, $context, $handler);
    }

    private function buildPipeline(array $middlewares, mixed $context, callable $handler): mixed
    {
        $pipeline = $handler;

        foreach (array_reverse($middlewares) as $middleware) {
            $prev = $pipeline;
            $pipeline = fn (...$args): mixed => $this->callMiddleware($middleware, $args, $prev);
        }

        return is_array($context) ? $pipeline(...$context) : $pipeline($context);
    }

    private function callMiddleware(object $m, array $args, callable $next): mixed
    {
        return call_user_func_array(
            callback: [$m, 'handle'],
            args: [...$args, $next]
        );
    }
}
