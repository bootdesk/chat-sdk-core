<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Attachment;

interface MustRehydrateAttachments
{
    public function rehydrateAttachment(Attachment $attachment): Attachment;
}
