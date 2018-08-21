<?php
class KK_Download_Model_Observer
{

    const CUSTOMER_CURR = null;
    protected $_shippingMethod = 'freeshipping_freeshipping';
    protected $_paymentMethod = 'free';
    
    protected $_customer =  self::CUSTOMER_CURR;
	
    protected $_subTotal = 0;
    protected $_order;
    protected $_storeId;
	
	public function runDownloadable(Varien_Event_Observer $observer)
	{
		$_item = $observer->getEvent()->getQuoteItem();
		$_item = ( $_item->getParentItem() ? $_item->getParentItem() : $_item );
		$iscreated=false;
		$product=$_item->getProduct();
		if('downloadable' == $product->getTypeId()) {
			$price = $product->getFinalPrice();
			if(round($price)==0)
			{
				$iscreated=$this->createOrder(array(
					'product' => $product->getId(),
					'links' => $_item->getBuyRequest()->getLinks(),
					'qty' => $_item->getQty()
				));
			}
		}
		if($iscreated)
		{			 
			$cart = Mage::getSingleton('checkout/cart');
			$cart->truncate();
			$cart->save();
			$cart->getItems()->clear()->save();        
    
			$response = Mage::app()->getFrontController()->getResponse();
			$url = Mage::getBaseUrl().'downloadable/customer/products/';
			$response->setRedirect($url);//->setParam('return_url',"$customerRedirectUrl");
			Mage::app()->getResponse()->sendResponse();
			exit;
		}
		

	}
			 
	public function setShippingMethod($methodName)
    {
        $this->_shippingMethod = $methodName;
    }

    public function setPaymentMethod($methodName)
    {
        $this->_paymentMethod = $methodName;
    }
    
    public function setCustomer($customer)
    {
        if ($customer instanceof Mage_Customer_Model_Customer){
            $this->_customer = $customer;
        }
        if (is_numeric($customer)){
            $this->_customer = Mage::getModel('customer/customer')->load($customer);
        }
        
    }

