### 4.1.1 - 2022/07/25 18:02

[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.0...4.1.1)
* Fix:
  -  Set 200 in webhook when event was not required by @elvisheredia [#21](https://github.com/conekta/customer-magento-plugin/pull/21)
  -  Update plugin name by @elvisheredia [#22](https://github.com/conekta/customer-magento-plugin/pull/22)

Magento 4.1.0, 2021-12-23
-------------------------------------
- Fix in OXXO order date format in magento admin.
- Fix in purchases with free shipping.
- Fixes for the marketplace store.
- Fixes to pass the MFTF tests.
- Listen to event notifications in the webhook saved in the store administration.
- Authentication fix for magento 2.3
- Fix of magneto status update in the Comments History section.
- Fix for embedded checkout, only X amount of charges are added to the same order.
- Added support for magento enterprise.
- Added sanitization of special characters to checkout.
- Fix to decimals.
- Conekta plugin version added as metadata.
- Sanitation of special fields also applied to SKU.