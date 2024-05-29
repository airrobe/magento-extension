<?php
// This is the observer that listeners to order creation events, and sends order data to our
// connector.
namespace AirRobe\TheCircularWardrobe\Observer;

use AirRobe\TheCircularWardrobe\Service\OrderProcessingService;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface as Logger;

/**
 * Class OrderAfter
 * @package AirRobe\TheCircularWardrobe\Observer
 * @noinspection PhpUnused
 */
class OrderAfter implements ObserverInterface
{
  protected Logger $_logger;
  protected OrderProcessingService $orderProcessingService;

  public function __construct(
    Logger $logger,
    OrderProcessingService $orderProcessingService
  ) {
    $this->_logger = $logger;
    $this->orderProcessingService = $orderProcessingService;
  }

  public function execute(EventObserver $observer)
  {
    if (!$this->orderProcessingService->isExtensionEnabled()) {
      $this->_logger->debug("[AIRROBE] AirRobe is installed but not enabled. Skipping AirRobe actions.");
      return null;
    }

    $order = $observer->getEvent()->getData('order');

    $this->orderProcessingService->sendOrderToAirRobe($order);
  }

}
