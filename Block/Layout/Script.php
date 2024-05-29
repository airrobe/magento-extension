<?php

namespace AirRobe\TheCircularWardrobe\Block\Layout;

use AirRobe\TheCircularWardrobe\Helper\Data;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Class Script
 * @package AirRobe\TheCircularWardrobe\Block\Layout
 * @noinspection PhpUnused
 *
 */
class Script extends Template
{
  protected Data $helper;

  public function __construct(
    Context $context,
    Data $helper,
    array $data = []
  ) {
    $this->_helper = $helper;
    parent::__construct($context, $data);
  }

  public function getScriptUrl(): string
  {
    return $this->_helper->getScriptUrl();
  }
}
