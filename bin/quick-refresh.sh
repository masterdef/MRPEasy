php -f bin/magento config:set dev/css/minify_files 0
rm -rf var/cache/*

chmod -R 777 var/ pub/static generated
