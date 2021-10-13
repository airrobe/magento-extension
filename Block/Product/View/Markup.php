<?php

namespace AirRobe\TheCircularWardrobe\Block\Product\View;

use Magento\Catalog\Model\Product;

/**
 * [Description Markup]
 */
class Markup extends \Magento\Framework\View\Element\Template
{
  protected $helper;

  /**
   * Core registry
   *
   * @var \Magento\Framework\Registry
   */
  protected $_registry = null;

  /**
   * @param \Magento\Framework\View\Element\Template\Context $context
   * @param \Magento\Framework\Registry                      $registry
   * @param array                                            $data
   */
  public function __construct(
    \Magento\Framework\View\Element\Template\Context $context,
    \Magento\Framework\Registry $registry,
    \AirRobe\TheCircularWardrobe\Helper\Data $helper,
    \Magento\Store\Model\StoreManagerInterface $storeManager,
    array $data = []
  ) {
    $this->_helper = $helper;
    $this->_storeManager = $storeManager;
    $this->_registry = $registry;
    parent::__construct($context, $data);
  }

  public function isExtensionEnabled()
  {
    return $this->_helper->isExtensionEnabled();
  }

  public function isOptedIn()
  {
    // The cookie is stored as a string, so we co-erce it to a boolean here.
    return $this->_helper->getIsOptedIn();
  }

  public function getAppId()
  {
    return $this->_helper->getAppID();
  }

  public function getFirstProductCategory()
  {
    return $this->_helper->getFirstProductCategory($this->getCurrentProduct());
  }

  public function getProductPriceCents()
  {
    return $this->getCurrentProduct()->getFinalPrice() * 100;
  }

  public function getProductOriginalFullPriceCents()
  {
    $regularPrice = $this->getCurrentProduct()->getPriceInfo()->getPrice('regular_price');

    if ($regularPrice && $this->isProductConfigurable()) {
      return $regularPrice->getMinRegularAmount()->getValue() * 100;
    }
  }

  protected function isProductConfigurable()
  {
    return $this->getCurrentProduct()->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE;
  }

  protected function getCurrentProduct()
  {
    return $this->_registry->registry('current_product');
  }
}
