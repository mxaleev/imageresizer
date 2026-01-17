<?php
// ==================== ФАБРИКА ДЛЯ СОЗДАНИЯ ОБЪЕКТОВ ====================

namespace MXUtils\ImageResizer;

use RuntimeException;

class ResizerFactory
{
    /**
     * @return Resizer
     */
    public static function create(): Resizer
    {
        // Проверка наличия GD библиотеки
        if (!extension_loaded('gd')) {
            throw new RuntimeException('GD library is not installed or enabled');
        }

        $gdInfo = gd_info();
        $missingFormats = [];

        // Проверка поддержки форматов
        if (!($gdInfo['JPEG Support'] ?? false)) {
            $missingFormats[] = 'JPEG';
        }
        if (!($gdInfo['PNG Support'] ?? false)) {
            $missingFormats[] = 'PNG';
        }
        if (!($gdInfo['WebP Support'] ?? false)) {
            $missingFormats[] = 'WebP';
        }

        if (!empty($missingFormats)) {
            throw new RuntimeException(
                'GD library missing support for: ' . implode(', ', $missingFormats)
            );
        }

        // Создание зависимостей
        $imageProcessor = new GdImageProcessor();
        $fileValidator = new ImageFileValidator();
        $directoryScanner = new DirectoryScanner();
        $resultFormatter = new JsonResultFormatter();

        // Создание основного объекта
        return new Resizer(
            $imageProcessor,
            $fileValidator,
            $directoryScanner,
            $resultFormatter
        );
    }
}
