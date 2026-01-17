<?php
// ==================== ОСНОВНОЙ КЛАСС ====================

namespace MXUtils\ImageResizer;

use MXUtils\ImageResizer\Interfaces\DirectoryScannerInterface;
use MXUtils\ImageResizer\Interfaces\FileValidatorInterface;
use MXUtils\ImageResizer\Interfaces\ImageProcessorInterface;
use MXUtils\ImageResizer\Interfaces\ResultFormatterInterface;
use Exception;

class Resizer
{
    private ImageProcessorInterface $imageProcessor;
    private FileValidatorInterface $fileValidator;
    private DirectoryScannerInterface $directoryScanner;
    private ResultFormatterInterface $resultFormatter;

    private const DEFAULT_MAX_FILE_SIZE = 20 * 1024 * 1024; // 20MB по умолчанию
    private const DEFAULT_QUALITY = 80;
    private array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    private int $maxImagesToProcess = 5;
    private int $maxFileSize = self::DEFAULT_MAX_FILE_SIZE;
    private int $quality = self::DEFAULT_QUALITY;
    private bool $dontEnlarge = true; // По умолчанию не увеличиваем маленькие изображения
    private array $warnings = [];
    private array $errors = [];

    public function __construct(
        ImageProcessorInterface $imageProcessor,
        FileValidatorInterface $fileValidator,
        DirectoryScannerInterface $directoryScanner,
        ResultFormatterInterface $resultFormatter
    ) {
        $this->imageProcessor = $imageProcessor;
        $this->fileValidator = $fileValidator;
        $this->directoryScanner = $directoryScanner;
        $this->resultFormatter = $resultFormatter;
    }

    /**
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * @return float
     */
    public function getMaxFileSizeMB(): float
    {
        return round($this->maxFileSize / (1024 * 1024), 2);
    }

    /**
     * Максимальный размер файла для обработки (в байтах)
     * @param int $maxSize
     * @return $this
     */
    public function setMaxFileSize(int $maxSize): self
    {
        // Устанавливаем максимальный размер файла в байтах
        $this->maxFileSize = max(1024, $maxSize); // Минимум 1KB
        return $this;
    }

    /**
     * Максимальный размер файла для обработки (в мегабайтах)
     * @param float $maxSizeMB
     * @return $this
     */
    public function setMaxFileSizeMB(float $maxSizeMB): self
    {
        // Удобный метод для установки размера в мегабайтах
        return $this->setMaxFileSize((int)($maxSizeMB * 1024 * 1024));
    }

    /**
     * Максимальное количество файлов для пакетной обработки
     * @param int $max
     * @return $this
     */
    public function setMaxImagesToProcess(int $max): self
    {
        $this->maxImagesToProcess = max(0, $max);
        return $this;
    }

    /**
     * @return int
     */
    public function getQuality(): int
    {
        return $this->quality;
    }

    /**
     * Коэффициент качества
     * @param int $quality
     * @return $this
     */
    public function setQuality(int $quality): self
    {
        // Ограничиваем качество в диапазоне 1-100
        $this->quality = max(1, min(100, $quality));
        return $this;
    }

