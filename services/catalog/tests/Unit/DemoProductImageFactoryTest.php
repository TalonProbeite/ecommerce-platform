<?php

namespace Tests\Unit;

use Database\Seeders\DemoProductImageFactory;
use PHPUnit\Framework\TestCase;

class DemoProductImageFactoryTest extends TestCase
{
    public function test_rgb_scanlines_match_png_dimensions(): void
    {
        $png = (new DemoProductImageFactory())->png('photo-video', 1, 0);
        $dimensions = unpack('Nwidth/Nheight', substr($png, 16, 8));
        $compressed = '';
        for ($offset = 8; $offset < strlen($png);) {
            $length = unpack('N', substr($png, $offset, 4))[1];
            if (substr($png, $offset + 4, 4) === 'IDAT') {
                $compressed .= substr($png, $offset + 8, $length);
            }
            $offset += 12 + $length;
        }
        $raw = gzuncompress($compressed);
        self::assertNotFalse($raw);
        $stride = 1 + $dimensions['width'] * 3;
        self::assertSame($stride * $dimensions['height'], strlen($raw));
        foreach (str_split($raw, $stride) as $row) {
            self::assertSame($stride, strlen($row));
            self::assertSame("\0", $row[0]);
        }
    }
}
