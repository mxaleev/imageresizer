<?php
// ==================== КЛАСС ДЛЯ РАБОТЫ С ИЗОБРАЖЕНИЯМИ ====================

namespace MXUtils\ImageResizer;

use MXUtils\ImageResizer\Interfaces\ImageProcessorInterface;

class GdImageProcessor implements ImageProcessorInterface
{
    private array $supportedFormats = [
        'jpg' => ['create' => 'imagecreatefromjpeg', 'save' => 'imagejpeg'],
        'jpeg' => ['create' => 'imagecreatefromjpeg', 'save' => 'imagejpeg'],
        'png' => ['create' => 'imagecreatefrompng', 'save' => 'imagepng'],
        'webp' => ['create' => 'imagecreatefromwebp', 'save' => 'imagewebp']
    ];

    /**
     * @param string $sourcePath
     * @param string $outputPath
     * @param int $width
     * @param int $height
     * @param int $quality
     * @return bool
     */
    public function resize(string $sourcePath, string $outputPath, int $width, int $height, int $quality = 85): bool
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if (!isset($this->supportedFormats[$extension])) {
            return false;
        }

        $image = null;
        $outputImage = null;

        try {
            $image = $this->loadImage($sourcePath, $extension);
            if (!$image) {
                return false;
            }

            // Если указаны размеры - ресайзим, иначе только компрессия
            if ($width > 0 && $height > 0) {
                [$newWidth, $newHeight] = $this->calculateDimensions($image, $width, $height);
                $outputImage = $this->createResizedImage($image, $newWidth, $newHeight, $extension);
                $image = null; // Уже не нужен, так как создан новый
            } else {
                // Только компрессия
                $outputImage = $image;
                $image = null;
            }

            if (!$outputImage) {
                return false;
            }

            return $this->saveImage($outputImage, $outputPath, $extension, $quality);

        } finally {
            $this->cleanupResources([$image, $outputImage]);
        }
    }

    /**
     * @param string $sourcePath
     * @return ImageInfo
     */
    public function getImageInfo(string $sourcePath): ImageInfo
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if (!isset($this->supportedFormats[$extension])) {
            return ImageInfo::createInvalid("Unsupported format: $extension");
        }

        $image = $this->loadImage($sourcePath, $extension);
        if (!$image) {
            return ImageInfo::createInvalid("Cannot load image");
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Получаем MIME тип
        $mimeType = match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'application/octet-stream'
        };

        imagedestroy($image);

        return new ImageInfo($width, $height, $mimeType, true);
    }

    private function loadImage(string $path, string $extension)
    {
        $function = $this->supportedFormats[$extension]['create'];
        return @$function($path);
    }

    private function calculateDimensions($image, int $targetWidth, int $targetHeight): array
    {
        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        if ($targetWidth <= 0 || $targetHeight <= 0) {
            return [$originalWidth, $originalHeight];
        }

        $ratio = $originalWidth / $originalHeight;

        if ($targetWidth / $targetHeight > $ratio) {
            $targetWidth = (int)($targetHeight * $ratio);
        } else {
            $targetHeight = (int)($targetWidth / $ratio);
        }

        return [$targetWidth, $targetHeight];
    }

    private function createResizedImage($sourceImage, int $width, int $height, string $extension)
    {
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Если размеры не изменились - возвращаем исходное
        if ($width === $originalWidth && $height === $originalHeight) {
            return $sourceImage;
        }

        $resizedImage = imagecreatetruecolor($width, $height);
        if (!$resizedImage) {
            return false;
        }

        if (in_array($extension, ['png', 'webp'])) {
            $this->preserveTransparency($resizedImage, $width, $height);
        }

        $success = imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0, 0, 0, 0,
            $width, $height,
            $originalWidth,
            $originalHeight
        );

        return $success ? $resizedImage : false;
    }

    private function preserveTransparency($image, int $width, int $height): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefilledrectangle($image, 0, 0, $width, $height, $transparent);
    }

    private function saveImage($image, string $path, string $extension, int $quality): bool
    {
        $function = $this->supportedFormats[$extension]['save'];

        return match ($extension) {
            'jpg', 'jpeg', 'webp' => $function($image, $path, $quality),
            'png' => $function($image, $path, $this->convertToPngCompression($quality)),
            default => false,
        };
    }

    private function convertToPngCompression(int $quality): int
    {
        return (int)(9 - (($quality - 1) / 99 * 9));
    }

    private function cleanupResources(array $resources): void
    {
        foreach ($resources as $resource) {
            if ($resource && is_resource($resource) && get_resource_type($resource) === 'gd') {
                imagedestroy($resource);
            }
        }
    }
}
