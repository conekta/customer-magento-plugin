### 5.16 - 2024/11/04
* create new release
### 5.1.5 - 2024/10/28
* Fix:
  - Fix bundle products
### 5.1.4 - 2024/07/18
* Fix:
  - Fix recovery orders when quote has not a valid shipping address
  - Fix, error to install another payment method
### 5.1.3 - 2024/03/07
* Fix:
  - [CRD-191]  Fix bundle product. @fcarrero [#51]
  - Fix applied rules on missing orders module
  
### 5.1.2 - 2024/03/07
* Fix:
  - [CRD-154]  Fix shipping line taxes. @fcarrero [#50]

### 5.1.1 - 2024/02/08
* Fix:
  - [PAY22-2358]  Refund orders from magento panel. @fcarrero [#49]
  - Update conekta-php version to 6.0.5

### 5.1.0 - 2023/10/30 10:55
* Feat: 
  - [PAY22-2045] Magento - Mejoras para órdenes perdidas. @fcarrero [#46](https://github.com/conekta/customer-magento-plugin/pull/46)
  - Upgrade php version requerida.
  - Fix conekta.png route location
  - cancel orders
  - change status from suspect fraud to pending payment
### 5.0.5 - 2023/10/09 10:55
* Feat:
  - [PAY22-2045] Magento - remove old sdk-php code. @fcarrero [#45](https://github.com/conekta/customer-magento-plugin/pull/45)

### 5.0.4 - 2023/10/02 10:55
* Feat:
  - [PAY22-2045] Magento - Modificar tiempo expiración ref. cash y spei. @fcarrero [#43](https://github.com/conekta/customer-magento-plugin/pull/43)

### 5.0.3 - 2023/06/14 10:55
* Feat:
  - [CHLP-1326] Refactor plugin to use new php-conekta version. @elvisheredia [#42](https://github.com/conekta/customer-magento-plugin/pull/42)

### 4.2.1 - 2023/06/07 17:55
* Feat:
  - [CHLP-1344] Fix error when monthly_installments_options doesn´t exist in checkout. @elvisheredia [#41](https://github.com/conekta/customer-magento-plugin/pull/41)

### 4.2.0 - 2023/05/18 19:43
* Feat:
  - [CHLP-1284] Change name for payment methods OXXO and SPEI to cash and bank transfer. @elvisheredia [#40](https://github.com/conekta/customer-magento-plugin/pull/40)
    - Set order status to Payment Review when payment method was cash or bank transfer
    - Show additional info in one success page showing payment reference or CLABE
  - [CHLP-1284] Change default webhook to resolve error with change status to Cancel or Processing when offline payment was processed @elvisheredia [#40](https://github.com/conekta/customer-magento-plugin/pull/40)

### 4.1.9 - 2023/03/15 16:34
[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.8...4.1.9)
* Feat:
  - [CHLP-1076] Resolve error when fail update address in one sigle page checkout @elvisheredia [#37](https://github.com/conekta/customer-magento-plugin/pull/37)

### 4.1.8 - 2023/03/08 16:34
[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.7...4.1.8)
* Feat:
  - [CHLP-830] Add session control, to prevent errors when session has expired @elvisheredia [#36](https://github.com/conekta/customer-magento-plugin/pull/36)

### 4.1.7 - 2023/01/07 12:34
[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.6...4.1.7)
* Feat:
  - [CHLP-830] Órdenes Perdidas @elvisheredia [#35](https://github.com/conekta/customer-magento-plugin/pull/35)

### 4.1.6 - 2022/12/07 12:34
[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.6...4.1.5)
* Feat:
  - Fix error when phone is empty @elvisheredia [#34](https://github.com/conekta/customer-magento-plugin/pull/34)

### 4.1.5 - 2022/12/05 10:37
[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.5...4.1.4)
* Feat:
  - Fix error when array attribute by @elvisheredia [#32](https://github.com/conekta/customer-magento-plugin/pull/32)
  - Fix versions by @elvisheredia [#33](https://github.com/conekta/customer-magento-plugin/pull/33)

### 4.1.4 - 2022/09/28 16:36
[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.4...4.1.3)
* Feat:
  - Update Conekta logo by @elvisheredia [#31](https://github.com/conekta/customer-magento-plugin/pull/31)

### 4.1.3 - 2022/08/16 12:12
[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.2...4.1.1)
* Fix:
  - Update plugin name by @elvisheredia [#22](https://github.com/conekta/customer-magento-plugin/pull/22)

### 4.1.2 - 2022/08/12 17:25
* Update readme & remove monolog by @agatto-conekta [#26](https://github.com/conekta/customer-magento-plugin/pull/26)

### 4.1.1 - 2022/07/25 18:02

[Full Changelog](https://github.com/conekta/customer-magento-plugin/compare/4.1.0...4.1.1)
* Fix:
  -  Set 200 in webhook when event was not required by @elvisheredia [#21](https://github.com/conekta/customer-magento-plugin/pull/21)

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
