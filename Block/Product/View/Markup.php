<?php

namespace AirRobe\TheCircularWardrobe\Block\Product\View;

use Magento\Catalog\Model\Product;

class Markup extends \Magento\Framework\View\Element\Template
{
  const COOKIE_NAME = 'airRobeOptedInState';

  protected $_cookieManager;

  protected $helper;
  /**
   * @var Product
   */
  protected $_product = null;

  /**
   * Core registry
   *
   * @var \Magento\Framework\Registry
   */
  protected $_coreRegistry = null;

  /**
   * @param \Magento\Framework\View\Element\Template\Context $context
   * @param \Magento\Framework\Registry $registry
   * @param array $data
   */
  public function __construct(
    \Magento\Framework\View\Element\Template\Context $context,
    \Magento\Framework\Registry $registry,
    \AirRobe\TheCircularWardrobe\Helper\Data $helper,
    \Magento\Store\Model\StoreManagerInterface $storeManager,
    \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
    array $data = []
  ) {
    $this->_helper = $helper;
    $this->_cookieManager = $cookieManager;
    $this->_storeManager = $storeManager;
    parent::__construct($context, $data);
  }

  public function isExtensionEnabled()
  {
    return $this->_helper->isExtensionEnabled();
  }

  public function isOptedIn()
  {
    return $this->_cookieManager->getCookie(self::COOKIE_NAME);
  }

  public function getAppId()
  {
    return $this->_helper->getAppID();
  }
}
