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

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * Options of the `primary_category` attribute: every category, labelled by its path.
 *
 * Own source instead of Webcode_CategoryTree's, so the module installs on its own
 * (it is free and open source). Same option shape — value = category id, label =
 * "Parent / Child / Grandchild" from the first level under the tree root — so a
 * product that already holds a primary category keeps showing it.
 */
class Category extends AbstractSource
{
    private const PATH_SEPARATOR = ' / ';

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
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

        return $this->_options = $options;
    }
}
