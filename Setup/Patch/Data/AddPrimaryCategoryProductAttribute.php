<?php
/*
 * @package      Webcode_Breadcrumbs
 *
 * @author       Kostadin Bashev (bashev@webcode.bg)
 * @copyright    Copyright © 2022 Webcode Ltd. (https://webcode.bg/)
 * @license      https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Webcode\Breadcrumbs\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Validator\ValidateException;
use Psr\Log\LoggerInterface;
use Webcode\Breadcrumbs\Model\Source\Category;

readonly class AddPrimaryCategoryProductAttribute implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private ModuleDataSetupInterface $moduleDataSetup,
        private EavSetupFactory          $eavSetupFactory,
        private LoggerInterface          $logger
    ) {
    }

    /**
     * Run code inside patch
     * If code fails, patch must be reverted, in case when we are speaking about schema - then under revert
     * means run PatchInterface::revert()
     *
     * If we speak about data, under revert means: $transaction->rollback()
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        // Webcode_CategoryTree creates the same attribute with its own source; where it
        // already exists, leave it to its owner instead of rewriting its definition.
        if ($eavSetup->getAttributeId(Product::ENTITY, 'primary_category')) {
            return $this;
        }

        try {
            $eavSetup->addAttribute(
                Product::ENTITY,
                'primary_category',
                [
                    'is_visible_in_grid' => false,
                    'is_html_allowed_on_front' => false,
                    'visible_on_front' => false,
                    'visible' => true,
                    'global' => ScopedAttributeInterface::SCOPE_STORE,
                    'label' => 'Primary Category',
                    'source' => Category::class,
                    'type' => 'int',
                    'is_used_in_grid' => false,
                    'required' => false,
                    'input' => 'select',
                    'is_filterable_in_grid' => false,
                    'sort_order' => 50,
                    'group' => 'Search Engine Optimization',
                ]
            );
        } catch (LocalizedException|ValidateException $e) {
            $this->logger->error($e->getMessage());
        }

        return $this;
    }

    /**
     * Get array of patches that have to be executed prior to this.
     *
     * Example of implementation:
     *
     * [
     *      \Vendor_Name\Module_Name\Setup\Patch\Patch1::class,
     *      \Vendor_Name\Module_Name\Setup\Patch\Patch2::class
     * ]
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get aliases (previous names) for the patch.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
