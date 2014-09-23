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

/**
 * <p>Represents DPD parcel terminal shipping method.</p>
 * <p>Extra order data is stored under specialized order comment</p>
 * <p>Can perform following business actions:</p>
 * <ul>
  <li>Calculate shipping price based on country and weight</li>
  <li>Display list of user selectable parcel terminals, which is auto updated.</li>
  <li>Send information about shipment data to DPD server.</li>
  <li>Call courier to pick up the shipment that was ordered using this carrier.</li>
  </ul>
 * @author Sarunas Narkevicius
 */
class balticode_dpd_parcelstore extends Module {

    const CONST_PREFIX = 'E_DPDLTP_';

    /**
     * <p>Copy of <code>CONST_PREFIX</code> for the instance</p>
     * @var string
     */
    protected $_const_prefix;

    /**
     * <p>@ will be replaced with actual tracking number.</p>
     */
    const TRACKING_URL = 'https://tracking.dpd.de/cgi-bin/delistrack?typ=1&lang=en&pknr=@';

    /**
     * If order comment starts with prefix marked here and is not visible on the frontend, then it is considered as extra data order comment.
     */
    const ORDER_COMMENT_START_PREFIX = '-----BALTICODE_DPDLT-----';

    /**
     * Shipping method code
     */
    const NAME = 'balticode_dpd_parcelstore';

    /**
     * <p>Holds generated form fields data</p>
     * @var array
     * @see balticode_dpd_parcelstore_html_helper
     */
    private $form_fields = array();

    /**
     * <p>For making sure that subclass would not call the footer itself again.</p>
     * @var bool
     */
    protected static $_footerCalled = false;

    /**
     * <p>To prevent displaying carrier extra code more than once.</p>
     * @var bool
     */
    protected $_carrierDisplayed = false;

    /**
     * <p>Makes sure only one instance is queried</p>
     * @var Balticode_Postoffice
     */
    protected static $_helperModuleInstance;

    /**
     * <p>Handles functions related to automated data sending and packing slip sending</p>
     * @var balticode_dpd_parcelstore_data_send_executor
     */
    public $dataSendExecutor;

    /**
     * <p>Default constructor</p>
     */
    final public function __construct() {

        $this->_construct();


        parent::__construct();
        $this->init();
    }

    /**
     * Any setup parameters can be set up here.
     */
    protected function _construct() {
        $this->_const_prefix = self::CONST_PREFIX;
        $this->name = self::NAME;
        $this->tab = 'shipping_logistics';
        $this->version = '0.4.1';
        $this->dependencies[] = 'balticode_postoffice';
        $this->module_key = "099e4b94b94d62de16433027dc34d010";
        $this->author = "Balticode";
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.7');
        $this->displayName = $this->l('DPD Parcelshop');
        $this->description = $this->l('DPD offers high-quality shipping service from Lithuania to whole Europe.');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
        if (!$this->getConfigData('TITLE') OR !$this->getConfigData('HANDLING_FEE'))
            $this->warning = $this->l('Details must be configured in order to use this module correctly');
        if (file_exists(_PS_MODULE_DIR_ . $this->name . '/datasend-executor.php')) {
            require_once(_PS_MODULE_DIR_ . $this->name . '/datasend-executor.php');
            $executorClass = $this->name . '_data_send_executor';
            $this->dataSendExecutor = new $executorClass($this);
        }
    }

    /**
     * <p>Not used</p>
     */
    private function init() {
        
    }

