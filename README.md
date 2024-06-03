# Magento2 Module AirRobe - TheCircularWardrobe

    ``airrobe/thecircularwardrobe``

## Table of contents
 - [Requirements](#requirements)
 - [Installation](#installation)
 - [Contributing](#contributing)

## Requirements

### Software versions
This release is compatible with:
- Magento 2.4.6 + PHP 8.2
- Magento 2.4.7 + PHP 8.2 or 8.3

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
 - Install the module composer by running `composer require airrobe/thecircularwardrobe`
 - enable the module by running `bin/magento module:enable AirRobe_TheCircularWardrobe`
 - apply database updates by running `bin/magento setup:upgrade --keep-generated`
 - Flush the cache by running `bin/magento cache:flush`

Once installed, login in to our [Connector Dashboard](https://connector.airrobe.com) and view the [documentation](https://connector.airrobe.com/docs/magento) for instructions on how to configure the module.

### Uninstalling the AirRobe extension
 - Disable the module by running `php bin/magento module:disable AirRobe_TheCircularWardrobe`
 - Run `php bin/magento setup:upgrade`
 - Remove the module by running `composer remove airrobe/thecircularwardrobe`
 - Flush the Magento cache by running `bin/magento cache:flush`
 - Remove the module by running 
   - Type 1: `rm -rf app/code/AirRobe/TheCircularWardrobe`
   - Type 2: `composer remove airrobe/thecircularwardrobe`

## Contributing

Use these steps to create your own environment with a clean magento install with dummy data
to test the module or contribute to the extension.

### Set up a PHP + Magento clean install and environment

See [markshust/docker-magento](https://github.com/markshust/docker-magento) for more information, options and commands.

- Create your project folder and Download the docker-magento template
```
mkdir docker-magento
cd docker-magento
curl -s https://raw.githubusercontent.com/markshust/docker-magento/master/lib/template | bash
```

- Check the `compose.yaml` file to ensure the PHP version matches your target. For PHP 8.2, the following line should be present:
```
phpfpm:
  image: markoshust/magento-php:8.2-fpm-4
```

- Download and install Magento with the following command (replace `2.4.7` with the desired version)
```
bin/download 2.4.7 community
```

### Set up the development environment
```
bin/setup magento.test
bin/magento sampledata:deploy
bin/magento setup:upgrade
bin/composer require markshust/magento2-module-disabletwofactorauth
bin/magento module:enable MarkShust_DisableTwoFactorAuth
bin/magento setup:upgrade
```

### Clone the AirRobe magento extension

Add the AirRobe repo into the `app/code` directory and enable the module

```
cd src/app/code
mkdir AirRobe
cd AirRobe
git clone git@github.com:airrobe/magento-extension.git TheCircularWardrobe
cd ../../../../
bin/magento module:enable AirRobe_TheCircularWardrobe
bin/magento setup:upgrade
bin/magento cache:flush
```

- See the [documentation](https://connector.airrobe.com/docs/magento) for instructions on how to configure the module.
- You can find the Magento Admin login details in the file `env/magento.env`
- Submit a Pull Request and our maintainers will respond as soon as possible

### Setup PhpStorm

If contributing to this project, we highly recommend using the IDE PhpStorm and the following plugins:
- [Magento PhpStorm](https://plugins.jetbrains.com/plugin/8024-magento-phpstorm)
- [Magento and Adobe Commerce PhpStorm by Atwix](https://plugins.jetbrains.com/plugin/20554-magento-and-adobe-commerce-phpstorm-by-atwix)

Set your PHP Language Level and CLI interpreter to match your installed PHP version under Settings > PHP

Configure the Magento PhpStorm plugin under Settings > PHP > Frameworks > Magento
- Set the Magento installation path to `src`
- Regenerate URN mappings or alternatively run `bin/magento dev:urn-catalog:generate .idea/misc.xml`

Change your VCS root to the `src/app/code/AirRobe/TheCircularWardrobe` directory [Change VCS project root](https://intellij-support.jetbrains.com/hc/en-us/community/posts/115000087244-Change-VCS-project-root)

Or remove the .git directory from the docker-magento directory [How to change Git root directory?](https://stackoverflow.com/questions/66969576/how-to-change-git-root-directory).

### Clearing the Magento install

If you want to completely clear the magento install in your local environment, `cd` to the root directory and run
```
bin/removeall 
rm -rf .* *
```