    public function createOrder($products)
    {
        if (!($this->_customer instanceof Mage_Customer_Model_Customer)){
			if(Mage::getSingleton('customer/session')->isLoggedIn())
            $this->setCustomer(Mage::getSingleton('customer/session')->getCustomer());
			else
			{
				
				$currentUrl =  Mage::helper('core/url')->getCurrentUrl().'qty/'.$products['qty'];
				//foreach($products['links'] as $lk=>$link)
				//$currentUrl.='/links['.$lk.']/'.$link;//Mage::app()->getRequest()->getServer('HTTP_REFERER');
				//die();
				$_session = Mage::getSingleton('customer/session');    
				$_session->setBeforeAuthUrl($currentUrl);
				$response = Mage::app()->getFrontController()->getResponse();
				$url = Mage::getBaseUrl().'customer/account/login';
				$response->setRedirect($url);//->setParam('return_url',"$customerRedirectUrl");
				Mage::app()->getResponse()->sendResponse();
				exit;
			}
        }

        $transaction = Mage::getModel('core/resource_transaction');
        $this->_storeId = $this->_customer->getStoreId();
        $reservedOrderId = Mage::getSingleton('eav/config')
            ->getEntityType('order')
            ->fetchNewIncrementId($this->_storeId);

        $currencyCode  = Mage::app()->getBaseCurrencyCode();
        $this->_order = Mage::getModel('sales/order')
            ->setIncrementId($reservedOrderId)
            ->setStoreId($this->_storeId)
            ->setQuoteId(0)
            ->setGlobalCurrencyCode($currencyCode)
            ->setBaseCurrencyCode($currencyCode)
            ->setStoreCurrencyCode($currencyCode)
            ->setOrderCurrencyCode($currencyCode);


        $this->_order->setCustomerEmail($this->_customer->getEmail())
            ->setCustomerFirstname($this->_customer->getFirstname())
            ->setCustomerLastname($this->_customer->getLastname())
            ->setCustomerGroupId($this->_customer->getGroupId())
            ->setCustomerIsGuest(0)
            ->setCustomer($this->_customer);

		$countryCode = Mage::getStoreConfig('general/country/default');
        $billing = $this->_customer->getDefaultBillingAddress();
		if($billing){
        $billingAddress = Mage::getModel('sales/order_address')
            ->setStoreId($this->_storeId)
            ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_BILLING)
            ->setCustomerId($this->_customer->getId())
            ->setCustomerAddressId($this->_customer->getDefaultBilling())
            ->setCustomerAddress_id($billing->getEntityId())
            ->setPrefix($billing->getPrefix())
            ->setFirstname($billing->getFirstname())
            ->setMiddlename($billing->getMiddlename())
            ->setLastname($billing->getLastname())
            ->setSuffix($billing->getSuffix())
            ->setCompany($billing->getCompany())
            ->setStreet($billing->getStreet())
            ->setCity($billing->getCity())
            ->setCountry_id($billing->getCountryId())
            ->setRegion($billing->getRegion())
            ->setRegion_id($billing->getRegionId())
            ->setPostcode($billing->getPostcode())
            ->setTelephone($billing->getTelephone())
            ->setFax($billing->getFax());
		}
		else{
			
        $billingAddress = Mage::getModel('sales/order_address')
            ->setStoreId($this->_storeId)
            ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_BILLING)
            ->setCustomerId($this->_customer->getId())
            ->setCustomerAddressId($this->_customer->getDefaultBilling())
            ->setFirstname($this->_customer->getFirstname())
            ->setMiddlename($this->_customer->getMiddlename())
            ->setLastname($this->_customer->getLastname())
            ->setCompany('Company')
            ->setStreet('Street')
            ->setCity('City')
            ->setCountry_id($countryCode)
            ->setPostcode('Postcode')
            ->setTelephone('Telephone')
            ->setFax('Fax');
		}
        $this->_order->setBillingAddress($billingAddress);

        $shipping = $this->_customer->getDefaultShippingAddress();
		if($shipping){
        $shippingAddress = Mage::getModel('sales/order_address')
            ->setStoreId($this->_storeId)
            ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_SHIPPING)
            ->setCustomerId($this->_customer->getId())
            ->setCustomerAddressId($this->_customer->getDefaultShipping())
            ->setCustomer_address_id($shipping->getEntityId())
            ->setPrefix($shipping->getPrefix())
            ->setFirstname($shipping->getFirstname())
            ->setMiddlename($shipping->getMiddlename())
            ->setLastname($shipping->getLastname())
            ->setSuffix($shipping->getSuffix())
            ->setCompany($shipping->getCompany())
            ->setStreet($shipping->getStreet())
            ->setCity($shipping->getCity())
            ->setCountry_id($shipping->getCountryId())
            ->setRegion($shipping->getRegion())
            ->setRegion_id($shipping->getRegionId())
            ->setPostcode($shipping->getPostcode())
            ->setTelephone($shipping->getTelephone())
            ->setFax($shipping->getFax());
		}
		else
		{
			$shippingAddress = Mage::getModel('sales/order_address')
            ->setStoreId($this->_storeId)
            ->setAddressType(Mage_Sales_Model_Quote_Address::TYPE_SHIPPING)
            ->setCustomerId($this->_customer->getId())
            ->setCustomerAddressId($this->_customer->getDefaultShipping())
            ->setFirstname($this->_customer->getFirstname())
            ->setMiddlename($this->_customer->getMiddlename())
            ->setLastname($this->_customer->getLastname())
            ->setCompany('Company')
            ->setStreet('Street')
            ->setCity('City')
            ->setCountry_id($countryCode)
            ->setPostcode('Postcode')
            ->setTelephone('Telephone')
            ->setFax('Fax');

		}
        $this->_order->setShippingAddress($shippingAddress)
            ->setShippingMethod($this->_shippingMethod);

        $orderPayment = Mage::getModel('sales/order_payment')
            ->setStoreId($this->_storeId)
            ->setCustomerPaymentId(0)
            ->setMethod($this->_paymentMethod)
            ->setPoNumber(' – ');

        $this->_order->setPayment($orderPayment);

        $this->_addProducts($products);

        $this->_order->setSubtotal($this->_subTotal)
            ->setBaseSubtotal($this->_subTotal)
            ->setGrandTotal($this->_subTotal)
            ->setBaseGrandTotal($this->_subTotal);

        $transaction->addObject($this->_order);
        $transaction->addCommitCallback(array($this->_order, 'place'));
        $transaction->addCommitCallback(array($this->_order, 'save'));
        $transaction->save();        
		$this->automaticallyInvoiceShipCompleteOrder($this->_order);
		return true;
    }
 	protected function _addProducts($requestData)
    {
        $request = new Varien_Object();
        $request->setData($requestData);

        $product = Mage::getModel('catalog/product')->load($request['product']);

        $cartCandidates = $product->getTypeInstance(true)
            ->prepareForCartAdvanced($request, $product);

        if (is_string($cartCandidates)) {
            throw new Exception($cartCandidates);
        }

        if (!is_array($cartCandidates)) {
            $cartCandidates = array($cartCandidates);
        }

        $parentItem = null;
        $errors = array();
        $items = array();
        foreach ($cartCandidates as $candidate) {
            $item = $this->_productToOrderItem($candidate, $candidate->getCartQty());

            $items[] = $item;

            /**
             * As parent item we should always use the item of first added product
             */
            if (!$parentItem) {
                $parentItem = $item;
            }
            if ($parentItem && $candidate->getParentProductId()) {
                $item->setParentItem($parentItem);
            }
            /**
             * We specify qty after we know about parent (for stock)
             */
            $item->setQty($item->getQty() + $candidate->getCartQty());

            // collect errors instead of throwing first one
            if ($item->getHasError()) {
                $message = $item->getMessage();
                if (!in_array($message, $errors)) { // filter duplicate messages
                    $errors[] = $message;
                }
            }
        }
        if (!empty($errors)) {
            Mage::throwException(implode("\n", $errors));
        }

        foreach ($items as $item){
            $this->_order->addItem($item);
        }

        return $items;
    }

    function _productToOrderItem(Mage_Catalog_Model_Product $product, $qty = 1)
    {
        $rowTotal = $product->getFinalPrice() * $qty;

        $options = $product->getCustomOptions();

        $optionsByCode = array();

        foreach ($options as $option)
        {
            $quoteOption = Mage::getModel('sales/quote_item_option')->setData($option->getData())
                ->setProduct($option->getProduct());

            $optionsByCode[$quoteOption->getCode()] = $quoteOption;
        }

        $product->setCustomOptions($optionsByCode);

        $options = $product->getTypeInstance(true)->getOrderOptions($product);

        $orderItem = Mage::getModel('sales/order_item')
            ->setStoreId($this->_storeId)
            ->setQuoteItemId(0)
            ->setQuoteParentItemId(NULL)
            ->setProductId($product->getId())
            ->setProductType($product->getTypeId())
            ->setQtyBackordered(NULL)
            ->setTotalQtyOrdered($product['rqty'])
            ->setQtyOrdered($product['qty'])
            ->setName($product->getName())
            ->setSku($product->getSku())
            ->setPrice($product->getFinalPrice())
            ->setBasePrice($product->getFinalPrice())
            ->setOriginalPrice($product->getFinalPrice())
            ->setRowTotal($rowTotal)
            ->setBaseRowTotal($rowTotal)

            ->setWeeeTaxApplied(serialize(array()))
            ->setBaseWeeeTaxDisposition(0)
            ->setWeeeTaxDisposition(0)
            ->setBaseWeeeTaxRowDisposition(0)
            ->setWeeeTaxRowDisposition(0)
            ->setBaseWeeeTaxAppliedAmount(0)
            ->setBaseWeeeTaxAppliedRowAmount(0)
            ->setWeeeTaxAppliedAmount(0)
            ->setWeeeTaxAppliedRowAmount(0)

            ->setProductOptions($options);

        $this->_subTotal += $rowTotal;

        return $orderItem;
    }

	//Invoice and ship , Complete order
	public function automaticallyInvoiceShipCompleteOrder($order)
    {
 
        $orders = Mage::getModel('sales/order_invoice')->getCollection()
                        ->addAttributeToFilter('order_id', array('eq'=>$order->getId()));
        $orders->getSelect()->limit(1);  
 
        if ((int)$orders->count() !== 0) {
            return $this;
        }
 
        if ($order->getState() == Mage_Sales_Model_Order::STATE_NEW) {
 
            try {
                if(!$order->canInvoice()) {
                    $order->addStatusHistoryComment('Inchoo_Invoicer: Order cannot be invoiced.', false);
                    $order->save();  
                }
 
                //START Handle Invoice
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
 
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                $invoice->register();
 
                $invoice->getOrder()->setCustomerNoteNotify(false);          
                $invoice->getOrder()->setIsInProcess(true);
                $order->addStatusHistoryComment('Automatically INVOICED by Inchoo_Invoicer.', false);
 
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
 
                $transactionSave->save();
                //END Handle Invoice
 
                //START Handle Shipment
                $shipment = $order->prepareShipment();
                $shipment->register();
 
                $order->setIsInProcess(true);
                $order->addStatusHistoryComment('Automatically SHIPPED by Inchoo_Invoicer.', false);
 
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
                //END Handle Shipment
            } catch (Exception $e) {
                $order->addStatusHistoryComment('Inchoo_Invoicer: Exception occurred during automaticallyInvoiceShipCompleteOrder action. Exception message: '.$e->getMessage(), false);
                $order->save();
            }                
        }
 
		return $this;        
    }	
}
