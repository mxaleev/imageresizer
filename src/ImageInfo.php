<?php

namespace MXUtils\ImageResizer;

class ImageInfo
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly string $mimeType,
        public readonly bool $isValid,
        public readonly string $errorMessage = ''
    ) {}

    /**
     * @param string $errorMessage
     * @return self
     */
    public static function createInvalid(string $errorMessage): self
    {
        return new self(0, 0, '', false, $errorMessage);
    }
}
