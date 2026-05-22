<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Events;

use Psr\EventDispatcher\ListenerProviderInterface;

final class ListenerProvider implements ListenerProviderInterface
{
    /** @var array<string, array<array{callable, int}>> */
    private array $listeners = [];

    public function getListenersForEvent(object $event): iterable
    {
        $class = $event::class;

        if (! isset($this->listeners[$class])) {
            return [];
        }

        $listeners = $this->listeners[$class];

        usort($listeners, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        return array_column($listeners, 0);
    }

    public function addListener(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][] = [$listener, $priority];
    }

    public function hasListeners(string $eventClass): bool
    {
        return isset($this->listeners[$eventClass]);
    }
}
