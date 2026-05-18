<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Support;

use BootDesk\ChatSDK\Core\Attachment;
use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\FileUploadConverter;
use BootDesk\ChatSDK\Core\Exceptions\AdapterException;
use BootDesk\ChatSDK\Core\FileUpload;

class NullFileUploadConverter implements FileUploadConverter
{
    public function upload(FileUpload $file, Adapter $adapter): Attachment
    {
        throw new AdapterException('No FileUploadConverter registered. Bind one in your service provider to support binary file uploads.');
    }
}
