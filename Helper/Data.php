<?php

namespace AirRobe\TheCircularWardrobe\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
  protected $_cookieManager;
  protected $_logger;
  protected $_storeManager;
  protected $_tree;
  protected $_attributeCollection;
  protected $_categoryCollectionFactory;

  const COOKIE_NAME = 'airRobeOptedInState';
  const MODULE_ENABLE = "airrobe/general/enable";
  const AIRROBE_API_URL = "airrobe/options/airrobe_api_url";
  const AIRROBE_APP_ID = "airrobe/options/airrobe_app_id";
  const AIRROBE_SECRET_TOKEN = "airrobe/options/airrobe_secret_token";
  const BRAND_ATTRIBUTE_CODE = "airrobe/options/airrobe_brand_attribute_code";
  const BASE_SITE_URL = "web/unsecure/base_url";

  public function __construct(
    \Magento\Framework\App\Helper\Context $context,
    \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
    \Magento\Store\Model\StoreManagerInterface $storeManager,
    \Magento\Catalog\Model\ResourceModel\Category\Tree $tree,
    \Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection $attributeCollection,
    \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
    \Psr\Log\LoggerInterface $logger
  ) {
    $this->_logger = $logger;
    $this->_categoryCollectionFactory = $categoryCollectionFactory;
    $this->_tree = $tree;
    $this->_attributeCollection = $attributeCollection;
    $this->_cookieManager = $cookieManager;
    $this->_storeManager = $storeManager;
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
    return base64_encode(hash_hmac('sha256', $string_payload, $secretToken, true));
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

  public function getBrandAttributeCode()
  {
    return $this->getConfigValue(self::BRAND_ATTRIBUTE_CODE);
  }

  public function getIsOptedIn()
  {
    // The cookie is stored as a string, so we co-erce it to a boolean here.
    return $this->_cookieManager->getCookie(self::COOKIE_NAME) == "true";
  }

  public function getFirstProductCategory($product)
  {
    $categories = $this->getProductCategories($product);
    return isset($categories[0]) ? $categories[0] : "default";
  }

  // Get a string representation of the full path of a category, e.g. womens/shoes/heels
  public function getCategoryTree($category)
  {
    $storeId = $this->_storeManager->getStore()->getId() ?? 1;

    $path = $category->getPath();
    $categoryTree = $this->_tree->setStoreId($storeId)->loadBreadcrumbsArray($path);

    $categoryTreepath = array();

    foreach ($categoryTree as $eachCategory) {
      $categoryTreepath[] = $eachCategory['name'];
    }

    return implode("/", $categoryTreepath);
  }

  // Get a string representation of all categories for a given product, of the form
  // ["womens/bags/handbags", "special-events/all-events"]
  public function getProductCategories($product)
  {
    $categoryIds = $product->getCategoryIds();

    if (count($categoryIds) == 0) {
      return [];
    }

    $categoryCollection = $this->_categoryCollectionFactory
      ->create()
      ->addAttributeToSelect('*')
      ->addIsActiveFilter()
      ->addAttributeToFilter('entity_id', $categoryIds);

    return $this->categoryTrees($categoryCollection);
  }

  // Return the category tree string for all active categories for the merchant
  public function getAllCategoryTrees()
  {
    $categoryCollection = $this->_categoryCollectionFactory
      ->create()
      ->addAttributeToSelect('*')
      ->addIsActiveFilter();

    return $this->categoryTrees($categoryCollection);
  }

  public function categoryTrees($categoryCollection)
  {
    $categories = [];

    foreach ($categoryCollection as $category) {
      $categories[] = $this->getCategoryTree($category);
    }

    return $categories;
  }

  // Get the names and possible values for all product attributes in the system. These are sent to
  // AirRobe to prepare mapping records in the connector.
  public function getAllAttributes()
  {
    // Filter to product attributes (entity type 4)
    $this->_attributeCollection
      ->addFieldToFilter(\Magento\Eav\Model\Entity\Attribute\Set::KEY_ENTITY_TYPE_ID, 4)
      ->addFieldToFilter("is_user_defined", 1);

    // I'm a javascript developer, sorry
    return array_values(
      array_map(
        function ($attribute) {
          return [
            'name' => $attribute->getAttributeCode(),
            'values' => array_values(
              // We filter out blank string values from the list of options
              array_filter(
                array_map(
                  function ($option) {
                    return $option["label"] != " " ? $option["label"] : null;
                  },
                  $attribute->getSource()->getAllOptions()
                )
              )
            )
          ];
        },
        $this->_attributeCollection->load()->getItems()
      )
    );
  }

  // Send a payload of data to the airrobe connector. Before sending we sign with an APP_ID taken
  // from the config file, and a message signature generated using the HMAC algorithm and a secret
  // token from the same config file. TODO: I understand that there are dedicated graphql classes
  // that ship with magento, and it may make sense to start using these. But for now, this CURL
  // approach is workable (and also avoids any potential compatability issues with our merchant
  // partner stores)
  public function sendToAirRobeAPI($payload)
  {
    $url = $this->getApiUrl();
    $appId = $this->getAppID();
    $json_payload = json_encode($payload);
    $signature = $this->getSignature($json_payload);

    $this->_logger->debug("[AIRROBE] URL: " . $url);
    $this->_logger->debug("[AIRROBE] APP_ID: " . $appId);
    $this->_logger->debug("[AIRROBE] PAYLOAD: " . $json_payload);
    $this->_logger->debug("[AIRROBE] SIGNATURE: " . $signature);

    $headers = [
      'Content-Type: application/json',
      'X-AIRROBE-APP-ID: ' . $appId,
      'X-AIRROBE-HMAC-SHA256: ' . $signature,
    ];

    $response = $this->curlPost($url, $json_payload, $headers);

    $this->_logger->debug("[AIRROBE] API_RESPONSE: " . json_encode($response));

    return $response;
  }

  public function curlPost($url, $string_payload, $headers = [])
  {
    try {
      $curl = curl_init();

      if ($curl == false) {
        throw new \Exception('Failed to initialize CURL');
      }

      curl_setopt_array(
        $curl,
        [
          CURLOPT_URL => $url,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS => $string_payload,
          CURLOPT_HTTPHEADER => $headers,
          CURLOPT_RETURNTRANSFER => true
        ]
      );

      $response = curl_exec($curl);

      if ($response === false) {
        throw new \Exception(curl_error($curl), curl_errno($curl));
      }

      curl_close($curl);

      return $response;
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

  // If something goes wrong, WE want to know about it. This will trigger notifications in our
  // systems.
  public function safelySendErrorDetailsToApi($e)
  {
    try {
      $url = "https://connector.airrobe.com/widget_errors";
      $payload = [
        'name' => $e->getCode(),
        'message' => $e->getMessage(),
        'host' => $this->getBaseSiteUrl(),
      ];
      $this->curlPost($url, json_encode($payload));
    } catch (\Exception $failsafeError) {
      // Drop any errors here on the floor, as, in the worst case, we don't want to break our
      // merchant partners' checkout flow.
    }

    // Finally, log the error to the application logs for additional visibility.
    $this->_logger->debug(
      sprintf(
        '[AIRROBE] ERROR DETAILS SENT TO AIRROBE: #%d: %s',
        $e->getCode(),
        $e->getMessage()
      )
    );
  }
}
