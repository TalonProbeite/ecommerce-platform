<?php

declare(strict_types=1);

namespace Database\Seeders;

use RuntimeException;

/**
 * Generates small, deterministic PNG placeholders without GD or external files.
 * The generated objects are real images, so the usual product image endpoints
 * and storage URL builder work exactly as they do for uploaded images.
 */
final class DemoProductImageFactory
{
    private const WIDTH = 480;
    private const HEIGHT = 360;

    public function png(string $categoryKey, int $position, int $imageIndex): string
    {
        $palette = $this->palette($categoryKey);
        $shift = (($position * 13) + ($imageIndex * 29)) % 41 - 20;

        $top = $this->adjust($palette['top'], $shift);
        $bottom = $this->adjust($palette['bottom'], -$shift);
        $accent = $this->adjust($palette['accent'], $shift);
        $surface = $palette['surface'];
        $ink = $palette['ink'];

        $rows = [];

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $ratio = $y / (self::HEIGHT - 1);
            $rows[$y] = str_repeat($this->pixel($this->mix($top, $bottom, $ratio)), self::WIDTH);
        }

        $this->rect($rows, 36 + ($imageIndex * 10), 40, 88 + ($position % 5) * 6, 88 + ($position % 4) * 5, $this->mix($accent, $bottom, 0.55));
        $this->circle($rows, self::WIDTH - 70 - ($position % 6) * 7, 70 + ($position % 5) * 9, 44 + ($position % 4) * 6, $this->mix($accent, $bottom, 0.4));

        $cardWidth = 280;
        $cardHeight = 200;
        $cardX = intdiv(self::WIDTH - $cardWidth, 2) + ($imageIndex === 0 ? 0 : 14);
        $cardY = intdiv(self::HEIGHT - $cardHeight, 2) + ($imageIndex === 0 ? 0 : -10);

        $this->rect($rows, $cardX, $cardY, $cardWidth, $cardHeight, $surface);
        $this->rect($rows, $cardX, $cardY, $cardWidth, 8, $accent);
        $this->icon($rows, $categoryKey, $cardX, $cardY, $cardWidth, $cardHeight, $accent, $ink);

        $raw = '';

        foreach ($rows as $row) {
            $raw .= "\x00" . $row;
        }

        $compressed = gzcompress($raw, 6);

        if ($compressed === false) {
            throw new RuntimeException('Unable to compress the generated demo image.');
        }

