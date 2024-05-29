<?php

namespace AirRobe\TheCircularWardrobe\Block\Product\View;

use AirRobe\TheCircularWardrobe\Helper\Data;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Markup
 * @package AirRobe\TheCircularWardrobe\Block\Product\View
 * @noinspection PhpUnused
 */
class Markup extends Template
{
  protected Data $helper;
  protected ?ProductRepositoryInterface $productRepository = null;

  public function __construct(
    Context $context,
    ProductRepositoryInterface $productRepository,
    Data $helper,
    array $data = []
  ) {
    $this->productRepository = $productRepository;
    $this->helper = $helper;
    parent::__construct($context, $data);
  }

  public function isExtensionEnabled()
  {
    return $this->helper->isExtensionEnabled();
  }

  public function isOptedIn(): bool
  {
    // The cookie is stored as a string, so we coerce it to a boolean here.
    return $this->helper->getIsOptedIn();
  }

  public function getAppId()
  {
    return $this->helper->getAppID();
  }

  public function getFirstProductCategory()
  {
    try {
      return $this->helper->getFirstProductCategory($this->getCurrentProduct());
    } catch (NoSuchEntityException) {
      return null;
    }
  }

  public function getProductBrand()
  {
    try {
      return $this->helper->getProductBrand($this->getCurrentProduct());
    } catch (NoSuchEntityException) {
      return null;
    }
  }

  public function getProductMaterial()
  {
    try {
      return $this->helper->getProductMaterial($this->getCurrentProduct());
    } catch (NoSuchEntityException) {
      return null;
    }
  }

  public function getProductPriceCents(): float|int
  {
    try {
      return $this->getCurrentProduct()->getFinalPrice() * 100;
    } catch (NoSuchEntityException) {
      return 0;
    }
  }

  public function getProductOriginalFullPriceCents(): float|int|null
  {
    try {
      $product = $this->getCurrentProduct();

      $regularPrice = $product->getPriceInfo()->getPrice('regular_price');
      if (!$regularPrice) {
        return null;
      }

      if (!$this->isProductConfigurable($product)) {
        return null;
      }

      return $regularPrice->getMinRegularAmount()->getValue() * 100;
    } catch (NoSuchEntityException) {
      return null;
    }
  }

  protected function isProductConfigurable(ProductInterface $product): bool
  {
    return $product->getTypeId() == Configurable::TYPE_CODE;
  }

  /**
   * @throws NoSuchEntityException
   */
  protected function getCurrentProduct(): ProductInterface
  {
    return $this->productRepository->getById($this->getRequest()->getParam('id'));
  }
}
