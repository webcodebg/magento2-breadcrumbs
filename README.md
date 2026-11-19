# Webcode_Breadcrumbs

Full category breadcrumbs on the product page (Home / Category / Subcategory / Product), following the product's primary category when it has one — for visitors and for search engines.

## How

- Magento shows only *Home / Product* when a product page is opened directly (from search, a link or Google). This module always builds the full category path.
- **Primary Category** (product attribute, Search Engine Optimization group): when set, the breadcrumbs follow the product's deepest category in that branch. Without it, the deepest category the product belongs to in the current store is used.
- The breadcrumbs also go out as a schema.org `BreadcrumbList` (JSON-LD). When `Webcode_StructuredData` is enabled it prints the `BreadcrumbList` from the same crumbs, so the module leaves it out to avoid a duplicate.
- The current product comes from `webcode/module-current-product`, not from the deprecated core registry.

## Configuration

None. Set **Primary Category** on the products whose breadcrumbs should follow a particular branch.

## Installation

Webcode's free modules are installed from our own Composer repository — they are not published on Packagist.

```bash
composer config repositories.webcode composer https://repo.webcode.bg/public
composer require webcode/module-breadcrumbs
bin/magento module:enable Webcode_Breadcrumbs
bin/magento setup:upgrade
```

## Compatibility

Magento Open Source and Mage-OS 2.4.7–2.4.9, PHP 8.3–8.5.

## License

[OSL-3.0](LICENSE.txt) and [AFL-3.0](LICENSE_AFL.txt), like Magento itself.

## About

Made by [Webcode](https://webcode.bg/), a Magento development and support studio from Bulgaria. Module page: <https://webcode.bg/en/breadcrumbs>.
