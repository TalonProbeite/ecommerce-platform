<?php

declare(strict_types=1);

namespace Database\Seeders;

use Ramsey\Uuid\Uuid;

/**
 * Deterministic DEMO catalog. The dataset is generated in code so the seeder
 * does not depend on external APIs or committed product dumps.
 *
 * @phpstan-type AttributeRow array{name: string, value: string}
 * @phpstan-type ImageRow array{index: int, is_main: bool, mime_type: string}
 * @phpstan-type StoreRow array{id: string, name: string, description: string, location: string, active: bool}
 * @phpstan-type CategoryRow array{id: string, store_id: string, name: string, slug: string, parent_id: null}
 * @phpstan-type ProductRow array{
 *     id: string,
 *     store_id: string,
 *     category_id: string,
 *     category_key: string,
 *     position: int,
 *     name: string,
 *     slug: string,
 *     description: string,
 *     price_per_hour: int,
 *     deposit_amount: int,
 *     is_active: bool,
 *     attributes: list<AttributeRow>,
 *     stock: array{quantity: int, reserved: int},
 *     images: list<ImageRow>
 * }
 * @phpstan-type Dataset array{
 *     version: int,
 *     money_unit: string,
 *     stores: list<StoreRow>,
 *     categories: list<CategoryRow>,
 *     products: list<ProductRow>
 * }
 */
final class DemoCatalogDataset
{
    private const UUID_NAMESPACE = 'https://rentify.example/demo/catalog/v1/';

    /**
     * @var array<string, array{name: string, location: string}>
     */
    private const STORES = [
        'studio' => [
            'name' => 'DEMO Студия',
            'location' => 'Демонстрационная площадка — центр',
        ],
        'digital' => [
            'name' => 'DEMO Цифровая мастерская',
            'location' => 'Демонстрационная площадка — север',
        ],
        'workshop' => [
            'name' => 'DEMO Инструментальная',
            'location' => 'Демонстрационная площадка — юг',
        ],
    ];

    /**
     * @var list<array{
     *     store: string,
     *     key: string,
     *     name: string,
     *     price_per_hour: int,
     *     deposit_amount: int,
     *     items: list<string>,
     *     attributes: list<AttributeRow>
     * }>
     */
    private const CATEGORIES = [
        [
            'store' => 'studio',
            'key' => 'photo-video',
            'name' => 'Фото/видео',
            'price_per_hour' => 18000,
            'deposit_amount' => 4000000,
            'items' => ['Камера', 'Видеокамера', 'Экшн-камера', 'Штатив', 'Стабилизатор', 'Суфлёр'],
            'attributes' => [
                ['name' => 'Назначение', 'value' => 'Видеосъёмка'],
                ['name' => 'Питание', 'value' => 'От сети'],
            ],
        ],
        [
            'store' => 'studio',
            'key' => 'lenses',
            'name' => 'Объективы',
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
            'attributes' => [
                ['name' => 'Байонет', 'value' => 'Универсальный'],
                ['name' => 'Фокусное расстояние', 'value' => 'Переменное'],
            ],
        ],
        [
            'store' => 'studio',
            'key' => 'lighting',
            'name' => 'Освещение',
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
            'attributes' => [
                ['name' => 'Тип света', 'value' => 'Постоянный'],
                ['name' => 'Питание', 'value' => 'От сети'],
            ],
        ],
        [
            'store' => 'studio',
            'key' => 'sound',
            'name' => 'Звук',
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
            'attributes' => [
                ['name' => 'Тип', 'value' => 'Студийный'],
                ['name' => 'Подключение', 'value' => 'XLR'],
            ],
        ],
        [
            'store' => 'digital',
            'key' => 'computers',
            'name' => 'Компьютеры',
            'price_per_hour' => 15000,
            'deposit_amount' => 5000000,
            'items' => ['Ноутбук', 'Рабочая станция', 'Монитор', 'Док-станция', 'Клавиатура', 'Веб-камера'],
            'attributes' => [
                ['name' => 'Процессор', 'value' => 'Средний класс'],
                ['name' => 'Память', 'value' => '16 ГБ'],
            ],
        ],
        [
            'store' => 'digital',
            'key' => 'projectors',
            'name' => 'Проекторы',
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
            'attributes' => [
                ['name' => 'Разрешение', 'value' => 'Full HD'],
                ['name' => 'Подключение', 'value' => 'HDMI'],
            ],
        ],
        [
            'store' => 'digital',
            'key' => 'sound',
            'name' => 'Звук',
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
            'attributes' => [
                ['name' => 'Тип', 'value' => 'Накладные'],
                ['name' => 'Подключение', 'value' => 'Bluetooth'],
            ],
        ],
        [
            'store' => 'digital',
            'key' => 'mobile-testing',
            'name' => 'Смартфоны для тестирования',
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
            'attributes' => [
                ['name' => 'Платформа', 'value' => 'Android/iOS'],
                ['name' => 'Экран', 'value' => 'Сенсорный'],
            ],
        ],
        [
            'store' => 'workshop',
            'key' => 'tools',
            'name' => 'Инструменты',
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
            'attributes' => [
                ['name' => 'Питание', 'value' => 'Сетевое'],
                ['name' => 'Назначение', 'value' => 'Ремонт'],
            ],
        ],
    ];

