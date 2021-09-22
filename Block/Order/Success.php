<?php

namespace AirRobe\TheCircularWardrobe\Block\Order;

/**
 * [Description Success]
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
{
  const COOKIE_NAME = 'airRobeOptedInState';

  public function getAppId()
  {
    return $this->helper()->getAppID();
  }

  public function getIsOrderOptedIn()
  {
    return $this->helper()->getIsOrderOptedIn();
  }

  public function getEmail()
  {
    return $this->_checkoutSession->getLastRealOrder()->getCustomerEmail();
  }

  protected function helper()
  {
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    return $objectManager->create("\AirRobe\TheCircularWardrobe\Helper\Data");
  }
}
