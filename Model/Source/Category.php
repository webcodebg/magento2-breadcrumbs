<?php
/*
 * @package      Webcode_Breadcrumbs
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2026 Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Webcode\Breadcrumbs\Model\Source;

use InvalidArgumentException;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Model\Cache\Type as EavCache;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Options of the `primary_category` attribute: every category, labelled by its path.
 *
 * Own source instead of Webcode_CategoryTree's, so the module installs on its own
 * (it is free and open source). Same option shape — value = category id, label =
 * "Parent / Child / Grandchild" from the first level under the tree root — so a
 * product that already holds a primary category keeps showing it.
 *
 * The list is built from the whole category tree, and the product form asks for it on
 * every open, so it is cached — as Webcode_CategoryTree did. A category save, delete or
 * move clears it (Observer\ClearPrimaryCategoryOptions); flushing the EAV cache too.
 */
class Category extends AbstractSource
{
    /**
     * Cache tag of the option list; cleaning it is how the list is refreshed.
     */
    public const CACHE_TAG = 'webcode_breadcrumbs_primary_category';

    private const CACHE_KEY = 'webcode_breadcrumbs_primary_category_options';

    /**
     * One month: the observer clears it on every change, the lifetime is only a backstop.
     */
    private const CACHE_LIFETIME = 2592000;

    private const PATH_SEPARATOR = ' / ';

    /**
     * @param CollectionFactory $collectionFactory
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Every category below the tree root, ordered by path, labelled by its names path.
     *
     * @return array<int,array{value:int,label:string}>
     */
    public function getAllOptions(): array
    {
        if ($this->_options !== null) {
            return $this->_options;
        }

        $cached = $this->loadCached();
        if ($cached !== null) {
            return $this->_options = $cached;
        }

        $options = $this->buildOptions();
        $this->cache->save(
            $this->serializer->serialize($options),
            self::CACHE_KEY,
            [self::CACHE_TAG, EavCache::CACHE_TAG],
            self::CACHE_LIFETIME
        );

        return $this->_options = $options;
    }

    /**
     * The cached option list, or null when there is none or it cannot be read.
     *
     * @return array<int,array{value:int,label:string}>|null
     */
    private function loadCached(): ?array
    {
        $data = $this->cache->load(self::CACHE_KEY);
        if (!is_string($data) || $data === '') {
            return null;
        }

        try {
            $options = $this->serializer->unserialize($data);
        } catch (InvalidArgumentException) {
            // A broken entry is rebuilt and overwritten, never shown.
            return null;
        }

        return is_array($options) ? $options : null;
    }

    /**
     * Builds the option list from the category tree.
     *
     * @return array<int,array{value:int,label:string}>
     */
    private function buildOptions(): array
    {
        $collection = $this->collectionFactory->create()
            ->addAttributeToSelect('name')
            ->addFieldToFilter('level', ['gt' => 0])
            ->setStoreId(0)
            ->setOrder('path', 'ASC');

        $names = [];
        foreach ($collection as $category) {
            $names[(int) $category->getId()] = (string) $category->getName();
        }

        $options = [];
        foreach ($collection as $category) {
            $labels = [];
            foreach (explode('/', (string) $category->getPath()) as $id) {
                if (isset($names[(int) $id])) {
                    $labels[] = $names[(int) $id];
                }
            }
            $options[] = ['value' => (int) $category->getId(), 'label' => implode(self::PATH_SEPARATOR, $labels)];
        }

        return $options;
    }
}
