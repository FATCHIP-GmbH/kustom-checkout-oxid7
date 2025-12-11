Kustom Checkout Module for the OXID eShop
=============================================================

## General ##

### Title: Kustom Checkout Module for the OXID eShop
### Author: Fatchip GmbH, https://www.fatchip.de
### Prefix: fckustom
### Version: 1.0.2
### Link: https://www.kustom.co/checkout
### Mail: support@fatchip.de

## Description ##

OXID eShop Plugin to integrate Kustom Checkout to OXID eShop Version >= 7.0.0

## Installation ##


### 1. Run composer require to install the package.

In the shop's main folder ( the one with composer.json file) run this command:

composer require fatchip-gmbh/kustom-checkout-oxid7:*

or add the following line within the "require" section to your composer.json file:

"fatchip-gmbh/kustom-checkout-oxid7": "*"

and run

composer install

## Update ##
For update instructions, please check the documentation.

## Testing ##

The tests are configured to work with OXIDs [docker-eshop-sdk-recipes](https://github.com/OXID-eSales/docker-eshop-sdk-recipes).
To set up the testing environment using the sdk-recipies, follow these steps:

- follow the installation instructions and chose `./recipes/oxid-esales/shop/b-7.0.x-ce-dev/run.sh` as the desired recipe.
- install the module using composer.
- activate the module.
- tweak the shop root's composer.json to prepare it for the oxid testing libraries dependencies:
```json
// notice: some packages need to be downgraded for the testing library to work.
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "squizlabs/php_codesniffer": "^3.5.4",
        "oxid-esales/testing-library": "dev-b-7.0.x",
        "codeception/module-webdriver": "^3",
        "codeception/lib-innerbrowser": "^3"
    }
```
- run `composer update`
- add a test_config.yml to your shop root and configure it for the module:
```yaml
mandatory_parameters:
  partial_module_paths: fatchip-gmbh/kustom-checkout-oxid7
  shop_path: /var/www/source

optional_parameters:
  activate_all_modules: true
  run_tests_for_shop: false
  run_tests_for_modules: true
```
- configure a symlink from source/modules to vendor/[MODULE REPO]
- run the tests using `phpunit -c [SOURCE/MODULES PATH]/phpunit.xml`

## Documentation ##

You'll find a detailed documentation in German here: https://wiki.fatchip.de/public/faqkustom7
and in English here: https://github.com/FATCHIP-GmbH/kustom-checkout-oxid7/wiki
