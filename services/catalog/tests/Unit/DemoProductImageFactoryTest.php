<?php

namespace Tests\Unit;

use Database\Seeders\DemoProductImageFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

    public function test_rect_writes_rgb_bytes_at_the_pixel_byte_offset_without_resizing_the_buffer(): void
    {
        $factory = new DemoProductImageFactory();
        $rows = array_fill(0, 4, str_repeat("\x00\x00\x00", 4));
        $before = array_map('strlen', $rows);

        $method = new ReflectionMethod($factory, 'rect');
        $args = [&$rows, 1, 2, 1, 1, [10, 20, 30]];
        $method->invokeArgs($factory, $args);

        self::assertSame($before, array_map('strlen', $rows));
        self::assertSame(pack('C3', 10, 20, 30), substr($rows[2], 3, 3));
        self::assertSame(str_repeat("\x00\x00\x00", 4), $rows[1]);
        self::assertSame(str_repeat("\x00\x00\x00", 4), $rows[3]);
    }
}
