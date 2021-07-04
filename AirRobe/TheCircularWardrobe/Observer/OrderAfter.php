<?php
/**
 * @package     AirRobe_TheCircularWardrobe
 * @author      Michael Dawson <developers@airrobe.com>
 * @copyright   Copyright AirRobe (https://airrobe.com/)
 * @license     https://airrobe.com/license-agreement.txt
 */

namespace AirRobe\TheCircularWardrobe\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Psr\Log\LoggerInterface as Logger;

class OrderAfter implements \Magento\Framework\Event\ObserverInterface
{

    /**
     * @var Logger
     */
    protected $logger;


	protected $helperData;


	protected $resourceConnection;


	protected $stockItemRepository;


	protected $_storeManager;


	protected $_productRepository;


	protected $_imageHelper;


	protected $_gallery;


	protected $_product;
    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger,
		\AirRobe\TheCircularWardrobe\Helper\Data $helperData,
		\AirRobe\TheCircularWardrobe\Block\Product\View\Markup $markup,
		\Magento\Framework\Registry $registry,
		\Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
		\Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\UrlInterface $urlInterface,
		\Magento\Framework\App\ResourceConnection $resourceConnection,
		\Magento\Catalog\Model\ProductRepository $productRepository,
		\Magento\Catalog\Helper\Image $imageHelper,
		\Magento\Catalog\Model\Product\Gallery\ReadHandler $gallery,
		\Magento\Catalog\Model\Product $product
    ) {
        $this->logger = $logger;
		$this->helperData = $helperData;
		$this->markup = $markup;
		$this->registry = $registry;
		$this->stockItemRepository = $stockItemRepository;
		$this->_storeManager = $storeManager;
        $this->_urlInterface = $urlInterface;
		$this->resourceConnection = $resourceConnection;
		$this->_productRepository = $productRepository;
		$this->_imageHelper = $imageHelper;
		$this->_gallery = $gallery;
		$this->_product = $product;
    }

    public function execute(EventObserver $observer)
	{



		$cookieData = $this->markup->getCookieData();

		$optedIn = false;

		if($cookieData=='Yes')
		{
			$optedIn = true;
		}

		$order = $observer->getEvent()->getOrder();
		$orderItems = $order->getAllItems();
		$connection = $this->resourceConnection->getConnection();

		$orderDetails =  array();

		$orderDetails['email'] = $order->getCustomerEmail();
		$orderDetails['currency'] = $order->getOrderCurrencyCode();
		$orderDetails['order_id'] = $order->getIncrementId();
		$orderDetails['country_code'] = $order->getShippingAddress()->getCountryId();
		$orderDetails['customer_name'] = $this->helperData->getCustomerName($order);
		$orderDetails['order_completed_at'] = $order->getCreatedAt();

		$orderlines = array();

		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();


		foreach ($orderItems as $k => $item)
		{
			$product = $this->_productRepository->get($item->getSku());

			$images = $this->getImages($product->getId());
			$attributes = $this->getAttributes($product);

			$image_url = $this->_imageHelper->init($product, 'small_image')->setImageFile($product->getSmallImage())->resize(200, 200)->getUrl();

			$orderlines[$k]['sku']=$item->getSku();
			$orderlines[$k]['product_name']=$item->getName();
			$orderlines[$k]['price']=$item->getPrice();
			$orderlines[$k]['qty']=$item->getQtyOrdered();
			$orderlines[$k]['image']=$images;
			$orderlines[$k]['attributes']=$attributes;

		}

		$orderDetails['orderItems'] = $orderlines;
		$orderDetails['domain'] = $this->_storeManager->getStore()->getBaseUrl();

		$this->helperData->ProcessMagentoOrder($orderDetails,$optedIn);

	}
	public function getAttributes($product)
	{
		$attributes = $product->getAttributes();
		$attrs=array();

		foreach ($attributes as $attribute) {
			$value = $attribute->getFrontend()->getValue($product);
			if(!is_object($value))
			{
				$attrs[$attribute->getAttributeCode()]=$value;
			}
		}

		return $attrs;
	}
	public function getImages($product_id)
	{

		$mediaUrl =  $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
		$product = $this->_product->load($product_id);
		$this->_gallery->execute($product);
		$images = $product->getMediaGalleryImages();

		$allImages = array();
		foreach($images as $image){
		 $allImages[] = $mediaUrl."/catalog/product".$image->getFile();
		}


		return $allImages;

	}


}
