<?php

/*
  
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * or OpenGPL v3 license (GNU Public License V3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * or
 * http://www.gnu.org/licenses/gpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@balticode.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category   Balticode
 * @package    Balticode_Dpd
 * @copyright  Copyright (c) 2013 UAB BaltiCode (http://www.balticode.com/)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt  GNU Public License V3.0
 * @author     Šarūnas Narkevičius
 * 

 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (!class_exists('balticode_dpd_courier', false)) {
    Module::getInstanceByName('balticode_dpd_courier');
}


/**
 * <p>Handles automatic data sending functions between PrestaShop order and DPD api</p>
 * <p>Performs following:</p>
 * <ul>
     <li>Adds extra fields to current shipping method configuration with appropriate validator rules.</li>
     <li>Sends data to DPD parcel terminal (op=order)</li>
     <li>Fetches PDF packing slip for the carrier</li>
 </ul>
 *
 * @author Sarunas Narkevicius
 */
class balticode_dpd_parcelstore_data_send_executor {
    /**
     * If order comment starts with prefix marked here and is not visible on the frontend, then it is considered as extra data order comment.
     */
    const ORDER_COMMENT_START_PREFIX = '-----BALTICODE_DPDLT-----';
    
    /**
     * <p>Shipping method code where current executor runs in</p>
     * @var string
     */
    protected $_code;
    
    /**
     * <p>Configuration prefix for the current executor</p>
     * @var string 
     */
    protected $_configPrefix;
    
    /**
     * <p>Carrier method instance</p>
     * @var balticode_dpd_parcelstore
     */
    protected $_baseInstance;
    
    /**
     * <p>Makes sure that extra info displayed for a maximum of 1 time</p>
     * @var bool
     */
    protected static $_infoByCartDisplayed;
    
    /**
     * <p>If current executor has configuration form, then fields are store here</p>
     * @var array 
     */
    protected $form_fields = array();
    
    

    /**
     * <p>Carrier method instance for this data send executor</p>
     * @param balticode_dpd_parcelstore $baseInstance
     */
    public function __construct($baseInstance) {
        $this->_baseInstance = $baseInstance;
        $this->_code = $this->_baseInstance->name;
        $this->_configPrefix = constant(get_class($this->_baseInstance).'::CONST_PREFIX');
        
    }
    
    /**
     * <p>Adds ability to change Shipping method configuration prefix after construction</p>
     * @param type $configPrefix
     * @return balticode_dpd_parcelstore_data_send_executor
     */
    public function setConfigPrefix($configPrefix) {
        $this->_configPrefix = $configPrefix;
        return $this;
    }
    
    /**
     * <p>Returns always true</p>
     * @return boolean
     */
    public function install() {
        return true;
    }

    /**
     * <p>Returns always true</p>
     * @return boolean
     */
    public function uninstall() {
        return true;
    }
    
    /**
     * <p>Returns always true</p>
     * @return boolean
     */
    public function _postProcess() {
        return true;
    }
    
    /**
     * <p>Does nothing, was included because earlier versions had postValidation in here.</p>
     * @return boolean
     */
    public function _postValidation(&$postErrors) {
        
    }
    
    /**
     * <p>Here is displayed extra info (if any) is related to the executor.</p>
     * @param type $cart_id
     * @return string
     */
    public function displayInfoByCart($cart_id) {
        $data = '';
        if (is_null(self::$_infoByCartDisplayed)) {
            //TODO handle data display here, make sure it is displayed only once...
            /* @var $order OrderCore */
            $order = new Order(Order::getOrderByCartId($cart_id));
            $orderData = $this->getDataFromOrder($order);
//            echo '<pre>'.htmlspecialchars(print_r($order, true)).'</pre>';
            if (isset($orderData['Parcel_numbers'])) {
                $data .= '<div class="balticode_dpd_parcelstore_track">';
                foreach ($orderData['Parcel_numbers'] as $trackingNumber) {
                    $data .= '<p><a target="_blank" href="'.htmlspecialchars(str_replace('@', $trackingNumber, balticode_dpd_parcelstore::TRACKING_URL)).'">'.  htmlspecialchars(sprintf($this->l('Track shipment: %s'), $trackingNumber)).'</a></p>';
                }
                $data .= '</div>';
            }
            
            self::$_infoByCartDisplayed = TRUE;
            
        }
        return $data;
        
    }
    
