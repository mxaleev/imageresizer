<?php

namespace MXUtils\ImageResizer\Interfaces;

use MXUtils\ImageResizer\ImageInfo;

interface ImageProcessorInterface
{
    /**
     * @param string $sourcePath
     * @param string $outputPath
     * @param int $width
     * @param int $height
     * @param int $quality
     * @return bool
     */
    public function resize(string $sourcePath, string $outputPath, int $width, int $height, int $quality): bool;

    /**
     * @param string $sourcePath
     * @return ImageInfo
     */
    public function getImageInfo(string $sourcePath): ImageInfo;
}
