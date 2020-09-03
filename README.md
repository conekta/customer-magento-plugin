![alt tag](https://raw.github.com/conekta/conekta-magento/master/readme_files/cover.png)

Magento 2 Plugin v.2.3 (Stable)
========================

Installation
-----------

1. First add this repository in your composer config

    ```bash
    composer config repositories.conekta git https://github.com/conekta/magento2.3
    ```
2. Add the dependency

    ```bash
    composer require conekta/conekta_payments dev-master
    ```
3. Update your Magento

    ```bash
    php bin/magento setup:upgrade
    ```
4. Compile your XML files

    ```bash
    php bin/magento setup:di:compile
    ```
    
Updates
-----------

For update this plugin execute the next command

```bash
php bin/magento setup:upgrade # version bump
php bin/magento setup:di:compile # version bump
composer update conekta/magento2-module
```

Magento Version Compatibility
-----------------------------
The plugin has been tested in Magento 2.3 Support is not guaranteed for untested versions.
