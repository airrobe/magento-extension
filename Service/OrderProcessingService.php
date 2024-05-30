<?php

namespace AirRobe\TheCircularWardrobe\Service;

use AirRobe\TheCircularWardrobe\Block\Product\View\Markup;
use AirRobe\TheCircularWardrobe\Helper\Data;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Exception;
use Psr\Log\LoggerInterface as Logger;

/**
 * Class OrderProcessingService
 * @package AirRobe\TheCircularWardrobe\Service
 * @noinspection PhpUnused
 */
class OrderProcessingService
{
  protected Logger $_logger;
  protected Data $helperData;
  protected Markup $markup;
  protected ResourceConnection $resourceConnection;
  protected UrlInterface $_urlInterface;
  protected ProductRepositoryInterface $_productRepository;
  protected Image $_imageHelper;
  protected ReadHandler $_gallery;
  protected Product $_product;
  protected Configurable $_configurableProduct;

  public function __construct(
    Logger                            $logger,
    Data                              $helperData,
    Markup                            $markup,
    UrlInterface                      $urlInterface,
    ResourceConnection                $resourceConnection,
    ProductRepositoryInterface        $productRepository,
    Image                             $imageHelper,
    ReadHandler                       $gallery,
    Product                           $product,
    Configurable                      $configurableProduct)
  {
    $this->_logger = $logger;
    $this->helperData = $helperData;
    $this->markup = $markup;
    $this->_urlInterface = $urlInterface;
    $this->resourceConnection = $resourceConnection;
    $this->_productRepository = $productRepository;
    $this->_imageHelper = $imageHelper;
    $this->_gallery = $gallery;
    $this->_product = $product;
    $this->_configurableProduct = $configurableProduct;
  }

  public function isExtensionEnabled(): bool
  {
    return $this->helperData->isExtensionEnabled();
  }

