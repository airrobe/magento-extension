# Magento2 Module AirRobe - TheCircularWardrobe

    ``airrobe/thecircularwardrobe``

## Table of contents
 - [Requirements](#requirements)
 - [Installation](#installation)
 - [Contributing](#contributing)

## Requirements

### Software versions
This release is compatible with:
- PHP 8.2
- Magento 2.4.6

### AirRobe API
This module requires an AirRobe account and access keys.

Please [contact us](mailto:developers@airrobe.com) if you do not yet have an account.

Once you have an account, login in to our [Connector Dashboard](https://connector.airrobe.com) and view our [documentation](https://connector.airrobe.com/docs/magento) to get your access keys.

## Installation

### Type 1: Zip file

 - Unzip the zip file in `app/code/AirRobe`
 - Enable the module by running `php bin/magento module:enable AirRobe_TheCircularWardrobe`
 - Apply database updates by running `php bin/magento setup:upgrade --keep-generated`
 - Flush the cache by running `php bin/magento cache:flush`

### Type 2: Composer
 - Install the module composer by running `composer require airrobe/thecircularwardrobe "^1.0"`
 - enable the module by running `bin/magento module:enable AirRobe_TheCircularWardrobe`
 - apply database updates by running `bin/magento setup:upgrade --keep-generated`
 - Flush the cache by running `bin/magento cache:flush`

Once installed, login in to our [Connector Dashboard](https://connector.airrobe.com) and view the [documentation](https://connector.airrobe.com/docs/magento) for instructions on how to configure the module.

## Contributing

Use these steps to create your own environment with a clean magento install with dummy data
to test the module or contribute to the extension.

### Setup PhpStorm

If contributing to this project, we highly recommend using the IDE PhpStorm and the following plugins:
- [Magento PhpStorm](https://plugins.jetbrains.com/plugin/8024-magento-phpstorm)
- [Magento and Adobe Commerce PhpStorm by Atwix](https://plugins.jetbrains.com/plugin/20554-magento-and-adobe-commerce-phpstorm-by-atwix)

Set your PHP Language Level and CLI interpreter to PHP 8.2 under Settings > PHP

Configure the Magento PhpStorm plugin under Settings > PHP > Frameworks > Magento
- Set the Magento installation path to `src`
- Regenerate URN mappings or alternatively run `bin/magento dev:urn-catalog:generate .idea/misc.xml`

### Setup a PHP 8.2 + Magento 2.4.6 clean install and environment

- `mkdir docker-magento`
- `cd docker-magento`
- `curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/template | bash`
- Modify the `compose.yaml` file with the following changes
  ```
  phpfpm:
    image: markoshust/magento-php:8.2-fpm-4
  ```
- Download and install Magento 2.4.6 with the following commands
```
  bin/download 2.4.6 community
  bin/setup magento.test
  bin/magento deploy:mode:set developer
  bin/magento sampledata:deploy
  bin/composer require markshust/magento2-module-disabletwofactorauth
  bin/magento module:enable MarkShust_DisableTwoFactorAuth
  bin/magento setup:upgrade
```
- Add the AirRobe repo into the `app/code` directory and enable the module
```
  cd src/app/code
  mkdir AirRobe
  git clone git@github.com:airrobe/magento-extension.git TheCircularWardrobe
  bin/magento module:enable AirRobe_TheCircularWardrobe
  bin/magento setup:upgrade --keep-generated
  bin/magento cache:flush
```
- You can find the Magento Admin login details in the file `env/magento.env`
- Submit any changes in a PR to the php82-magento246 branch
