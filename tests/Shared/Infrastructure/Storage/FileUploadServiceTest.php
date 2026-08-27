<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Storage;

use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Storage\FileUploadService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploadServiceTest extends TestCase
{
    private string $tempDir;
    private FileUploadService $service;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mypills_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempDir, 0777, true);
        $this->service = new FileUploadService($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testUploadRejectsUnsupportedMimeType(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, 'hello world');

        $uploadedFile = new UploadedFile($filePath, 'test.txt', 'text/plain', null, true);
        $result = $this->service->upload($uploadedFile);

        self::assertTrue($result->isFailure());
        self::assertStringContainsString('Unsupported file type', $result->getFailure()->getMessage());
    }

    public function testUploadAcceptsJpeg(): void
    {
        $filePath = $this->tempDir . '/test.jpg';
        file_put_contents($filePath, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00\xFF\xDB\x00C");

        $uploadedFile = new UploadedFile($filePath, 'test.jpg', 'image/jpeg', null, true);
        $result = $this->service->upload($uploadedFile);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertStringStartsWith('/uploads/', $data['url']);
        self::assertSame('image/jpeg', $data['mimeType']);
    }

    public function testUploadAcceptsPng(): void
    {
        $filePath = $this->tempDir . '/test.png';
        // PNG header
        file_put_contents($filePath, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15c4");

        $uploadedFile = new UploadedFile($filePath, 'test.png', 'image/png', null, true);
        $result = $this->service->upload($uploadedFile);

        self::assertTrue($result->isSuccess());
        $data = $result->getValue();
        self::assertStringStartsWith('/uploads/', $data['url']);
        self::assertSame('image/png', $data['mimeType']);
    }

    public function testUploadRejectsOversizedFile(): void
    {
        $filePath = $this->tempDir . '/big.jpg';
        // Create 5.5MB file
        $fp = fopen($filePath, 'wb');
        if ($fp !== false) {
            fseek($fp, 5500000);
            fwrite($fp, "\x00");
            fclose($fp);
        }

        $uploadedFile = new UploadedFile($filePath, 'big.jpg', 'image/jpeg', null, true);
        $result = $this->service->upload($uploadedFile);

        self::assertTrue($result->isFailure());
        self::assertStringContainsString('exceeds maximum allowed size', $result->getFailure()->getMessage());
    }

    public function testUploadRejectsCorruptedOrInvalidUpload(): void
    {
        $filePath = $this->tempDir . '/error.jpg';
        file_put_contents($filePath, 'data');

        $uploadedFile = new UploadedFile($filePath, 'error.jpg', 'image/jpeg', UPLOAD_ERR_PARTIAL, true);
        $result = $this->service->upload($uploadedFile);

        self::assertTrue($result->isFailure());
        self::assertSame('Invalid or corrupted upload file.', $result->getFailure()->getMessage());
    }
}
