<?php

namespace AirRobe\TheCircularWardrobe\Block\Layout;

/**
 * [Description Markup]
 */
class Script extends \Magento\Framework\View\Element\Template
{
  protected $helper;

  public function __construct(
    \Magento\Framework\View\Element\Template\Context $context,
    \AirRobe\TheCircularWardrobe\Helper\Data $helper,
    array $data = []
  ) {
    $this->_helper = $helper;
    parent::__construct($context, $data);
  }

  public function getScriptUrl()
  {
    return $this->_helper->getScriptUrl();
  }
}
