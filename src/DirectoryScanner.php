<?php
// ==================== КЛАСС ДЛЯ СКАНИРОВАНИЯ ДИРЕКТОРИИ ====================

namespace MXUtils\ImageResizer;

use MXUtils\ImageResizer\Interfaces\DirectoryScannerInterface;
use DirectoryIterator;
use Exception;

class DirectoryScanner implements DirectoryScannerInterface
{
    /**
     * @param string $directory
     * @return array
     * @throws Exception
     */
    public function scan(string $directory): array
    {
        if (!is_dir($directory) || !is_readable($directory)) {
            throw new Exception("Directory is not accessible: $directory");
        }

        $files = [];
        $iterator = new DirectoryIterator($directory);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->isDir()) {
                continue;
            }

            $files[] = $fileInfo->getFilename();
        }

        return $files;
    }
}