    /**
     * <p>Performs install function for this module</p>
     * @return boolean
     */
    final public function install() {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }
        if (!parent::install() or !$this->_install()) {
            return false;
        }
        return true;
    }

    /**
     * <p>Performs uninstall function for this module</p>
     * @return boolean
     */
    final public function uninstall() {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }
        if (!$this->_uninstall() || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    /**
     * <p>Performs following actions:</p>
     * <ul>
      <li>Registers hook <code>extraCarrier</code></li>
      <li>Registers carrier with name <code>balticode_dpdee_parcelstore</code></li>
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
                || !$this->_getHelperModule()->addCarrierModule($this->name, get_class($this), self::TRACKING_URL) 
                || !$this->registerHook('paymentConfirm') 
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
      <li>Removes carrier with name <code>balticode_dpdee_parcelstore</code></li>
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
        if (!$this->unregisterHook('extraCarrier') 
                || !$this->unregisterHook('paymentConfirm') 
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

        if ($this->dataSendExecutor != null) {
            $this->dataSendExecutor->uninstall();
        }
        return true;
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
        
        //address is selected office address

        $requestData = array(
            'name1' => $address->firstname.' '.$address->lastname,
            'street' => $selectedOfficeId['name'],
            'pcode' => $selectedOfficeId['zip_code'],
            'country' => strtoupper($selectedOfficeId['country']),
            'city' => $selectedOfficeId['city'],
            'weight' => $this->_getWeightForOrder($order),
            'phone' => $this->_getPhoneFromDescription($selectedOfficeId),
            'remark' => $this->_getRemark($order),
            'parcelshop_id' => $selectedOfficeId['remote_place_id'],
            'num_of_parcel' => $this->_getNumberOfPackagesForOrder($order),
            'order_number' => $order->id,
            'idm' => 'Y',
            'phone' => $telephone,
            'idm_sms_number' => $telephone,
            'idm_sms_rule' => '902',
            'parcel_type' => 'PS'
        );
        if ($order->payment == 'Cash on delivery (COD)') {
            $requestData['cod_amount'] = $order->total_paid;
        }
        if ($address->id_state) {
              $requestData['city'] = $address->city . ', ' . State::getNameById($address->id_state);
        }


        $phoneNumbers = $this->_getDialCodeHelper()->separatePhoneNumberFromCountryCode($telephone, Country::getIsoById($address->id_country));
        $requestData['Sh_notify_phone_code'] = $phoneNumbers['dial_code'];
        $requestData['Sh_notify_contact_phone'] = $phoneNumbers['phone_number'];
        return $requestData;
    }
    
    private function _getStreetFromDescription($selectedOffice) {
        $zip = $selectedOffice['zip_code'];
        $encoding = 'UTF-8';
        return trim(mb_substr($selectedOffice['description'], 0, mb_strpos($selectedOffice['description'], $zip, 0, $encoding), $encoding));
    }
    private function _getPhoneFromDescription($selectedOffice) {
        $zip = $selectedOffice['zip_code'];
        $country = $selectedOffice['country'];
        $matches = array();
        $isMatched = preg_match('/(?s:[\+][0-9]+)/', $selectedOffice['description'], $matches);
        if ($isMatched) {
            return $matches[0];
        }
        return '';
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
        $this->context->controller->addCSS($this->_path . 'css/' . self::NAME . '.css', 'all');
        $this->context->controller->addJqueryPlugin('loadTemplate', $this->_path . 'js/plugins/');
        $this->context->controller->addJS($this->_path . 'js/' . self::NAME . '.js');
    }

    /**
     * Module that adds the button to call the courier.
     */
    public function hookDisplayBackOfficeFooter() {
        $className = 'AdminOrdersController';

        //detect order id, and if detected, then add the button.....


        if (!self::$_footerCalled && $this->context->controller instanceof $className && $this->getConfigData('SENDDATA_ENABLE') == 'yes') {
            self::$_footerCalled = true;

            $targetName = balticode_dpd_parcelstore::NAME;
            if (!ModuleCore::isEnabled(balticode_dpd_parcelstore::NAME)) {
                $targetName = 'balticode_dpd_courier';
            }
        $buttonCssClass = 'process-icon-new';
        if (substr(_PS_VERSION_, 0, 3) == "1.6") {
            $buttonCssClass = 'process-icon-dpd';
        }
            
            if (!(Tools::getValue('vieworder', false) === '')) {
                //we are in list
                if ($this->getConfigData('COURIER_ENABLE') == 'yes') {
                    $jsonParams = array(
                        'url' => $this->context->link->getModuleLink($targetName, 'courier'),
                        'button' => '<li id="balticode-dpdee-courier"><a href="#" ><span class="'.$buttonCssClass.'"></span><div></div></a></li>',
                        'a_class' => 'toolbar_btn balticode_dpdee_call_courier_button',
                        'title' => $this->l('Order in DPD Courier'),
                        'redirect_click' => false,
                    );
                }
            } else {
                //we have a order
                $order = new Order(Tools::getValue('id_order'));
                if ($pdf = $this->dataSendExecutor->getBarcode($order)) {
                    $jsonParams = array(
                        'url' => '',
                        'button' => '<li id="balticode-dpdee-parcel-pdf"><a href="#" ><span class="'.$buttonCssClass.'"></span><div></div></a></li>',
                        'a_class' => 'toolbar_btn balticode_dpdee_print_slip_button',
                        'title' => $this->l('Print packing slip'),
                        'redirect_click' => $this->dataSendExecutor->getBarcode($order, false),
                        'pdf' => utf8_encode($this->dataSendExecutor->getBarcode($order, true)),
                    );
                } else {
                    return '';
                }
            }

            return $this->_getFooterJs($jsonParams, $jsonParams2);
        }
    }

    protected function _getFooterJs($jsonParams, $jsonParams2 = "") {
        $js = '';
        $jQuerySelector = 'div.toolbarHead ul.cc_button';
        if (substr(_PS_VERSION_, 0, 3) == "1.6") {
            $jQuerySelector = 'ul#toolbar-nav';
        }
        
        $js .= <<<JS
<script type="text/javascript">
//                    <![CDATA[
                    jQuery(document).ready(function() {
                        jQuery({$this->_toJson($jQuerySelector)}).balticode_dpdee_courierbutton(
                            {$this->_toJson($jsonParams)});
                        });
//                    ]]>
</script>
JS;
        return $js;
    }

    protected function _toJson($input) {
        return json_encode($input);
    }

    /**
     * <p>For adding CSS,JS scripts in frontend</p>
     */
    public function hookDisplayHeader() {
        $this->context->controller->addCSS($this->_path . 'css/' . self::NAME . '-public.css', 'all');
        $this->context->controller->addJS($this->_path . 'js/' . self::NAME . '-public.js');
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
        }
    }

    /**
     * <p>Validates Admin Configuration Form and returns array of error message on validation failure.</p>
     * @return array error messages as array, if any
     */
    protected function _postValidation() {
        $errors = array();
        if (strtoupper($_SERVER['REQUEST_METHOD']) != 'POST') {
            //on no post mode do not validate
            return $errors;
        }
        foreach ($this->_initFormFields() as $formFieldName => $formFieldData) {
            $validationRules = isset($formFieldData['validate']) ? $formFieldData['validate'] : array();
            $validationIfRules = isset($formFieldData['validate-if']) ? $formFieldData['validate-if'] : array();
            //select,multiselect validation
            $selectValidationResult = $this->_getValidator()->validate('validate_select', strtoupper($formFieldName), $_POST, $validationIfRules, $formFieldData);
            if (is_string($selectValidationResult)) {
                //we have validation error
                $errors[] = sprintf($selectValidationResult, $formFieldData['title']);
                break;
            }

            foreach ($validationRules as $validationRule) {
                $validationResult = $this->_getValidator()->validate($validationRule, strtoupper($formFieldName), $_POST, $validationIfRules, $formFieldData);
                if (is_string($validationResult)) {
                    //we have validation error
                    $errors[] = sprintf($this->l($validationResult), $formFieldData['title']);
                    break;
                }
            }
        }


        return $errors;
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
                    $finalTitle = isset($title[$this->context->language->id]) ? $title[$this->context->language->id] : $title[0];
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
        $html = '<img src="../modules/' . $this->name . '/' . $this->name . '.png" style="float:left; margin-right:15px;"><b>' . $this->l('DPD offers high-quality shipping service from Lithuania to whole Europe.') . '</b><br /><br />
		' . $this->l('DPD ParcelShop service can be used in Estonia, Latvia, and Lithuania by selecting preferred DPD ParcelShop from select menu.') . '<br />';
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
                    if (max($loadedProductWeights) > (float) $this->getConfigData('MAX_PACKAGE_WEIGHT')) {
                        $shouldHide = true;
                    }
                }
                if ($this->getConfigData('MIN_PACKAGE_WEIGHT') > 0 && !$shouldHide) {
                    if (min($loadedProductWeights) > (float) $this->getConfigData('MIN_PACKAGE_WEIGHT')) {
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
            'logo' => 'http://balticode.com/dpd.jpg',
            'id_address_invoice' => $cart->id_address_invoice,
            'error_message' => '', //not required since phone nr is mandatory
            'is_default' => false,
        );

        return $this->_getHelperModule()->displayExtraCarrier($this->name, $extraParams, $shouldHide);
    }
    
    protected function _isSerialized($input) {
        return preg_match('/^([adObis]):/', $input);
    }
    

    /**
     * <p>PrestaShop implementation for displaing configuration form for this module</p>
     * @return string
     */
    public function getContent() {
        $html = '<h2>' . $this->displayName . '</h2>';

        if (!empty($_POST)) {
            $postErrors = $this->_postValidation();
            if (!sizeof($postErrors)) {
                $html .= $this->_postProcess();
            } else {
                foreach ($postErrors as $err) {
                    $html .= '<div class="alert error">' . $err . '</div>';
                }
            }
        } else {
            $html .= '<br />';
        }
        $html .= $this->_displayFormHeader();
        $html .= $this->_displayForm();


        return $html;
    }

    /**
     * <p>Generates actual configuration form by rules</p>
     * @return string
     */
    protected function _displayForm() {
        $html = '';
        $html .= $this->_getFormHtml($_SERVER['REQUEST_URI'], 'post', $this->_initFormFields());
        return $html;
    }

    /**
     * <p>Fetches configuration for this instance.</p>
     * @param string $param
     * @return mixed
     */
    public function getConfigData($param) {
        $value = Configuration::get(self::CONST_PREFIX . $param);
        if ($value === null || $value === false) {
            $formFields = $this->_initFormFields();
            if (isset($formFields[strtolower($param)]) && $formFields[strtolower($param)]['default']) {
                return $formFields[strtolower($param)]['default'];
            }
        }
        return $value;
    }

    /**
     * <p>Gets configuration data only for this instance.</p>
     * @param string $param
     * @return mixed
     */
    public function getConfigDataForThis($param) {
        $value = Configuration::get($this->_const_prefix . $param);
        if ($value === null || $value === false) {
            $formFields = $this->_initFormFields();
            if (isset($formFields[strtolower($param)]) && $formFields[strtolower($param)]['default']) {
                return $formFields[strtolower($param)]['default'];
            }
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
    protected function _getFormHtml($action, $method, $formFields) {
        $action = Tools::htmlentitiesUTF8($action);
        $html = '';

        $formElementsHtml = '';
        $formElementHelper = $this->_getHtmlHelper();
        foreach ($formFields as $fieldName => $fieldData) {
            $methodName = 'get' . ucfirst($fieldData['type']) . 'Html';
            $fieldPropertyName = '_' . $fieldName;
            $value = Tools::getValue(strtoupper($fieldName), $this->getConfigData(strtoupper($fieldName)));
            if (method_exists($formElementHelper, $methodName)) {
                $formElementsHtml .= $formElementHelper->$methodName(strtoupper($fieldName), $fieldData, $value);
            } else {
                $formElementsHtml .= $formElementHelper->getTextHtml(strtoupper($fieldName), $fieldData, $value);
            }
        }

        $formClass = balticode_dpd_parcelstore::NAME;

        $html .= <<<HTML
   <form action="{$action}" method="{$method}">
       <fieldset>
           <legend><img src="../img/admin/contact.gif" alt="" />{$this->l('Configuration details')}</legend>
               <table id="form" class="{$formClass}">
                <colgroup class="label"></colgroup>
                <colgroup class="value"></colgroup>
               <tbody>
               {$formElementsHtml}
                   <tr>
                       <td colspan="2"><input class="button" name="btnSubmit" value="{$this->l('Update settings')}" type="submit" /></td>
                   </tr>
                </tbody>
               </table>
       </fieldset>
   </form>
HTML;
        return $html;
    }

    /**
     * @see balticode_dpd_parcelstore::getOrderShippingCost()
     * @param CartCode $cartObject
     * @return float
     */
    public function getOrderShippingCostExternal($cartObject) {
        return $this->getOrderShippingCost($cartObject, 0);
    }

    /**
     * <p>Attemps to calculate shipping price from price-country shipping price matrix.</p>
     * <p>If unsuccessful, then default handling fee is returned.</p>
     * <p>If shipping calculation mode is set Per Item, then price will be multiplied by number of packages</p>
     * @param CartCore $cartObject
     * @param float $shippingPrice
     * @return float
     */
    public function getOrderShippingCost($cartObject, $shippingPrice = 0) {
        if ($cartObject->id_customer > 0) {
            //check the customer group
            $freeGroups = explode(',', $this->getConfigData('FREE_GROUPS'));
            if (count($freeGroups) > 0 && $this->getConfigData('FREE_GROUPS') != '') {
                $customerGroups = CustomerCore::getGroupsStatic($cartObject->id_customer);
                foreach ($customerGroups as $customerGroup) {
                    if (in_array($customerGroup, $freeGroups)) {
                        //free shipping if customer belongs to group
                        return 0;
                    }
                }
            }
        }



        $totalSum = $cartObject->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING);

        //check if free shipping by total sum  is enabled
        if ($this->getConfigData('ENABLE_FREE_SHIPPING') == 'yes' && $totalSum >= Tools::convertPrice($this->getConfigData('FREE_SHIPPING_FROM'), Currency::getCurrencyInstance((int) ($cartObject->id_currency)))) {
            return 0;
        }

        $shippingMatrix = $this->_decodeShippingMatrix($this->getConfigDataForThis('HANDLING_FEE_COUNTRY'));
        $destinationAddress = new Address($cartObject->id_address_delivery);

        if ($destinationAddress->id_country && ($destCountry = Country::getIsoById($destinationAddress->id_country)) && isset($shippingMatrix[$destCountry])) {
            //free price
            if ($shippingMatrix[$destCountry]['free_shipping_from'] !== '') {
                if ($totalSum >= Tools::convertPrice($shippingMatrix[$destCountry]['free_shipping_from'], Currency::getCurrencyInstance((int) ($cartObject->id_currency)))) {
                    return 0;
                }
            }

            //weight check
            $loadedProductWeights = array();
            if (($this->getConfigData('MAX_PACKAGE_WEIGHT') > 0 || $this->getConfigData('MIN_PACKAGE_WEIGHT') > 0)) {
                $products = $cartObject->getProducts();
                foreach ($products as $product) {
                    if ($product['is_virtual']) {
                        continue;
                    }
                    for ($i = 0; $i < $product['cart_quantity']; $i++) {
                        $loadedProductWeights[] = $product['weight'];
                    }
                }
            }



            //subtraction is required because edges are 0-10,10.00001-20,....,....
            $packageWeight = $cartObject->getTotalWeight() - 0.000001;
            $weightSet = 10;
            //we need to have price per every kg, where
            //0-10kg consists only base price
            //10,1-20kg equals base price + extra price
            //20,1-30kg equals base price + extra price * 2
            $extraWeightCost = max(floor($packageWeight / $weightSet) * $shippingMatrix[$destCountry]['kg_price'], 0);

            $handlingFee = $shippingMatrix[$destCountry]['base_price'];
            if ($this->getConfigData('HANDLING_ACTION') == 'P') {
                $handlingFee = ($this->_getDpdHelper()->getNumberOfPackagesFromItemWeights($loadedProductWeights, $this->getConfigData('MAX_PACKAGE_WEIGHT'))) * $handlingFee;
            }
            $handlingFee += $extraWeightCost;
            
            //strip VAT, because we want prices to be VAT inclusive under the configuration screen.
            $idTaxRulesGroup = $this->getConfigDataForThis('TAX');
            if ($idTaxRulesGroup) {
                $handlingFee = $handlingFee / (1 + ($this->_getTaxRateForCart($cartObject)/100));
            }


            return Tools::convertPrice($handlingFee, Currency::getCurrencyInstance((int) ($cartObject->
                                    id_currency)));
        }

        $handlingFee = (float) str_replace(',', '.', $this->getConfigData('HANDLING_FEE'));
        if ($this->getConfigData('HANDLING_ACTION') == 'P') {
            $price = ($this->_getDpdHelper()->getNumberOfPackagesFromItemWeights($loadedProductWeights, $this->getConfigData('MAX_PACKAGE_WEIGHT'))) * $handlingFee;
        } else {
            $price = $handlingFee;
        }
        //strip VAT, because we want prices to be VAT inclusive under the configuration screen.
        $idTaxRulesGroup = $this->getConfigDataForThis('TAX');
        if ($idTaxRulesGroup) {
            $price = $price / (1 + ($this->_getTaxRateForCart($cartObject)/100));
        }


        return Tools::convertPrice($price, Currency::getCurrencyInstance((int) ($cartObject->
                                id_currency)));
    }

    /**
     * 
     * @param CartCore $cart
     */
    protected function _getTaxRateForCart($cart) {

        $complete_product_list = $cart->getProducts();
        $products = $complete_product_list;

        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
            $address_id = (int) $cart->id_address_invoice;
        } elseif (count($products)) {
            $prod = current($products);
            $address_id = (int) $prod['id_address_delivery'];
        } else {
            $address_id = null;
        }
        if (!Address::addressExists($address_id)) {
            $address_id = null;
        }

        $carrier = $this->_getHelperModule()->getCarrierFromCode($this->name);
        /* @var $carrier CarrierCore */
        $address = Address::initialize((int) $address_id);
//        echo '<pre>'.htmlspecialchars(print_r($address, true)).'</pre>';

        return $carrier->getTaxesRate($address);
    }

    /**
     * <p>Decodes json encoded string to assoc array (array keys are country ISO codes) and returns in following format:</p>
     * <ul>
      <li><code>country_id</code> - Country ISO code, also array key for this element</li>
      <li><code>base_price</code> - base shipping price up to 10kg</li>
      <li><code>kg_price</code> - additional shipping price for each 10kg</li>
      <li><code>free_shipping_from</code> - when and if to apply free shipping</li>
      </ul>
     * @param string $input
     * @return array
     */
    protected function _decodeShippingMatrix($input) {
        $shippingMatrix = @unserialize($input);
        $result = array();
        if (!is_array($shippingMatrix)) {
            return $result;
        }
        foreach ($shippingMatrix as $countryDefinition) {
            $result[$countryDefinition['country_id']] = $countryDefinition;
        }
        return $result;
    }

    /**
     * <p>Creates admin form fields for this module and caches them after creation</p>
     * @see balticode_dpd_parcelstore_html_helper
     * @return array
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
                'default' => $this->l('DPD Parcelshop'),
                'css' => 'width: 300px;',
            ),
            'handling_fee' => array(
                'title' => $this->l('Price'),
                'type' => 'text',
                'description' => '',
                'default' => '4.90',
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
                'default' => 'a:3:{s:18:"_1388439524852_852";a:4:{s:10:"country_id";s:2:"EE";s:10:"base_price";s:4:"4.90";s:8:"kg_price";s:1:"0";s:18:"free_shipping_from";s:0:"";}s:17:"_1388442604053_53";a:4:{s:10:"country_id";s:2:"LV";s:10:"base_price";s:5:"12.90";s:8:"kg_price";s:1:"0";s:18:"free_shipping_from";s:0:"";}s:18:"_1388709088932_932";a:4:{s:10:"country_id";s:2:"LT";s:10:"base_price";s:5:"13.90";s:8:"kg_price";s:1:"0";s:18:"free_shipping_from";s:0:"";}}',
                'css' => 'width: 300px;',
                'validate' => array('validate_handling_fee_country'),
            ),
            'shortname' => array(
                'title' => $this->l('Show short office names'),
                'type' => 'select',
                'description' => $this->l('Yes: Shows only office name') . '<br/>' . $this->l('No: Shows office name and address'),
                'default' => 'yes',
                'css' => 'width: 300px;',
                'options' => $yesno,
            ),
            'sort_offices' => array(
                'title' => $this->l('Sort offices by priority'),
                'type' => 'select',
                'description' => $this->l('Yes: Offices from bigger cities will be in front')
                . '<br/>' . $this->l('No: Offices are sorted alphabetically')
                ,
                'default' => 'yes',
                'css' => 'width: 300px;',
                'options' => $yesno,
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
                'default' => '20',
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
            'dis_first' => array(
                'title' => $this->l('Show customer one dropdown instead of two'),
                'type' => 'select',
                'description' => $this->l('If this setting is enabled, then customer will be displayed only with a list of stores you entered.')
                . '<br/>' . $this->l('If this setting is disabled, then customer has to pick country/city first and then customer will be displayed second select menu, which contains stores in the selected county/city.'),
                'default' => 'yes',
                'css' => 'width: 300px;',
                'options' => $yesno,
            ),
            'gr_width' => array(
                'title' => $this->l('Width in pixels for city select menu'),
                'type' => 'text',
                'description' => $this->l('Use only when you feel that select menu is too wide.'),
                'default' => '',
                'css' => 'width: 300px;',
            ),
            'of_width' => array(
                'title' => $this->l('Width in pixels for office select menu'),
                'type' => 'text',
                'description' => $this->l('Use only when you feel that select menu is too wide.'),
                'default' => '250',
                'css' => 'width: 300px;',
            ),
        );


        if ($this->dataSendExecutor) {
            $this->form_fields = $this->addArrayAfterKey($this->form_fields, $this->dataSendExecutor->initFormFields(), 'free_shipping_from');
        }

        return $this->form_fields;
    }

    /**
     * <p>This function is called when store administrator is viewing the order.</p>
     * <p>Renders the selected parcelstore</p>
     * @param int $cart_id id cart for the order
     * @return string html string
     */
    public function displayInfoByCart($cart_id) {
        $offices = $this->_getHelperModule()->getOfficesFromCart($cart_id);
        $terminals = array();
        foreach ($offices as $address_id => $office) {
            $terminals[] = $this->getAdminTerminalTitle($office);
        }
        if ($this->dataSendExecutor != null) {
            $extraInfo = $this->dataSendExecutor->displayInfoByCart($cart_id);
        }
        return '<div class="balticode_dpd_parcelstore_chosen">'.$this->l('Chosen parcel terminal:') . ' <b>' . implode(', ', $terminals) . '</b>' . $extraInfo.'</div>';
    }

    /**
     * <p>Adds <code>$appendArray</code> after specified <p>$afterKey</p> inside <code>$inputArray</code></p>
     * @param array $inputArray original assoc array
     * @param array $appendArray array to be appended to original assoc array
     * @param boolean $afterKey when not supplied or key is not found, then appendArray is added to the end
     * @return array
     */
    public function addArrayAfterKey($inputArray, $appendArray, $afterKey = false) {
        $resultingArray = array();
        $appended = false;
        if (!is_string($afterKey)) {
            $afterKey = false;
        }

        foreach ($inputArray as $key => $value) {
            $resultingArray[$key] = $value;
            if ($key === $afterKey) {
                foreach ($appendArray as $iKey => $iValue) {
                    $resultingArray[$iKey] = $iValue;
                }
                $appended = true;
            }
        }

        if (!$appended) {
            foreach ($appendArray as $iKey => $iValue) {
                $resultingArray[$iKey] = $iValue;
            }
            $appended = true;
        }
        return $resultingArray;
    }

    /**
     * <p>Returns cached instance of base helper module</p>
     * @return Balticode_Postoffice
     */
    public function _getHelperModule() {
        if (is_null(self::$_helperModuleInstance)) {
            self::$_helperModuleInstance = Module::getInstanceByName('balticode_postoffice');
        }
        return self::$_helperModuleInstance;
    }

    /**
     * <p>Indicates that parcel terminals should be updated once every 1440 minutes (24h)</p>
     * @return int
     */
    public function getUpdateInterval() {
        $interval = $this->getConfigData('UPD_INTERVAL');
        if (!$interval) {
            return 1440;
        }
        return $interval;
    }

    /**
     * <p>Marks that this modules pickup point list updated as of timestamp</p>
     * @param int $lastUpdated timestamp
     * @return null
     */
    public function setLastUpdated($lastUpdated) {
        Configuration::updateValue(self::CONST_PREFIX . 'LST_UPD', $lastUpdated);
        return;
    }

    /**
     * <p>Returns unix timestamp, when pickup points for this module were last updated</p>
     * @return int timestamp
     */
    public function getLastUpdated() {
        return $this->getConfigData('LST_UPD');
    }

    /**
     * <p>Should return group title for current pickup point.</p>
     * @param array $group row from database <code>balticode_postoffice</code>
     * @return string
     */
    public function getGroupTitle($group) {
        return htmlspecialchars($group['group_name']);
    }

    /**
     * <p>Returns parcel terminal name when short names are enabled.</p>
     * <p>Returns parcel terminal name with address, telephone, opening times when short names are disabled.</p>
     * @param string $terminal
     * @return string
     */
    public function getTerminalTitle($terminal) {
        if ($this->getConfigData('SHORTNAME') == 'yes') {
            return htmlspecialchars($terminal['name']);
        }
        return htmlspecialchars($terminal['name'] . ' (' . $terminal['description'] . ')');
    }

    /**
     * <p>Returns parcel terminal name when short names are enabled.</p>
     * <p>Returns parcel terminal name with address, telephone, opening times when short names are disabled.</p>
     * @param array $terminal
     * @return string
     */
    public function getAdminTerminalTitle($terminal) {
        if ($this->getConfigData('SHORTNAME') == 'yes') {
            return htmlspecialchars($terminal['group_name'] . ' - ' . $terminal['name']);
        }
        return htmlspecialchars($terminal['group_name'] . ' - ' . $terminal['name'] . ' ' . $terminal['description']);
    }

    /**
     * <p>Calls this modules <code>l</code> function</p>
     * @param string $string
     * @return string
     */
    public function ls($string) {
        return $this->l($string);
    }

    /**
     * <p>Returns array of parcel terminals from DPD server or boolean false if fetching failed.</p>
     * @return array|boolean
     */
    public function getOfficeList() {
        $params['username'] = $this->getConfigData('SENDPACKAGE_USERNAME');
        $params['password'] = $this->getConfigData('SENDPACKAGE_PASSWORD');
        if (!$params['username']) {
          return true;
        }
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
//                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($params),
        ));
        $url = $this->getConfigData('API_URL').'parcelshop_info.php';
        $context = stream_context_create($options);
        $postRequestResult = file_get_contents($url, false, $context);
        $body = @json_decode($postRequestResult, true);
        if (!is_array($body) || $body['status'] !== "ok") {
            throw new Exception(sprintf($this->l('DPD request failed with response: %s'), print_r($body, true)));
        }




        if (!$body || !is_array($body) || !isset($body['parcelshops'])) {
            return false;
        }
        $result = array();
        foreach ($body['parcelshops'] as $remoteParcelTerminal) {
            $result[] = array(
                'place_id' => $remoteParcelTerminal['parcelshop_id'],
                'name' => $remoteParcelTerminal['company'],
                'city' => trim($remoteParcelTerminal['city']),
                'county' => '',
                'description' => $remoteParcelTerminal['street'],
                'country' => $remoteParcelTerminal['country'],
                'zip' => $remoteParcelTerminal['pcode'],
                'group_sort' => $this->getGroupSort($remoteParcelTerminal['city']),
            );
        }
        if (count($result) == 0) {
            return false;
        }
        return $result;
    }

    /**
     * <p>Fetches one line long human readable parcel terminal description from DPD Pudo instance</p>
     * @param array $parcelT
     * @return string
     */
    protected function _getDescription($parcelT) {
        if (!isset($parcelT['Pudo_worktime']) || !$parcelT['Pudo_worktime']) {
            return trim($parcelT['Sh_street'] . ' ' . $parcelT['Sh_city'] . ' ' . $parcelT['Sh_postal'] . ', ' . $parcelT['Sh_country'] . ' ' . $parcelT['Sh_phone']);
        } else {
            return trim($parcelT['Sh_street'] . ' ' . $parcelT['Sh_city'] . ' ' . $parcelT['Sh_postal'] . ', ' . $parcelT['Sh_country'] . ' ' . $parcelT['Sh_phone']
                    . ' ' . $this->_getDpdHelper()->getOpeningsDescriptionFromTerminal($parcelT['Pudo_worktime'], $this->_getDpdHelper()->getLocaleToTerritory(strtoupper($parcelT['Sh_country']))));
        }
    }

    /**
     * <p>Groups parcel terminals by following rules:</p>
     * <ul>
      <li>In Estonia parcel terminals from Tallinn, Tartu, Pärnu are displayed first respectively and remaining parcel terminals are displayed in alphabetical order.</li>
      <li>In Latvia parcel terminals from Riga, Daugavpils, Liepaja, Jelgava, Jurmala are displayed first respectively and remaining parcel terminals are displayed in alphabetical order.</li>
      <li>In Lithuania parcel terminals from Vilnius, Kaunas, Klaipeda, Siauliai, Alytus are displayed first respectively and remaining parcel terminals are displayed in alphabetical order.</li>
      </ul>
     * @param string $group_name
     * @return int
     * @see Balticode_Postoffice_Model_Carrier_Abstract::getGroupSort()
     */
    public function getGroupSort($group_name) {
        $group_name = trim(strtolower($group_name));
        $sorts = array(
            //Estonia
            'tallinn' => 20,
            'tartu' => 19,
            'pärnu' => 18,
            //Latvia
            'riga' => 20,
            'daugavpils' => 19,
            'liepaja' => 18,
            'jelgava' => 17,
            'jurmala' => 16,
            //Lithuania
            'vilnius' => 20,
            'kaunas' => 19,
            'klaipeda' => 18,
            'siauliai' => 17,
            'alytus' => 16,
        );
        if (isset($sorts[$group_name]) && $this->getConfigData('SORT_OFFICES')) {
            return $sorts[$group_name];
        }
        if (strpos($group_name, '/') > 0 && $this->getConfigData('SORT_OFFICES')) {
            return 0;
        }
        return 0;
    }

    /**
     * 
     * @return balticode_dpd_parcelstore_validator_helper
     */
    protected function _getValidator() {
        return $this->_getHelperModule()->helper('validator_helper', balticode_dpd_parcelstore::NAME);
    }

    /**
     * 
     * @return balticode_dpd_parcelstore_html_helper
     */
    protected function _getHtmlHelper() {
        /* @var $helper balticode_dpd_parcelstore_html_helper */
        $helper = $this->_getHelperModule()->helper('html_helper', balticode_dpd_parcelstore::NAME);
        $helper->setContext($this->context)->setModuleInstance($this);
        return $helper;
    }

    /**
     * 
     * @return balticode_dpd_parcelstore_dpd_helper
     */
    protected function _getDpdHelper() {
        return $this->_getHelperModule()->helper('dpd_helper', balticode_dpd_parcelstore::NAME);
    }

    /**
     * 
     * @return balticode_postoffice_dialcode_helper
     */
    protected function _getDialCodeHelper() {
        return $this->_getHelperModule()->helper('dialcode_helper', 'balticode_postoffice');
    }

    /**
     * <p>Returns empty string</p>
     * @param OrderCore $order
     * @return string
     */
    protected function _getRemark($order) {
        return '';
    }

    /**
     * <p>Returns number or parcels for the order according to Maximum Package Weight defined in DPD settings</p>
     * @param OrderCore $order
     * @return int
     * @see Balticode_Postoffice_Helper_Data::getNumberOfPackagesFromItemWeights()
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
        return $this->_getDpdHelper()->getNumberOfPackagesFromItemWeights($productWeights, $this->getConfigData('MAX_PACKAGE_WEIGHT'));
    }

    protected function _getWeightForOrder($order) {
        $productWeights = 0;
        $orderItems = $order->getProducts();
        foreach ($orderItems as $orderItem) {
            /* @var $orderItem Mage_Sales_Model_Order_Item */
            for ($i = 0; $i < ($orderItem['product_quantity'] - $orderItem['product_quantity_refunded']); $i++) {
                $productWeights += $orderItem['product_weight'];
            }
        }
        return $productWeights;
    }

}
