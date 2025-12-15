# AllSecure EXCHANGE Magento™ Plugin

Accept payments in your Magento store using AllSecure **EXCHANGE** Platform.

- Current version: 2.0.3
- Tested with Magento: 2.4.8
- Tested with PHP: 8.4


See a fully functional WooCommerce <a href="https://mage-demo.allsecure.xyz/" target="_new">demo store</a> with AllSecure **EXCHANGE** as a payment gateway.

## How to install extension on magento2

1. copy folder Allsecureexchange to your magento2 /app/code/ directory.
2. go to your magento2 root directory.
3. run this command :
   - php bin/magento module:enable Allsecureexchange_Allsecureexchange
   - php bin/magento setup:upgrade
   - php bin/magento setup:static-content:deploy
4. clear cache - php bin/magento cache:clean
5. setting folders and files permission.

## How to reindex on magento2 :
1. go to your magento2 root directory.
2. run this command : php bin/magento indexer:reindex

## How to update extension on magento2 :
1. copy file from src directory to your magento2 root directory.
2. go to your magento2 root directory.
3. run this command :
   - sudo rm -rf var/generation/*
   - sudo rm -rf pub/static/*
   - php bin/magento setup:upgrade
   - php bin/magento setup:static-content:deploy
4. setting folders and files permission.
5. clear cache - php bin/magento cache:clean

