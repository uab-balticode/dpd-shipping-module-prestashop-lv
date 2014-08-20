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
 * @copyright  Copyright (c) 2013 BaltiCode UAB (http://balticode.com/)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt  GNU Public License V3.0
 * @author     Sarunas Narkevicius
 * 

 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * <p>Wrapper class for communicating with DPD API</p>
 * <p>Each request is prefilled with username, password, return address data whenever possible.</p>
 * <p>Each response is json_decoded to assoc array and Exception is thrown when response error code is else than integer 0</p>
 *
 * @author Sarunas Narkevicius
 */
class balticode_dpd_parcelstore_dpd_api {

    protected $_store;
    protected $_code;
    
    protected static $_logRequestsLimit = 60;
    
    /**
     * <p>Information about requests done via this API is stored here</p>
     * <p>Each item is in following format:</p>
     * <ul>
         <li><code>url</code> - destination URL for the request</li>
         <li><code>request</code> - assoc array of request parameters to DPD</li>
         <li><code>response</code> - assoc array of response parameters from DPD (can be empty if request fails</li>
     </ul>
     *
     * @var array 
     */
    protected static $_loggedRequests = array();
    
    public function __construct() {
        ;
    }
    
    public function setStore($store) {
        $this->_store = $store;
        return $this;
    }
    
    public function setCode($code) {
        $this->_code = $code;
        return $this;
    }
    
    public function getCode() {
        return $this->_code;
    }
    
    public function getStore() {
        return $this->_store;
    }
    
    /**
     * Retrieve information from carrier configuration
     *
     * @param   string $field
     * @return  mixed
     */
    public function getConfigData($field) {
        if (!$this->getCode()) {
            return false;
        }
        $path = $this->getCode().$field;
        return Configuration::get($path, null, null, $this->getStore());
    }
    
    /**
     * <p>Fetches list of parcel terminals from DPD API. (op=pudo)</p>
     * <p>This function can be used without DPD API account.</p>
     * <p>Parcel terminals are included in 'data' array key.</p>
     * @return array
     */
    public function getOfficeList() {
        $body = @$this->_getRequest();
        return $body;
    }
    
    
    /**
     * <p>Fetches available courier collection times. (op=date)</p>
     * @param array $requestData
     * @return array
     */
    public function getCourierCollectionTimes(array $requestData = array()) {
        $details = array(
            'op' => 'date',
            'Po_postal' => $this->getConfigData('RETURN_POSTCODE'),
            'Po_country' => strtolower($this->getConfigData('RETURN_COUNTRY')),
            'Po_type' => isset($requestData['Po_type'])?strtolower($requestData['Po_type']):'po',
        );
        if ($details['Po_country'] == 'ee') {
            //documentation required 'eesti' as supplied country
            //in order to use same api in other countries, we change it to 'eesti' only for estonia
            //probably in the future all countries require only iso-3166 code
            $details['Po_country'] = 'eesti';
        }
        foreach ($details as $key => $detail) {
            $requestData[$key] = $detail;
        }
        $requestResult = $this->_getRequest($requestData);
        return $requestResult;
        
    }
    
    /**
     * <p>Send parcel data to DPD server, prefills with return data from PrestaShop configuration.</p>
     * @param array $requestData
     * @return array
     */
    public function autoSendData(array $requestData) {
        echo "string";
        die();
        $returnDetails = array(
            'op' => 'order',
            'Po_name' => $this->getConfigData('RETURN_NAME'),
            'Po_company' => $this->getConfigData('RETURN_COMPANY'),
            'Po_street' => $this->getConfigData('RETURN_STREET'),
            'Po_postal' => $this->getConfigData('RETURN_POSTCODE'),
            'Po_country' => strtolower($this->getConfigData('RETURN_COUNTRY')),
            'Po_city' => $this->getConfigData('RETURN_CITYCOUNTY'),
            'Po_contact' => $this->getConfigData('RETURN_NAME'),
            'Po_phone' => $this->getConfigData('RETURN_PHONE'),
            //po-remark
            'Po_email' => $this->getConfigData('RETURN_EMAIL'),
            'Po_show_on_label' => $this->getConfigData('PO_SHOW_ON_LABEL')?'true':'false',
            'Po_save_address' => $this->getConfigData('PO_SAVE_ADDRESS')?'true':'false',
//            'Po_type' => $this->getConfigData('senddata_service'),
            'LabelsPosition' => $this->getConfigData('LABEL_POSITION'),
            
        );
        
        foreach ($returnDetails as $key => $returnDetail) {
            $requestData[$key] = $returnDetail;
        }
        if (!isset($requestData['Po_type'])) {
            $requestData['Po_type'] = 'LO';
        }
        
        $requestResult = $this->_getRequest($requestData);
        return $requestResult;
    }
    
    
    /**
     * <p>Determines if courier has been called to pick up the packages.</p>
     * <p>If courier has been called to fetch packages and courier pickup time from has not yet been reached, then it returns array consisting following elements:</p>
     * <ul>
         <li>UNIX timestamp when courier pickup should start</li>
         <li>UNIX timestamp when courier pickup should end</li>
     </ul>
     * <p>On every other scenario this function returns boolean false</p>
     * @return boolean|array
     */
    public function isCourierComing() {
        $pickupTime = $this->getConfigData('COURIER_PICKUP_TIME');
        $time = time();
        $timezoneDiff = 0;
        $time2 = new DateTime("now");
        $timeZone = new DateTimeZone('Etc/GMT+0');
        
        $timezoneDiff = date('Z');
        $time += $timezoneDiff;
        
        
        if ($pickupTime) {
            $pickupTime = explode(',', $pickupTime);
        }
        if ($pickupTime[0] >= $time) {
            return $pickupTime;
        }
        return false;
    }
    
    
    /**
     * <p>Sends actual request to DPD API, prefills with username and password and json decodes the result.</p>
     * <p>Default operation (op=pudo), on such scenario username and password is not sent.</p>
     * <p>If return error code is else than 0, then exception is thrown.</p>
     * @param array $params
     * @param string $url
     * @return array
     */
    protected function _getRequest($params = array('action' => 'parcelshop_info'), $url = null) {,
        echo "string2";
        die();
        if (!$url) {
            $url = $this->getConfigData('API_URL');
        }
        $url .= $params['action'].'.php';
        Logger::addLog($url,0);
        $params['username'] = $this->getConfigData('SENDPACKAGE_USERNAME');
        $params['password'] = $this->getConfigData('SENDPACKAGE_PASSWORD');
        $logRequest = array(
            'url' => $url,
            'request' => $params,
            'response' => '',
            );
        Logger::addLog($logRequest,1);



        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query($params),
                'timeout' => $this->getConfigData('HTTP_REQUEST_TIMEOUT') > 10 ? $this->getConfigData('HTTP_REQUEST_TIMEOUT') : 10,
        ));
        $context = stream_context_create($options);
        $postRequestResult = file_get_contents($url, false, $context);
        $body = @json_decode($postRequestResult, true);


        if (is_array($body) &  $params['action'] == 'pickup_info') {
            return $body;
        }



        if (!is_array($body) || !isset($body['errlog']) || $body['errlog'] !== '') {
            $translatedText = sprintf($this->l('DPD request failed with response: %s'), print_r($url, true));
            $logRequest['response'] = $body;
            $this->_addLogRequest($logRequest);

            throw new Exception($translatedText);
        }
        $logRequest['response'] = $body;
        $this->_addLogRequest($logRequest);
        return $logRequest['response'];
    }

    /**
     * <p>Makes sure that maximum of 60 entries are stored in the request log.</p>
     * @param array $logRequest
     * @return null
     */
    protected function _addLogRequest($logRequest) {
        if (count(self::$_loggedRequests) > self::$_logRequestsLimit) {
            return;
        }
        if (count(self::$_loggedRequests) == self::$_logRequestsLimit) {
            self::$_loggedRequests[] = array(
                'url' => '',
                'request' => 'Logging limit reached',
                'response' => '',
            );
            return;
        }
        self::$_loggedRequests[] = $logRequest;
        
    }
    
    /**
     * <p>Get all the logged requests which are performed thru this class.</p>
     * <p>Each item is in following format:</p>
     * <ul>
         <li><code>url</code> - destination URL for the request</li>
         <li><code>request</code> - assoc array of request parameters to DPD</li>
         <li><code>response</code> - assoc array of response parameters from DPD (can be empty if request fails</li>
     </ul>
     * @return array
     */
    public function getLoggedRequests() {
        return self::$_loggedRequests;
    }

    /**
     * <p>Required for the PrestaShop module to recognize translations</p>
     * @param string $i
     * @return string
     */
    protected function l($i) {
        return Module::getInstanceByName(balticode_dpd_parcelstore::NAME)->l($i);
    }
    
    
    
}
