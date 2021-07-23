<?php
// This is the observer that listeners to order creation events, and sends order data to our
// connector.
namespace AirRobe\TheCircularWardrobe\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class OrderAfter implements \Magento\Framework\Event\ObserverInterface
{
  protected $_logger;
  protected $helperData;
  protected $resourceConnection;
  protected $stockItemRepository;
  protected $_objectManager;
  protected $_productRepository;
  protected $_productRepositoryFactory;
  protected $_imageHelper;
  protected $_gallery;
  protected $_product;

  protected $currency;

  public function __construct(
    Logger $logger,
    \AirRobe\TheCircularWardrobe\Helper\Data $helperData,
    \AirRobe\TheCircularWardrobe\Block\Product\View\Markup $markup,
    \Magento\Framework\Registry $registry,
    \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
    \Magento\Framework\UrlInterface $urlInterface,
    \Magento\Framework\App\ResourceConnection $resourceConnection,
    \Magento\Catalog\Model\ProductRepository $productRepository,
    \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory,
    \Magento\Catalog\Helper\Image $imageHelper,
    \Magento\Catalog\Model\Product\Gallery\ReadHandler $gallery,
    \Magento\Catalog\Model\Product $product
  ) {
    $this->_logger = $logger;
    $this->helperData = $helperData;
    $this->markup = $markup;
    $this->registry = $registry;
    $this->stockItemRepository = $stockItemRepository;
    $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $this->_urlInterface = $urlInterface;
    $this->resourceConnection = $resourceConnection;
    $this->_productRepository = $productRepository;
    $this->_productRepositoryFactory = $productRepositoryFactory;
    $this->_imageHelper = $imageHelper;
    $this->_gallery = $gallery;
    $this->_product = $product;
  }

  public function execute(EventObserver $observer)
  {
    try {
      if (!$this->helperData->isExtensionEnabled()) {
        $this->_logger->debug("[AIRROBE] AirRobe is installed but not enabled. Skipping AirRobe actions.");
        return null;
      }

      $order = $observer->getEvent()->getOrder();
      $currency = $order->getOrderCurrencyCode();
      $visibleLineItems = $this->getVisibleLineItems($order);

      // If there are no items to send to airrobe, bail out early
      if (count($visibleLineItems) == 0) {
        return;
      }

      // See comment in method - we need to first get all image urls indexed by product id, to handle
      // some quirks with magento
      $imageUrlsByProductId = $this->getImageUrlsByProductId($order);

      $this->helperData->sendToAirRobeAPI(
        [
          'query' => "mutation ProcessMagentoOrder(\$input: CreateMagentoOrderMutationInput!){
                  createMagentoOrder(input: \$input) { error created }
                }",
          'variables' => [
            'input' => [
              'id' => $order->getIncrementId(),
              'optedIn' => $this->markup->isOptedIn(),
              'lineItems' => array_map(
                function ($item) use ($currency, $imageUrlsByProductId) {
                  $productId = $item->getProductId();
                  $imageUrls = isset($imageUrlsByProductId[$productId]) ? $imageUrlsByProductId[$productId] : [];

                  return $this->lineItemData($item, $currency, $imageUrls);
                },
                $visibleLineItems
              ),
              'customer' => [
                'email' => $order->getCustomerEmail(),
                'givenName' => $order->getBillingAddress()->getFirstname(),
                'familyName' => $order->getBillingAddress()->getLastname()
              ],
            ]
          ]
        ]
      );
    } catch (\Exception $e) {
      // MOST IMPORTANTLY: don't ever break the checkout for our merchant partners!
      // All exceptions must be handled by a try { } catch { } block
      $this->helperData->safelySendErrorDetailsToApi($e);
    }
  }

  protected function lineItemData($item, $currency, $imageUrls)
  {
    $product = $this->_objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());

    // shift the first image URL to use as the hero image, leaving the rest in the $imageUrls array
    $heroImageUrl = array_shift($imageUrls);

    return [
      'sku' => $item->getSku(),
      'title' => $item->getName(),
      'brand' => $this->getProductBrand($product),
      'description' => $product->getDescription(),
      'productType' => $this->helperData->getFirstProductCategory($product),
      'heroImageUrl' => $heroImageUrl,
      'additionalImageUrls' => $imageUrls,
      'productAttributes' => $this->getItemProductOptions($item),
      'paidPrice' => [
        'cents' => $item->getPrice(),
        'currency' => $currency,
      ],
      'rrp' => [
        'cents' => $item->getOriginalPrice(),
        'currency' => $currency,
      ],
    ];
  }

  // Workaround for an issue with Magento2 in which the getVisibleItems() method also returns simple
  // (non-visisble) items inside the observer context. See:
  // https://community.magento.com/t5/Magento-2-x-Programming/getAllVisibleItems-shows-both-configurable-and-parent-products/td-p/83184
  protected function getVisibleLineItems($order)
  {
    return array_filter(
      $order->getAllItems(),
      function ($item) {
        // Exclude line items with a parent item, as they are duplicates with incomplete data
        if ($item->getParentItem() != null) {
          return false;
        }

        // Exclude downloadable products as they can't be added to airrobe
        if ($item->getProductType() == "downloadable") {
          return false;
        }

        return true;
      }
    );
  }

  // Return an array with all image URLs, indexed by the "canonical" product id. This is necessary
  // due to two quirks with Magento. The first is that for "configurable" products (products with
  // options such as size, color, etc), there is a "configuruable" product in the database to
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
  protected function getImageUrlsByProductId($order)
  {
    return array_reduce(
      $order->getAllItems(),
      function ($images, $item) {
        $productId = $item->getProductId();
        $canonicalProductId = $this->productParentId($productId) ?? $productId;
        $product = $this->_objectManager->create('Magento\Catalog\Model\Product')->load($productId);

        if (!isset($images[$canonicalProductId])) {
          $images[$canonicalProductId] = [];
        }

        foreach ($product->getMediaGalleryImages() as $image) {
          $images[$canonicalProductId][] = $image->getUrl();
        }
        return $images;
      },
      []
    );
  }

  protected function getProductBrand($product)
  {
    $brandAttributeCode = $this->helperData->getBrandAttributeCode();
    $brand = $product->getAttributeText($brandAttributeCode);

    return $brand ? $brand : null;
  }

  // For a simple product that is a child of a parent configurable product, return the id of the
  // parent
  protected function productParentId($productId)
  {
    $parents = $this->_objectManager->create('Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable')->getParentIdsByChild($productId);
    return isset($parents[0]) ? $parents[0] : null;
  }

  // Return an array of product options of the form { 'name' => 'size', 'value' => 'small' }
  protected function getItemProductOptions($item)
  {
    $productOptions = $item->getProductOptions();

    // Some product types, including simple / grouped products, don't have attributes
    if (!isset($productOptions['attributes_info'])) {
      return [];
    }

    // // Return our options in an array with "name" and "value" keys
    return array_map(
      function ($option) {
        return [
          'name' => $option['label'],
          'value' => $option['value']
        ];
      },
      $productOptions['attributes_info']
    );
  }
}