    /**
     * <p>At the moment, when order is marked as paid, data is sent to remote server.</p>
     * <p>This Prestashop hook <code>paymentConfirm</code> uses following parameters:</p>
     * <ul>
         <li><code>id_order</code> - id of the order, which is being currently marked as paid.</li>
     </ul>
     * <p>Data is sent when <code>SENDDATA_ENABLE</code> is valued as <code>yes</code> and data has not already been sent previously</p>
     * <p>Data is sent separately for each order address.</p>
     * <p>If no exceptions occur then information about data sending is added to order comments</p>
     * <p>If exceptions occur, then exceptions are added to order comments</p>
     * <p>Order comments are not visible in the frontend.</p>
     * 
     * @param array $params
     * @return bool
     */
    public function hookpaymentConfirm(&$params) {
        $order = new Order($params['id_order']);
        
        if ($this->_baseInstance->getConfigData('SENDDATA_ENABLE') != 'yes') {
            return;
        }
        
        /* @var $order OrderCore */
        $order = new Order($params['id_order']);
        if (!Validate::isLoadedObject($order)) {
            return;
        }
        //check if carrier matches
        /* @var $carrier CarrierCore */
        $carrier = new Carrier($order->id_carrier);
        if (!Validate::isLoadedObject($carrier)) {
            return;
        }
        
        //run this hook only if order carrier matches baseinstance name
        if ($this->_baseInstance->name != $carrier->name) {
            return;
        }
        
        if (!in_array($carrier->name, array(balticode_dpd_parcelstore::NAME, balticode_dpd_courier::NAME))) {
            return;
        }
        
        //check if data is already sent
        if ($this->isDataSent($order)) {
            return;
        }
        
        //data is not sent, find selected parcel terminal
        $selectedParcelTerminals = $this->_baseInstance->_getHelperModule()->getOfficesFromCart($order->id_cart);

        if (count($selectedParcelTerminals) > 0){
          foreach ($selectedParcelTerminals as $addressId => $selectedParcelTerminal) {
              try {
                  $autoSendResult = $this->autoSendData($order, new Address($addressId), $selectedParcelTerminal);
                  //success
                  $this->_addOrderComment($order->id, $order->id_customer, sprintf($this->_baseInstance->ls('Parcel data sent to server, barcode: %s'), $autoSendResult['barcode']));
                  
              } catch (Exception $ex) {
                  //failure
                  $this->_addOrderComment($order->id, $order->id_customer, $this->_baseInstance->ls('Parcel data send to server failed: ').$ex->getMessage());

              }
          }
        }else{
          try {
                  $autoSendResult = $this->autoSendData($order,  new Address((int)$order->id_address_delivery), '');
                  //success
                  $this->_addOrderComment($order->id, $order->id_customer, sprintf($this->_baseInstance->ls('Parcel data sent to server, barcode: %s'), $autoSendResult['barcode']));
                  
              } catch (Exception $ex) {
                  //failure
                  $this->_addOrderComment($order->id, $order->id_customer, $this->_baseInstance->ls('Parcel data send to server failed: ').$ex->getMessage());

              }
        }
        
    }
    
    /**
     * <p>Returns Packing slip PDF file, which can be echoed to browser for current order if one exists.</p>
     * @param OrderCore $order
     * @return string
     */
    public function getBarcodePdf($order) {
        $orderData = $this->getDataFromOrder($order);
        if (isset($orderData['pl_number']) && $orderData['pl_number']) {
            return file_get_contents(urldecode($orderData['pl_number']));
        }
    }
    
    
    /**
     * <p>Returns packing slip URL if data is sent or false otherwise.</p>
     * @param OrderCore $order
     * @return boolean|string
     */
    public function getBarcode($order, $return=false) {
         $orderData = $this->getDataFromOrder($order);
        if (isset($orderData['pl_number']) && $orderData['pl_number']) {
            
            $PDF = $this->_getDpdHelper()->getApi(Context::getContext()->shop->id, $this->_configPrefix)
                ->getPDF($orderData['pl_number']);
            if($return){
                return $PDF;
            }
            return 'true';
        }
        return false;
    }



