<?php

class GocoinpayValidationModuleFrontController extends ModuleFrontController {

    /**
     * @see FrontController::initContent()
     */
    public function initContent() {

        $this->gocoin = new Gocoinpay;
        $result = $this->_paymentStandard();
        die($result);
    }

    private function _paymentStandard() {
        $module_display = $this->module->displayName;
        $data = $this->gocoin->postData();
                        
        if (isset($data->error)) {
            return Logger::addLog($data->error);
            ;
        } else {
            $key = Configuration::get('GOCOIN_ACCESS_KEY');
            $event_id = $data->id;
            $event = $data->event;
            $invoice = $data->payload;
            $payload_arr = get_object_vars($invoice);
            ksort($payload_arr);
            $signature = isset($invoice->user_defined_8) && !empty($invoice->user_defined_8) ? $invoice->user_defined_8 : '';
            $sig_comp = $this->gocoin->sign($payload_arr, $key);
            $status = $invoice->status;

            $currency_id = $invoice->user_defined_1;
            $secure_key = $invoice->user_defined_2;
            $cart_id = (int) $invoice->user_defined_3;
            $transction_id     = $invoice->id;  
            
            $cart = new Cart((int) $cart_id);
            // Check that if a signature exists, it is valid
            if (empty($signature) || empty($sig_comp)) {
                $msg = "Order Signature is blank in GoCoin invoice ( $transction_id ) ";
                Logger::addLog($msg);
            } elseif ($signature != $sig_comp) {
                $msg = "Signature : " . $signature . "does not match for GoCoin invoice ( $transction_id ) ";
                Logger::addLog($msg);
            } elseif ($signature == $sig_comp) {

                if (!Validate::isLoadedObject($cart)) {
                    $msg = "Invalid Cart ID for GoCoin invoice ( $transction_id ) ";
                    Logger::addLog($msg);
                } else {

                    switch ($event) {

                        case 'invoice_created':
                            $msg = " GoCoin Invoice is created ( $transction_id ) ";
                            break;

                        case 'invoice_payment_received':
                            if ($cart->OrderExists()) { 
                                    $order = new Order((int) Order::getOrderByCartId($cart->id));
                                    $order_status = (int) Configuration::get('PS_OS_PREPARATION');
                                    $new_history = new OrderHistory();
                                    $new_history->id_order = (int) $order->id;
                                    $new_history->changeIdOrderState((int) $order_status, $order, true);
                                      $new_history->addWithemail(true);
                             }
                            
                            $msg = 'Order ' . $order->id . ' is '.$status; 
                            Logger::addLog($msg);

                            break;
                        case 'invoice_merchant_review':
                            if ($cart->OrderExists()) {
                                $order = new Order((int) Order::getOrderByCartId($cart->id));
                                $order_status = (int) Configuration::get('PS_OS_PREPARATION');
                                $new_history = new OrderHistory();
                                $new_history->id_order = (int) $order->id;
                                $new_history->changeIdOrderState((int) $order_status, $order, true);
                                  $new_history->addWithemail(true);
                            }
                            $msg = 'Order ' . $order->id. ' is under review. Action must be taken from the GoCoin Dashboard.';
                            Logger::addLog($msg);
                            break;
                        case 'invoice_ready_to_ship':
                            if ($cart->OrderExists()) {
                               
                                if (($status == 'paid') || ($status == 'ready_to_ship')) {
                                    $order = new Order((int) Order::getOrderByCartId($cart->id));
                                    $order_status = (int) Configuration::get('PS_OS_PAYMENT');
                                    $new_history = new OrderHistory();
                                    $new_history->id_order = (int) $order->id;
                                    $new_history->changeIdOrderState((int) $order_status, $order, true);
                                    $this->gocoin->addTransactionId((int)$order->id,$transction_id); 
                                    $new_history->addWithemail(true);
                                     
                                }
                            }
                            $msg = 'Order ' . $order->id . ' has been paid in full and confirmed on the blockchain.';
                            Logger::addLog($msg);
                            break;

                        case 'invoice_invalid':
                            if ($cart->OrderExists()) {
                                if (($status == 'paid') || ($status == 'ready_to_ship')) {
                                    $order = new Order((int) Order::getOrderByCartId($cart->id));
                                    $order_status = (int) Configuration::get('PS_OS_ERROR');
                                    $new_history = new OrderHistory();
                                    $new_history->id_order = (int) $order->id;
                                    $new_history->changeIdOrderState((int) $order_status, $order, true);
                                    $new_history->addWithemail(true);
                                }
                            } 
                            $msg = 'Order ' . $order->id. ' is invalid and will not be confirmed on the blockchain.';
                            Logger::addLog($msg);
                            break;

                        default:
                                    $order = new Order((int) Order::getOrderByCartId($cart->id));
                                    $order_status = (int) Configuration::get('PS_OS_ERROR');
                                    $new_history = new OrderHistory();
                                    $new_history->id_order = (int) $order->id;
                                    $new_history->changeIdOrderState((int) $order_status, $order, true);
                                    $new_history->addWithemail(true);
                            $msg = "Unrecognized event type: " . $event;
                            Logger::addLog($msg);
                            break;
                    }
                }


                if (isset($msg)) {
                    $msg .= ' Event ID: ' . $event_id;
                    Logger::addLog($msg);
                    error_log($msg, 3, '/var/www/prestashop_16/log/gocoin_tester.log');
                }
            }
        }
    }

}

