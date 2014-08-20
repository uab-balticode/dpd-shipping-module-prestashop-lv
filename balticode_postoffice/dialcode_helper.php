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
 * Description of dialcode_helper
 *
 * @author Sarunas Narkevicius
 */
class balticode_postoffice_dialcode_helper {
    
    protected $_countryCodes = array(
        'IL' => '972',
        'AF' => '93',
        'AL' => '355',
        'DZ' => '213',
        'AS' => '1684',
        'AD' => '376',
        'AO' => '244',
        'AI' => '1264',
        'AG' => '1268',
        'AR' => '54',
        'AM' => '374',
        'AW' => '297',
        'AU' => '61',
        'AT' => '43',
        'AZ' => '994',
        'BS' => '1242',
        'BH' => '973',
        'BD' => '880',
        'BB' => '1246',
        'BY' => '375',
        'BE' => '32',
        'BZ' => '501',
        'BJ' => '229',
        'BM' => '1441',
        'BT' => '975',
        'BA' => '387',
        'BW' => '267',
        'BR' => '55',
        'IO' => '246',
        'BG' => '359',
        'BF' => '226',
        'BI' => '257',
        'KH' => '855',
        'CM' => '237',
        'CA' => '1',
        'CV' => '238',
        'KY' => '345',
        'CF' => '236',
        'TD' => '235',
        'CL' => '56',
        'CN' => '86',
        'CX' => '61',
        'CO' => '57',
        'KM' => '269',
        'CG' => '242',
        'CK' => '682',
        'CR' => '506',
        'HR' => '385',
        'CU' => '53',
        'CY' => '537',
        'CZ' => '420',
        'DK' => '45',
        'DJ' => '253',
        'DM' => '1767',
        'DO' => '1849',
        'EC' => '593',
        'EG' => '20',
        'SV' => '503',
        'GQ' => '240',
        'ER' => '291',
        'EE' => '372',
        'ET' => '251',
        'FO' => '298',
        'FJ' => '679',
        'FI' => '358',
        'FR' => '33',
        'GF' => '594',
        'PF' => '689',
        'GA' => '241',
        'GM' => '220',
        'GE' => '995',
        'DE' => '49',
        'GH' => '233',
        'GI' => '350',
        'GR' => '30',
        'GL' => '299',
        'GD' => '1473',
        'GP' => '590',
        'GU' => '1671',
        'GT' => '502',
        'GN' => '224',
        'GW' => '245',
        'GY' => '595',
        'HT' => '509',
        'HN' => '504',
        'HU' => '36',
        'IS' => '354',
        'IN' => '91',
        'ID' => '62',
        'IQ' => '964',
        'IE' => '353',
        'IT' => '39',
        'JM' => '1876',
        'JP' => '81',
        'JO' => '962',
        'KZ' => '77',
        'KE' => '254',
        'KI' => '686',
        'KW' => '965',
        'KG' => '996',
        'LV' => '371',
        'LB' => '961',
        'LS' => '266',
        'LR' => '231',
        'LI' => '423',
        'LT' => '370',
        'LU' => '352',
        'MG' => '261',
        'MW' => '265',
        'MY' => '60',
        'MV' => '960',
        'ML' => '223',
        'MT' => '356',
        'MH' => '692',
        'MQ' => '596',
        'MR' => '222',
        'MU' => '230',
        'YT' => '262',
        'MX' => '52',
        'MC' => '377',
        'MN' => '976',
        'ME' => '382',
        'MS' => '1664',
        'MA' => '212',
        'MM' => '95',
        'NA' => '264',
        'NR' => '674',
        'NP' => '977',
        'NL' => '31',
        'AN' => '599',
        'NC' => '687',
        'NZ' => '64',
        'NI' => '505',
        'NE' => '227',
        'NG' => '234',
        'NU' => '683',
        'NF' => '672',
        'MP' => '1670',
        'NO' => '47',
        'OM' => '968',
        'PK' => '92',
        'PW' => '680',
        'PA' => '507',
        'PG' => '675',
        'PY' => '595',
        'PE' => '51',
        'PH' => '63',
        'PL' => '48',
        'PT' => '351',
        'PR' => '1939',
        'QA' => '974',
        'RO' => '40',
        'RW' => '250',
        'WS' => '685',
        'SM' => '378',
        'SA' => '966',
        'SN' => '221',
        'RS' => '381',
        'SC' => '248',
        'SL' => '232',
        'SG' => '65',
        'SK' => '421',
        'SI' => '386',
        'SB' => '677',
        'ZA' => '27',
        'GS' => '500',
        'ES' => '34',
        'LK' => '94',
        'SD' => '249',
        'SR' => '597',
        'SZ' => '268',
        'SE' => '46',
        'CH' => '41',
        'TJ' => '992',
        'TH' => '66',
        'TG' => '228',
        'TK' => '690',
        'TO' => '676',
        'TT' => '1868',
        'TN' => '216',
        'TR' => '90',
        'TM' => '993',
        'TC' => '1649',
        'TV' => '688',
        'UG' => '256',
        'UA' => '380',
        'AE' => '971',
        'GB' => '44',
        'US' => '1',
        'UY' => '598',
        'UZ' => '998',
        'VU' => '678',
        'WF' => '681',
        'YE' => '967',
        'ZM' => '260',
        'ZW' => '263',
        'BO' => '591',
        'BN' => '673',
        'CC' => '61',
        'CD' => '243',
        'CI' => '225',
        'FK' => '500',
        'GG' => '44',
        'VA' => '379',
        'HK' => '852',
        'IR' => '98',
        'IM' => '44',
        'JE' => '44',
        'KP' => '850',
        'KR' => '82',
        'LA' => '856',
        'LY' => '218',
        'MO' => '853',
        'MK' => '389',
        'FM' => '691',
        'MD' => '373',
        'MZ' => '258',
        'PS' => '970',
        'PN' => '872',
        'RE' => '262',
        'RU' => '7',
        'BL' => '590',
        'SH' => '290',
        'KN' => '1869',
        'LC' => '1758',
        'MF' => '590',
        'PM' => '508',
        'VC' => '1784',
        'ST' => '239',
        'SO' => '252',
        'SJ' => '47',
        'SY' => '963',
        'TW' => '886',
        'TZ' => '255',
        'TL' => '670',
        'VE' => '58',
        'VN' => '84',
        'VG' => '1284',
        'VI' => '1340',
    );

