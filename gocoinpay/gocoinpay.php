<?php

if (!defined('_PS_VERSION_'))
    exit;

class Gocoinpay extends PaymentModule {

    private $_error = array();
    private $_validation = array();
    private $_shop_country = array();

    public function __construct() {

        $this->name = 'gocoinpay';
        $this->version = '1.3.6';
        $this->author = 'GoCoinpay';
        $this->className = 'Gocoinpay';
        $this->tab = 'payments_gateways';

        parent::__construct();
        $this->_shop_country = new Country((int) Configuration::get('PS_SHOP_COUNTRY_ID'));
        $this->displayName = $this->l((Validate::isLoadedObject($this->_shop_country) && $this->_shop_country->iso_code == 'MX') ? 'GoCoin' : 'GoCoin');
        $this->description = $this->l((Validate::isLoadedObject($this->_shop_country) && $this->_shop_country->iso_code == 'MX') ? 'Accept payments using Bitcoin or Licoin using GoCoin Payment Gateway.' : 'Accept payments using Bitcoin or Licoin using GoCoin Payment Gateway.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
        /* Backward compatibility */
        require(_PS_MODULE_DIR_ . 'gocoinpay/backward_compatibility/backward.php');
        $this->context->smarty->assign('base_url', _PS_BASE_URL_ . __PS_BASE_URI__);
        $this->context->smarty->assign('base_dir', __PS_BASE_URI__);

        if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
            $php_version_allowed = true;
            $this->context->smarty->assign('php_version_allowed', 'Y');
        } else {
            $php_version_allowed = false;
            $this->_error[] = 'PHP Version Error:'; // The minimum PHP version required for GoCoin plugin is 5.3.0
            $this->context->smarty->assign('php_version_allowed', 'N');
        }
    }

    /** GoCoin installation process:
     *
     * Step 1 - Pre-set Configuration option values
     * Step 2 - Install the Addon and create a database table to store transaction details
     *
     * @return boolean Installation result
     */
    public function install() {

        /* The cURL PHP extension must be enabled to use this module */
        if (!function_exists('curl_version')) {
            $this->_errors[] = $this->l('Sorry, this module requires the cURL PHP Extension (http://www.php.net/curl), which is not enabled on your server. Please ask your hosting provider for assistance.');
            return false;
        }

        /* General Configuration options */
        // 2013-11-8 if there is no country specified, choose US, otherwise module will fail
        if (version_compare(_PS_VERSION_, '1.5', '<')) {
            Configuration::updateValue('PS_SHOP_COUNTRY', 'US');
        } else {
            Configuration::updateValue('PS_SHOP_COUNTRY_ID', 21);
        }

        return parent::install() && $this->registerHook('payment') && $this->registerHook('adminOrder') &&
                $this->registerHook('header') && $this->registerHook('orderConfirmation') && $this->registerHook('shoppingCartExtra') &&
                $this->registerHook('productFooter') && $this->registerHook('BackOfficeHeader')  ;
    }

    /**
     * GoCOin database table installation (to store the transaction details)
     *
     * @return boolean Database table installation result
     */

    /**
     * GoCoin uninstallation process:
     *
     * Step 1 - Remove Configuration option values from database
     * Step 2 - Remove the database containing the transaction details (optional, must be done manually)
     * Step 3 - Uninstallation of the Addon itself
     *
     * @return boolean Uninstallation result
     */
    public function uninstall() {
        $keys_to_uninstall = array('GOCOIN_MERCHANT_ID', 'GOCOIN_ACCESS_KEY');
        $result = true;
        foreach ($keys_to_uninstall as $key_to_uninstall)
            $result &= Configuration::deleteByName($key_to_uninstall);

        /* Uncomment this line if you would like to also delete the Transaction details table */
        /* $result &= Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'gocoin_ipn`'); */
        return $result && parent::uninstall();
    }

    /* GoCoin configuration section
     *
     * @return HTML page (template) to configure the Addon
     */

