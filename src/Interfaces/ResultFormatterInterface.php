<?php

namespace MXUtils\ImageResizer\Interfaces;

interface ResultFormatterInterface
{
    /**
     * @param array $converted
     * @param array $unsupported
     * @param array $errors
     * @param array $warnings
     * @param array $settings
     * @return string
     */
    public function format(
        array $converted,
        array $unsupported,
        array $errors,
        array $warnings,
        array $settings = []
    ): string;
}
