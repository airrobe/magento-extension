<?php
/**
 * @package     AirRobe_TheCircularWardrobe
 * @author      Michael Dawson <developers@airrobe.com>
 * @copyright   Copyright AirRobe (https://airrobe.com/)
 * @license     https://airrobe.com/license-agreement.txt
 */
namespace AirRobe\TheCircularWardrobe\Block\Product\View;

use Magento\Catalog\Model\Product;


class Markup extends \Magento\Framework\View\Element\Template
{
	const COOKIE_NAME = 'airRobeOptedInState';

    protected $_cookieManager;
    /**
     * @var Product
     */
    protected $_product = null;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
		\Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        array $data = []
    ) {
        $this->_coreRegistry = $registry;
		$this->_cookieManager = $cookieManager;
        parent::__construct($context, $data);
    }

	 public function getCookieData() {

        $result = $this->_cookieManager->getCookie(self::COOKIE_NAME);
        return $result;

    }


}
