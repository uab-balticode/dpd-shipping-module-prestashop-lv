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

if (!class_exists('balticode_dpd_parcelstore', false)) {
    Module::getInstanceByName('balticode_dpd_parcelstore');
}

/**
 * <p>Represents DPD courier shipping method.</p>
 * <p>Extra order data is stored under specialized order comment</p>
 * <p>Can perform following business actions:</p>
 * <ul>
     <li>Calculate shipping price based on country and weight</li>
     <li>Send information about shipment data to DPD server.</li>
     <li>Call courier to pick up the shipment that was ordered using this carrier.</li>
 </ul>
 * @author Sarunas Narkevicius
 */
class balticode_dpd_courier extends balticode_dpd_parcelstore {
    const CONST_PREFIX = 'E_DPDLTC_';
    
    const NAME = 'balticode_dpd_courier';
    
    
    /**
     * <p>Holds generated form fields data</p>
     * @var array
     * @see balticode_dpd_parcelstore_html_helper
     */
    private $form_fields = array();
    

    /**
     * <p>Since it is inherited from DPD parcelstore, then parent shippig method is stored here.</p>
     * @var string
     */
    protected $_parent_code;
    

    /**
     *
     * @var balticode_dpd_parcelstore_data_send_executor
     */
    public $dataSendExecutor;
    
    /**
     * <p>Evaluates to true, if current shipment method has already been rendered</p>
     * @var bool
     */
    protected $_carrierDisplayed = false;
    