    /**
     * Gets array of all phone country codes, where array key is country ISO-3166 code and value is numeric dial code without '+'
     * @return array
     */
    public function getCountryCodes() {
        return $this->_countryCodes;
    }


    /**
     * Gets numeric dialcode without '+' sign for specified country code
     * @param string $countryId ISO-3166 country code
     * @return string
     */
    public function getCountryCode($countryId) {
        if (isset($this->_countryCodes[$countryId])) {
            return $this->_countryCodes[$countryId];
        }
        return null;
    }
    
    
    
    /**
     * <p>Attempts to separate country code from phone number by supplied default country.</p>
     * <p>If Phone number is missing country code, then it is applied by supplied country ISO-3166 code</p>
     * <p>Returns array with following format</p>
     * <pre>
     *  array(
     *      'dial_code' => country dial code with + prefix
     *      'phone_number' => phone number without country code
     *  );
     * </pre>
     * 
     * @param string $phonenumber phone number that may contain country code
     * @param string $countryId ISO-3166 country code
     * @return array
     */
    public function separatePhoneNumberFromCountryCode($phonenumber, $countryId) {
        $result = array(
            'dial_code' => '',
            'phone_number' => '',
        );
        $defaultDialCode = $this->getCountryCode($countryId);
        
        //remove all whitespace
        $phonenumber = str_replace(' ', '', $phonenumber);
        
        //when country code is supplied, then it can:
        //start with country code
        //start with + sign
        //start with 00 (double zero)
        //be longer than 10 digits
        $containsCountryCode = strpos($phonenumber, $defaultDialCode) === 0
                || strpos($phonenumber, '+') === 0
                || strpos($phonenumber, '00') === 0
                || strlen($phonenumber) > 10;
        
        //when country code is not supplied, then it can
        //start with single zero
        //start with any number
        if (!$containsCountryCode) {
            $result['dial_code'] = '+'.$defaultDialCode;
            if (strpos($phonenumber, '0') === 0) {
                $result['phone_number'] = substr($phonenumber, 1);
            } else {
                $result['phone_number'] = $phonenumber;
            }
        } else {
            //phone number contains country code
            //we need to know what country code phone number contains
            //remove 00 or + sign
            $phonenumber = ltrim($phonenumber, '+0');
            $dialCode = $this->_getCountryCodeFromPhonenumber($phonenumber, $defaultDialCode);
            $result['dial_code'] = '+'.$dialCode;
            $result['phone_number'] = substr($phonenumber, strlen($dialCode));
        }
        
        
        return $result;
    }
    

    /**
     * <p>Attempts to find country code from phone number, when it is known that phone number most certainly contains country code.</p>
     * <p>Method assumes that longest found dial code is matcing dial code for this phone number.</p>
     * <p>If now match is found then default code is used.</p>
     * @param string $phonenumber phone number that is known to contain country code
     * @param string $defaultCode dial code to be used if no country code is found
     * @return string resulting dial code without + sign
     */
    protected function _getCountryCodeFromPhonenumber($phonenumber, $defaultCode) {
        $matchingCountryCode = '';
        foreach ($this->getCountryCodes() as $countryIso => $dialCode) {
            if (strpos($phonenumber, $dialCode) === 0) {
                if (strlen($dialCode) > $matchingCountryCode) {
                    $matchingCountryCode = $dialCode;
                }
            }
        }
        //as last resort apply default dial code
        if ($matchingCountryCode === '') {
            return $defaultCode;
        }
        return $matchingCountryCode;
    }
    
    
}