    public function getLabels($orderIds) {
        $barcodes = array();
        foreach ($orderIds as $orderId) {
            $order = new Order((int)$orderId);
            if (!Validate::isLoadedObject($order))
                $this->errors[] = sprintf(Tools::displayError('Order #%d cannot be loaded'), $id_order);
            else
            {
                $orderData = $this->getDataFromOrder($order);
                if (isset($orderData['pl_number']) && $orderData['pl_number']) {
                    $barcodes[] = implode( "|",$orderData['pl_number']);
                } 
            }
        }
            
            $PDF = $this->_getDpdHelper()->getApi(Context::getContext()->shop->id, $this->_configPrefix)
                ->getPDF($barcodes, true);
            return $PDF;
    }

    public function getManualManifest($orderIds) {
        
        require_once _PS_MODULE_DIR_ . 'balticode_dpd_parcelstore/html2fpdf/html2pdf.class.php';
        $today = date('Y-m-d');
        $name = 'dpdManifest' . '-'.$today. '.pdf';
		$logo = '<img style="float:right;" src="'._PS_MODULE_DIR_ . 'balticode_dpd_parcelstore/img/logo.jpg" height="49" width="98">';
        $ISSN = '<img src="'._PS_MODULE_DIR_ . 'balticode_dpd_parcelstore/img/issn.jpg" height="17" width="17">';
        $footer = '<img src="'._PS_MODULE_DIR_ . 'balticode_dpd_parcelstore/img/footer.jpg" width="100%">';
        $userId = $this->_baseInstance->getConfigData('SENDPACKAGE_USERID');    
        $mfile =  _PS_MODULE_DIR_ . 'balticode_dpd_parcelstore/manifest.nr';
            $handle = fopen($mfile, 'r');
            $mNumber = fread($handle,filesize($mfile));
            $mNumber = ($mNumber ? $mNumber = sprintf("%08d", ++$mNumber) : sprintf("%08d", 00000001));
            fclose($handle);

            $handle = fopen($mfile, 'w') or die('Cannot open file:  '.$mfile);
            fwrite($handle, $mNumber);
            fclose($handle);
        $table = "";
$table .= <<<EOT
            <table style="width:2000mm; " border="0" cellspacing="5">
              <tr>
                <td colspan="2">DPD LIETUVA</td>
                <td>Telefonas:</td>
                <td style="margin-right:100px">+37052106777</td>
                <td colspan="3" rowspan="3">{$logo}</td>
              </tr>
              <tr>
                <td colspan="2">PVM LT116392917</td>
                <td>Faksas:</td>
                <td>+37052106740</td>
              </tr>
              <tr>
                <td colspan="2">LIEPKALNIO G. 180</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td><h3>Manifesto nr.</h3></td>
                <td>{$mNumber}</td>
                <td style="width:30mm">Klientas:</td>
                <td style="width:40mm">DPD LIETUVA UAB</td>
                <td style="width:30mm">PVM kodas</td>
                <td>Tel.</td>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>Uždarymo data</td>
                <td>{$today}</td>
                <td>{$userId}</td>
                <td>LIEPKALNIO G. 180</td>
                <td>LT116392917</td>
                <td>2106777</td>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>VILNIUS</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>LT-02121</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
              <tr>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
            </table>
            <table style="width:2000mm; " border="0" cellspacing="5">
              <tr>
                <td>Eil. Nr.</td>
                <td>Siuntos tipas</td>
                <td style="width:40mm">Gavėjas</td>
                <td style="width:30mm">Tel.</td>
                <td>Svoris</td>
                <td style="width:40mm">Siuntos NR.</td>
                <td>ISSN</td>
              </tr>
EOT;
            $counter=1;
            $packages=0;
            $weight=0;


            foreach ($orderIds as $orderId) {
                $barcodes = array();
                $order = new Order((int)$orderId);
                if (!Validate::isLoadedObject($order))
                    $this->errors[] = sprintf(Tools::displayError('Order #%d cannot be loaded'), $id_order);
                else
                {
                    $orderData = $this->getDataFromOrder($order);
                    if (isset($orderData['pl_number']) && $orderData['pl_number']) {
                        $barcodes[] = implode( "|",$orderData['pl_number']);
                    } 
                $shipping = $order->getShipping();
                if ($shipping[0]['state_name'] == 'balticode_dpd_parcelstore') {
                    $parcel_type = 'Parcel Shop'; 
                }else{
                    if ($order->payment == 'Cash on delivery (COD)')
                        $parcel_type = 'normal parcel,<br>COD, B2C<br><strong>'.number_format($order->total_paid, 2).'</strong>';  
                    else
                        $parcel_type = 'normal parcel,<br>B2C';
                    
                }
                    $weight = 0;
                    $orderItems = $order->getProducts();
                    foreach ($orderItems as $orderItem) {
                        for ($i = 0; $i < ($orderItem['product_quantity'] - $orderItem['product_quantity_refunded']); $i++) {
                            $weight += $orderItem['product_weight'];
                        }
                        
                    }
                    $table .="<tr>";
                    $table .="<td>".$counter++."</td>";
                    $table .="<td>".$parcel_type."</td>";
                    $delivery_address = new Address((int)$order->id_address_delivery);
                    $table .="<td><p>".$delivery_address->firstname." ".$delivery_address->lastname."<br>";
                    $table .=$delivery_address->address1."<br>";
                    if ($delivery_address->address2){
                        $table .=$delivery_address->address2."<br>";
                    }
                    $table .=$delivery_address->postcode."<br>";
                    $table .="<strong>".$delivery_address->city."</strong> </p></td>";
                    $table .="<td>".$delivery_address->phone."</td>";
                    $table .="<td>".$weight."</td>";
                    $table .="<td>".implode( "<br>",$barcodes)."</td>";
                    
                    $table .="<td>".$ISSN."</td>";
                    $table .="</tr>";

                    $i++;
                    $packages+=$this->_getNumberOfPackagesForOrder($order);

                }
            }




                $table .='<tr>
                    <td><strong>Viso</strong></td>
                    <td colspan="2">&nbsp;</td>
                    <td><strong>'.$weight.'</strong></td>
                    <td colspan="3">&nbsp;</td>
                  </tr>
                  <tr>
                    <td>Siuntų kiekis</td>
                    <td colspan="7">'.$i.'</td>
                  </tr>
                  <tr>
                    <td>Pakuočių kiekis</td>
                    <td colspan="7">'.$packages.'</td>
                  </tr>
                </table>';


            $cfooter='<page_footer style="width: 100%;">
                    <table class="page_footer" style="width: 100%;">
                    <tr style="border-top:1px solid #000000">
                        <td style="width: 50%; text-align: left">
                            '.date('Y-m-d').'
                        </td>
                        <td style="width: 50%; text-align: right">
                            [[page_cu]] / [[page_nb]]
                        </td>
                    </tr>
                </table>
                </page_footer>';

        $datasent = $this->_getDpdHelper()->getApi(Context::getContext()->shop->id, $this->_configPrefix)
                ->getPDF(array('manifest' => true));
        $pdf = new HTML2PDF('P', 'A4', 'en', true, 'UTF-8', array(10, 5, 5, 10));
        $table = '<page style="font-family: freeserif">'.$cfooter.$table.$footer.'</page>';
        $pdf->pdf->SetDisplayMode('real');
        $pdf->WriteHTML($table);
        $pdf->Output($name, 'D');
    }

