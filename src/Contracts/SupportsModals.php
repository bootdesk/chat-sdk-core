<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Modals\Modal;

interface SupportsModals
{
    /**
     * @return array{viewId: string}|null
     */
    public function openModal(string $triggerId, Modal $modal, ?string $contextId = null): ?array;
}
