![alt tag](https://raw.github.com/conekta/conekta-magento/master/readme_files/cover.png)

Magento 2 Plugin v.4.1.0 (Stable)
========================

Installation for Magento 2.3
-----------

1. First add this repository in your composer config
```bash
composer config repositories.conekta git https://github.com/conekta/customer-magento-plugin
```

2. Add composer dependency
```bash
composer require conekta/conekta_payments dev-master
```

3. Update Magento
```bash
php bin/magento setup:upgrade
```

4. Compile the component
```bash
php bin/magento setup:di:compile
```

5. Enable plugin
```bash
php bin/magento module:enable Conekta_Payments 
```

6. Update and/or enable cache
```bash
bin/magento c:f
```

Installation for Magento 2.4
-----------

1. First add this repository in your composer config
```bash
composer config repositories.conekta git https://github.com/conekta/customer-magento-plugin
```

2. Add composer dependency
```bash
composer require conekta/conekta_payments dev-master
```

3. Update Magento
```bash
php bin/magento setup:upgrade
```

4. Compile the component
```bash
php bin/magento setup:di:compile
```

5. Enable plugin
```bash
php bin/magento module:enable Conekta_Payments 
```

6. Update and/or enable cache
```bash
bin/magento c:f
```

Updates
-----------

Plugin updates
-----------

1. List all the components
```bash
php bin/magento module:status 
```
2. Verify that the Conekta_Payments component is listed

3. Disable the module
```bash
php bin/magento module:disable conekta_payments --clear-static-content
```

4. Delete the files generated in the folder ```<path_magento>/generated/code/Conekta/```

5. Add composer dependency
```bash
composer require conekta/conekta_payments dev-master
```

6. Update Magento
```bash
php bin/magento setup:upgrade
```

7. Compile the component
```bash
php bin/magento setup:di:compile
```

8. Enable plugin
```bash
php bin/magento module:enable conekta_payments 
```

9. Update and/or enable cache
```bash
bin/magento c:f
```

Magento Version Compatibility
-----------------------------
The plugin has been tested in Magento 2.3 and 2.4 
Support is not guaranteed for untested versions.
