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
		\Magento\Catalog\Helper\Image $imageHelper
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
			$image_url = $this->_imageHelper->init($product, 'small_image')->setImageFile($product->getSmallImage())->resize(200, 200)->getUrl();
			
			$orderlines[$k]['sku']=$item->getSku();
			$orderlines[$k]['product_name']=$item->getName();		
			$orderlines[$k]['price']=$item->getPrice();		
			$orderlines[$k]['qty']=$item->getQtyOrdered();											 			 						
			$orderlines[$k]['image']=$image_url;											 			 									
		}	
		
		$orderDetails['orderItems'] = $orderlines;
		$orderDetails['domain'] = $this->_storeManager->getStore()->getBaseUrl();
		
		$this->helperData->ProcessMagentoOrder($orderDetails,$optedIn); 				
		
	}
}
