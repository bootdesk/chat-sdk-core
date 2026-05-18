<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Concerns;

use BootDesk\ChatSDK\Core\Contracts\SupportsModals;
use BootDesk\ChatSDK\Core\Modals\Modal;

trait OpensModals
{
    public function openModal(Modal $modal): ?array
    {
        if ($this->triggerId === null) {
            return null;
        }

        if (! $this->thread->adapter instanceof SupportsModals) {
            return null;
        }

        $contextId = bin2hex(random_bytes(16));

        $this->thread->chat->storeModalContext(
            $this->thread->adapter->getName(),
            $contextId,
            ['threadId' => $this->thread->id],
        );

        return $this->thread->adapter->openModal($this->triggerId, $modal, $contextId);
    }
}
