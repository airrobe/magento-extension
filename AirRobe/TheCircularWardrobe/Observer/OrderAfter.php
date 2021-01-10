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
    /**
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger,
		\AirRobe\TheCircularWardrobe\Helper\Data $helperData,
		\Magento\Framework\Registry $registry,
		\Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,		
		\Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
        $this->logger = $logger;
		$this->helperData = $helperData;
		$this->registry = $registry;
		$this->stockItemRepository = $stockItemRepository;
		$this->resourceConnection = $resourceConnection;
    }

    public function execute(EventObserver $observer)
	{
	}
}
