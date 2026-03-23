<?php

namespace App\Services\Scrapers;

trait ParsesSalary
{
    /**
     * @return array{min: int|null, max: int|null}
     */
    protected function parseSalary(string $text): array
    {
        if (preg_match('/\$(\d{2,3})[kK]\s*[-–—]\s*\$?(\d{2,3})[kK]/', $text, $matches)) {
            return [
                'min' => (int) $matches[1] * 1000,
                'max' => (int) $matches[2] * 1000,
            ];
        }

        if (preg_match('/\$?(\d{2,3}),?000\s*[-–—]\s*\$?(\d{2,3}),?000/', $text, $matches)) {
            return [
                'min' => (int) $matches[1] * 1000,
                'max' => (int) $matches[2] * 1000,
            ];
        }

        return ['min' => null, 'max' => null];
    }
}
