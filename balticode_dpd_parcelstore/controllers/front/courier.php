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
 * <p>Handles Order DPD courier to pickup goods commands when DPD parcelstore is enabled.</p>
 * <p>This controller is included in the front, because PrestaShop does not support backend URLs for the modules without creating menu item for it first.</p>
 * <p>It is only checked that user would be at least logged in the admin.</p>
 *
 * @author Sarunas Narkevicius
 */
class Balticode_dpd_parcelstoreCourierModuleFrontController extends ModuleFrontController {
    
    /**
     * <p>Exits script with message not logged in message when user is not logged in as admin.</p>
     */
    public function __construct() {
        $cookie = new Cookie('psAdmin');
        
        $isAdmin = false;

        if ($cookie->id_employee) {
            $isAdmin = true;
        }
        if (!$isAdmin) {
            echo 'not logged in';
            exit;
        }

        parent::__construct();
    }
    
    
    /**
     * <p>Sends courier call pickup goods action to DPD server and returns information about its status.</p>
     * <p>Available POST params:</p>
     * <ul>
         <li><b>Po_remark</b> - Note to sent to courier</li>
         <li><b>Po_Date</b> - Date when courier should pick up goods. Format: YYYY-MM-DD</li>
         <li><b>Po_Time</b> - Time range when courier should pick up goods. Format: HMM-HMM (timefrom-timetill)</li>
         <li><b>Po_envelope_qty</b> - Number of envelopes courier should pick up</li>
         <li><b>Po_parcel_qty</b> - Number of parcels courier should pick up</li>
         <li><b>Po_pallet_qty</b> - Number of pallets courier should pick up</li>
     </ul>
     * @return boolean|array
     */
    public function initContent() {
        $params = $_POST;
        try {
            $prefix = balticode_dpd_parcelstore::ORDER_COMMENT_START_PREFIX;

            $api = $this->_getDpdHelper()->getApi(Context::getContext()->shop->id, balticode_dpd_parcelstore::CONST_PREFIX);
            $isCourierComing = $api->isCourierComing();
            if (isset($params) &&  !empty($params) && ($params['Po_parcel_qty'] != '0' || $params['Po_pallet_qty'] != '0' || $params['Po_remark'] != '')) {
                $orderSendData = array(
                    'nonStandard' => isset($params['Po_remark']) ? $params['Po_remark'] : '',
                    'parcelsCount' => $params['Po_parcel_qty'],
                    'palletsCount' => $params['Po_pallet_qty'],
                   
                );
                $orderSendResult = $api->callCurier($orderSendData);
               
            } else {
                $this->context->smarty->assign(array(
                    //'availableDates' => $this->getAvailableDates(),
                    //'availableTimes' => $this->getAvailabeTimes(),
                    'requests' => '',//'<pre>'.htmlspecialchars(print_r($api->getLoggedRequests(), true)).'</pre>',
                ));
                
                if (!$isCourierComing) {
                    $result = array(
                        'needs_reload' => true,
                        'is_action_error' => false,
                        'html' => $this->context->smarty->fetch($this->getTemplatePath().'courier.tpl'),
                    );
                    die(json_encode($result));
                    return $result;
                    
                } else {
                    $pickupTimeFrom = new DateTime();
                    $pickupTimeTill = new DateTime();
                    $pickupTimeFrom->setTimezone(new DateTimeZone('ETC/GMT+0'));
                    $pickupTimeTill->setTimezone(new DateTimeZone('ETC/GMT+0'));
                    $this->setTimestamp($pickupTimeFrom, $isCourierComing[0]);
                    $this->setTimestamp($pickupTimeTill, $isCourierComing[1]);
//                    $pickupTimeFrom->setTimestamp($isCourierComing[0]);
//                    $pickupTimeTill->setTimestamp($isCourierComing[1]);
                    $courierArrivalDate = $pickupTimeFrom->format(Context::getContext()->language->date_format_lite);
                    $courierArrivalTime = $pickupTimeFrom->format('G') . ' ' . $this->module->l('and') . ' ' . $pickupTimeTill->format('G');
                    
                    
                }
            }
            
            
            
            
            
        } catch (Exception $e) {
            $result = array(
                'errors' => array($e->getMessage()),
                'needs_reload' => true,
                'is_action_error' => false,
            );
            die(json_encode($result));
            return $result;
        }
        if ($orderSendResult == 'DONE') {
            $result = array(
                'messages' => array(sprintf($this->module->l('DPD kurjeris sėkmingai iškviestas.'))),
                'needs_reload' => true,
                'is_action_error' => false,
            );
            die(json_encode($result));
            return $result;
        }
        else{
            $result = array(
                'messages' => array(sprintf($this->module->l('DPD kurjerio iškviesti nepavyko, klaida: %1$s'), $orderSendResult)),
                'needs_reload' => true,
                'is_action_error' => false,
            );
            die(json_encode($result));
            return $result;
        }
        
    }
    
