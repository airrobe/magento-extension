<?php
/**
 * @package     AirRobe_TheCircularWardrobe 
 * @author      Michael Dawson <developers@airrobe.com>
 * @copyright   Copyright AirRobe (https://airrobe.com/)
 * @license     https://airrobe.com/license-agreement.txt
 */
 
namespace AirRobe\TheCircularWardrobe\Block\Order;

class Success extends \Magento\Sales\Block\Order\Totals
{
	/**
     * Checkout Session
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * Customer Session
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Sales Factory
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * Order Address
     * @var \Magento\Sales\Model\Order\Address\Renderer
     */
    protected $render;

    /**
     * Bss Helper Data
     * @var \Bss\OrderDetails\Helper\Data
     */
    protected $helper;

    /**
     * Pricing Helper Data
     * @var \Magento\Framework\Pricing\Helper\Data
     */
    protected $formatPrice;
	
	
	/**
     * Order Details Constructor
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Bss\OrderDetails\Helper\Data $helper
     * @param \Magento\Sales\Model\Order\Address\Renderer $render
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Pricing\Helper\Data $formatPrice
     * @param array $data
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Model\Order\Address\Renderer $render,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Pricing\Helper\Data $formatPrice,
        array $data = []
    ) {
        parent::__construct($context, $registry, $data);
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->_orderFactory = $orderFactory;
        $this->render = $render;	
        $this->formatPrice = $formatPrice;
    }
    	
    public function getOrderId()
    {
        return $this->checkoutSession->getLastRealOrderId();
    }
	
	public function getOrder()
    {
        return  $this->_order = $this->_orderFactory->create()->loadByIncrementId($this->checkoutSession->getLastRealOrderId());
    }
}