<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Attachment;
use BootDesk\ChatSDK\Core\FileUpload;

interface FileUploadConverter
{
    public function upload(FileUpload $file, Adapter $adapter): Attachment;
}