    /**
     * <p>Emulates DateTime::setTimeStamp for PHP 5.2</p>
     * @param DateTime $dateTime
     * @param int $timeStamp
     */
    protected function setTimestamp(DateTime &$dateTime, $timeStamp, $ignoreOffset = true) {
        if (method_exists($dateTime, 'setTimestamp') && false) {
            $dateTime->setTimestamp($timeStamp);
            return;
        }
        $offset = 0;
        if ($timeStamp != 0) {
            $offset = -21600;
        }
        if (!$ignoreOffset && $timeStamp != 0) {
            $offset = $dateTime->getOffset();
        }
        $timeStamp += $offset;

        //we need to emulate
        $dateTime->setDate(date('Y', $timeStamp), date('n', $timeStamp), date('d', $timeStamp));
        $dateTime->setTime(date('G', $timeStamp), date('i', $timeStamp), date('s', $timeStamp));
    }

    /**
     * 
     * @return balticode_dpd_parcelstore_dpd_helper
     */
    protected function _getDpdHelper() {
        return $this->module->_getHelperModule()->helper('dpd_helper', balticode_dpd_parcelstore::NAME);
    }
    
    /**
     * 
     * @param string $input
     * @return string
     */
    protected function _getTimeFrom($input) {
        $parts = explode('-', $input);
        return str_replace('00', '', $parts[0]);
    }
    protected function _getTimeTil($input) {
        $parts = explode('-', $input);
        return str_replace('00', '', $parts[1]);
    }
    
    protected $_dateFormat = 'yyyy-MM-dd';
    protected $_timeFormat = 'Hi';
    protected $_timeFormatNice = 'H:i';
    
    protected $_availableDates;
    protected $_apiResult;
    
