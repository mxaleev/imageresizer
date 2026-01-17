<?php
// ==================== КЛАСС ДЛЯ ВАЛИДАЦИИ ФАЙЛОВ ====================

namespace MXUtils\ImageResizer;

use MXUtils\ImageResizer\Interfaces\FileValidatorInterface;

class ImageFileValidator implements FileValidatorInterface
{
    /**
     * @param string $path
     * @param int $maxFileSize
     * @param array $allowedExtensions
     * @return array
     */
    public function validate(string $path, int $maxFileSize, array $allowedExtensions): array
    {
        // Проверка существования файла
        if (!file_exists($path)) {
            return [false, 'File not found'];
        }

        // Проверка размера файла
        $fileSize = filesize($path);
        if ($fileSize > $maxFileSize) {
            $maxSizeMB = round($maxFileSize / (1024 * 1024), 2);
            $fileSizeMB = round($fileSize / (1024 * 1024), 2);
            return [false, sprintf(
                'File size (%sMB) exceeds maximum allowed size (%sMB)',
                $fileSizeMB,
                $maxSizeMB
            )];
        }

        // Проверка расширения
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            return [false, 'Unsupported file extension'];
        }

        // Проверка, что это валидное изображение
        $imageInfo = @getimagesize($path);
        if (!$imageInfo) {
            return [false, 'File is not a valid image or is corrupted'];
        }

        return [true, ''];
    }
}
