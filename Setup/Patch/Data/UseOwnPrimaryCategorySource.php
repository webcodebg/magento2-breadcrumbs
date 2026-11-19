<?php
/*
 * @package      Webcode_Breadcrumbs
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2026 Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Webcode\Breadcrumbs\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Webcode\Breadcrumbs\Model\Source\Category;

/**
 * Points `primary_category` at this module's source when Webcode_CategoryTree's is gone.
 *
 * Up to 1.2.x the attribute was created with `Webcode\CategoryTree\Model\Source\Category`.
 * Breadcrumbs no longer requires category-tree, so `composer update` may remove it — and
 * an attribute whose source class no longer exists breaks the admin product form (500)
 * while the storefront keeps working. Where category-tree is still installed its source
 * is left alone: Webcode_CategoryTree creates the same attribute and owns it there.
 */
class UseOwnPrimaryCategorySource implements DataPatchInterface
{
    // `::class` resolves to the name without autoloading, so it is safe when the
    // category-tree package is gone — exactly the case this patch exists for.
    private const CATEGORY_TREE_SOURCE = \Webcode\CategoryTree\Model\Source\Category::class;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * Switches the source model only when it names a class that does not exist.
     *
     * @return $this
     */
    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $source = (string) $eavSetup->getAttribute(Product::ENTITY, 'primary_category', 'source_model');

        if ($source === self::CATEGORY_TREE_SOURCE && !class_exists($source)) {
            $eavSetup->updateAttribute(Product::ENTITY, 'primary_category', 'source_model', Category::class);
        }

        return $this;
    }

    /**
     * Runs after the patch that creates the attribute.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [AddPrimaryCategoryProductAttribute::class];
    }

    /**
     * No former names.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
