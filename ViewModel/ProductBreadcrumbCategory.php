<?php
/*
 * @package      Webcode_Breadcrumbs
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2022 Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Webcode\Breadcrumbs\ViewModel;

use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Category;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductBreadcrumbCategory implements ArgumentInterface
{
    /**
     * @var array
     */
    private array $storeCategories = [];

    /**
     * @param CatalogHelper $catalogHelper
     * @param Category $categoryHelper
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        private readonly CatalogHelper         $catalogHelper,
        private readonly Category              $categoryHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json                  $json,
        private readonly ModuleManager         $moduleManager
    ) {
    }

    /**
     * Get Store Specific Categories.
     *
     * @return array
     */
    private function getStoreCategories(): array
    {
        if (empty($this->storeCategories)) {
            foreach ($this->categoryHelper->getStoreCategories(false, true) as $category) {
                $this->storeCategories[] = $category->getId();
            }
        }

        return $this->storeCategories;
    }

    /**
     * Get Current Category of the product, for specific Store.
     *
     * @param Product|ProductInterface $product
     * @return CategoryInterface
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    private function getCurrentCategory(Product|ProductInterface $product): CategoryInterface
    {
        try {
            $productCategoriesCollection = $product->getCategoryCollection()
                ->addFieldToFilter('entity_id', ['in' => $this->getStoreCategories()]);

            $currentCategory = $productCategoriesCollection
                ->setOrder('level', 'desc')
                ->getFirstItem();
        } catch (NoSuchEntityException|LocalizedException) {
            $currentCategory = null;
        }

        if (isset($productCategoriesCollection) && $categoryCollection = clone $productCategoriesCollection) {
            foreach ($categoryCollection as $category) {
                if (($primaryCategory = (int)$product->getData('primary_category'))
                    && $primaryCategory != $this->storeManager->getStore()->getRootCategoryId()
                    && in_array($primaryCategory, $category->getPathIds())
                    && in_array($category->getId(), $this->getStoreCategories())
                ) {
                    $currentCategory = $category;
                    break;
                }
            }
        }

        return $currentCategory;
    }

    /**
     * Return Product Breadcrumbs with Categories.
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];
        $breadcrumbs['home'] = [
            'label' => __('Home'),
            'title' => __('Go to Home Page'),
            'link' => $this->storeManager->getStore()->getBaseUrl()
        ];

        /** @var Product $product */
        // Core's current product (set on the product page by the catalog controller);
        // Webcode_CurrentProduct did the same and is no longer a dependency.
        if ($product = $this->catalogHelper->getProduct()) {
            if ($currentCategory = $this->getCurrentCategory($product)) {
                $sortedCategories = [];

                foreach ($currentCategory->getParentCategories() as $category) {
                    $sortedCategories['category-' . $category->getLevel()] = [
                        'label' => $category->getName(),
                        'title' => $category->getName(),
                        'link' => $category->getUrl()
                    ];
                }
                ksort($sortedCategories);

                $breadcrumbs = array_merge($breadcrumbs, $sortedCategories);
            }

            $breadcrumbs['product'] = [
                'label' => $product->getName(),
                'title' => $product->getName(),
                'link' => null
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Return Breadcrumbs Structured Data Snippet.
     *
     * @return string|null
     */
    public function getBreadcrumbsJsonLd(): ?string
    {
        // Webcode_StructuredData prints the BreadcrumbList from these same crumbs.
        if ($this->moduleManager->isEnabled('Webcode_StructuredData')) {
            return null;
        }

        $jsonData = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList'
        ];

        $position = 1;
        try {
            foreach ($this->getBreadcrumbs() as $crumbInfo) {
                $item = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'name' => $crumbInfo['label']
                ];

                if (!empty($crumbInfo['link'])) {
                    $item['item'] = $crumbInfo['link'];
                }

                $jsonData['itemListElement'][] = $item;
                $position++;
            }
        } catch (NoSuchEntityException|LocalizedException) {
            return null;
        }

        return $this->json->serialize($jsonData);
    }
}
