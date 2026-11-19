<?php
/*
 * @package      Webcode_Breadcrumbs
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2026 Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Webcode\Breadcrumbs\Test\Unit\Model\Source;

use ArrayIterator;
use Magento\Catalog\Model\Category as CategoryModel;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Webcode\Breadcrumbs\Model\Source\Category;
use Webcode\Breadcrumbs\Observer\ClearPrimaryCategoryOptions;

class CategoryTest extends TestCase
{
    private const OPTIONS = [
        ['value' => 2, 'label' => 'Default Category'],
        ['value' => 3, 'label' => 'Default Category / Modules'],
    ];

    /**
     * @param array<int,array{0:int,1:string,2:string}> $rows id, name, path
     */
    private function collectionFactory(array $rows): CollectionFactory
    {
        $categories = [];
        foreach ($rows as [$id, $name, $path]) {
            $category = $this->getMockBuilder(CategoryModel::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getId', 'getName', 'getPath'])
                ->getMock();
            $category->method('getId')->willReturn($id);
            $category->method('getName')->willReturn($name);
            $category->method('getPath')->willReturn($path);
            $categories[] = $category;
        }

        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addAttributeToSelect', 'addFieldToFilter', 'setStoreId', 'setOrder', 'getIterator'])
            ->getMock();
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setStoreId')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturnCallback(static fn () => new ArrayIterator($categories));

        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return $factory;
    }

    public function testCacheMissBuildsPathLabelsAndCachesThemUnderTheTag(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects(self::once())->method('save')->with(
            self::isType('string'),
            self::isType('string'),
            self::callback(static fn (array $tags): bool => in_array(Category::CACHE_TAG, $tags, true)),
            self::greaterThan(0)
        );

        $source = new Category(
            $this->collectionFactory([[2, 'Default Category', '1/2'], [3, 'Modules', '1/2/3']]),
            $cache,
            new Json()
        );

        self::assertSame(self::OPTIONS, $source->getAllOptions());
    }

    public function testCacheHitDoesNotLoadTheCategoryTree(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn((new Json())->serialize(self::OPTIONS));
        $cache->expects(self::never())->method('save');

        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects(self::never())->method('create');

        self::assertSame(self::OPTIONS, (new Category($factory, $cache, new Json()))->getAllOptions());
    }

    public function testBrokenCacheEntryIsRebuilt(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn('{not json');
        $cache->expects(self::once())->method('save');

        $source = new Category(
            $this->collectionFactory([[2, 'Default Category', '1/2'], [3, 'Modules', '1/2/3']]),
            $cache,
            new Json()
        );

        self::assertSame(self::OPTIONS, $source->getAllOptions());
    }

    public function testObserverCleansTheOptionTag(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('clean')->with([Category::CACHE_TAG]);

        (new ClearPrimaryCategoryOptions($cache))->execute(new Observer());
    }
}
