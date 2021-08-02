<?php

namespace AirRobe\TheCircularWardrobe\Block\Order;

/**
 * [Description Success]
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
{
  const COOKIE_NAME = 'airRobeOptedInState';

  // /**
  //  * @param \Magento\Framework\View\Element\Template\Context $context
  //  * @param \Magento\Checkout\Model\Session $checkoutSession
  //  * @param \Magento\Sales\Model\Order\Config $orderConfig
  //  * @param \Magento\Framework\App\Http\Context $httpContext
  //  * @param array $data
  //  */

  // public function __construct(
  //   \Magento\Framework\View\Element\Template\Context $context,
  //   \Magento\Checkout\Model\Session $checkoutSession,
  //   \Magento\Sales\Model\Order\Config $orderConfig,
  //   \Magento\Framework\App\Http\Context $httpContext,
  //   // \AirRobe\TheCircularWardrobe\Helper\Data $helper,
  //   array $data = []
  // ) {
  //   $this->_checkoutSession = $checkoutSession;
  //   $this->_orderConfig = $orderConfig;
  //   $this->_isScopePrivate = true;
  //   $this->httpContext = $httpContext;
  //   $this->_helper = $helper;
  //   parent::__construct($context, $data);
  // }

  public function getAppId()
  {

    return $this->helper->getAppID();
  }

  public function getIsOrderOptedIn()
  {
    return $this->helper->getIsOrderOptedIn();
  }

  protected function helper()
  {
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    return $objectManager->create("\AirRobe\TheCircularWardrobe\Helper\Data");
  }
}
