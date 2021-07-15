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
  protected $_categoryCollectionFactory;
  protected $stockItemRepository;
  protected $_storeManager;
  protected $_objectManager;
  protected $_productRepository;
  protected $_productRepositoryFactory;
  protected $_imageHelper;
  protected $_tree;
  protected $_gallery;
  protected $_product;

  protected $currency;

  public function __construct(
    Logger $logger,
    \AirRobe\TheCircularWardrobe\Helper\Data $helperData,
    \AirRobe\TheCircularWardrobe\Block\Product\View\Markup $markup,
    \Magento\Framework\Registry $registry,
    \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
    \Magento\Store\Model\StoreManagerInterface $storeManager,
    \Magento\Framework\UrlInterface $urlInterface,
    \Magento\Framework\App\ResourceConnection $resourceConnection,
    \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
    \Magento\Catalog\Model\ProductRepository $productRepository,
    \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory,
    \Magento\Catalog\Helper\Image $imageHelper,
    \Magento\Catalog\Model\ResourceModel\Category\Tree $tree,
    \Magento\Catalog\Model\Product\Gallery\ReadHandler $gallery,
    \Magento\Catalog\Model\Product $product
  ) {
    $this->_logger = $logger;
    $this->helperData = $helperData;
    $this->markup = $markup;
    $this->registry = $registry;
    $this->stockItemRepository = $stockItemRepository;
    $this->_storeManager = $storeManager;
    $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $this->_urlInterface = $urlInterface;
    $this->resourceConnection = $resourceConnection;
    $this->_categoryCollectionFactory = $categoryCollectionFactory;
    $this->_productRepository = $productRepository;
    $this->_productRepositoryFactory = $productRepositoryFactory;
    $this->_imageHelper = $imageHelper;
    $this->_tree = $tree;
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

      // See comment in method - we need to first get all image urls indexed by product id, to handle
      // some quirks with magento
      $imageUrlsByProductId = $this->getImageUrlsByProductId($order);

      $this->sendToAirRobeAPI([
        'query' => "mutation ProcessMagentoOrder(\$input: CreateOrderMutationInput!){ createOrder(input: \$input) { error order { id } } }",
        'variables' => [
          'id' => $order->getIncrementId(),
          'optedIn' => $this->markup->isOptedIn(),
          'lineItems' => array_map(function ($item) use ($currency, $imageUrlsByProductId) {
            $imageUrls = $imageUrlsByProductId[$item->getProductId()];

            return $this->lineItemData($item, $currency, $imageUrls);
          }, $this->getVisibleLineItems($order)),
          'customer' => [
            'email' => $order->getCustomerEmail(),
            'givenName' => $order->getBillingAddress()->getFirstname(),
            'familyName' => $order->getBillingAddress()->getLastname()
          ],
        ]
      ]);
    } catch (\Exception $e) {
      // MOST IMPORTANTLY: don't ever break the checkout for our merchant partners!
      // All exceptions must be handled by a try { } catch { } block
      $this->safelySendErrorDetailsToApi($e);
    }
  }

  protected function lineItemData($item, $currency, $imageUrls)
  {
    $product = $this->_objectManager->create('Magento\Catalog\Model\Product')->load($item->getProductId());

    // shift the first image URL to use as the hero image, leaving the rest in the $imageUrls array
    $heroImageUrl = array_shift($imageUrls);

    // TODO: for now, we only send the first category of an item to our API. In future we may like
    // to send multiple.
    $categories = $this->getProductCategories($product);
    $productType = isset($categories[0]) ? $categories[0] : "default";

    return [
      'sku' => $item->getSku(),
      'title' => $item->getName(),
      'productType' => $productType,
      'heroImageUrl' => $heroImageUrl,
      'additionalImageUrls' => $imageUrls,
      'productAttributes' => $this->getItemProductOptions($item),
      'description' => $item->getDescription(),
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
    return array_filter($order->getAllItems(), function ($item) {
      if ($item->getProductType() == "simple" && getType($item->getParentItem()) == "object") {
        // This is a the "phantom" simple line item entry for a configurable product, skip it
        return false;
      }

      return true;
    });
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
    return array_reduce($order->getAllItems(), function ($images, $item) {
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
    }, []);
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
    // Bail out early if we have a simple product (with no configurable options)
    if ($item->getProductType() == "simple") {
      return [];
    }

    // // Return our options in an array with "name" and "value" keys
    return array_map(function ($option) {
      return [
        'name' => $option['label'],
        'value' => $option['value']
      ];
    }, $item->getProductOptions()['attributes_info']);
  }

  // Send a payload of data to the airrobe connector. Before sending we sign with an APP_ID taken
  // from the config file, and a message signature generated using the HMAC algorithm and a secret
  // token from the same config file. TODO: I understand that there are dedicated graphql classes
  // that ship with magento, and it may make sense to start using these. But for now, this CURL
  // approach is workable (and also avoids any potential compatability issues with our merchant
  // partner stores)
  protected function sendToAirRobeAPI($payload)
  {
    $url = $this->helperData->getApiUrl();
    $appId = $this->helperData->getAppID();
    $json_payload = json_encode($payload);
    $signature = $this->helperData->getSignature($json_payload);

    $this->_logger->debug("[AIRROBE] URL: " . $url);
    $this->_logger->debug("[AIRROBE] APP_ID: " . $appId);
    $this->_logger->debug("[AIRROBE] PAYLOAD: " . $json_payload);
    $this->_logger->debug("[AIRROBE] SIGNATURE: " . $signature);

    try {
      $curl = curl_init();

      if ($curl == false) {
        throw new \Exception('Failed to initialize CURL');
      }

      curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $json_payload,
        CURLOPT_HTTPHEADER => [
          'Content-Type: application/json',
          'X-AIRROBE-APP-ID: ' . $appId,
          'X-AIRROBE-HMAC-SHA256: ' . $signature,
        ],
      ]);

      $response = curl_exec($curl);

      if ($response === false) {
        throw new \Exception(curl_error($curl), curl_errno($curl));
      }

      $this->_logger->debug("[AIRROBE] API_RESPONSE: " . json_encode($response));

      curl_close($curl);
    } catch (\Exception $e) {
      trigger_error(
        sprintf(
          'Curl failed with error #%d: %s',
          $e->getCode(),
          $e->getMessage()
        ),
        E_USER_ERROR
      );
    }
  }

  protected function safelySendErrorDetailsToApi($e)
  {
    try {
      sprintf(
        'Curl failed with error #%d: %s',
        $e->getCode(),
        $e->getMessage()
      );
    } catch (\Exception $failsafeError) {
      // Drop any errors here on the floor, in the worst case, we don't want to break our merchant
      // partners' checkout flow.
    }
  }

  // Get a string representation of all categories for a given product, of the form
  // ["womens/bags/handbags", "special-events/all-events"]
  public function getProductCategories($product)
  {
    $categoryIds = $product->getCategoryIds();

    if (count($categoryIds) == 0) {
      return [];
    }

    $collection = $this->_categoryCollectionFactory
      ->create()
      ->addAttributeToSelect('*')
      ->addIsActiveFilter()
      ->addAttributeToFilter('entity_id', $categoryIds);

    $categories = [];
    foreach ($collection as $category) {
      $categories[] = $this->getCategoryTree($category);
    }

    return $categories;
  }

  // Get a string representation of the full path of a category, e.g. womens/shoes/heels
  protected function getCategoryTree($category)
  {
    $storeId = $this->_storeManager->getStore()->getId();
    $path = $category->getPath();
    $categoryTree = $this->_tree->setStoreId($storeId)->loadBreadcrumbsArray($path);

    $categoryTreepath = array();

    foreach ($categoryTree as $eachCategory) {
      $categoryTreepath[] = $eachCategory['name'];
    }

    return implode("/", $categoryTreepath);
  }
}
