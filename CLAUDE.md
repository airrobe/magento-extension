# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Magento 2 extension (`AirRobe_TheCircularWardrobe`) integrating AirRobe's circular wardrobe widgets into Magento storefronts. Injects `<airrobe-opt-in>` on product pages and `<airrobe-confirmation>` on order success pages.

## Tech Stack

- PHP 8.2/8.3, Magento 2.4.6+/2.4.7+
- Docker (markshust/docker-magento for dev)
- Composer, no external PHP dependencies beyond Magento core

## Commands

- **Enable module:** `php bin/magento module:enable AirRobe_TheCircularWardrobe`
- **Setup:** `php bin/magento setup:upgrade --keep-generated`
- **DI compile:** `php bin/magento setup:di:compile`
- **Cache flush:** `php bin/magento cache:flush`
- **Taxonomy sync:** `php bin/magento airrobe:taxonomy:sync`
- **Install (Composer):** `composer require airrobe/thecircularwardrobe`
- **Automated install:** `./script/install-airrobe.sh`

## Architecture

- `Block/` — View blocks (Script, Markup, Order/Success)
- `Console/Command/SyncTaxonomy.php` — CLI command for taxonomy sync
- `Helper/Data.php` — Configuration, API communication (CURL-based GraphQL with HMAC-SHA256), product data extraction
- `Observer/OrderAfter.php` — Listens to `sales_order_save_after` event
- `Service/OrderProcessingService.php` — Order processing, handles configurable/simple product variants
- `etc/` — Magento module config (di.xml, events.xml, system.xml, csp_whitelist.xml)
- `view/frontend/` — Layout XML and phtml templates

## Admin Configuration

Stores > Configuration > AirRobe:
- **General:** Enable Module, Live Mode toggle
- **Credentials:** App ID, Secret Token (from Connector Dashboard)
- **Mapping:** Brand Attribute Code (default: `manufacturer`), Material Attribute Code

## Conventions

- API URL defaults to `https://connector.airrobe.com/graphql`; sandbox overrides to `https://sandbox.connector.airrobe.com/graphql`
- CSP whitelisted: widgets.airrobe.com, connector.airrobe.com, fonts.airrobe.com
- Module version: 0.4.0

## Gotchas

- `Helper/Data.php` is acknowledged as an anti-pattern in its comments — should be refactored into service classes
- Configurable products have both parent and child line items in Magento; `OrderProcessingService` de-duplicates to send only the configurable parent
- All exceptions in `OrderAfter` observer are caught silently to avoid breaking merchant checkout
- Uses raw CURL instead of Magento's HTTP client to avoid compatibility issues
- No test suite exists — testing is manual
