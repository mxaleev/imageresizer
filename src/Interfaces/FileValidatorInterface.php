<?php

namespace MXUtils\ImageResizer\Interfaces;

interface FileValidatorInterface
{
    /**
     * @param string $path
     * @param int $maxFileSize
     * @param array $allowedExtensions
     * @return array
     */
    public function validate(string $path, int $maxFileSize, array $allowedExtensions): array;
}
