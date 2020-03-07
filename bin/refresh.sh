rm -rf generated/*
php bin/magento setup:di:compile

chmod -R 777 var/ pub/static generated
