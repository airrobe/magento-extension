<?php
/** @noinspection PhpComposerExtensionStubsInspection */
/** @noinspection PhpMissingClassConstantTypeInspection */

namespace AirRobe\TheCircularWardrobe\Helper;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\Tree;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\Entity\Attribute\Set;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\Collection;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Data
 * @package AirRobe\TheCircularWardrobe\Helper
 * @noinspection UsingHelperClassInspection
 * @noinspection PhpUnused
 *
 * Using Helper classes are an anti pattern in Magento 2. It is recommended to use service classes instead.
 * This should be refactored into separate service classes grouped into specific functionality.
 */
class Data extends AbstractHelper
{
  protected CookieManagerInterface $_cookieManager;
  protected $_logger;
  protected StoreManagerInterface $_storeManager;
  protected Tree $_tree;
  protected Collection $_attributeCollection;
  protected CollectionFactory $_categoryCollectionFactory;

  const COOKIE_NAME = 'airRobeOptedInState';
  const MODULE_ENABLE = "airrobe/general/enable";
  const LIVEMODE_ENABLE = "airrobe/general/live_mode";
  const AIRROBE_APP_ID = "airrobe/options/airrobe_app_id";
  const AIRROBE_SECRET_TOKEN = "airrobe/options/airrobe_secret_token";

  const BRAND_ATTRIBUTE_CODE = "airrobe/mapping/airrobe_brand_attribute_code";
  const MATERIAL_ATTRIBUTE_CODE = "airrobe/mapping/airrobe_material_attribute_code";
  const BASE_SITE_URL = "web/unsecure/base_url";

  public function __construct(
    Context $context,
    CollectionFactory $categoryCollectionFactory,
    StoreManagerInterface $storeManager,
    Tree $tree,
    Collection $attributeCollection,
    CookieManagerInterface $cookieManager,
    LoggerInterface $logger
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
    return filter_var($this->getConfigValue(self::MODULE_ENABLE), FILTER_VALIDATE_BOOLEAN);
  }

  public function getSignature($string_payload): string
  {
    $secretToken = $this->getConfigValue(self::AIRROBE_SECRET_TOKEN);
    return base64_encode(hash_hmac('sha256', $string_payload, $secretToken, true));
  }

  public function getAppID()
  {
    return $this->getConfigValue(self::AIRROBE_APP_ID);
  }

  public function getApiUrl(): string
  {
    $livemode_enabled = filter_var($this->getConfigValue(self::LIVEMODE_ENABLE), FILTER_VALIDATE_BOOLEAN);

    if($livemode_enabled) {
      return "https://connector.airrobe.com/graphql";
    } else {
      return "https://sandbox.connector.airrobe.com/graphql";
    }
  }

  public function getScriptUrl(): string
  {
    $this->_logger->debug("LIVEMODE_ENABLE: " . $this->getConfigValue(self::LIVEMODE_ENABLE));
    $this->_logger->debug("TYPE OF ENABLED: " . gettype($this->getConfigValue(self::LIVEMODE_ENABLE)));

    $livemode_enabled = filter_var($this->getConfigValue(self::LIVEMODE_ENABLE), FILTER_VALIDATE_BOOLEAN);
    $ext_enabled = filter_var($this->getConfigValue(self::MODULE_ENABLE), FILTER_VALIDATE_BOOLEAN);

    $this->_logger->debug("EXT ENABLED: " . $ext_enabled);

    $host = $livemode_enabled ? "https://widgets.airrobe.com" : "https://staging.widgets.airrobe.com";

    return $host . "/versions/magento/v2/" . $this->getAppID() . "/airrobe.min.js";
  }

  public function getBaseSiteUrl()
  {
    return $this->getConfigValue(self::BASE_SITE_URL);
  }

  public function getBrandAttributeCode()
  {
    return $this->getConfigValue(self::BRAND_ATTRIBUTE_CODE);
  }

  public function getMaterialAttributeCode()
  {
    return $this->getConfigValue(self::MATERIAL_ATTRIBUTE_CODE);
  }

  public function getIsOptedIn(): bool
  {
    // The cookie is stored as a string, so we coerce it to a boolean here.
    return $this->_cookieManager->getCookie(self::COOKIE_NAME) == "true";
  }

  public function getFirstProductCategory(ProductInterface $product)
  {
    $categories = $this->getProductCategories($product);
    return $categories[0] ?? "default";
  }

