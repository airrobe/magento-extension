<?php

/**
 * @package     AirRobe_TheCircularWardrobe
 * @author      Michael Dawson <developers@airrobe.com>
 * @copyright   Copyright AirRobe (https://airrobe.com/)
 * @license     https://airrobe.com/license-agreement.txt
 */

namespace AirRobe\TheCircularWardrobe\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
  protected $_logger;

  const MODULE_ENABLE = "airrobe/general/enable";
  const AIRROBE_API_URL = "airrobe/options/airrobe_api_url";
  const AIRROBE_APP_ID = "airrobe/options/airrobe_app_id";
  const AIRROBE_SECRET_TOKEN = "airrobe/options/airrobe_secret_token";
  const BASE_SITE_URL = "web/unsecure/base_url";

  public function __construct(
    \Magento\Framework\App\Helper\Context $context,
    \Psr\Log\LoggerInterface $logger
  ) {
    $this->_logger = $logger;
    parent::__construct($context);
  }

  public function getConfigValue($field, $storeId = null)
  {
    return $this->scopeConfig->getValue(
      $field,
      ScopeInterface::SCOPE_STORE,
      $storeId
    );
  }

  public function getGeneralConfig($code, $storeId = null)
  {
    return $this->getConfigValue('general/' . $code, $storeId);
  }

  public function isExtensionEnabled()
  {
    return $this->getConfigValue(self::MODULE_ENABLE);
  }

  public function getSignature($string_payload)
  {
    $secretToken = $this->getConfigValue(self::AIRROBE_SECRET_TOKEN);
    return hash_hmac('sha256', $string_payload, $secretToken);
  }

  public function getAppID()
  {
    return $this->getConfigValue(self::AIRROBE_APP_ID);
  }

  public function getApiUrl()
  {
    return $this->getConfigValue(self::AIRROBE_API_URL);
  }

  public function getBaseSiteUrl()
  {
    return $this->getConfigValue(self::BASE_SITE_URL);
  }
}
