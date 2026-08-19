<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Storage;

use Shared\Domain\Failure;
use Shared\Domain\Result;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    public function __construct(
        private readonly string $projectDir
    ) {
    }

    /**
     * @return Result<array{url: string, filename: string, mimeType: string, size: int}>
     */
    public function upload(UploadedFile $file): Result
    {
        if (!$file->isValid()) {
            return Result::failure(Failure::validation('Invalid or corrupted upload file.'));
        }

        $size = (int) $file->getSize();
        if ($size > self::MAX_SIZE_BYTES) {
            return Result::failure(Failure::validation('File exceeds maximum allowed size of 5MB.'));
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo !== false ? finfo_file($finfo, $file->getPathname()) : false;
        $mimeType = is_string($detectedMime) && $detectedMime !== '' ? $detectedMime : $file->getClientMimeType();
        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            return Result::failure(Failure::validation('Unsupported file type. Only JPEG, PNG, and WebP are allowed.'));
        }

        $extension = self::ALLOWED_MIME_TYPES[$mimeType];
        $filename = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
        $targetDir = $this->projectDir . '/public/uploads';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            return Result::failure(Failure::server('Unable to create upload directory.'));
        }

        try {
            $file->move($targetDir, $filename);
        } catch (\Exception $e) {
            return Result::failure(Failure::server('Failed to move uploaded file: ' . $e->getMessage()));
        }

        return Result::success([
            'url' => '/uploads/' . $filename,
            'filename' => $filename,
            'mimeType' => $mimeType,
            'size' => $size,
        ]);
    }
}
