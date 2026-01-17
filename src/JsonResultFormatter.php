<?php
// ==================== КЛАСС ДЛЯ ФОРМАТИРОВАНИЯ РЕЗУЛЬТАТА ====================

namespace MXUtils\ImageResizer;

use MXUtils\ImageResizer\Interfaces\ResultFormatterInterface;

class JsonResultFormatter implements ResultFormatterInterface
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
    ): string
    {
        $result = [
            'files_converted' => $converted,
            'files_unsupported' => $unsupported,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => $this->createSummary($converted, $unsupported),
            'settings' => $settings
        ];

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array $converted
     * @param array $unsupported
     * @return array
     */
    private function createSummary(array $converted, array $unsupported): array
    {
        $total = count($converted) + count($unsupported);

        // Считаем статистику по типам обработки
        $resizeTypes = [
            'downscale' => 0,
            'quality_only' => 0
        ];

        foreach ($converted as $file) {
            if (isset($file['resize_type'])) {
                $resizeTypes[$file['resize_type']] = ($resizeTypes[$file['resize_type']] ?? 0) + 1;
            }
        }

        return [
            'total_files' => $total,
            'converted' => count($converted),
            'unsupported' => count($unsupported),
            'success_rate' => $total > 0 ? round(count($converted) / $total * 100, 2) : 0,
            'downscaled_images' => $resizeTypes['downscale'],
            'quality_optimized_only' => $resizeTypes['quality_only']
        ];
    }
}
