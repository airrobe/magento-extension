#!/bin/bash

set -e

if [ ! -f "./composer.json" ]; then
    echo "composer.json not found. Please run this installation script on your Magento project's root folder"
    exit
fi

# Account for both ways composer can be installed in the system
if [ -f "./composer.phar" ]; then
  composer_cmd="php composer.phar"
elif command -v composer &> /dev/null; then
  composer_cmd="composer"
else
  echo "composer command not found"
  exit
fi

echo "--- Using composer command '${composer_cmd}' ---"

echo "--- Configuring airrobe repository ---"

eval "$composer_cmd config repositories.airrobe vcs git@github.com:airrobe/magento-extension.git"

echo "--- Installing airrobe/thecircularwardrobe ---"

eval "$composer_cmd require airrobe/thecircularwardrobe"

echo "--- Enabling airrobe/thecircularwardrobe ---"

php bin/magento module:enable AirRobe_TheCircularWardrobe

echo "--- Running magento setup:di:compile --- "

php bin/magento setup:di:compile

echo "--- Running magento setup:upgrade --- "

php bin/magento setup:upgrade

echo "--- Running magento cache:flush --- "

php bin/magento cache:flush