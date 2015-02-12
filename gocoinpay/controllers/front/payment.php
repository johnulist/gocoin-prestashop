<?php
include_once _PS_CLASS_DIR_ . 'gocoinlib/src/GoCoin.php';
class GocoinpayPaymentModuleFrontController extends ModuleFrontController {
    public $ssl = true;
    /**
     * @see FrontController::initContent()
     */
    public function initContent() {
        $this->display_column_left = false;
        parent::initContent();
        $gocoin = new Gocoinpay();
        $default_sts    = (int) Configuration::get('PS_OS_CHEQUE');
        $cart           = $this->context->cart;
        $ssl            = Configuration::get('PS_SSL_ENABLED')?true:null;
        $error_log_path =  _PS_ROOT_DIR_.'/log/gocoin_error.log';
        $errorlog       = '';
        
        if (!$this->module->checkCurrency($cart)){
            Tools::redirect($this->context->link->getPageLink('order.php', ''));
        }
      
        $merchant_id                = Configuration::get('GOCOIN_MERCHANT_ID');
        $access_token               = Configuration::get('GOCOIN_ACCESS_KEY');
       

                $json_str = '';
                $result = '';
                $messages ='';
                $redirect ='';
                $errorlog ='';
        // Check to make sure we have an access token (API Key)
              if (empty($access_token)) {
                  $messages = 'Improper Gateway set up. API Key not found.'; 
                  $result = 'error';  
              }
              //Check to make sure we have a merchant ID
              elseif (empty($merchant_id)) {
                  $messages = 'Improper Gateway set up.  Merchant ID not found.'; 
                  $result = 'error';  
              }
                // Proceed
              else {  
                    $currency                   = new Currency((int) $this->context->cart->id_currency);
                    
                    $cart                       = $this->context->cart;
                    $shipping_customer           = $this->context->customer;
                    $shipping_address            = new Address((int) $this->context->cart->id_address_invoice);
                    $shipping_address->country   = new Country((int) $shipping_address->id_country);
                    $shipping_address->state     = new State((int) $shipping_address->id_state);

                    $data = array();
                    $data['currency_code']      = urlencode($currency->iso_code);
                    $data['name']               = $shipping_customer->firstname . " " . $shipping_customer->lastname;
                    $data['address1']           = $shipping_address->address1;
                    $data['address2']           = $shipping_address->address2;
                    $data['city']               = $shipping_address->city;
                    $data['state']              = $shipping_address->state->name;
                    $data['zip']                = $shipping_address->postcode;
                    $data['country']            = $shipping_address->country->iso_code;
                    $data['email']              = $shipping_customer->email;

                    if (isset($shipping_address->phone_mobile) && !empty($shipping_address->phone_mobile)) {
                        $data['day_phone_b']    = $shipping_address->phone_mobile;
                    } elseif (isset($shipping_address->phone) && !empty($shipping_address->phone)) {
                        $data['day_phone_b']    = $shipping_address->phone;
                    }
 
        
                    $total                  = (float) $this->context->cart->getOrderTotal(true);
                    $url                    = array();
                    $url['cancel_url']      = $this->context->link->getPageLink('order.php', $ssl);
                    $url['callback_url']    = $this->context->link->getModuleLink('gocoinpay', 'validation', array('pps' => 1), (Configuration::get('PS_SSL_ENABLED'))?true :false);
        
                    $ps_version = _PS_VERSION_;
                    $show_breadcrumb = '1';
                    $return_url_param = array('id_cart'   =>(int) $this->context->cart->id,
                          		  'id_module' =>(int) $this->module->id,
	  	                          'key'		    =>	$this->context->customer->secure_key);
                    if((int) version_compare($ps_version, '1.5.6.2', '>')){
                        $show_breadcrumb = '0';
                    }  
                   $url['redirect_url'] = $this->context->link->getPageLink('order-confirmation.php', $ssl, (int)$this->context->language->id,$return_url_param);
                    
                   $options = array(
                    "type"                     => 'bill',
                    "base_price"               =>  $total,
                    "base_price_currency"      => $data['currency_code'],
                    "callback_url"             => $url['callback_url'],
                    "redirect_url"             => $url['redirect_url'],
                    "order_id"                 => 'Temporary Id :' . $cart->id,
                    "customer_name"            => $data['name'],
                    "customer_address_1"       => $data['address1'],
                    "customer_address_2"       => $data['address2'],
                    "customer_city"            => $data['city'],
                    "customer_region"          => $data['state'],
                    "customer_postal_code"     => $data['zip'],
                    "customer_country"         => $data['country'],
                    "customer_phone"           => $data['day_phone_b'],
                    "customer_email"           => $data['email'],
                    "user_defined_1"           => (int) $currency->id,
                    "user_defined_2"           => $shipping_customer->secure_key,
                    "user_defined_3"           => $cart->id
                    );
                    
      
                    if ($signature = $gocoin->sign($options, $access_token)) {
                      $options['user_defined_8'] = $signature;
                     
                    }
                    
                    try {
                       
                        $invoice = GoCoin::createInvoice($access_token, $merchant_id, $options); 
                        if(isset($invoice) && isset($invoice->gateway_url) && !empty($invoice->gateway_url)){
                            $url = $invoice->gateway_url;
                            if(isset($url) && !empty($url)){ 
                                $sts = (int) Configuration::get('PS_OS_CHEQUE');
                                $gocoin->validateOrder($cart->id, $sts, $total, $gocoin->displayName, NULL, Null, (int) $currency->id, false, $shipping_customer->secure_key);
                                 Tools::redirect($url);
                            }
                            
                        }
                        else{
                              echo  $result = 'error'; 
                                $messages = 'error'; 
                       }
                    } catch (Exception $e) { 
                        $result = 'error'; 
                        $messages =  $e->getMessage();  
                    }
                   
              } // End Of Proceed
        
         
                
         
       
           
        
         $this->context->smarty->assign(array(
            '_show_breadcrumb' => $show_breadcrumb,
            '_payformaction' => $this->context->link->getModuleLink('gocoinpay', 'payform', array(),  (Configuration::get('PS_SSL_ENABLED'))?true :false),
            '_result'        => $result,
            '_messages'      => $messages,
            '_redirect'      => $redirect,
            'nbProducts'     => $cart->nbProducts(),
            'cust_currency'  => $cart->id_currency,
            'currencies'     => $this->module->getCurrency((int) $cart->id_currency),
            'total'          => $cart->getOrderTotal(true, Cart::BOTH),
            'this_path'      => $this->module->getPathUri(),
            'this_path_bw'   => $this->module->getPathUri(),
            'this_path_ssl'  => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/'
        ));
        
        if(isset($result) && $result=='error'){
               Logger::addLog($messages);
               $this->setTemplate('errors-messages.tpl');
        } 
   }
}