    public function getLabelsOutput($pdf) {
      $today = date('Y-m-d');
        $name = 'dpdLabels' . '-'.$today. '.pdf';
        if (ob_get_contents()) {
            print_r('Some data has already been output, can\'t send PDF file');
            die();
        }
        header('Content-Description: File Transfer');
        if (headers_sent()) {
            print_r('Some data has already been output to browser, can\'t send PDF file');
            die();
        }
        header('Cache-Control: public, must-revalidate, max-age=0'); // HTTP/1.1
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header( "refresh:1;url=".$_SERVER[HTTP_REFERER]."" ); 
        header('Content-Type: application/force-download');
        header('Content-Type: application/octet-stream', false);
        header('Content-Type: application/download', false);
        header('Content-Type: application/pdf', false);
        header('Content-Disposition: attachment; filename="'.$name.'";');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '.strlen($pdf));
        echo $pdf;
        
        return '';
    }


    /**
     * <p>Returns manifest.</p>
     */

    
    
    /**
     * <p>Returns number or parcels for the order according to Maximum Package Weight defined in DPD settings</p>
     * @param OrderCore $order
     * @return int
     */
    protected function _getNumberOfPackagesForOrder($order) {
        $productWeights = array();
        $orderItems = $order->getProducts();
        foreach ($orderItems as $orderItem) {
            /* @var $orderItem Mage_Sales_Model_Order_Item */
            for ($i = 0; $i < ($orderItem['product_quantity'] - $orderItem['product_quantity_refunded']); $i++) {
                $productWeights[] = $orderItem['product_weight'];
            }
            
        }
        return $this->_getDpdHelper()->getNumberOfPackagesFromItemWeights($productWeights, $this->_baseInstance->getConfigData('MAX_PACKAGE_WEIGHT'));
    }
    
    
    /**
     * <p>Adds comment for the order</p>
     * @param int $id_order
     * @param int $id_customer
     * @param string $message
     * @return null
     */
    protected function _addOrderComment($id_order, $id_customer, $message) {
        $db = Db::getInstance();
        return $db->insert('message', array(
            'id_order' => $id_order,
            'message' => $message,
            'private' => 1,
            'id_customer' => $id_customer,
            'date_add' => date('Y-m-d H:i:s'),
        ));
        
    }
    
    
    /**
     * <p>Returns true if parcel data is sent to DPD server for specified order.</p>
     * @param OrderCore $order
     * @return boolean
     */
    public function isDataSent($order) {
        $orderData = $this->getDataFromOrder($order);
        if (isset($orderData['DPD_OrderID'])) {
            return true;
        }
        return false;
    }
    
    
    
    
    /**
     * 
     * @return balticode_postoffice_dialcode_helper
     */
    protected function _getDialCodeHelper() {
        return $this->_baseInstance->_getHelperModule()->helper('dialcode_helper', 'balticode_postoffice');
    }
    
    
    