  public function sendOrderToAirRobe(Order $order): void
  {
    try {
      $currency = $order->getOrderCurrencyCode();
      $visibleLineItems = $this->getVisibleLineItems($order);

      // If there are no items to send to airrobe, bail out early
      if (count($visibleLineItems) == 0) {
        return;
      }

      // See comment in method - we need to first get all image urls indexed by product id, to handle
      // some quirks with magento
      $imageUrlsByProductId = $this->getImageUrlsByProductId($order);

      $lineItemsInput = [];
      foreach ($visibleLineItems as $item) {
        $productId = $item->getProductId();
        $imageUrls = $imageUrlsByProductId[$productId] ?? [];

        $lineItemsInput[] = $this->lineItemData($item, $currency, $imageUrls);
      }

      $this->helperData->sendToAirRobeAPI([
        'query' => "mutation ProcessMagentoOrder(\$input: CreateMagentoOrderMutationInput!){
                      createMagentoOrder(input: \$input) { error created }
                    }",
        'variables' => [
          'input' => [
            'id' => $order->getIncrementId(),
            'optedIn' => $this->markup->isOptedIn(),
            'lineItems' => $lineItemsInput,
            'customer' => [
              'email' => $order->getCustomerEmail(),
              'givenName' => $order->getBillingAddress()->getFirstname(),
              'familyName' => $order->getBillingAddress()->getLastname()
            ],
          ]
        ]
      ]);
    } catch (Exception $e) {
      // MOST IMPORTANTLY: don't ever break the checkout for our merchant partners!
      // All exceptions must be handled by a try { } catch { } block
      $this->helperData->safelySendErrorDetailsToApi($e);
    }
  }

  /**
   * @param Item $item
   * @param $currency
   * @param $imageUrls
   * @return array
   * @throws NoSuchEntityException
   */
  public function lineItemData(Item $item, $currency, $imageUrls): array
  {
    $product = $this->_productRepository->getById($item->getProductId());

    // shift the first image URL to use as the hero image, leaving the rest in the $imageUrls array
    $heroImageUrl = array_shift($imageUrls);

    return [
      'sku' => $item->getSku(),
      'title' => $item->getName(),
      'brand' => $this->helperData->getProductBrand($product),
      'description' => $this->helperData->getProductDescription($product),
      'productType' => $this->helperData->getFirstProductCategory($product),
      'heroImageUrl' => $heroImageUrl,
      'additionalImageUrls' => $imageUrls,
      'productAttributes' => $this->getItemProductAttributes($item),
      'paidPrice' => [
        'cents' => $item->getPrice() * 100,
        'currency' => $currency,
      ],
      'rrp' => [
        'cents' => $item->getOriginalPrice() * 100,
        'currency' => $currency,
      ],
    ];
  }

  // Workaround for an issue with Magento2 in which the getVisibleItems() method also returns simple
  // (non-visible) items inside the observer context. See:
  // https://community.magento.com/t5/Magento-2-x-Programming/getAllVisibleItems-shows-both-configurable-and-parent-products/td-p/83184
  /**
   *
   * Workaround for an issue with Magento2 in which the getVisibleItems() method also returns simple
   * (non-visible) items inside the observer context. See:
   * https://community.magento.com/t5/Magento-2-x-Programming/getAllVisibleItems-shows-both-configurable-and-parent-products/td-p/83184
   * @param Order $order
   * @return Item[]
   */
  public function getVisibleLineItems(Order $order): array
  {
    $items = [];
    foreach ($order->getAllItems() as $item) {
      if (!$item->isDeleted() && !$item->getParentItem() && $item->getProductType() != "downloadable") {
        $items[] = $item;
      }
    }
    return $items;
  }

  // Return an array with all image URLs, indexed by the "canonical" product id. This is necessary
  // due to two quirks with Magento. The first is that for "configurable" products (products with
  // options such as size, color, etc.), there is a "configurable" product in the database to
  // represent the parent, plus a "simple" product for each variant of the product. When a variant
  // is added to the cart, there are similarly two line items added - one for the configurable
  // product, and one for the simple product. This introduces some complexities, as there may be
  // images associated with both types of product, and in the case of a variant being added to the
  // cart, we want all "parent" (configurable product) images, as well as all "child" (simple)
  // product images to be sent to our API, but we want to de-duplicate the line item rows, and only
  // send the configurable product line item. To achieve this, we first loop over all line items,
  // and generate a map of all image urls, indexed by what we call the "canonical" product id, which
  // represents the parent product id in the case of a configurable product, or the basic product id
  // in the case of a simple product.
  public function getImageUrlsByProductId($order): array
  {
    $imageUrlsByProductId = [];
    $allItems = $order->getAllItems();

    foreach ($allItems as $item) {
      $productId = $item->getProductId();
      $canonicalProductId = $this->productParentId($productId) ?? $productId;

      try {
        $product = $this->_productRepository->getById($item->getProductId());

        if (!isset($imageUrlsByProductId[$canonicalProductId])) {
          $imageUrlsByProductId[$canonicalProductId] = [];
        }

        foreach ($product->getMediaGalleryImages() as $image) {
          $imageUrlsByProductId[$canonicalProductId][] = $image->getUrl();
        }
      } catch (NoSuchEntityException) {
        continue;
      }
    }

    return $imageUrlsByProductId;
  }

  // For a simple product that is a child of a parent configurable product, return the id of the
  // parent
  public function productParentId($productId)
  {
    $parents = $this->_configurableProduct->getParentIdsByChild($productId);
    return $parents[0] ?? null;
  }


  /**
   * Return an array of product options of the form { 'name' => 'size', 'value' => 'small' }
   * @param Item $item
   * @return array
   */
  public function getItemProductOptions(Item $item): array
  {
    $productOptions = $item->getProductOptions();

    if (!array_key_exists('attributes_info', $productOptions)) {
      return [];
    }

    $productOptionAttributes = $productOptions['attributes_info'];

    // Return our options in an array with "name" and "value" keys
    $options = [];
    foreach ($productOptionAttributes as $option) {
      $options[] = [
        'name' => $option['label'],
        'value' => $option['value']
      ];
    }

    return $options;
  }

  /**
   * Return an array of product options of the form { 'name' => 'size', 'value' => 'small' }
   * @param Item $item
   * @return array
   */
  public function getItemProductAttributes(Item $item): array
  {
    $options = $this->getItemProductOptions($item);

    $product = $item->getProduct();
    $productAttributes = $product->getCustomAttributes();

    foreach ($productAttributes as $attribute) {
      $attributeCode = $attribute->getAttributeCode();
      $options[] = array(
        'name' => $attribute->getAttributeCode(),
        'value' => $this->helperData->getProductAttributeByCode($product, $attributeCode)
      );
    }

    return $options;
  }
}