    public function getContent() {
        $this->context->controller->addCSS(array($this->_path . 'css/gocoin.css', $this->_path . 'css/gocoin.css'));
        /* Update the Configuration option values depending on which form has been submitted */
        if ((Validate::isLoadedObject($this->_shop_country) && $this->_shop_country->iso_code == 'MX') && Tools::isSubmit('SubmitBasicSettings')) {

            $this->_saveSettingsBasic();
            unset($this->_validation[count($this->_validation) - 1]);
        } elseif (Tools::isSubmit('SubmitBasicSettings'))
            $this->_saveSettingsBasic();
        elseif (Tools::isSubmit('SubmitAdvancedSettings'))
            $this->_saveSettingsAdvanced();

        // 2013-11-8 add 1.4 token support
        if (method_exists('Tools', 'getAdminTokenLite')) {
            $token = Tools::getAdminTokenLite('AdminModules');
        } else {
            $tabid = (int) Tab::getCurrentTabId();
            $employee_id = (int) $this->context->cookie->id_employee;
            $token = 'AdminModules' . $tabid . $employee_id;
            $token = Tools::getAdminToken($token);
        }
        $this->context->smarty->assign(array(
            'gocoin_tracking' => 'http://www.prestashop.com/modules/gocoin.png?url_site=' . Tools::safeOutput($_SERVER['SERVER_NAME']) . '&id_lang=' . (int) $this->context->cookie->id_lang,
            'gocoin_form_link' => './index.php?tab=AdminModules&configure=gocoin&token=' . Tools::getAdminTokenLite('AdminModules') . '&tab_module=' . $this->tab . '&module_name=gocoin',
            'gocoin_ssl' => Configuration::get('PS_SSL_ENABLED'),
            'gocoin_validation' => (empty($this->_validation) ? false : $this->_validation),
            'gocoin_error' => (empty($this->_error) ? false : $this->_error),
            'gocoin_warning' => (empty($this->_warning) ? false : $this->_warning),
            'gocoin_configuration' => Configuration::getMultiple(array('GOCOIN_MERCHANT_ID', 'GOCOIN_ACCESS_KEY', 'GOCOIN_TOKEN')),
            'gocoin_merchant_country_is_usa' => (Validate::isLoadedObject($this->_shop_country) && $this->_shop_country->iso_code == 'US'),
            'gocoin_merchant_country_is_mx' => (Validate::isLoadedObject($this->_shop_country) && $this->_shop_country->iso_code == 'MX'),
            'gocoin_ps_14' => (version_compare(_PS_VERSION_, '1.5', '<') ? 1 : 0),
            'gocoin_b1width' => (version_compare(_PS_VERSION_, '1.5', '>') ? '350' : '300'),
            'gocoin_js_files' => stripcslashes('"' . _PS_JS_DIR_ . 'jquery/jquery-ui-1.8.10.custom.min.js","' . $this->_path . 'js/colorpicker.js","' . $this->_path . 'js/jquery.lightbox_me.js","' . $this->_path . 'js/gocoin.js' . '"')
        ));

        return $this->display(__FILE__, 'views/templates/admin/configuration' . ((Validate::isLoadedObject($this->_shop_country) && $this->_shop_country->iso_code == 'MX') ? '-mx' : '') . '.tpl');
    }

    /*
     * GoCoin configuration section - Basic settings (Merchant ID, Secrect Key, Access Token)
     */

    private function _saveSettingsBasic() {
        if (!isset($_POST['gocoin_merchant_id']) || !$_POST['gocoin_merchant_id']) {
            $this->_error[] = $this->l('Client ID is required.');
        }
        if (!isset($_POST['gocoin_access_key']) || !$_POST['gocoin_access_key']) {
            $this->_error[] = $this->l('Client Secret Key is required.');
        }

        Configuration::updateValue('GOCOIN_MERCHANT_ID', pSQL(Tools::getValue('gocoin_merchant_id')));
        Configuration::updateValue('GOCOIN_ACCESS_KEY', pSQL(Tools::getValue('gocoin_access_key')));


        if (!count($this->_error))
            $this->_validation[] = $this->l('Congratulations, your configuration was updated successfully');
    }

    /* GoCoin payment hook
     *
     * @param $params Array Default PrestaShop parameters sent to the hookPayment() method (Order details, etc.)
     *
     * @return HTML content (Template) displaying the enable GoCoin payment methods (Bitcoin, Litcoin)
     */