    /**
     * <p>Fetches list of available pickup dates from DPD server and returns it as HTML select string</p>
     * @return string
     */
    private function getAvailableDates() {
        if ($this->_availableDates) {
            return $this->_availableDates;
        }
        $showOnlyDates = false;
        $datesResult = $this->_getDpdHelper()
                ->getApi(Context::getContext()->shop->id, balticode_dpd_parcelstore::CONST_PREFIX)->getCourierCollectionTimes();
        $this->_apiResult = $datesResult;
        $dateObj = null;

        $resultString = '';
//        $resultString .= '<pre>'.htmlspecialchars(print_r($datesResult, true)).'</pre>';
            $resultString .= '<select name="Po_Date" id="Po_date" onchange="updateDpdEeTimes(this);">';
            $resultString .= '<option value="-">'.$this->module->l(' - select pickup date - ').'</option>';

        foreach ($datesResult['Po_date'] as $dateString) {
            $dateParts = explode('-', $dateString);
            if ((date('Ymd') == date('Ymd', mktime(0, 0, 0, $dateParts[1], $dateParts[2], $dateParts[0]))) && !$showOnlyDates) {
                //today
                $resultString .= $this->_getDateInputLabel($this->module->l('Today'), $dateString);
            } else if ((date('Ymd') == date('Ymd', mktime(0, 0, 0, $dateParts[1], $dateParts[2] - 1, $dateParts[0]))) && !$showOnlyDates) {
                //tomorrow
                $resultString .= $this->_getDateInputLabel($this->module->l('Tomorrow'), $dateString);
            } else {
                //other dates
                $resultString .= $this->_getDateInputLabel($dateString, $dateString);
            }
        }
        $resultString .= '</select>';

        //do availabletimes as well
        $this->_availableDates = $resultString;
        return $this->_availableDates;
    }
    
    
    /**
     * <p>Fetches available pickup times from DPD server and returns it as json encoded object</p>
     * <p>Format:</p>
     * <pre>
     *  array(
     *      '2013-12-24' => 'html select menu with available time ranges',
     *      '2013-12-25' => 'html select menu with available time ranges',
     *      ....
     * );
     * </pre>
     * @return string
     */
    public function getAvailabeTimes() {
        if (!$this->_apiResult) {
            $this->getAvailableDates();
        }
        $availableTimes = array();
        $timeDateObject = null;
        //array key is date, content is html with select menu
        $availableTimes["-"] = $this->module->l('Select pickup date first');
        foreach ($this->_apiResult['Po_date'] as $key => $dateString) {
            $timeFrom = $this->_normalizeTime($this->_apiResult['Po_time_from'][$key]);
            $timeTo = $this->_normalizeTime($this->_apiResult['Po_time_til'][$key]);
            $timeWindow = $this->_normalizeTime($this->_apiResult['Minimal_time_window'][$key]);
            $availableTimes[$dateString] = $this->_getTimeSelectMenu($timeFrom, $timeTo, $timeWindow);
        }
        return json_encode($availableTimes);
    }
    
    
    protected function _getDateInputLabel($label, $value) {
        return '<option value="'.  htmlspecialchars($value).'" >'.  htmlspecialchars($label).'</option>';
    }
    
    
    /**
     * <p>Takes earliest possible time, latest possible time, minimum allowed timewindow and renders it into one select menu with pickup time ranges.</p>
     * <p>All arguments have to be within same date.</p>
     * <p>Pickup time ranges are always displayed within narrowest possible timewindow.</p>
     * @param DateTime $timeFrom earliest time possible
     * @param DateTime $timeTo latest time possible
     * @param DateTime $timeWindow minimal allowed time between start and end time.
     * @return string HTML select menu
     */
    protected function _getTimeSelectMenu($timeFrom, $timeTo, $timeWindow) {
        $position = $timeFrom->format('H');
        $result = '<select name="Po_Time" id="Po_Time">';
        do {
            $endTime = clone $timeFrom;
//            $endTime->setTimestamp($endTime->format('U') + $timeWindow->format('U'));
            $this->setTimestamp($endTime, $endTime->format('U') + $timeWindow->format('U'));
            if ($timeTo->format('U') < $endTime->format('U')) {
                $timeDisplay = htmlspecialchars(ltrim($timeFrom->format($this->_timeFormat), '0') . '-' . ltrim($timeTo->format($this->_timeFormat), '0'));
                $timeDisplayNice = htmlspecialchars($timeFrom->format($this->_timeFormatNice) . '-' . $timeTo->format($this->_timeFormatNice));
            } else {
                $timeDisplay = htmlspecialchars(ltrim($timeFrom->format($this->_timeFormat), '0') . '-' . ltrim($endTime->format($this->_timeFormat), '0'));
                $timeDisplayNice = htmlspecialchars($timeFrom->format($this->_timeFormatNice) . '-' . $endTime->format($this->_timeFormatNice));
            }
            $result .= '<option value="' . $timeDisplay . '">' . $timeDisplayNice . '</option>';
//            $timeFrom->setTimestamp($timeFrom->format('U') + 60 * 60);
            $this->setTimestamp($timeFrom, $timeFrom->format('U') + 60 * 60);
        } while ($timeFrom->format('G') + $timeWindow->format('G') <= $timeTo->format('G'));
        $result .= '</select>';
        
        return $result;
    }
    
    
    /**
     * <p>Normalizes DPD times to Unix timestamps.</p>
     * <p>For example 930 should be displayed as 09:30 and 9 should be displayed as 09:00</p>
     * @param string $input
     * @return int
     */
    protected function _normalizeTime($input) {
        if (strlen($input) === 1) {
            $input = '0'.$input .'00';
        }
        if (strlen($input) === 2) {
            $input = $input .'00';
        }
        if (strlen($input) === 3) {
            $input = '0'.$input;
        }
        $date = new DateTime();
        $this->setTimestamp($date, 0);
//        $date->setTimestamp(0);
        $date->setTimezone(new DateTimeZone('ETC/GMT+0'));
        $date->setTime($input[0].$input[1], $input[2].$input[3], 0);
        return $date;
    }
    
    
    
}
