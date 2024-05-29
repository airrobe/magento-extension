<?php

namespace AirRobe\TheCircularWardrobe\Block\Order;

use AirRobe\TheCircularWardrobe\Helper\Data;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order\Config;
use Magento\Framework\App\Http\Context as HttpContext;

/**
 * Class Success
 * @package AirRobe\TheCircularWardrobe\Block\Order
 * @noinspection PhpUnused
 */
class Success extends \Magento\Checkout\Block\Onepage\Success
{
  protected Data $helperData;

  public function __construct(
    Context $context,
    Session $checkoutSession,
    Config $orderConfig,
    HttpContext $httpContext,
    Data $helperData,
    array $data = []
  ) {
    $this->helperData = $helperData;
    parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
  }

  public function getAppId()
  {
    return $this->helperData->getAppID();
  }

  public function getEmail(): float|string|null
  {
    return $this->_checkoutSession->getLastRealOrder()->getCustomerEmail();
  }

  public function getOrderId(): float|string|null
  {
    return $this->_checkoutSession->getLastRealOrder()->getIncrementId();
  }
}
