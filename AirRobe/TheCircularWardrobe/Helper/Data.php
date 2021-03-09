<?php
/**
 * @package     AirRobe_TheCircularWardrobe
 * @author      Michael Dawson <developers@airrobe.com>
 * @copyright   Copyright AirRobe (https://airrobe.com/)
 * @license     https://airrobe.com/license-agreement.txt
 */

namespace AirRobe\TheCircularWardrobe\Helper;

 
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
 
class Data extends AbstractHelper
{
	protected $_logger;
	
    const XML_PATH_ATCW = 'thecircularwardrobe/';
	
	public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger                  = $logger;
        parent::__construct($context);
    }

	public function getConfigValue($field, $storeId = null)
	{
		return $this->scopeConfig->getValue(
			$field, ScopeInterface::SCOPE_STORE, $storeId
		);
	}

	public function getGeneralConfig($code, $storeId = null)
	{
		return $this->getConfigValue(self::XML_PATH_IMPORTEXPORT .'general/'. $code, $storeId);
	}
	
	public function ProcessMagentoOrder($orderData,$optedIn)
	{
				
		$items =  array();
		/*
		{
			"title": "Thing",
			"brand": "gucci",
			"description": "Some thing",
			"rrp": 10000,
			"size": "S",
			"imageUrls": [],
			"currency": "AUD"
		}
		*/
		foreach($orderData['orderItems'] as $k => $orderItem)
		{						
		
			$items[$k]['title']= $orderItem['product_name'];
			$items[$k]['description']= $orderItem['sku'];
			$items[$k]['rrp']= $orderItem['price'];
			$items[$k]['currency']= $orderData['currency'];
			/*$items[$k]['currency']= 'AUD';
			$items[$k]['brand']= "gucci";
			$items[$k]['size']= "S";*/
			$items[$k]['imageUrls']= $orderItem['image'];			
			//$items[$k]= array_merge($items[$k],$orderItem['attributes']);
			
		}
		
		$lineItems =  json_encode($items);				
		
	
		$curl = curl_init();
		
		if($optedIn==1) 
		{
			$optedIn = 'true';
		}
		else
		{
			$optedIn = 'false';
		}
		
		
		$body = '{"query": "mutation ProcessMagentoOrder($input: CreateMagentoOrderMutationInput!){ createMagentoOrder(input: $input) { error order { id } } }",
					"variables": {
						"input": {
							"optedIn": '.$optedIn.',
							"customer": {
								"givenName": "'.$orderData['customer_name'].'",
								"familyName": "'.$orderData['customer_name'].'",
								"email": "'.$orderData['email'].'"
							},
							"id": "'.$orderData['order_id'].'",
							"store": {
								"domain": "'.$orderData['domain'].'"
							},
							"lineItems": '.$lineItems.'
						}
					}
				}';
				
				
				
		$this->_logger->addDebug("ORDER_INFO:".$body);
		
		curl_setopt_array($curl, array(
		  CURLOPT_URL => 'https://michael.shopify-app.airrobe.com/graphql',
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => '',
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 0,
		  CURLOPT_FOLLOWLOCATION => true,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => 'POST',
		  CURLOPT_POSTFIELDS =>$body,
		  CURLOPT_HTTPHEADER => array(
			'Content-Type: application/json'
		  ),
		));
		
		$response = curl_exec($curl);
					
		$this->_logger->addDebug("API_RESPO:".$response);
		
		curl_close($curl);
		
		
	}
	
	public function getCustomerName($order)
    {
        if ($order->getCustomerId()) {
            return $order->getCustomerName();
        }

        $firstname = $order->getBillingAddress()->getFirstname();
        $middlename = $order->getBillingAddress()->getMiddlename();
        $lastname = $order->getBillingAddress()->getLastname();

        if (!empty($middlename)) {
            return $firstname . ' ' . $middlename . ' ' . $lastname;
        } else {
            return $firstname . ' ' . $lastname;
        }
    }
	
}