    public function hookPayment($params) {
      
        if (!$this->active){
            Logger::addLog("GoCoin Payment Gateway is not active ");            
            return;
        }
        if (!$this->checkCurrency($params['cart'])){
            Logger::addLog("Currency is not set ");            
            return;
        }
        if (count($this->_error))
         {  
                $msg = @implode (',', $this->_error);
                Logger::addLog($msg);            
                return;
         }
        $access_token = Configuration::get('GOCOIN_ACCESS_KEY');
        $merchant_id = Configuration::get('GOCOIN_MERCHANT_ID');
        
        // Check to make sure we have an access token (API Key)
        if (empty($access_token)) {
             Logger::addLog("GoCoin API key is not set ");            
            return;
        }
        //Check to make sure we have a merchant ID
       if (empty($merchant_id)) {
              Logger::addLog("GoCoin merchant ID is not set ");   
             return;
        }
        

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            // 'paytype'          => $pay_arr,
            'gocoinpay_action' => $this->context->link->getModuleLink('gocoinpay', 'payment', array(), (Configuration::get('PS_SSL_ENABLED')) ? true : false )
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    public function checkCurrency($cart) {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;
        return false;
    }

    /* GoCoin Order confirmation hook
     *
     * @param $params Array Default PrestaShop parameters sent to the hookOrderConfirmation() method
     *
     * @return HTML content (Template) displaying a confirmation or error message upon order creation
     */

    public function hookOrderConfirmation($params) {

        if (!isset($params['objOrder']) || ($params['objOrder']->module != $this->name))
            return false;
        if (isset($params['objOrder']) && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid) &&
                version_compare(_PS_VERSION_, '1.5', '>=') && isset($params['objOrder']->reference)) {
            $this->smarty->assign('gocoin_order', array('id' => $params['objOrder']->id, 'reference' => $params['objOrder']->reference, 'valid' => $params['objOrder']->valid));
            return $this->display(__FILE__, 'views/templates/hook/order-confirmation.tpl');
        }
        // 2013-11-8 add 1.4 support
        if (isset($params['objOrder']) && Validate::isLoadedObject($params['objOrder']) && isset($params['objOrder']->valid) &&
                version_compare(_PS_VERSION_, '1.5', '<')) {
            $this->smarty->assign('gocoin_order', array('id' => $params['objOrder']->id, 'valid' => $params['objOrder']->valid));

            return $this->display(__FILE__, 'views/templates/hook/order-confirmation.tpl');
        }
    }

    /* GoCoin Order Transaction ID update
     * Attach a GoCoin Transaction ID to an existing order (it will be displayed in the Order details section of the Back-office)
     *
     * @param $id_order integer Order ID
     * @param $id_transaction string   Transaction ID
     */

    public function addTransactionId($id_order, $id_transaction) {
        if (version_compare(_PS_VERSION_, '1.5', '>=')) {
            $new_order = new Order((int) $id_order);
            if (Validate::isLoadedObject($new_order)) {
                $payment = $new_order->getOrderPaymentCollection();
                if (isset($payment[0])) {
                    $payment[0]->transaction_id = pSQL($id_transaction);
                    $payment[0]->save();
                }
            }
        }
    }

    /* GoCoin Transaction details update
     * Attach transactions details to an existing order (it will be displayed in the Order details section of the Back-office)
     *
     * @param $type Can be either 'payment' or 'refund' depending on the desired operation
     * @param $details Array Transaction details
     *
     * @return boolean Operation result
     */

    public function sign($data, $key) {
     //     $include = array('price_currency', 'base_price', 'base_price_currency', 'order_id', 'customer_name');
            $include = array( 'base_price', 'base_price_currency', 'order_id', 'customer_name');
        // $data must be an array
        if (is_array($data)) {
      
            $querystring = "";
            while (count($include) > 0) {
                $k = $include[0];
                
                if (isset($data[$k])) {
                    $querystring .= $k . "=" . $data[$k] . "&";
                    array_shift($include);
                } else {
                    return false;
                }
            }

            //Strip trailing '&' and lowercase 
            $msg = substr($querystring, 0, strlen($querystring) - 1);
            $msg = strtolower($msg);

            // hash with key
            $hash = hash_hmac("sha256", $msg, $key, true);
            $encoded = base64_encode($hash);
          
            return $encoded;
        } else {
            return false;
        }
    }
    
    public function postData() {
      //get webhook content
      $response = new stdClass();
      $post_data = file_get_contents("php://input");
      error_log($post_data.'\n', 3, '/var/www/prestashop_16/log/gocoin_tester.log');
      if (!$post_data) {
        $response->error = 'Request body is empty';
      }

      $post_as_json = json_decode($post_data);
      if (is_null($post_as_json)){
        $response->error = 'Request body was not valid json';
      } else {
        $response = $post_as_json;
      }
      return $response;
  }

}
