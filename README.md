# Magento2
Bitcoin Payment Extension for Magento 2


# Installation via FTP
Installation of Magento requires some technical and system knowledge. You can findout more information at official Adobe Magento 2 documentation https://devdocs.magento.com/guides/v2.2/comp-mgr/install-extensions.html.
Also we would like to recommended PuTTY. It is a free implementation of SSH and Telnet for Windows and Unix platforms.

## Step 1
Download  https://github.com/Apirone/Magento2/archive/master.zip

## Step 2
Open directory /app/code/. Create folders /Apirone/Merchant and upack archive.

## Step 3
Connect to your host via SSH. Change directory to your store root.
Magento2 support CLI (Command Line Interface).
Execute next commang "sudo sh bin/magento-cli setup:upgrade". This command search new extensions and update the Store.

## Step 4
Execute command to clear cache "sudo sh bin/magento-cli cache:clean".
