<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Support;

use BootDesk\ChatSDK\Core\Attachment;
use BootDesk\ChatSDK\Core\FileUpload;
use BootDesk\ChatSDK\Core\PostableMessage;

class AttachmentUtils
{
    /** @return FileUpload[] */
    public static function extractFiles(PostableMessage $message): array
    {
        return $message->files;
    }

    /** @return Attachment[] */
    public static function extractAttachments(PostableMessage $message): array
    {
        return $message->attachments;
    }

    public static function hasAttachments(PostableMessage $message): bool
    {
        return $message->attachments !== [] || $message->files !== [];
    }
}