    /**
     * <p>Sends parcel data to DPD server for specified order and selected parcel terminal id.</p>
     * @param OrderCore $order
     * @param AddressCore $address
     * @param int $selectedOfficeId
     * @return array comma separated parcel numbers in array key of 'barcode'
     */
    public function autoSendData($order, $address, $selectedOfficeId) {

        $requestResult = $this->_getDpdHelper()->getApi(Context::getContext()->shop->id, $this->_configPrefix)
                ->autoSendData($this->_baseInstance->getRequestForAutoSendData($order, $address, $selectedOfficeId));
        
        $this->setDataToOrder($order, $requestResult);
        
        $db = Db::getInstance();
        $db->update('orders', array('shipping_number' => $db->escape(implode('', $requestResult['Parcel_numbers']))), 'id_order = '.$db->escape($order->id));
        $db->update('order_carrier', array('tracking_number' => $db->escape(implode('', $requestResult['Parcel_numbers']))), 'id_order = '.$db->escape($order->id));
        
//        $this->_addOrderComment($order->id, $order->id_customer, sprintf($this->_baseInstance->ls('Response log is: %s'), print_r($this->_getDpdHelper()->getApi(Context::getContext()->shop->id, $this->_configPrefix)->getLoggedRequests(), true)));
        
        
        //on failure return false
        //is success
        return array('barcode' => '##'.  implode(',', $requestResult['Parcel_numbers']).'##');
    }
    
    
    /**
     * 
     * @return balticode_dpd_parcelstore_dpd_helper
     */
    protected function _getDpdHelper() {
        return $this->_baseInstance->_getHelperModule()->helper('dpd_helper', balticode_dpd_parcelstore::NAME);
    }
    
    
    