    /** @var list<string> */
    private const VARIANTS = ['Стандарт', 'Компакт', 'Профи', 'Люкс'];

    /** @var list<string> */
    private const CONDITIONS = ['Отличное', 'Хорошее', 'Как новое'];

    /**
     * @return Dataset
     */
    public function make(): array
    {
        $stores = [];
        /** @var array<string, string> $storeIds */
        $storeIds = [];

        foreach (self::STORES as $key => $store) {
            $id = $this->id('store/' . $key);
            $storeIds[$key] = $id;
            $stores[] = [
                'id' => $id,
                'name' => $store['name'],
                'description' => 'DEMO seed data — fictional rental store.',
                'location' => $store['location'],
                'active' => true,
            ];
        }

        $categories = [];
        /** @var array<string, string> $categoryIds */
        $categoryIds = [];

        foreach (self::CATEGORIES as $category) {
            $key = $category['store'] . '/' . $category['key'];
            $id = $this->id('category/' . $key);
            $categoryIds[$key] = $id;
            $categories[] = [
                'id' => $id,
                'store_id' => $storeIds[$category['store']],
                'name' => 'DEMO ' . $category['name'],
                'slug' => 'demo-' . $category['key'],
                'parent_id' => null,
            ];
        }

        $products = [];

        foreach (self::CATEGORIES as $category) {
            $categoryKey = $category['store'] . '/' . $category['key'];
            $position = 0;

            foreach ($category['items'] as $itemIndex => $item) {
                foreach (self::VARIANTS as $variantIndex => $variant) {
                    $position++;
                    $attributes = $category['attributes'];
                    $attributes[] = ['name' => 'Класс', 'value' => $variant];
                    $attributes[] = [
                        'name' => 'Состояние',
                        'value' => self::CONDITIONS[($itemIndex + $variantIndex) % count(self::CONDITIONS)],
                    ];

                    $products[] = [
                        'id' => $this->id('product/' . $categoryKey . '/' . $position),
                        'store_id' => $storeIds[$category['store']],
                        'category_id' => $categoryIds[$categoryKey],
                        'category_key' => $category['key'],
                        'position' => $position,
                        'name' => 'DEMO ' . $item . ' ' . $variant,
                        'slug' => 'demo-' . $category['key'] . '-' . str_pad((string) $position, 2, '0', STR_PAD_LEFT),
                        'description' => sprintf(
                            'DEMO: %s (%s) — демонстрационный товар для аренды. Название, характеристики и условия вымышлены.',
                            $item,
                            $variant,
                        ),
                        'price_per_hour' => $category['price_per_hour'] + $variantIndex * 500,
                        'deposit_amount' => $category['deposit_amount'] + $variantIndex * 100000,
                        'is_active' => true,
                        'attributes' => $attributes,
                        'stock' => [
                            'quantity' => 2 + (($itemIndex + $variantIndex) % 7),
                            'reserved' => 0,
                        ],
                        'images' => [
                            ['index' => 0, 'is_main' => true, 'mime_type' => 'image/png'],
                            ['index' => 1, 'is_main' => false, 'mime_type' => 'image/png'],
                        ],
                    ];
                }
            }
        }

        return [
            'version' => 2,
            'money_unit' => 'kopecks',
            'stores' => $stores,
            'categories' => $categories,
            'products' => $products,
        ];
    }

    private function id(string $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, self::UUID_NAMESPACE . $key)->toString();
    }
}
