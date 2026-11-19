<?php
/*
 * @package      Webcode_Breadcrumbs
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2026 Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Webcode\Breadcrumbs\Observer;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Webcode\Breadcrumbs\Model\Source\Category;

/**
 * Clears the cached `primary_category` options when a category is saved, deleted or moved.
 *
 * A rename changes a label, a move changes a path — and Magento cleans its general
 * category tag only for new, deleted or menu-toggled categories, so the option cache
 * cannot rely on it. Global area: categories also change through the API and imports.
 */
class ClearPrimaryCategoryOptions implements ObserverInterface
{
    /**
     * @param CacheInterface $cache
     */
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Drops the cached option list; the next product form rebuilds it.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $this->cache->clean([Category::CACHE_TAG]);
    }
}
