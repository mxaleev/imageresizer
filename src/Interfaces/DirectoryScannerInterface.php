<?php

namespace MXUtils\ImageResizer\Interfaces;

interface DirectoryScannerInterface
{
    /**
     * @param string $directory
     * @return array
     */
    public function scan(string $directory): array;
}