    /**
     * @param bool $dontEnlarge
     * @return $this
     */
    public function setDontEnlarge(bool $dontEnlarge): self
    {
        $this->dontEnlarge = $dontEnlarge;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDontEnlarge(): bool
    {
        return $this->dontEnlarge;
    }

    /**
     * @param string $sourceDir
     * @param string $outputDir
     * @param string $suffix
     * @param int $targetWidth
     * @param int $targetHeight
     * @return string
     */
    public function resizeImages(
        string $sourceDir,
        string $outputDir,
        string $suffix,
        int $targetWidth,
        int $targetHeight
    ): string {
        // Сброс состояния
        $convertedFiles = [];
        $unsupportedFiles = [];
        $this->warnings = [];
        $this->errors = [];

        try {
            // Валидация директорий
            $this->validateDirectories($sourceDir, $outputDir);

            // Сканирование директории
            $files = $this->scanDirectory($sourceDir);

            // Добавляем информацию о настройках в предупреждения
            $this->addSettingsWarnings();

            // Обработка файлов
            foreach ($files as $filename) {
                $this->processFile(
                    $filename,
                    $sourceDir,
                    $outputDir,
                    $suffix,
                    $targetWidth,
                    $targetHeight,
                    $convertedFiles,
                    $unsupportedFiles
                );
            }

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        // Форматирование результата с передачей настроек
        return $this->formatResult($convertedFiles, $unsupportedFiles, $targetWidth, $targetHeight, $suffix);
    }

    /**
     * @param string $sourceDir
     * @param string $outputDir
     * @return void
     * @throws Exception
     */
    private function validateDirectories(string $sourceDir, string $outputDir): void
    {
        if (!is_dir($sourceDir)) {
            throw new Exception("Source directory does not exist: $sourceDir");
        }

        if (!is_writable(dirname($outputDir))) {
            throw new Exception("Cannot write to output directory parent: $outputDir");
        }

        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
            throw new Exception("Failed to create output directory: $outputDir");
        }
    }

    /**
     * @param string $directory
     * @return array
     */
    private function scanDirectory(string $directory): array
    {
        // Сканируем директорию один раз без ограничений
        $allFiles = $this->directoryScanner->scan($directory);

        // Предупреждение если файлов больше лимита
        if ($this->maxImagesToProcess > 0 && count($allFiles) > $this->maxImagesToProcess) {
            $this->warnings[] = sprintf(
                'Found %d files, limited to processing %d files',
                count($allFiles),
                $this->maxImagesToProcess
            );

            // Возвращаем только ограниченное количество файлов
            return array_slice($allFiles, 0, $this->maxImagesToProcess);
        }

        // Возвращаем все файлы
        return $allFiles;
    }

    /**
     * @param string $filename
     * @param string $sourceDir
     * @param string $outputDir
     * @param string $suffix
     * @param int $width
     * @param int $height
     * @param array $convertedFiles
     * @param array $unsupportedFiles
     * @return void
     */
    private function processFile(
        string $filename,
        string $sourceDir,
        string $outputDir,
        string $suffix,
        int $width,
        int $height,
        array &$convertedFiles,
        array &$unsupportedFiles
    ): void {
        $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $filename;
        $outputFilename = pathinfo($filename, PATHINFO_FILENAME) . $suffix . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $outputFilename;

        // Валидация файла
        [$isValid, $error] = $this->fileValidator->validate($sourcePath, $this->maxFileSize, $this->allowedExtensions);

        if (!$isValid) {
            $this->addUnsupportedFile($filename, $sourcePath, $error, $unsupportedFiles);
            return;
        }

        $sizeBefore = filesize($sourcePath);

        // Получаем информацию об изображении
        $imageInfo = $this->imageProcessor->getImageInfo($sourcePath);

        if (!$imageInfo->isValid) {
            $this->addUnsupportedFile($filename, $sourcePath, $imageInfo->errorMessage, $unsupportedFiles, $sizeBefore);
            return;
        }

        // Принимаем решение о необходимости ресайза
        [$shouldResize, $reason, $resizeWidth, $resizeHeight] = $this->shouldResizeImage(
            $imageInfo,
            $width,
            $height
        );

        // Обрабатываем изображение
        if ($shouldResize) {
            // Полноценный ресайз
            $success = $this->imageProcessor->resize($sourcePath, $outputPath, $resizeWidth, $resizeHeight, $this->quality);
            $resizeType = 'downscale';
        } else {
            if ($reason === 'image_too_small') {
                // Только компрессия (ресайз с оригинальными размерами)
                $success = $this->imageProcessor->resize($sourcePath, $outputPath, $imageInfo->width, $imageInfo->height, $this->quality);
                $resizeWidth = $imageInfo->width;
                $resizeHeight = $imageInfo->height;
                $resizeType = 'quality_only';
            } else {
                $this->addUnsupportedFile($filename, $sourcePath, $reason, $unsupportedFiles, $sizeBefore);
                return;
            }
        }

        $this->addResult(
            $success,
            $filename,
            $sizeBefore,
            $outputPath,
            $imageInfo->width,
            $imageInfo->height,
            $resizeWidth,
            $resizeHeight,
            $resizeType,
            $reason,
            $convertedFiles,
            $unsupportedFiles
        );
    }

    /**
     * Принимает решение о необходимости ресайза
     * @param ImageInfo $imageInfo
     * @param int $targetWidth
     * @param int $targetHeight
     * @return array
     */
    private function shouldResizeImage(ImageInfo $imageInfo, int $targetWidth, int $targetHeight): array
    {
        // Если целевые размеры нулевые - только компрессия
        if ($targetWidth <= 0 || $targetHeight <= 0) {
            return [false, 'compression_only', $imageInfo->width, $imageInfo->height];
        }

        // Рассчитываем пропорциональные размеры
        $ratio = $imageInfo->width / $imageInfo->height;

        if ($targetWidth / $targetHeight > $ratio) {
            $newWidth = (int)($targetHeight * $ratio);
            $newHeight = $targetHeight;
        } else {
            $newWidth = $targetWidth;
            $newHeight = (int)($targetWidth / $ratio);
        }

        // Проверяем, нужно ли увеличивать
        if ($this->dontEnlarge && $imageInfo->width <= $newWidth && $imageInfo->height <= $newHeight) {
            return [false, 'image_too_small', $newWidth, $newHeight];
        }

        return [true, 'should_resize', $newWidth, $newHeight];
    }

    /**
     * @param string $filename
     * @param string $sourcePath
     * @param string $error
     * @param array $unsupportedFiles
     * @param int|null $sizeBefore
     * @return void
     */
    private function addUnsupportedFile(
        string $filename,
        string $sourcePath,
        string $error,
        array &$unsupportedFiles,
        int $sizeBefore = null
    ): void {
        $fileSize = $sizeBefore ?? (file_exists($sourcePath) ? filesize($sourcePath) : 0);

        $unsupportedFiles[] = [
            'name' => $filename,
            'size_before' => $fileSize,
            'size_before_mb' => round($fileSize / (1024 * 1024), 2),
            'error' => $error,
            'max_allowed_size' => $this->maxFileSize,
            'max_allowed_size_mb' => $this->getMaxFileSizeMB()
        ];
    }

    private function addResult(
        bool $success,
        string $filename,
        int $sizeBefore,
        string $outputPath,
        int $originalWidth,
        int $originalHeight,
        int $resizedWidth,
        int $resizedHeight,
        string $resizeType,
        string $reason,
        array &$convertedFiles,
        array &$unsupportedFiles
    ): void {
        if ($success && file_exists($outputPath)) {
            $sizeAfter = filesize($outputPath);
            $result = [
                'name' => $filename,
                'size_before' => $sizeBefore,
                'size_before_mb' => round($sizeBefore / (1024 * 1024), 2),
                'size_after' => $sizeAfter,
                'size_after_mb' => round($sizeAfter / (1024 * 1024), 2),
                'quality' => $this->quality,
                'compression_ratio' => $sizeBefore > 0 ? round(($sizeBefore - $sizeAfter) / $sizeBefore * 100, 2) : 0,
                'original_width' => $originalWidth,
                'original_height' => $originalHeight,
                'resized_width' => $resizedWidth,
                'resized_height' => $resizedHeight,
                'resize_type' => $resizeType,
                'reason' => $reason
            ];

            if ($resizeType === 'quality_only') {
                $result['note'] = 'Image was not resized (too small), only quality was optimized';
            }

            $convertedFiles[] = $result;
        } else {
            $this->addUnsupportedFile(
                $filename,
                $outputPath,
                $resizeType === 'quality_only' ? 'Failed to compress image' : 'Failed to resize image',
                $unsupportedFiles,
                $sizeBefore
            );
        }
    }

    private function addSettingsWarnings(): void
    {
        if ($this->getMaxFileSize() > 0) {
            $this->warnings[] = sprintf(
                'Maximum file size limit: %sMB',
                $this->getMaxFileSizeMB()
            );
        }

        if ($this->maxImagesToProcess > 0) {
            $this->warnings[] = sprintf(
                'Maximum files to process: %d',
                $this->maxImagesToProcess
            );
        }

        $this->warnings[] = sprintf(
            'Output quality: %d%%',
            $this->quality
        );

        $this->warnings[] = sprintf(
            'Dont enlarge small images: %s',
            $this->dontEnlarge ? 'enabled' : 'disabled'
        );
    }

    private function formatResult(
        array $convertedFiles,
        array $unsupportedFiles,
        int $targetWidth,
        int $targetHeight,
        string $suffix
    ): string
    {
        // Подготавливаем настройки для передачи в форматтер
        $settings = [
            'quality' => $this->quality,
            'max_file_size' => $this->getMaxFileSize(),
            'max_file_size_mb' => $this->getMaxFileSizeMB(),
            'max_images_to_process' => $this->maxImagesToProcess,
            'dont_enlarge' => $this->dontEnlarge,
            'target_width' => $targetWidth,
            'target_height' => $targetHeight,
            'total_files_found' => count($convertedFiles) + count($unsupportedFiles),
            'files_converted' => count($convertedFiles),
            'files_unsupported' => count($unsupportedFiles),
            'suffix' => $suffix
        ];

        return $this->resultFormatter->format(
            $convertedFiles,
            $unsupportedFiles,
            $this->errors,
            $this->warnings,
            $settings
        );
    }
}