    /**
     * <p>Creates or returns cached instance of Admin Form Configuration fields for this instance.</p>
     * @return array
     */
    public function initFormFields() {
        if (count($this->form_fields)) {
            return $this->form_fields;
        }
        $yesno = array(
            'yes' => $this->l('Yes'),
            'no' => $this->l('No'),
        );
        $labelPositions = array(
            '1234' => sprintf($this->l('Position %s'), '1234'),
            '4123' => sprintf($this->l('Position %s'), '4123'),
            '3412' => sprintf($this->l('Position %s'), '3412'),
            '3421' => sprintf($this->l('Position %s'), '3421'),
        );

        $this->form_fields = array(
            'senddata_enable' => array(
                'title' => $this->l('Auto send data to DPD server'),
                'type' => 'select',
                'description' => $this->l('Only if the order has been paid for or the order is COD'),
                'default' => 'no',
                'css' => 'width: 300px;',
                'options' => $yesno,
            ),
            'courier_enable' => array(
                'title' => $this->l('Allow courier pickup'),
                'type' => 'select',
                'description' => $this->l('Only if the order has been paid for or the order is COD'),
                'default' => 'yes',
                'css' => 'width: 300px;',
                'options' => $yesno,
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'sendpackage_username' => array(
                'title' => $this->l('DPD Self service username'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'sendpackage_password' => array(
                'title' => $this->l('DPD Self-service password'),
                'type' => 'password',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'sendpackage_userid' => array(
                'title' => $this->l('DPD Self service user ID'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'http_request_timeout' => array(
                'title' => $this->l('Http request timeout'),
                'type' => 'text',
                'description' => $this->_baseInstance->ls('If timeout is greater than 10 seconds, then parcel data can only be sent manually. This field with the limitation will be removed latest 17th of February'),
                'default' => '60',
                'css' => 'width: 300px;',
                'validate' => array('required_entry', 'validate_digit'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'return_name' => array(
                'title' => $this->l('Pickup address name'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'return_company' => array(
                'title' => $this->l('Pickup address company'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'return_email' => array(
                'title' => $this->l('Pickup address e-mail'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry', 'validate_email'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'return_phone' => array(
                'title' => $this->l('Pickup address phone'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'return_street' => array(
                'title' => $this->l('Pickup address street'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'return_citycounty' => array(
                'title' => $this->l('Pickup address city, county'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'return_postcode' => array(
                'title' => $this->l('Pickup address zip code'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'return_country' => array(
                'title' => $this->l('Pickup address country'),
                'type' => 'select',
                'description' => '',
                'default' => 'EE',
                'css' => 'width: 300px;',
                'options' => $this->_baseInstance->_getHelperModule()->getCountriesAsOptions(),
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'po_show_on_label' => array(
                'title' => $this->l('Show pickup address on packing label'),
                'type' => 'select',
                'description' => '',
                'default' => 'no',
                'css' => 'width: 300px;',
                'options' => $yesno,
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'label_position' => array(
                'title' => $this->l('Labels position on packing slip'),
                'type' => 'select',
                'description' => '',
                'default' => 'no',
                'css' => 'width: 300px;',
                'options' => $labelPositions,
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            'api_url' => array(
                'title' => $this->l('Api URL'),
                'type' => 'text',
                'description' => '<b>Live:</b> https://weblabel.dpd.lt/parcel_interface/',
                'default' => 'https://weblabel.dpd.lt/parcel_interface/',
                'css' => 'width: 300px;',
                'validate' => array('required_entry'),
                'validate-if' => array('senddata_enable' => 'yes'),
            ),
            
        );
        return $this->form_fields;
        
    }
    
    /**
     * <p>Attempts to decode extra data stored within order commetns and return it as array.</p>
     * @param OrderCore $order
     * @return array
     */
    public function getDataFromOrder($order) {
        return $this->_baseInstance->_getHelperModule()->getDataFromOrder($order, self::ORDER_COMMENT_START_PREFIX);
    }
    
    /**
     * <p>Sets extra data to order and creates specialized order comment for it when neccessary.</p>
     * @param OrderCore $order
     * @param array $data
     * @return array
     */
    public function setDataToOrder($order, $data = array()) {
        return $this->_baseInstance->_getHelperModule()->setDataToOrder($order, $data, self::ORDER_COMMENT_START_PREFIX);
    }
    
    
    /**
     * <p>Wrapper for <code>$this->l()</code> (was protected in 1.4)</p>
     * <p>Kept for compatiblity reasons</p>
     * @param string $string
     * @return string
     */
    public function l($string) {
        return $this->_baseInstance->ls($string);
    }
    
    
    
    

}