  public function getCategoryTree($category): string
  {
    try {
      $storeId = $this->_storeManager->getStore()->getId();
    } catch (NoSuchEntityException) {
      $storeId = null;
    }

    if (!isset($storeId)) {
      $storeId = $this->_storeManager->getDefaultStoreView()->getId();
    }

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
  public function getProductCategories(ProductInterface $product): array
  {
    $categoryIds = $product->getCategoryIds();

    if (count($categoryIds) == 0) {
      return [];
    }

    try {
      $categoryCollection = $this->_categoryCollectionFactory
        ->create()
        ->addAttributeToSelect('*')
        ->addIsActiveFilter()
        ->addAttributeToFilter('entity_id', $categoryIds);

      return $this->categoryTrees($categoryCollection);
    } catch (LocalizedException $e) {
      $this->safelySendErrorDetailsToApi($e);
      return [];
    }
  }

  /**
   * Since we're not sure how the attribute is stored, we need to check a few places.
   */
  public function getProductAttributeByCode(ProductInterface $product, string $attributeCode): string|null
  {
    if (!$attributeCode)
      $value = null;
    elseif ($product->getAttributeText($attributeCode))
      $value = $product->getAttributeText($attributeCode);
    else
      $value = $product->getData($attributeCode);

    if (is_object($value)) {
      if (method_exists($value, 'getText'))
        $value = $value->getText();
      elseif (method_exists($value, 'getValue'))
        $value = $value->getValue();
      else
        return null;
    }

    if (is_array($value)) {
      return implode(", ", $value);
    }

    return $value;
  }

  public function getProductBrand(ProductInterface $product): string|null
  {
    return $this->getProductAttributeByCode($product, $this->getBrandAttributeCode());
  }

  public function getProductMaterial(ProductInterface $product): string|null
  {
    return $this->getProductAttributeByCode($product, $this->getMaterialAttributeCode());
  }

  public function getProductDescription(ProductInterface $product): string
  {
    return $this->getProductAttributeByCode($product, 'description');
  }

  // Return the category tree string for all active categories for the merchant

  /**
   * @throws LocalizedException
   */
  public function getAllCategoryTrees(): array
  {
    $categoryCollection = $this->_categoryCollectionFactory
      ->create()
      ->addAttributeToSelect('*')
      ->addIsActiveFilter();

    return $this->categoryTrees($categoryCollection);
  }

  public function categoryTrees($categoryCollection): array
  {
    $categories = [];

    foreach ($categoryCollection as $category) {
      $categories[] = $this->getCategoryTree($category);
    }

    return $categories;
  }

  /**
   * Get the names and possible values for all product attributes in the system. These are sent to
   * AirRobe to prepare mapping records in the connector.
   * @return array
   * @throws LocalizedException
   */
  public function getAllAttributes(): array
  {
    // Filter to product attributes (entity type 4)
    $this->_attributeCollection
      ->addFieldToFilter(Set::KEY_ENTITY_TYPE_ID, 4)
      ->addFieldToFilter("is_user_defined", 1);

    $data = [];
    foreach ($this->_attributeCollection->load()->getItems() as $attribute) {
      /* @var $attribute Attribute */

      $values = [];
      foreach ($attribute->getSource()->getAllOptions() as $option) {
        if (trim($option["label"]) != "") {
          $values[] = $option["label"];
        }
      }

      $data[] = [
        'name' => $attribute->getAttributeCode(),
        'values' => $values
      ];
    }
    return $data;
  }

  // Send a payload of data to the airrobe connector. Before sending we sign with an APP_ID taken
  // from the config file, and a message signature generated using the HMAC algorithm and a secret
  // token from the same config file. TODO: I understand that there are dedicated graphql classes
  // that ship with magento, and it may make sense to start using these. But for now, this CURL
  // approach is workable (and also avoids any potential compatability issues with our merchant
  // partner stores)
  public function sendToAirRobeAPI($payload): bool|string|null
  {
    $url = $this->getApiUrl();
    $appId = $this->getAppID();
    $json_payload = json_encode($payload);
    $signature = $this->getSignature($json_payload);

    $this->_logger->debug("[AIRROBE] URL: " . $url);
    $this->_logger->debug("[AIRROBE] APP_ID: " . $appId);
    $this->_logger->debug("[AIRROBE] PAYLOAD: " . $json_payload);

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

      if (!$curl) {
        throw new Exception('Failed to initialize CURL');
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
        throw new Exception(curl_error($curl), curl_errno($curl));
      }

      curl_close($curl);

      return $response;
    } catch (Exception $e) {
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
  public function safelySendErrorDetailsToApi($e): void
  {
    try {
      $url = "https://connector.airrobe.com/widget_errors";
      $payload = [
        'name' => $e->getCode(),
        'message' => $e->getMessage(),
        'host' => $this->getBaseSiteUrl(),
      ];
      $this->curlPost($url, json_encode($payload));
    } catch (Exception) {
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