        return $this->pngEnvelope($compressed);
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function icon(
        array &$rows,
        string $categoryKey,
        int $cardX,
        int $cardY,
        int $cardWidth,
        int $cardHeight,
        array $color,
        array $ink,
    ): void {
        $cx = $cardX + intdiv($cardWidth, 2);
        $cy = $cardY + intdiv($cardHeight, 2) + 10;

        switch ($categoryKey) {
            case 'photo-video':
                $this->cameraIcon($rows, $cx, $cy, $color, $ink);
                break;
            case 'lenses':
                $this->lensIcon($rows, $cx, $cy, $color, $ink);
                break;
            case 'lighting':
                $this->lightingIcon($rows, $cx, $cy, $color, $ink);
                break;
            case 'sound':
                $this->soundIcon($rows, $cx, $cy, $color, $ink);
                break;
            case 'computers':
                $this->computerIcon($rows, $cx, $cy, $color, $ink);
                break;
            case 'projectors':
                $this->projectorIcon($rows, $cx, $cy, $color, $ink);
                break;
            case 'mobile-testing':
                $this->mobileIcon($rows, $cx, $cy, $color, $ink);
                break;
            case 'tools':
                $this->toolIcon($rows, $cx, $cy, $color, $ink);
                break;
            default:
                $this->abstractIcon($rows, $cx, $cy, $color, $ink);
                break;
        }
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function cameraIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->rect($rows, $cx - 30, $cy - 48, 60, 18, $ink);
        $this->rect($rows, $cx - 70, $cy - 32, 140, 82, $ink);
        $this->circle($rows, $cx, $cy + 10, 33, $color);
        $this->circle($rows, $cx, $cy + 10, 20, $ink);
        $this->circle($rows, $cx - 9, $cy + 1, 7, $this->mix($color, [255, 255, 255], 0.55));
        $this->rect($rows, $cx + 42, $cy - 24, 16, 10, $color);
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function lensIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->circle($rows, $cx, $cy, 58, $ink);
        $this->circle($rows, $cx, $cy, 46, $color);
        $this->circle($rows, $cx, $cy, 27, $ink);
        $this->circle($rows, $cx - 14, $cy - 14, 9, $this->mix($color, [255, 255, 255], 0.6));
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function lightingIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->rect($rows, $cx - 4, $cy - 10, 8, 82, $ink);
        $this->rect($rows, $cx - 56, $cy - 64, 112, 58, $color);
        $this->rect($rows, $cx - 34, $cy - 72, 68, 8, $ink);
        $this->rect($rows, $cx - 36, $cy + 72, 72, 10, $ink);

        foreach ([-1, 1] as $direction) {
            for ($i = 0; $i < 3; $i++) {
                $this->rect($rows, $cx + $direction * (62 + $i * 12) - 3, $cy - 50 + $i * 17, 7, 7, $color);
            }
        }
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function soundIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->rect($rows, $cx - 60, $cy - 58, 120, 116, $ink);
        $this->circle($rows, $cx, $cy + 10, 32, $color);
        $this->circle($rows, $cx, $cy - 34, 16, $color);
        $this->circle($rows, $cx, $cy + 10, 9, $ink);
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function computerIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->rect($rows, $cx - 72, $cy - 58, 144, 88, $ink);
        $this->rect($rows, $cx - 61, $cy - 47, 122, 66, $color);
        $this->rect($rows, $cx - 36, $cy + 32, 72, 10, $ink);
        $this->rect($rows, $cx - 58, $cy + 48, 116, 8, $ink);
        $this->rect($rows, $cx - 18, $cy + 58, 36, 5, $ink);
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function projectorIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->rect($rows, $cx - 62, $cy - 28, 124, 62, $ink);
        $this->circle($rows, $cx + 32, $cy + 4, 19, $color);
        $this->rect($rows, $cx - 48, $cy - 12, 38, 8, $color);
        $this->rect($rows, $cx - 48, $cy + 4, 26, 6, $color);

        for ($i = 0; $i < 30; $i++) {
            $halfWidth = intdiv($i, 2) + 3;
            $this->rect($rows, $cx + 54 + $i, $cy + 4 - $halfWidth, 2, $halfWidth * 2, $this->mix($color, $this->pixelColor($rows, $cx, $cy), 0.65));
        }
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function mobileIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->rect($rows, $cx - 40, $cy - 72, 80, 144, $ink);
        $this->rect($rows, $cx - 32, $cy - 60, 64, 108, $color);
        $this->rect($rows, $cx - 12, $cy - 67, 24, 4, $color);
        $this->circle($rows, $cx, $cy + 56, 9, $color);
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function toolIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->rect($rows, $cx - 64, $cy - 32, 100, 56, $ink);
        $this->rect($rows, $cx - 36, $cy + 22, 34, 64, $ink);
        $this->rect($rows, $cx + 36, $cy - 18, 28, 22, $color);
        $this->rect($rows, $cx + 64, $cy - 10, 28, 6, $color);
        $this->circle($rows, $cx - 32, $cy - 4, 10, $color);
        $this->rect($rows, $cx - 58, $cy + 32, 18, 6, $color);
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     * @param array{int, int, int} $ink
     */
    private function abstractIcon(array &$rows, int $cx, int $cy, array $color, array $ink): void
    {
        $this->circle($rows, $cx - 28, $cy - 14, 36, $color);
        $this->rect($rows, $cx + 8, $cy - 48, 48, 48, $ink);
        $this->circle($rows, $cx + 20, $cy + 32, 24, $ink);
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     */
    private function rect(array &$rows, int $x, int $y, int $width, int $height, array $color): void
    {
        $x0 = max(0, $x);
        $y0 = max(0, $y);
        $x1 = min(self::WIDTH, $x + $width);
        $y1 = min(self::HEIGHT, $y + $height);

        if ($x1 <= $x0 || $y1 <= $y0) {
            return;
        }

        $pixel = str_repeat($this->pixel($color), $x1 - $x0);

        for ($row = $y0; $row < $y1; $row++) {
            $rows[$row] = substr_replace($rows[$row], $pixel, $x0 * 3, ($x1 - $x0) * 3);
        }
    }

    /**
     * @param array<int, string> $rows
     * @param array{int, int, int} $color
     */
    private function circle(array &$rows, int $cx, int $cy, int $radius, array $color): void
    {
        if ($radius <= 0) {
            return;
        }

        for ($dy = -$radius; $dy <= $radius; $dy++) {
            $y = $cy + $dy;

            if ($y < 0 || $y >= self::HEIGHT) {
                continue;
            }

            $dx = (int) floor(sqrt($radius * $radius - $dy * $dy));
            $this->rect($rows, $cx - $dx, $y, $dx * 2 + 1, 1, $color);
        }
    }

    /**
     * @param array<int, string> $rows
     * @return array{int, int, int}
     */
    private function pixelColor(array $rows, int $x, int $y): array
    {
        $x = max(0, min(self::WIDTH - 1, $x));
        $y = max(0, min(self::HEIGHT - 1, $y));
        $offset = $x * 3;

        return [
            ord($rows[$y][$offset]),
            ord($rows[$y][$offset + 1]),
            ord($rows[$y][$offset + 2]),
        ];
    }

    /**
     * @return array{
     *     top: array{int, int, int},
     *     bottom: array{int, int, int},
     *     accent: array{int, int, int},
     *     surface: array{int, int, int},
     *     ink: array{int, int, int}
     * }
     */
    private function palette(string $categoryKey): array
    {
        return match ($categoryKey) {
            'photo-video' => [
                'top' => [17, 24, 39],
                'bottom' => [30, 64, 86],
                'accent' => [34, 211, 238],
                'surface' => [15, 23, 42],
                'ink' => [226, 232, 240],
            ],
            'lenses' => [
                'top' => [30, 27, 75],
                'bottom' => [67, 56, 202],
                'accent' => [129, 140, 248],
                'surface' => [30, 27, 75],
                'ink' => [238, 242, 255],
            ],
            'lighting' => [
                'top' => [69, 26, 3],
                'bottom' => [180, 83, 9],
                'accent' => [251, 191, 36],
                'surface' => [69, 26, 3],
                'ink' => [255, 251, 235],
            ],
            'sound' => [
                'top' => [59, 7, 100],
                'bottom' => [126, 34, 206],
                'accent' => [232, 121, 249],
                'surface' => [59, 7, 100],
                'ink' => [250, 232, 255],
            ],
            'computers' => [
                'top' => [8, 47, 73],
                'bottom' => [14, 116, 144],
                'accent' => [34, 211, 238],
                'surface' => [8, 47, 73],
                'ink' => [236, 254, 255],
            ],
            'projectors' => [
                'top' => [46, 16, 101],
                'bottom' => [91, 33, 182],
                'accent' => [196, 181, 253],
                'surface' => [46, 16, 101],
                'ink' => [245, 243, 255],
            ],
            'mobile-testing' => [
                'top' => [6, 78, 59],
                'bottom' => [5, 150, 105],
                'accent' => [52, 211, 153],
                'surface' => [6, 78, 59],
                'ink' => [236, 253, 245],
            ],
            'tools' => [
                'top' => [124, 45, 18],
                'bottom' => [194, 65, 12],
                'accent' => [251, 146, 60],
                'surface' => [67, 20, 7],
                'ink' => [255, 247, 237],
            ],
            default => [
                'top' => [15, 23, 42],
                'bottom' => [51, 65, 85],
                'accent' => [148, 163, 184],
                'surface' => [15, 23, 42],
                'ink' => [241, 245, 249],
            ],
        };
    }

    /**
     * @param array{int, int, int} $color
     * @return array{int, int, int}
     */
    private function adjust(array $color, int $shift): array
    {
        return [
            max(0, min(255, $color[0] + $shift)),
            max(0, min(255, $color[1] + $shift)),
            max(0, min(255, $color[2] + $shift)),
        ];
    }

    /**
     * @param array{int, int, int} $from
     * @param array{int, int, int} $to
     * @return array{int, int, int}
     */
    private function mix(array $from, array $to, float $ratio): array
    {
        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $ratio),
            (int) round($from[1] + ($to[1] - $from[1]) * $ratio),
            (int) round($from[2] + ($to[2] - $from[2]) * $ratio),
        ];
    }

    /**
     * @param array{int, int, int} $color
     */
    private function pixel(array $color): string
    {
        return pack('C3', ...$color);
    }

    private function pngEnvelope(string $compressed): string
    {
        return "\x89PNG\r\n\x1a\n"
            . $this->chunk('IHDR', pack('NNCCCCC', self::WIDTH, self::HEIGHT, 8, 2, 0, 0, 0))
            . $this->chunk('IDAT', $compressed)
            . $this->chunk('IEND', '');
    }

    private function chunk(string $type, string $data): string
    {
        $body = $type . $data;

        return pack('N', strlen($data)) . $body . pack('N', crc32($body) & 0xffffffff);
    }
}
