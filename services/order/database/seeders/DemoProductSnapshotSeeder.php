<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DemoProductSnapshotSeeder extends Seeder
{
    private const string URL_NAMESPACE = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    private const string SEED_NAMESPACE = 'https://rentify.example/demo/catalog/v1/';

    /**
     * @var list<array{store: string, key: string, price_per_hour: int, deposit_amount: int, items: list<string>}>
     */
    private const array CATEGORIES = [
        [
            'store' => 'studio',
            'key' => 'photo-video',
            'price_per_hour' => 18000,
            'deposit_amount' => 4000000,
            'items' => ['Камера', 'Видеокамера', 'Экшн-камера', 'Штатив', 'Стабилизатор', 'Суфлёр'],
        ],
        [
            'store' => 'studio',
            'key' => 'lenses',
            'price_per_hour' => 10000,
            'deposit_amount' => 2000000,
            'items' => [
                'Объектив широкоугольный',
                'Объектив портретный',
                'Объектив макро',
                'Объектив зум',
                'Объектив фикс',
                'Объектив универсальный',
            ],
        ],
        [
            'store' => 'studio',
            'key' => 'lighting',
            'price_per_hour' => 5000,
            'deposit_amount' => 600000,
            'items' => [
                'Светодиодная панель',
                'Софтбокс',
                'Цветной светильник',
                'Стойка для света',
                'Отражатель',
                'Импульсная вспышка',
            ],
        ],
        [
            'store' => 'studio',
            'key' => 'sound',
            'price_per_hour' => 7000,
            'deposit_amount' => 1000000,
            'items' => [
                'Микрофон для интервью',
                'Петличный микрофон',
                'Аудиорекордер',
                'Микшер',
                'Студийный микрофон',
                'Поп-фильтр',
            ],
        ],
        [
            'store' => 'digital',
            'key' => 'computers',
            'price_per_hour' => 15000,
            'deposit_amount' => 5000000,
            'items' => ['Ноутбук', 'Рабочая станция', 'Монитор', 'Док-станция', 'Клавиатура', 'Веб-камера'],
        ],
        [
            'store' => 'digital',
            'key' => 'projectors',
            'price_per_hour' => 12000,
            'deposit_amount' => 2500000,
            'items' => [
                'Проектор',
                'Проекционный экран',
                'Медиаплеер',
                'Крепление для проектора',
                'Кабель HDMI',
                'Презентер',
            ],
        ],
        [
            'store' => 'digital',
            'key' => 'sound',
            'price_per_hour' => 5000,
            'deposit_amount' => 1000000,
            'items' => [
                'Наушники',
                'Портативная колонка',
                'Саундбар',
                'Аудиоинтерфейс',
                'Стойка для акустики',
                'Кабель XLR',
            ],
        ],
        [
            'store' => 'digital',
            'key' => 'mobile-testing',
            'price_per_hour' => 8000,
            'deposit_amount' => 1500000,
            'items' => [
                'Смартфон',
                'Планшет',
                'Чехол для теста',
                'Держатель для смартфона',
                'Кабель зарядки',
                'Тестовый адаптер',
            ],
        ],
        [
            'store' => 'workshop',
            'key' => 'tools',
            'price_per_hour' => 4000,
            'deposit_amount' => 500000,
            'items' => [
                'Дрель',
                'Шуруповёрт',
                'Перфоратор',
                'Электролобзик',
                'Шлифовальная машина',
                'Лазерный уровень',
            ],
        ],
    ];

    /** @var list<string> */
    private const array VARIANTS = ['Стандарт', 'Компакт', 'Профи', 'Люкс'];

    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (self::CATEGORIES as $category) {
            $storeId = $this->uuid5('store/' . $category['store']);
            $categoryId = $this->uuid5('category/' . $category['store'] . '/' . $category['key']);
            $position = 0;

            foreach ($category['items'] as $item) {
                foreach (self::VARIANTS as $variantIndex => $variant) {
                    $position++;

                    $rows[] = [
                        'product_id' => $this->uuid5(
                            'product/' . $category['store'] . '/' . $category['key'] . '/' . $position,
                        ),
                        'store_id' => $storeId,
                        'category_id' => $categoryId,
                        'name' => sprintf('DEMO %s %s', $item, $variant),
                        'price_per_hour' => $category['price_per_hour'] + $variantIndex * 500,
                        'deposit_amount' => $category['deposit_amount'] + $variantIndex * 100000,
                        'is_active' => true,
                        'source_occurred_at' => $now,
                        'deleted_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('product_snapshots')->upsert(
                $chunk,
                ['product_id'],
                [
                    'store_id',
                    'category_id',
                    'name',
                    'price_per_hour',
                    'deposit_amount',
                    'is_active',
                    'source_occurred_at',
                    'deleted_at',
                    'updated_at',
                ],
            );
        }

        $this->command?->info(sprintf('Seeded %d product snapshots.', count($rows)));
    }

    private function uuid5(string $key): string
    {
        $hash = sha1(pack('H*', str_replace('-', '', self::URL_NAMESPACE)) . self::SEED_NAMESPACE . $key, true);

        $hash[6] = chr((ord($hash[6]) & 0x0F) | 0x50);
        $hash[8] = chr((ord($hash[8]) & 0x3F) | 0x80);

        $hex = bin2hex($hash);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