    /**
     * <p>Initiates module. Is used because PrestaShop requires special Module initiation which is done in parent class</p>
     */
    protected function _construct() {
        $this->_const_prefix = balticode_dpd_courier::CONST_PREFIX;
        $this->name = 'balticode_dpd_courier';
        $this->tab = 'shipping_logistics';
        $this->_parent_code = balticode_dpd_parcelstore::NAME;
        $this->version = '0.2';
        $this->module_key = "099e4b94b94d62de16433027dc34d010";
        $this->author = "Balticode";
        $this->dependencies[] = 'balticode_dpd_parcelstore';
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.7');
        $this->displayName = $this->l('DPD kurjeris');
        $this->description = $this->l('DPD offers high-quality shipping service from Lithuania to whole Europe.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
        if (!$this->getConfigData('TITLE') OR !$this->getConfigData('HANDLING_FEE'))
            $this->warning = $this->l('Details must be configured in order to use this module correctly');
        if (file_exists(_PS_MODULE_DIR_ . balticode_dpd_parcelstore::NAME. '/datasend-executor.php')) {
            if (!class_exists(balticode_dpd_parcelstore::NAME . '_data_send_executor', false)) {
                require_once(_PS_MODULE_DIR_ . balticode_dpd_parcelstore::NAME . '/datasend-executor.php');
            }
            $executorClass = balticode_dpd_parcelstore::NAME . '_data_send_executor';
            $this->dataSendExecutor = new $executorClass($this);
            $this->dataSendExecutor->setConfigPrefix(balticode_dpd_parcelstore::CONST_PREFIX);
        }
        $this->form_fields = array();
        
        
    }
    
    
    /**
     * <p>Not used</p>
     */
    private function init() {
    }
    
    /**
     * <p>Prepares op=order request to be sent via DPD API.</p>
     * @param OrderCore $order Order that is using this carrier
     * @param AddressCore $address address, that is from that order (one order can support multiple addresses)
     * @param array $selectedOfficeId selected entry from table <code>balticode_postoffice</code>
     * @return array request that is passed directly to DPD
     */
    public function getRequestForAutoSendData($order, $address, $selectedOfficeId) {
        $telephone = $address->phone_mobile;
        if (!$telephone) {
            $telephone = $address->phone;
        }
        
        $requestData = array(
            'name1' => $address->firstname.' '.$address->lastname,
            'company' => $address->company,
            'street' => implode(' ', array($address->address1, $address->address2)),
            'pcode' => $address->postcode,
            'country' => strtoupper(Country::getIsoById($address->id_country)),
            'city' => $address->city,
            'Sh_contact' => $address->firstname.' '.$address->lastname,
            'phone' => $telephone,
            'remark' => $this->_getRemark($order),
            'num_of_parcel' => $this->_getNumberOfPackagesForOrder($order),
            'order_number' => $order->id,
            'parcel_type' => $order->payment == 'Cash on delivery (COD)' ? 'D-COD-B2C' : 'D-B2C'
        );
        if ($order->payment == 'Cash on delivery (COD)') {
            $requestData['cod_amount'] = $order->total_paid;
        }
        if ($address->id_state) {
            $requestData['city'] = $address->city . ', ' . State::getNameById($address->id_state);
        }
        
        
        return $requestData;
        
    }
    

    
    /**
     * <p>Performs following actions:</p>
     * <ul>
         <li>Registers hook <code>extraCarrier</code></li>
         <li>Registers carrier with name <code>balticode_dpdee_courier</code></li>
         <li>Registers hook with name <code>paymentConfirm</code> - for auto sending data after payment</li>
         <li>Registers hook with name <code>actionAdminControllerSetMedia</code> - for adding css,js scripts</li>
         <li>Registers hook with name <code>displayBackOfficeFooter</code> - for adding call to courier button</li>
         <li>Registers hook with name <code>displayHeader</code> - for adding css,js scripts</li>
         <li>Registers hook with name <code>displayFooter</code> - for removing shipping methods at onepage checkouts, if <code>HOOK_EXTRACARRIER</code> is not called</li>
     </ul>
     * @return boolean
     */
    protected function _install() {
        if (!$this->registerHook('extraCarrier')
                || !$this->_getHelperModule()->addCarrierModule($this->name, get_class($this), self::TRACKING_URL) or !$this->registerHook('paymentConfirm')
                || !$this->registerHook('actionAdminControllerSetMedia')
                || !$this->registerHook('displayBackOfficeFooter')
                || !$this->registerHook('displayHeader')
                || !$this->registerHook('displayFooter')) {
            return false;
        }
        return true;
    }
    
    
    /**
     * <p>Performs following actions:</p>
     * <ul>
         <li>Unregisters hook <code>extraCarrier</code></li>
         <li>Removes carrier with name <code>balticode_dpdee_courier</code></li>
         <li>Unregisters hook with name <code>paymentConfirm</code></li>
         <li>Unregisters hook with name <code>actionAdminControllerSetMedia</code></li>
         <li>Unregisters hook with name <code>displayBackOfficeFooter</code></li>
         <li>Unregisters hook with name <code>displayHeader</code></li>
         <li>Unregisters hook with name <code>displayFooter</code></li>
        <li>If <code>dataSendExecutor</code> is available, then <code>uninstall()</code> method will be called on same object</li>
     </ul>
     * @return boolean
     */
    protected function _uninstall() {
        if (!$this->unregisterHook('extraCarrier') || !$this->unregisterHook('paymentConfirm')
                || !$this->unregisterHook('displayBackOfficeFooter')
                || !$this->unregisterHook('displayHeader')
                || !$this->unregisterHook('displayFooter')) {
            return false;
        }
        //TODO: remove in future releases, now it is left because there was hook rename from:
        //displayBackOfficeHeader => actionAdminControllerSetMedia
        $this->unregisterHook('displayBackOfficeHeader');
        $this->unregisterHook('actionAdminControllerSetMedia');
        if (!$this->_getHelperModule()->removeCarrierModule($this->name)) {
            return false;
        }

        return true;
        
    }
    
    
    
    
    /**
     * <p>Automatic data sending is executed at the moment, when order is marked as Paid.</p>
     * @param array $params
     * @return string
     */
    public function hookPaymentConfirm(&$params) {
        if ($this->dataSendExecutor != null) {
            $this->dataSendExecutor->hookpaymentConfirm($params);
        }
        return '';
    }

    /**
     * <p>For adding CSS,JS scripts in backoffice</p>
     */
    public function hookActionAdminControllerSetMedia() {
        $path = __PS_BASE_URI__.'modules/'.balticode_dpd_parcelstore::NAME.'/';
        $this->context->controller->addCSS($path . 'css/'.  balticode_dpd_parcelstore::NAME.'.css', 'all');
        $this->context->controller->addJqueryPlugin('loadTemplate', $path .'js/plugins/');
        $this->context->controller->addJS($path . 'js/'.balticode_dpd_parcelstore::NAME.'.js');
        
    }
    
    /**
     * <p>For adding CSS,JS scripts in frontend</p>
     */
    public function hookDisplayHeader() {
        $path = __PS_BASE_URI__.'modules/'.balticode_dpd_parcelstore::NAME.'/';
        $this->context->controller->addCSS($path . 'css/'.balticode_dpd_parcelstore::NAME.'-public.css', 'all');
        $this->context->controller->addJS($path . 'js/'.balticode_dpd_parcelstore::NAME.'-public.js');
    }
    
    /**
     * <p>For adding HOOK_EXTRACARRIER callout, when original callout did not occur when it had to.</p>
     * <p>PrestaShop does not call extracarrier when for example address is not entered yet</p>
     * @return string
     */
    public function hookDisplayFooter() {
        $className = 'OrderOpcController';
        $php_self = 'order-opc';
        if ($this->context->controller instanceof $className && $this->context->controller->php_self == $php_self) {
            if (!$this->_carrierDisplayed) {
                return $this->hookExtraCarrier(array('cart' => $this->context->cart));
            }
//            return '<pre>'.htmlspecialchars(print_r('junnike', true)).'</pre>';
        }
    }

    
    
    
    /**
     * <p>Saves configuration entered from the admin form and returns HTML generated during process.</p>
     * @return string any generated HTML from the postprocess, for example success message
     */
    private function _postProcess() {
        $html = '';
        $data = $_POST;
        if (isset($data['btnSubmit'])) {
            foreach ($this->_initFormFields() as $formFieldName => $formFieldDataSet) {
                //multiselect must be normalized to CSV string
                if ($formFieldDataSet['type'] == 'multiselect') {
                    if (!isset($data[strtoupper($formFieldName)])) {
                        $data[strtoupper($formFieldName)] = '';
                    }
                    if (is_array($data[strtoupper($formFieldName)])) {
                        $data[strtoupper($formFieldName)] = implode(',', $data[strtoupper($formFieldName)]);
                    }
                }
                
                //country_select_html is always submitted as array
                if (is_array($data[strtoupper($formFieldName)])) {
                    $data[strtoupper($formFieldName)] = serialize($data[strtoupper($formFieldName)]);
                }
                Configuration::updateValue(self::CONST_PREFIX . strtoupper($formFieldName), $data[strtoupper($formFieldName)]);
            }
            $this->_getHelperModule()->setTaxGroup($this->name, $this->getConfigData('TAX'));
            
            
            $title = $this->getConfigData('TITLE');
            $finalTitle = $title;
            if ($this->_isSerialized($title)) {
                $title = @unserialize($title);
                if (is_array($title)) {
                    $finalTitle = isset($title[$this->context->language->id])?$title[$this->context->language->id]:$title[0];
                }
            }
            $this->_getHelperModule()->setDisplayName($this->name, $finalTitle);
            
            $this->_getHelperModule()->refresh($this->name, true);
            
            
            $html .= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="' . $this->l('ok') . '" /> ' . $this->l('Settings updated') . '</div>';
        }
        return $html;
    }

    /**
     * <p>Displayes admin configuration form header</p>
     * @return string html
     */
    private function _displayFormHeader() {
        $html = '<img src="../modules/'.$this->name.'/'.$this->name.'.png" style="float:left; margin-right:15px;"><b>' . $this->l('DPD offers high-quality shipping service from Lithuania to whole Europe.') . '</b><br /><br />
		' . $this->l('DPD courier service can be used in Estonia, Baltics and Europe.') . '<br />';
        return $html;
    }
    




    /**
     * <p>This hook is called right after PrestaShop own carriers are rendered.</p>
     * <p>Returns HTML, which:</p>
     * <ul>
         <li>Replaces PrestaShop carrier element with ajax refreshing select menu element</li>
         <li>Hides PrestaShop carrier, when this carrier should not be available</li>
     </ul>
     * <p>Current PrestaShops <code>extraCarrier</code> hook uses following parameters:</p>
     * <ul>
         <li><code>cart</code> - Cart instance for current customer or order</li>
         <li><code>address</code> - Address instance for current cart</li>
     </ul>
     * <p>Checks following and hides if:</p>
     * <ul>
         <li>Shipping is not allowed for the specified country</li>
         <li>Products in the cart contain specific HTML comment string</li>
         <li>Any of the products is overweight</li>
     </ul>
     * @param array $params
     * @return string
     */
    public function hookExtraCarrier($params) {
        //if this shipping method is available or not
        $shouldHide = false;
        $this->_carrierDisplayed = true;

        /* @var $cart CartCore */
        $cart = $params['cart'];
        $summaryDetails = $cart->getSummaryDetails();

        //check if this shipping method is in allowed country list
        if ($this->getConfigData('SALLOWSPECIFIC') == '1') {
            $allowedCountries = explode(',', $this->getConfigData('SPECIFICCOUNTRY'));
            if (!in_array(Country::getIsoById($summaryDetails['delivery']->id_country), $allowedCountries)) {
                $shouldHide = true;
            }
        }

        //check if address exists and if not, then create dummy address
        if (!$summaryDetails['delivery']->country) {
            $shouldHide = true;
            if (!isset($params['address']) || !$params['address']) {
                $params['address'] = (object) array(
                            'id' => '0',
                );
            }
        }
        
        //check if cart contains any of the products which contain forbidden html comment
        if ($this->getConfigData('CHECKITEMS') == 'yes' && !$shouldHide) {
            $prods = $cart->getProducts();
            foreach ($prods as $prod) {
                if (stripos($prod['description_short'], '<!-- no dpd_ee_module -->') !== false) {
                    $shouldHide = true;
                    break;
                }
            }
        }
        
        //weight check
        $loadedProductWeights = array();
        if (($this->getConfigData('MAX_PACKAGE_WEIGHT') > 0 || $this->getConfigData('MIN_PACKAGE_WEIGHT') > 0) && !$shouldHide) {
            $products = $cart->getProducts();
            foreach ($products as $product) {
                if ($product['is_virtual']) {
                    continue;
                }
                for ($i = 0; $i < $product['cart_quantity']; $i++) {
                    $loadedProductWeights[] = $product['weight'];
                }
                if ($this->getConfigData('MAX_PACKAGE_WEIGHT') > 0 && !$shouldHide) {
                    if (max($loadedProductWeights) > (float)$this->getConfigData('MAX_PACKAGE_WEIGHT')) {
                        $shouldHide = true;
                    }
                }
                if ($this->getConfigData('MIN_PACKAGE_WEIGHT') > 0 && !$shouldHide) {
                    if (min($loadedProductWeights) > (float)$this->getConfigData('MIN_PACKAGE_WEIGHT')) {
                        $shouldHide = true;
                    }
                }
            }
        }

        $title = $this->getConfigData('TITLE');
        $finalTitle = $title;
        if ($this->_isSerialized($title)) {
            $title = @unserialize($title);
            if (is_array($title)) {
                $finalTitle = isset($title[$this->context->language->id]) ? $title[$this->context->language->id] : $title[0];
            }
        }


        $extraParams = array(
            'id_address_delivery' => $cart->id_address_delivery,
            'price' => $this->getOrderShippingCost($cart),
            'title' => $finalTitle,
            'logo' => 'http://balticode.com/bclogo.jpg',
            'id_address_invoice' => $cart->id_address_invoice,
            'error_message' => '', //not required since phone nr is mandatory
            'is_default' => false,
        );
        
        return $this->_getHelperModule()->displayExtraCarrier($this->name, $extraParams, $shouldHide);
        
        
        
        
    }
    

    /**
     * <p>PrestaShop implementation for displaing configuration form for this module</p>
     * @return string
     */
    public function getContent() {
        $html = '<h2>'.$this->displayName.'</h2>';
        
        if (!empty($_POST)) {
            $postErrors = $this->_postValidation();
            if (!sizeof($postErrors)) {
                $html .= $this->_postProcess();
            } else {
                foreach ($postErrors as $err) {
                    $html .= '<div class="alert error">' . $err . '</div>';
                }
                
            }
        }
        else {
            $html .= '<br />';
            
        }
        $html .= $this->_displayFormHeader();
        $html .= $this->_displayForm();
        
        
        return $html;
    }
    
    /**
     * <p>Fetches configuration for this instance and if not found, then attempts to look to parent instance.</p>
     * @param string $param
     * @return mixed
     */
    public function getConfigData($param) {
        $value = Configuration::get(balticode_dpd_courier::CONST_PREFIX . $param);
        if ($value === null || $value === false) {
            $formFields = $this->_initFormFields();
            if (isset($formFields[strtolower($param)]) && $formFields[strtolower($param)]['default']) {
                return $formFields[strtolower($param)]['default'];
            }
            return Configuration::get(balticode_dpd_parcelstore::CONST_PREFIX . $param);
        }
        return $value;
    }
    
    


    /**
     * <p>Renders form HTML from the form fields configuration</p>
     * @param string $action action url
     * @param string $method form element method attribute
     * @param array $formFields form fields array
     * @return string resulting html
     */
    protected function _initFormFields() {
        if (count($this->form_fields)) {
            return $this->form_fields;
        }
        $yesno = array(
            'yes' => $this->l('Yes'),
            'no' => $this->l('No'),
        );
        $boxUnits = array(
            'order' => $this->l('Per Order'),
            'item' => $this->l('Per Package'),
        );
        $countryUnits = array(
            '0' => $this->l('All Allowed Countries'),
            '1' => $this->l('Specific Countries'),
        );

        $this->form_fields = array(
            'title' => array(
                'title' => $this->l('Title'),
                'type' => 'multilang',
                'description' => $this->l('This controls the title which the user sees during checkout.'),
                'default' => $this->l('DPD kurjeris'),
                'css' => 'width: 300px;',
            ),
            'handling_fee' => array(
                'title' => $this->l('Price'),
                'type' => 'text',
                'description' => '',
                'default' => '5.80',
                'css' => 'width: 300px;',
                'validate' => array('required_entry', 'validate_number'),
            ),
            'tax' => array(
                'title' => $this->l('Tax Id'),
                'type' => 'select',
                'description' => $this->l('Prices here are tax inclusive'),
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array(),
                'options' => $this->_getHelperModule()->getTaxes(),
            ),
            'handling_fee_country' => array(
                'title' => $this->l('Price per country'),
                'type' => 'countryprice',
                'description' => $this->l('If country is not listed here, but this method is available, then general handling fee is applied'),
                'default' => 'a:3:{s:18:"_1388700940288_288";a:4:{s:10:"country_id";s:2:"EE";s:10:"base_price";s:3:"5.8";s:8:"kg_price";s:4:"1.28";s:18:"free_shipping_from";s:0:"";}s:18:"_1388700961178_178";a:4:{s:10:"country_id";s:2:"LV";s:10:"base_price";s:5:"10.38";s:8:"kg_price";s:4:"3.15";s:18:"free_shipping_from";s:0:"";}s:18:"_1388700962221_221";a:4:{s:10:"country_id";s:2:"LT";s:10:"base_price";s:5:"11.75";s:8:"kg_price";s:4:"3.85";s:18:"free_shipping_from";s:0:"";}}',
                'css' => 'width: 300px;',
                'validate' => array('validate_handling_fee_country'),
            ),
            'checkitems' => array(
                'title' => sprintf($this->l('Disable this carrier if product\'s short description contains HTML comment %s'), '&lt;!-- no dpd_ee_module --&gt;'),
                'type' => 'select',
                'description' => '',
                'default' => 'no',
                'css' => 'width: 300px;',
                'options' => $yesno,
            ),
            'max_package_weight' => array(
                'title' => $this->l('Maximum allowed package weight for this carrier'),
                'type' => 'text',
                'description' => '',
                'default' => '31.5',
                'css' => 'width: 300px;',
            ),
            'handling_action' => array(
                'title' => $this->l('Handling action'),
                'type' => 'select',
                'description' => $this->l('Per Order: Shipping cost equals Shipping price')
                . '<br/>' . $this->l('Per Package: Shipping cost equals Number of Items in cart multiplied by shipping price')
                ,
                'default' => 'yes',
                'css' => 'width: 300px;',
                'options' => $boxUnits,
            ),
            'free_groups' => array(
                'title' => $this->l('Client groups who can get free shipping'),
                'type' => 'multiselect',
                'description' => $this->l('hold down CTRL / CMD button to select/deselect multiple'),
                'default' => '',
                'css' => 'width: 300px;',
                'options' => $this->_getHelperModule()->getClientGroups(),
            ),
            'enable_free_shipping' => array(
                'title' => $this->l('Enable free shipping'),
                'type' => 'select',
                'description' => '',
                'default' => 'no',
                'css' => 'width: 300px;',
                'options' => $yesno,
            ),
            'free_shipping_from' => array(
                'title' => $this->l('Free shipping subtotal'),
                'type' => 'text',
                'description' => '',
                'default' => '',
                'css' => 'width: 300px;',
                'validate' => array('validate_number'),
            ),
            'sallowspecific' => array(
                'title' => $this->l('Ship to applicable countries'),
                'type' => 'select',
                'description' => '',
                'default' => '1',
                'css' => 'width: 300px;',
                'options' => $countryUnits,
            ),
            'specificcountry' => array(
                'title' => $this->l('Ship to Specific countries'),
                'type' => 'multiselect',
                'description' => '',
                'default' => 'EE,LV,LT',
                'css' => 'width: 300px;',
                'options' => $this->_getHelperModule()->getCountriesAsOptions(),
            ),
            
        );
        
        
        
        return $this->form_fields;
    }
    

    /**
     * <p>We need this to override addressId, so always one terminal would be available</p>
     * @param type $code
     * @param type $groupId
     * @param type $officeId
     * @param null $addressId
     */
    public function __getPostOffices($code, &$groupId = null, &$officeId = null, &$addressId = null) {
        $addressId = null;
    }
    
    /**
     * <p>This function is called when store administrator is viewing the order.</p>
     * <p>Nothing</p>
     * @param int $cart_id id cart for the order
     * @return string html string
     */
    public function displayInfoByCart($cart_id) {
        if ($this->dataSendExecutor != null) {
            $extraInfo = $this->dataSendExecutor->displayInfoByCart($cart_id);
            if ($extraInfo) {
                return '<div class="balticode_dpd_parcelstore_chosen">'. $extraInfo.'</div>';
            }
        }
        return false;
    }
    
    
    /**
     * 
     * @return balticode_dpd_parcelstore_html_helper
     */
    protected function _getHtmlHelper() {
        /* @var $helper balticode_dpd_parcelstore_html_helper */
        $helper = $this->_getHelperModule()->helper('html_helper', balticode_dpd_parcelstore::NAME);
        $helper->setContext($this->context)->setModuleInstance(Module::getInstanceByName(balticode_dpd_parcelstore::NAME));
        return $helper;
    }
    
    
    

    /**
     * <p>Does nothing, <code>balticode_postoffice</code> module calls this</p>
     * @param int $lastUpdated
     * @return null
     */
    public function setLastUpdated($lastUpdated) {
        return;
    }

    /**
     * <p>Returns current timestamp, <code>balticode_postoffice</code> module calls this</p>
     * @return int
     */
    public function getLastUpdated() {
        return time();
    }
    
    
    /**
     * <p>Returns empty string, <code>balticode_postoffice</code> module calls this</p>
     * @return int
     */
    public function getGroupTitle($group) {
        return '';
    }


    /**
     * <p>Returns parcel terminal name when short names are enabled.</p>
     * <p>Returns parcel terminal name with address, telephone, opening times when short names are disabled.</p>
     * @param string $terminal
     * @return string
     */
    public function getTerminalTitle($terminal) {
        $title = $this->getConfigData('TITLE');
        $finalTitle = $title;
        if ($this->_isSerialized($title)) {
            $title = @unserialize($title);
            if (is_array($title)) {
                $finalTitle = isset($title[$this->context->language->id]) ? $title[$this->context->language->id] : $title[0];
            }
        }
        
        return htmlspecialchars($finalTitle);
    }

    
    
    /**
     * <p>Returns parcel terminal name when short names are enabled.</p>
     * <p>Returns parcel terminal name with address, telephone, opening times when short names are disabled.</p>
     * @param array $terminal
     * @return string
     */
    public function getAdminTerminalTitle($terminal) {
        $title = $this->getConfigData('TITLE');
        $finalTitle = $title;
        if ($this->_isSerialized($title)) {
            $title = @unserialize($title);
            if (is_array($title)) {
                $finalTitle = isset($title[$this->context->language->id]) ? $title[$this->context->language->id] : $title[0];
            }
        }
        
        return htmlspecialchars($finalTitle);
    }
    
    
    
    
    /**
     * <p>This carrier has no parcel terminal selection feature, so one entry must still be added with shipping method title defined for this carrier.</p>
     * @return array single office element
     */
    public function getOfficeList() {
        //we have only one item to insert here
        $result = array();
        $result[] = array(
            'place_id' => 1,
            'name' => $this->getConfigData('TITLE'),
            'city' => '',
            'county' => '',
            'description' => '',
            'country' => '',
            'zip' => '',
            'group_sort' => 0,
        );
        return $result;
        
    }
    
    
    

}
