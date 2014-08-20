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
 * <p>Helper class for DPD shipping method related actions</p>
 * <p><code>getLocaleToTerritory</code> originates from Zend Framework since PrestaShop does not support proper locales and PHP itself does not too.</p>
 *
 * @author Sarunas Narkevicius Halmann
 */
class balticode_dpd_parcelstore_dpd_helper {
    protected $_apis = array();

    /**
     * Class wide Locale Constants
     *
     * @var array $_territoryData
     */
    private static $_territoryData = array(
        'AD' => 'ca_AD', 'AE' => 'ar_AE', 'AF' => 'fa_AF', 'AG' => 'en_AG', 'AI' => 'en_AI',
        'AL' => 'sq_AL', 'AM' => 'hy_AM', 'AN' => 'pap_AN', 'AO' => 'pt_AO', 'AQ' => 'und_AQ',
        'AR' => 'es_AR', 'AS' => 'sm_AS', 'AT' => 'de_AT', 'AU' => 'en_AU', 'AW' => 'nl_AW',
        'AX' => 'sv_AX', 'AZ' => 'az_Latn_AZ', 'BA' => 'bs_BA', 'BB' => 'en_BB', 'BD' => 'bn_BD',
        'BE' => 'nl_BE', 'BF' => 'mos_BF', 'BG' => 'bg_BG', 'BH' => 'ar_BH', 'BI' => 'rn_BI',
        'BJ' => 'fr_BJ', 'BL' => 'fr_BL', 'BM' => 'en_BM', 'BN' => 'ms_BN', 'BO' => 'es_BO',
        'BR' => 'pt_BR', 'BS' => 'en_BS', 'BT' => 'dz_BT', 'BV' => 'und_BV', 'BW' => 'en_BW',
        'BY' => 'be_BY', 'BZ' => 'en_BZ', 'CA' => 'en_CA', 'CC' => 'ms_CC', 'CD' => 'sw_CD',
        'CF' => 'fr_CF', 'CG' => 'fr_CG', 'CH' => 'de_CH', 'CI' => 'fr_CI', 'CK' => 'en_CK',
        'CL' => 'es_CL', 'CM' => 'fr_CM', 'CN' => 'zh_Hans_CN', 'CO' => 'es_CO', 'CR' => 'es_CR',
        'CU' => 'es_CU', 'CV' => 'kea_CV', 'CX' => 'en_CX', 'CY' => 'el_CY', 'CZ' => 'cs_CZ',
        'DE' => 'de_DE', 'DJ' => 'aa_DJ', 'DK' => 'da_DK', 'DM' => 'en_DM', 'DO' => 'es_DO',
        'DZ' => 'ar_DZ', 'EC' => 'es_EC', 'EE' => 'et_EE', 'EG' => 'ar_EG', 'EH' => 'ar_EH',
        'ER' => 'ti_ER', 'ES' => 'es_ES', 'ET' => 'en_ET', 'FI' => 'fi_FI', 'FJ' => 'hi_FJ',
        'FK' => 'en_FK', 'FM' => 'chk_FM', 'FO' => 'fo_FO', 'FR' => 'fr_FR', 'GA' => 'fr_GA',
        'GB' => 'en_GB', 'GD' => 'en_GD', 'GE' => 'ka_GE', 'GF' => 'fr_GF', 'GG' => 'en_GG',
        'GH' => 'ak_GH', 'GI' => 'en_GI', 'GL' => 'iu_GL', 'GM' => 'en_GM', 'GN' => 'fr_GN',
        'GP' => 'fr_GP', 'GQ' => 'fan_GQ', 'GR' => 'el_GR', 'GS' => 'und_GS', 'GT' => 'es_GT',
        'GU' => 'en_GU', 'GW' => 'pt_GW', 'GY' => 'en_GY', 'HK' => 'zh_Hant_HK', 'HM' => 'und_HM',
        'HN' => 'es_HN', 'HR' => 'hr_HR', 'HT' => 'ht_HT', 'HU' => 'hu_HU', 'ID' => 'id_ID',
        'IE' => 'en_IE', 'IL' => 'he_IL', 'IM' => 'en_IM', 'IN' => 'hi_IN', 'IO' => 'und_IO',
        'IQ' => 'ar_IQ', 'IR' => 'fa_IR', 'IS' => 'is_IS', 'IT' => 'it_IT', 'JE' => 'en_JE',
        'JM' => 'en_JM', 'JO' => 'ar_JO', 'JP' => 'ja_JP', 'KE' => 'en_KE', 'KG' => 'ky_Cyrl_KG',
        'KH' => 'km_KH', 'KI' => 'en_KI', 'KM' => 'ar_KM', 'KN' => 'en_KN', 'KP' => 'ko_KP',
        'KR' => 'ko_KR', 'KW' => 'ar_KW', 'KY' => 'en_KY', 'KZ' => 'ru_KZ', 'LA' => 'lo_LA',
        'LB' => 'ar_LB', 'LC' => 'en_LC', 'LI' => 'de_LI', 'LK' => 'si_LK', 'LR' => 'en_LR',
        'LS' => 'st_LS', 'LT' => 'lt_LT', 'LU' => 'fr_LU', 'LV' => 'lv_LV', 'LY' => 'ar_LY',
        'MA' => 'ar_MA', 'MC' => 'fr_MC', 'MD' => 'ro_MD', 'ME' => 'sr_Latn_ME', 'MF' => 'fr_MF',
        'MG' => 'mg_MG', 'MH' => 'mh_MH', 'MK' => 'mk_MK', 'ML' => 'bm_ML', 'MM' => 'my_MM',
        'MN' => 'mn_Cyrl_MN', 'MO' => 'zh_Hant_MO', 'MP' => 'en_MP', 'MQ' => 'fr_MQ', 'MR' => 'ar_MR',
        'MS' => 'en_MS', 'MT' => 'mt_MT', 'MU' => 'mfe_MU', 'MV' => 'dv_MV', 'MW' => 'ny_MW',
        'MX' => 'es_MX', 'MY' => 'ms_MY', 'MZ' => 'pt_MZ', 'NA' => 'kj_NA', 'NC' => 'fr_NC',
        'NE' => 'ha_Latn_NE', 'NF' => 'en_NF', 'NG' => 'en_NG', 'NI' => 'es_NI', 'NL' => 'nl_NL',
        'NO' => 'nb_NO', 'NP' => 'ne_NP', 'NR' => 'en_NR', 'NU' => 'niu_NU', 'NZ' => 'en_NZ',
        'OM' => 'ar_OM', 'PA' => 'es_PA', 'PE' => 'es_PE', 'PF' => 'fr_PF', 'PG' => 'tpi_PG',
        'PH' => 'fil_PH', 'PK' => 'ur_PK', 'PL' => 'pl_PL', 'PM' => 'fr_PM', 'PN' => 'en_PN',
        'PR' => 'es_PR', 'PS' => 'ar_PS', 'PT' => 'pt_PT', 'PW' => 'pau_PW', 'PY' => 'gn_PY',
        'QA' => 'ar_QA', 'RE' => 'fr_RE', 'RO' => 'ro_RO', 'RS' => 'sr_Cyrl_RS', 'RU' => 'ru_RU',
        'RW' => 'rw_RW', 'SA' => 'ar_SA', 'SB' => 'en_SB', 'SC' => 'crs_SC', 'SD' => 'ar_SD',
        'SE' => 'sv_SE', 'SG' => 'en_SG', 'SH' => 'en_SH', 'SI' => 'sl_SI', 'SJ' => 'nb_SJ',
        'SK' => 'sk_SK', 'SL' => 'kri_SL', 'SM' => 'it_SM', 'SN' => 'fr_SN', 'SO' => 'sw_SO',
        'SR' => 'srn_SR', 'ST' => 'pt_ST', 'SV' => 'es_SV', 'SY' => 'ar_SY', 'SZ' => 'en_SZ',
        'TC' => 'en_TC', 'TD' => 'fr_TD', 'TF' => 'und_TF', 'TG' => 'fr_TG', 'TH' => 'th_TH',
        'TJ' => 'tg_Cyrl_TJ', 'TK' => 'tkl_TK', 'TL' => 'pt_TL', 'TM' => 'tk_TM', 'TN' => 'ar_TN',
        'TO' => 'to_TO', 'TR' => 'tr_TR', 'TT' => 'en_TT', 'TV' => 'tvl_TV', 'TW' => 'zh_Hant_TW',
        'TZ' => 'sw_TZ', 'UA' => 'uk_UA', 'UG' => 'sw_UG', 'UM' => 'en_UM', 'US' => 'en_US',
        'UY' => 'es_UY', 'UZ' => 'uz_Cyrl_UZ', 'VA' => 'it_VA', 'VC' => 'en_VC', 'VE' => 'es_VE',
        'VG' => 'en_VG', 'VI' => 'en_VI', 'VU' => 'bi_VU', 'WF' => 'wls_WF', 'WS' => 'sm_WS',
        'YE' => 'ar_YE', 'YT' => 'swb_YT', 'ZA' => 'en_ZA', 'ZM' => 'en_ZM', 'ZW' => 'sn_ZW'
    );
    
    /**
     * <p>PHP setLocale is not thread-safe, but we need multiple locale weekday names in one thread</p>
     * @var array
     */
    private static $_weekdayNames = array(
        'aa' => array(
            'sun' => 'A',
            'mon' => 'E',
            'tue' => 'T',
            'wed' => 'A',
            'thu' => 'K',
            'fri' => 'G',
            'sat' => 'S',
        ),
        'af' => array(
            'sun' => 'So',
            'mon' => 'Ma',
            'tue' => 'Di',
            'wed' => 'Wo',
            'thu' => 'Do',
            'fri' => 'Vr',
            'sat' => 'Sa',
        ),
        'ak' => array(
            'sun' => 'K',
            'mon' => 'D',
            'tue' => 'B',
            'wed' => 'W',
            'thu' => 'Y',
            'fri' => 'F',
            'sat' => 'M',
        ),
        'ar' => array(
            'sun' => 'ح',
            'mon' => 'ن',
            'tue' => 'ث',
            'wed' => 'ر',
            'thu' => 'خ',
            'fri' => 'ج',
            'sat' => 'س',
        ),
        'az' => array(
            'sun' => 'B.',
            'mon' => 'B.E.',
            'tue' => 'Ç.A.',
            'wed' => 'Ç.',
            'thu' => 'C.A.',
            'fri' => 'C',
            'sat' => 'Ş.',
        ),
        'be' => array(
            'sun' => 'н',
            'mon' => 'п',
            'tue' => 'а',
            'wed' => 'с',
            'thu' => 'ч',
            'fri' => 'п',
            'sat' => 'с',
        ),
        'bg' => array(
            'sun' => 'н',
            'mon' => 'п',
            'tue' => 'в',
            'wed' => 'с',
            'thu' => 'ч',
            'fri' => 'п',
            'sat' => 'с',
        ),
        'bs' => array(
            'sun' => 'Ned',
            'mon' => 'Pon',
            'tue' => 'Uto',
            'wed' => 'Sri',
            'thu' => 'Čet',
            'fri' => 'Pet',
            'sat' => 'Sub',
        ),
        'ca' => array(
            'sun' => 'g',
            'mon' => 'l',
            'tue' => 't',
            'wed' => 'c',
            'thu' => 'j',
            'fri' => 'v',
            'sat' => 's',
        ),
        'cch' => array(
            'sun' => 'Yok',
            'mon' => 'Tung',
            'tue' => 'T. Tung',
            'wed' => 'Tsan',
            'thu' => 'Nas',
            'fri' => 'Nat',
            'sat' => 'Chir',
        ),
        'cs' => array(
            'sun' => 'N',
            'mon' => 'P',
            'tue' => 'Ú',
            'wed' => 'S',
            'thu' => 'Č',
            'fri' => 'P',
            'sat' => 'S',
        ),
        'cy' => array(
            'sun' => 'S',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'I',
            'fri' => 'G',
            'sat' => 'S',
        ),
        'da' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'O',
            'thu' => 'T',
            'fri' => 'F',
            'sat' => 'L',
        ),
        'de' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'D',
            'wed' => 'M',
            'thu' => 'D',
            'fri' => 'F',
            'sat' => 'S',
        ),
        'ee' => array(
            'sun' => 'K',
            'mon' => 'D',
            'tue' => 'B',
            'wed' => 'K',
            'thu' => 'Y',
            'fri' => 'F',
            'sat' => 'M',
        ),
        'el' => array(
            'sun' => 'Κ',
            'mon' => 'Δ',
            'tue' => 'Τ',
            'wed' => 'Τ',
            'thu' => 'Π',
            'fri' => 'Π',
            'sat' => 'Σ',
        ),
        'en' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'W',
            'thu' => 'T',
            'fri' => 'F',
            'sat' => 'S',
        ),
        'eo' => array(
            'sun' => 'di',
            'mon' => 'lu',
            'tue' => 'ma',
            'wed' => 'me',
            'thu' => 'ĵa',
            'fri' => 've',
            'sat' => 'sa',
        ),
        'es' => array(
            'sun' => 'D',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'J',
            'fri' => 'V',
            'sat' => 'S',
        ),
        'et' => array(
            'sun' => 'P',
            'mon' => 'E',
            'tue' => 'T',
            'wed' => 'K',
            'thu' => 'N',
            'fri' => 'R',
            'sat' => 'L',
        ),
        'eu' => array(
            'sun' => 'ig',
            'mon' => 'al',
            'tue' => 'as',
            'wed' => 'az',
            'thu' => 'og',
            'fri' => 'or',
            'sat' => 'lr',
        ),
        'fa' => array(
            'sun' => 'ی',
            'mon' => 'د',
            'tue' => 'س',
            'wed' => 'چ',
            'thu' => 'پ',
            'fri' => 'ج',
            'sat' => 'ش',
        ),
        'fi' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'K',
            'thu' => 'T',
            'fri' => 'P',
            'sat' => 'L',
        ),
        'fil' => array(
            'sun' => 'L',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'H',
            'fri' => 'B',
            'sat' => 'S',
        ),
        'fo' => array(
            'sun' => 'sun',
            'mon' => 'mán',
            'tue' => 'týs',
            'wed' => 'mik',
            'thu' => 'hós',
            'fri' => 'frí',
            'sat' => 'ley',
        ),
        'fr' => array(
            'sun' => 'D',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'J',
            'fri' => 'V',
            'sat' => 'S',
        ),
        'fur' => array(
            'sun' => 'D',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'J',
            'fri' => 'V',
            'sat' => 'S',
        ),
        'ga' => array(
            'sun' => 'D',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'C',
            'thu' => 'D',
            'fri' => 'A',
            'sat' => 'S',
        ),
        'gaa' => array(
            'sun' => 'Ho',
            'mon' => 'Dzu',
            'tue' => 'Dzf',
            'wed' => 'Sho',
            'thu' => 'Soo',
            'fri' => 'Soh',
            'sat' => 'Ho',
        ),
        'gl' => array(
            'sun' => 'D',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'X',
            'fri' => 'V',
            'sat' => 'S',
        ),
        'gsw' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'D',
            'wed' => 'M',
            'thu' => 'D',
            'fri' => 'F',
            'sat' => 'S',
        ),
        'gv' => array(
            'sun' => 'Jed',
            'mon' => 'Jel',
            'tue' => 'Jem',
            'wed' => 'Jerc',
            'thu' => 'Jerd',
            'fri' => 'Jeh',
            'sat' => 'Jes',
        ),
        'ha' => array(
            'sun' => 'L',
            'mon' => 'L',
            'tue' => 'T',
            'wed' => 'L',
            'thu' => 'A',
            'fri' => 'J',
            'sat' => 'A',
        ),
        'haw' => array(
            'sun' => 'LP',
            'mon' => 'P1',
            'tue' => 'P2',
            'wed' => 'P3',
            'thu' => 'P4',
            'fri' => 'P5',
            'sat' => 'P6',
        ),
        'he' => array(
            'sun' => 'א',
            'mon' => 'ב',
            'tue' => 'ג',
            'wed' => 'ד',
            'thu' => 'ה',
            'fri' => 'ו',
            'sat' => 'ש',
        ),
        'hi' => array(
            'sun' => 'र',
            'mon' => 'सो',
            'tue' => 'मं',
            'wed' => 'बु',
            'thu' => 'गु',
            'fri' => 'शु',
            'sat' => 'श',
        ),
        'hr' => array(
            'sun' => 'n',
            'mon' => 'p',
            'tue' => 'u',
            'wed' => 's',
            'thu' => 'č',
            'fri' => 'p',
            'sat' => 's',
        ),
        'hu' => array(
            'sun' => 'V',
            'mon' => 'H',
            'tue' => 'K',
            'wed' => 'S',
            'thu' => 'C',
            'fri' => 'P',
            'sat' => 'S',
        ),
        'hy' => array(
            'sun' => 'Կիր',
            'mon' => 'Երկ',
            'tue' => 'Երք',
            'wed' => 'Չոր',
            'thu' => 'Հնգ',
            'fri' => 'Ուր',
            'sat' => 'Շաբ',
        ),
        'ia' => array(
            'sun' => 'dom',
            'mon' => 'lun',
            'tue' => 'mar',
            'wed' => 'mer',
            'thu' => 'jov',
            'fri' => 'ven',
            'sat' => 'sab',
        ),
        'id' => array(
            'sun' => 'Min',
            'mon' => 'Sen',
            'tue' => 'Sel',
            'wed' => 'Rab',
            'thu' => 'Kam',
            'fri' => 'Jum',
            'sat' => 'Sab',
        ),
        'ig' => array(
            'sun' => 'Ụka',
            'mon' => 'Mọn',
            'tue' => 'Tiu',
            'wed' => 'Wen',
            'thu' => 'Tọọ',
            'fri' => 'Fraị',
            'sat' => 'Sat',
        ),
        'in' => array(
            'sun' => 'Min',
            'mon' => 'Sen',
            'tue' => 'Sel',
            'wed' => 'Rab',
            'thu' => 'Kam',
            'fri' => 'Jum',
            'sat' => 'Sab',
        ),
        'is' => array(
            'sun' => 's',
            'mon' => 'm',
            'tue' => 'þ',
            'wed' => 'm',
            'thu' => 'f',
            'fri' => 'f',
            'sat' => 'l',
        ),
        'it' => array(
            'sun' => 'D',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'G',
            'fri' => 'V',
            'sat' => 'S',
        ),
        'iw' => array(
            'sun' => 'א',
            'mon' => 'ב',
            'tue' => 'ג',
            'wed' => 'ד',
            'thu' => 'ה',
            'fri' => 'ו',
            'sat' => 'ש',
        ),
        'ja' => array(
            'sun' => '日',
            'mon' => '月',
            'tue' => '火',
            'wed' => '水',
            'thu' => '木',
            'fri' => '金',
            'sat' => '土',
        ),
        'ka' => array(
            'sun' => 'კ',
            'mon' => 'ო',
            'tue' => 'ს',
            'wed' => 'ო',
            'thu' => 'ხ',
            'fri' => 'პ',
            'sat' => 'შ',
        ),
        'kaj' => array(
            'sun' => 'Lad',
            'mon' => 'Lin',
            'tue' => 'Tal',
            'wed' => 'Lar',
            'thu' => 'Lam',
            'fri' => 'Jum',
            'sat' => 'Asa',
        ),
        'kam' => array(
            'sun' => 'Jpl',
            'mon' => 'Jtt',
            'tue' => 'Jnn',
            'wed' => 'Jtn',
            'thu' => 'Alh',
            'fri' => 'Ijm',
            'sat' => 'Jms',
        ),
        'kcg' => array(
            'sun' => 'Lad',
            'mon' => 'Tan',
            'tue' => 'Tal',
            'wed' => 'Lar',
            'thu' => 'Lam',
            'fri' => 'Jum',
            'sat' => 'Asa',
        ),
        'kfo' => array(
            'sun' => 'Lah',
            'mon' => 'Kub',
            'tue' => 'Gba',
            'wed' => 'Tan',
            'thu' => 'Yei',
            'fri' => 'Koy',
            'sat' => 'Sat',
        ),
        'kk' => array(
            'sun' => 'жс.',
            'mon' => 'дс.',
            'tue' => 'сс.',
            'wed' => 'ср.',
            'thu' => 'бс.',
            'fri' => 'жм.',
            'sat' => 'сһ.',
        ),
        'kl' => array(
            'sun' => 'sab',
            'mon' => 'ata',
            'tue' => 'mar',
            'wed' => 'pin',
            'thu' => 'sis',
            'fri' => 'tal',
            'sat' => 'arf',
        ),
        'ko' => array(
            'sun' => '일',
            'mon' => '월',
            'tue' => '화',
            'wed' => '수',
            'thu' => '목',
            'fri' => '금',
            'sat' => '토',
        ),
        'kok' => array(
            'sun' => 'रवि',
            'mon' => 'सोम',
            'tue' => 'मंगळ',
            'wed' => 'बुध',
            'thu' => 'गुरु',
            'fri' => 'शुक्र',
            'sat' => 'शनि',
        ),
        'ku' => array(
            'sun' => 'ی',
            'mon' => 'د',
            'tue' => 'س',
            'wed' => '4',
            'thu' => '5',
            'fri' => '6',
            'sat' => '7',
        ),
        'kw' => array(
            'sun' => 'Sul',
            'mon' => 'Lun',
            'tue' => 'Mth',
            'wed' => 'Mhr',
            'thu' => 'Yow',
            'fri' => 'Gwe',
            'sat' => 'Sad',
        ),
        'ln' => array(
            'sun' => 'eye',
            'mon' => 'm1',
            'tue' => 'm2',
            'wed' => 'm3',
            'thu' => 'm4',
            'fri' => 'm5',
            'sat' => 'mps',
        ),
        'lt' => array(
            'sun' => 'S',
            'mon' => 'P',
            'tue' => 'A',
            'wed' => 'T',
            'thu' => 'K',
            'fri' => 'P',
            'sat' => 'Š',
        ),
        'lv' => array(
            'sun' => 'S',
            'mon' => 'P',
            'tue' => 'O',
            'wed' => 'T',
            'thu' => 'C',
            'fri' => 'P',
            'sat' => 'S',
        ),
        'mk' => array(
            'sun' => 'н',
            'mon' => 'п',
            'tue' => 'в',
            'wed' => 'с',
            'thu' => 'ч',
            'fri' => 'п',
            'sat' => 'с',
        ),
        'mn' => array(
            'sun' => 'Ня',
            'mon' => 'Да',
            'tue' => 'Мя',
            'wed' => 'Лх',
            'thu' => 'Пү',
            'fri' => 'Ба',
            'sat' => 'Бя',
        ),
        'mo' => array(
            'sun' => 'D',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'J',
            'fri' => 'V',
            'sat' => 'S',
        ),
        'mr' => array(
            'sun' => 'र',
            'mon' => 'सो',
            'tue' => 'मं',
            'wed' => 'बु',
            'thu' => 'गु',
            'fri' => 'शु',
            'sat' => 'श',
        ),
        'ms' => array(
            'sun' => 'Ahd',
            'mon' => 'Isn',
            'tue' => 'Sel',
            'wed' => 'Rab',
            'thu' => 'Kha',
            'fri' => 'Jum',
            'sat' => 'Sab',
        ),
        'mt' => array(
            'sun' => 'Ħ',
            'mon' => 'T',
            'tue' => 'T',
            'wed' => 'E',
            'thu' => 'Ħ',
            'fri' => 'Ġ',
            'sat' => 'S',
        ),
        'nb' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'O',
            'thu' => 'T',
            'fri' => 'F',
            'sat' => 'L',
        ),
        'nds' => array(
            'sun' => '1',
            'mon' => '2',
            'tue' => '3',
            'wed' => '4',
            'thu' => '5',
            'fri' => '6',
            'sat' => '7',
        ),
        'ne' => array(
            'sun' => '१',
            'mon' => '२',
            'tue' => '३',
            'wed' => '४',
            'thu' => '५',
            'fri' => '६',
            'sat' => '७',
        ),
        'nl' => array(
            'sun' => 'Z',
            'mon' => 'M',
            'tue' => 'D',
            'wed' => 'W',
            'thu' => 'D',
            'fri' => 'V',
            'sat' => 'Z',
        ),
        'nn' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'O',
            'thu' => 'T',
            'fri' => 'F',
            'sat' => 'L',
        ),
        'no' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'O',
            'thu' => 'T',
            'fri' => 'F',
            'sat' => 'L',
        ),
        'nr' => array(
            'sun' => 'Son',
            'mon' => 'Mvu',
            'tue' => 'Bil',
            'wed' => 'Tha',
            'thu' => 'Ne',
            'fri' => 'Hla',
            'sat' => 'Gqi',
        ),
        'nso' => array(
            'sun' => 'Son',
            'mon' => 'Mos',
            'tue' => 'Bed',
            'wed' => 'Rar',
            'thu' => 'Ne',
            'fri' => 'Hla',
            'sat' => 'Mok',
        ),
        'ny' => array(
            'sun' => 'Mul',
            'mon' => 'Lem',
            'tue' => 'Wir',
            'wed' => 'Tat',
            'thu' => 'Nai',
            'fri' => 'San',
            'sat' => 'Wer',
        ),
        'om' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'W',
            'thu' => 'T',
            'fri' => 'F',
            'sat' => 'S',
        ),
        'pl' => array(
            'sun' => 'N',
            'mon' => 'P',
            'tue' => 'W',
            'wed' => 'Ś',
            'thu' => 'C',
            'fri' => 'P',
            'sat' => 'S',
        ),
        'pt' => array(
            'sun' => 'D',
            'mon' => 'S',
            'tue' => 'T',
            'wed' => 'Q',
            'thu' => 'Q',
            'fri' => 'S',
            'sat' => 'S',
        ),
        'ro' => array(
            'sun' => 'D',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'J',
            'fri' => 'V',
            'sat' => 'S',
        ),
        'ru' => array(
            'sun' => 'В',
            'mon' => 'П',
            'tue' => 'В',
            'wed' => 'С',
            'thu' => 'Ч',
            'fri' => 'П',
            'sat' => 'С',
        ),
        'rw' => array(
            'sun' => 'cyu.',
            'mon' => 'mbe.',
            'tue' => 'kab.',
            'wed' => 'gtu.',
            'thu' => 'kan.',
            'fri' => 'gnu.',
            'sat' => 'gnd.',
        ),
        'se' => array(
            'sun' => 's',
            'mon' => 'v',
            'tue' => 'm',
            'wed' => 'g',
            'thu' => 'd',
            'fri' => 'b',
            'sat' => 'L',
        ),
        'sh' => array(
            'sun' => 'n',
            'mon' => 'p',
            'tue' => 'u',
            'wed' => 's',
            'thu' => 'č',
            'fri' => 'p',
            'sat' => 's',
        ),
        'sid' => array(
            'sun' => 'S',
            'mon' => 'S',
            'tue' => 'M',
            'wed' => 'R',
            'thu' => 'H',
            'fri' => 'A',
            'sat' => 'Q',
        ),
        'sk' => array(
            'sun' => 'N',
            'mon' => 'P',
            'tue' => 'U',
            'wed' => 'S',
            'thu' => 'Š',
            'fri' => 'P',
            'sat' => 'S',
        ),
        'sl' => array(
            'sun' => 'n',
            'mon' => 'p',
            'tue' => 't',
            'wed' => 's',
            'thu' => 'č',
            'fri' => 'p',
            'sat' => 's',
        ),
        'so' => array(
            'sun' => 'A',
            'mon' => 'I',
            'tue' => 'S',
            'wed' => 'A',
            'thu' => 'K',
            'fri' => 'J',
            'sat' => 'S',
        ),
        'sq' => array(
            'sun' => 'D',
            'mon' => 'H',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'E',
            'fri' => 'P',
            'sat' => 'S',
        ),
        'sr' => array(
            'sun' => 'н',
            'mon' => 'п',
            'tue' => 'у',
            'wed' => 'с',
            'thu' => 'ч',
            'fri' => 'п',
            'sat' => 'с',
        ),
        'ss' => array(
            'sun' => 'Son',
            'mon' => 'Mso',
            'tue' => 'Bil',
            'wed' => 'Tsa',
            'thu' => 'Ne',
            'fri' => 'Hla',
            'sat' => 'Mgc',
        ),
        'st' => array(
            'sun' => 'Son',
            'mon' => 'Mma',
            'tue' => 'Bed',
            'wed' => 'Rar',
            'thu' => 'Ne',
            'fri' => 'Hla',
            'sat' => 'Moq',
        ),
        'sv' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'O',
            'thu' => 'T',
            'fri' => 'F',
            'sat' => 'L',
        ),
        'sw' => array(
            'sun' => 'Jpi',
            'mon' => 'Jtt',
            'tue' => 'Jnn',
            'wed' => 'Jtn',
            'thu' => 'Alh',
            'fri' => 'Iju',
            'sat' => 'Jmo',
        ),
        'tg' => array(
            'sun' => 'Яшб',
            'mon' => 'Дшб',
            'tue' => 'Сшб',
            'wed' => 'Чшб',
            'thu' => 'Пшб',
            'fri' => 'Ҷмъ',
            'sat' => 'Шнб',
        ),
        'th' => array(
            'sun' => 'อ',
            'mon' => 'จ',
            'tue' => 'อ',
            'wed' => 'พ',
            'thu' => 'พ',
            'fri' => 'ศ',
            'sat' => 'ส',
        ),
        'tl' => array(
            'sun' => 'L',
            'mon' => 'L',
            'tue' => 'M',
            'wed' => 'M',
            'thu' => 'H',
            'fri' => 'B',
            'sat' => 'S',
        ),
        'tn' => array(
            'sun' => 'Tsh',
            'mon' => 'Mos',
            'tue' => 'Bed',
            'wed' => 'Rar',
            'thu' => 'Ne',
            'fri' => 'Tla',
            'sat' => 'Mat',
        ),
        'to' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'T',
            'wed' => 'P',
            'thu' => 'T',
            'fri' => 'F',
            'sat' => 'T',
        ),
        'tr' => array(
            'sun' => 'P',
            'mon' => 'P',
            'tue' => 'S',
            'wed' => 'Ç',
            'thu' => 'P',
            'fri' => 'C',
            'sat' => 'C',
        ),
        'trv' => array(
            'sun' => 'E',
            'mon' => 'K',
            'tue' => 'D',
            'wed' => 'T',
            'thu' => 'S',
            'fri' => 'R',
            'sat' => 'M',
        ),
        'ts' => array(
            'sun' => 'Son',
            'mon' => 'Mus',
            'tue' => 'Bir',
            'wed' => 'Har',
            'thu' => 'Ne',
            'fri' => 'Tlh',
            'sat' => 'Mug',
        ),
        'uk' => array(
            'sun' => 'Н',
            'mon' => 'П',
            'tue' => 'В',
            'wed' => 'С',
            'thu' => 'Ч',
            'fri' => 'П',
            'sat' => 'С',
        ),
        'uz' => array(
            'sun' => 'Я',
            'mon' => 'Д',
            'tue' => 'С',
            'wed' => 'Ч',
            'thu' => 'П',
            'fri' => 'Ж',
            'sat' => 'Ш',
        ),
        've' => array(
            'sun' => 'Swo',
            'mon' => 'Mus',
            'tue' => 'Vhi',
            'wed' => 'Rar',
            'thu' => 'Ṋa',
            'fri' => 'Ṱan',
            'sat' => 'Mug',
        ),
        'vi' => array(
            'sun' => 'CN',
            'mon' => 'Th 2',
            'tue' => 'Th 3',
            'wed' => 'Th 4',
            'thu' => 'Th 5',
            'fri' => 'Th 6',
            'sat' => 'Th 7',
        ),
        'xh' => array(
            'sun' => 'Caw',
            'mon' => 'Mvu',
            'tue' => 'Bin',
            'wed' => 'Tha',
            'thu' => 'Sin',
            'fri' => 'Hla',
            'sat' => 'Mgq',
        ),
        'yo' => array(
            'sun' => 'Àìkú',
            'mon' => 'Ajé',
            'tue' => 'Ìsẹ́gun',
            'wed' => 'Ọjọ́rú',
            'thu' => 'Àṣẹ̀ṣẹ̀dáiyé',
            'fri' => 'Ẹtì',
            'sat' => 'Àbámẹ́ta',
        ),
        'zh' => array(
            'sun' => '日',
            'mon' => '一',
            'tue' => '二',
            'wed' => '三',
            'thu' => '四',
            'fri' => '五',
            'sat' => '六',
        ),
        'zu' => array(
            'sun' => 'S',
            'mon' => 'M',
            'tue' => 'B',
            'wed' => 'T',
            'thu' => 'S',
            'fri' => 'H',
            'sat' => 'M',
        ),
    );

    /**
     * <p>Converts DPD op=pudo Opening Times into human readable format.</p>
     * <ul>
      <li><b>Input: 1:11:0:16:0,2:7:30:20:0,3:7:30:20:0,4:7:30:20:0,5:7:30:20:0,6:7:30:20:0,7:8:0:16:0</b></li>
      <li><b>Result: (E-R 7:30-20; L 8-16; P 11-16)</b></li>
      </ul>
     * 
     * @param string $dpdOpeningDescription DPD Openings description
     * @param string $locale language code to be used for printing out weekday names
     * @return string
     */
    public function getOpeningsDescriptionFromTerminal($dpdOpeningDescription, $locale = null) {
        $openingTimeFormat = 'H:m';
        $displayTimeFormat = 'H:i';

        if (!$locale) {

            $locale = Context::getContext()->language->language_code;
        }

        //days  start from monday
        $passThruOrder = array('2', '3', '4', '5', '6', '7', '1');

        /*
         * Format: array key = weekday name
         */
        $openingDescriptions = array();

        //we need these in order to get times in normalized manner
        $startTime = new DateTime(); //Zend_Date(0, Zend_Date::TIMESTAMP);
        $endTime = new DateTime();
        $this->setTimestamp($startTime, 0);
        $this->setTimestamp($endTime, 0);

        //here are comma separeted opening times
        $openings = explode(',', $dpdOpeningDescription);
        /*
         * Format:
         * <weekday>:<starth>:<startm>:<endh>:<endm>
         * 1=sunday
         * 2=monday
         * ...
         * 7=saturday
         * 
         */
        foreach ($openings as $opening) {
            $openTimePartials = explode(':', $opening);
            $startTime->setTime($openTimePartials[1], $openTimePartials[2], 0);
            $endTime->setTime($openTimePartials[3], $openTimePartials[4], 0);

            if (!isset($openingDescriptions[(string) $openTimePartials[0]])) {
                $openingDescriptions[(string) $openTimePartials[0]] = array();
            }
            $openingDescriptions[(string) $openTimePartials[0]][] = str_replace(':00', '', $startTime->format($displayTimeFormat)) . '-' . str_replace(':00', '', $endTime->format($displayTimeFormat));
        }


        /*
         * Format:
         * array key = day of week digit
         * array value = all opening times for that day separated by comma
         */
        $finalOpeningDescriptions = array();
        $previusOpeningStatement = false;
        $previusWeekdayName = false;
        $firstElement = false;


        foreach ($passThruOrder as $dayOfWeekDigit) {
//            $startTime->setTimestamp(strtotime('2008-W05-' . ($dayOfWeekDigit - 1)));
            $this->setTimestamp($startTime, strtotime('2008-W05-' . ($dayOfWeekDigit - 1)));
//            $startTime->($dayOfWeekDigit - 1, Zend_Date::WEEKDAY_DIGIT);
//            $weekDayName = $startTime->get(Zend_Date::WEEKDAY_NARROW, $locale);
            $weekDayName = $this->_getLocalizedWeekdayName($startTime, $locale);
            if ($firstElement === false) {
                $firstElement = $previusWeekdayName;
            }
            if (isset($openingDescriptions[$dayOfWeekDigit])) {

                $openingStatement = str_replace('0-0', '0-24', implode(',', $openingDescriptions[$dayOfWeekDigit]));
            } else {
                $openingStatement = '';
            }

            if ($previusOpeningStatement !== false && $previusOpeningStatement != $openingStatement) {
                //we have a change
                if ($firstElement != $previusWeekdayName) {
                    $finalOpeningDescriptions[] = $firstElement . '-' . $previusWeekdayName . ' ' . $previusOpeningStatement;
                } else {
                    $finalOpeningDescriptions[] = $previusWeekdayName . ' ' . $previusOpeningStatement;
                }


                $firstElement = false;
            }
            $previusOpeningStatement = $openingStatement;
            $previusWeekdayName = $weekDayName;
        }
        if ($previusOpeningStatement !== false) {
            if ($previusOpeningStatement !== '') {
                //we have a change
                if (!$firstElement) {
                    $finalOpeningDescriptions[] = $previusWeekdayName . ' ' . $previusOpeningStatement;
                } else {
                    $finalOpeningDescriptions[] = $firstElement . '-' . $previusWeekdayName . ' ' . $previusOpeningStatement;
                }
            }
        }

        if (count($finalOpeningDescriptions)) {
            return '(' . implode('; ', $finalOpeningDescriptions) . ')';
        }
        return '';
    }
    
    /**
     * <p>Emulates DateTime::setTimeStamp for PHP 5.2</p>
     * @param DateTime $dateTime
     * @param int $timeStamp
     */
    protected function setTimestamp(DateTime &$dateTime, $timeStamp) {
        if (method_exists($dateTime, 'setTimestamp')) {
            $dateTime->setTimestamp($timeStamp);
            return;
        }
        //we need to emulate
        $dateTime->setDate(date('Y', $timeStamp), date('n', $timeStamp), date('d', $timeStamp));
        $dateTime->setTime(date('G', $timeStamp), date('i', $timeStamp), date('s', $timeStamp));
    }

    /**
     * <p>Attempts to return short weekday name for specified date if possible</p>
     * <p>If not possible, then <code>strftime('%a')</code> is performed</p>
     * @param DateTime $time
     * @param string $locale language code
     * @return string
     */
    protected function _getLocalizedWeekdayName(DateTime $time, $locale) {
        $weekDayRefs = array(
            '0' => 'sun',
            '1' => 'mon',
            '2' => 'tue',
            '3' => 'wed',
            '4' => 'thu',
            '5' => 'fri',
            '6' => 'sat',
            
        );
        $lang = explode('_', $locale);
        $weekDayNum = date('w', $time->format('U'));
        if (isset(self::$_weekdayNames[$lang[0]]) && isset(self::$_weekdayNames[$lang[0]][$weekDayRefs[$weekDayNum]])) {
            return self::$_weekdayNames[$lang[0]][$weekDayRefs[$weekDayNum]];
        }
        return strftime('%a', $time->format('U'));
    }

    /**
     * Returns the expected locale for a given territory
     *
     * @param string $territory Territory for which the locale is being searched
     * @return string|null Locale string or null when no locale has been found
     */
    public function getLocaleToTerritory($territory) {
        $territory = strtoupper($territory);
        if (array_key_exists($territory, self::$_territoryData)) {
            return self::$_territoryData[$territory];
        }

        return null;
    }
    
    
    /**
     * <p>Takes in array of parcel weights and returns number of packages calculated by maximum allowed weight per package</p>
     * <p>Uses better methology to find number of packages than regular cart weight divided by maximum package weight</p>
     * <p>For example, if maximum package weight is 31kg, ang we have 3x 20kg packages, then number of packages would be 3 (not 2)</p>
     * <p>If maximum package weight is not defined, then it returns 1</p>
     * <p>If single item in <code>$itemWeights</code> exceeds <code>$maximumWeight</code> then this function returns false</p>
     * @param array $itemWeights array of item weights
     * @param int $maximumWeight maximum allowed weight of one package
     * @return int
     */
    public function getNumberOfPackagesFromItemWeights(array $itemWeights, $maximumWeight) {
        $numPackages = 1;
        $weight = 0;
        if ($maximumWeight > 0) {
            
            foreach ($itemWeights as $itemWeight) {
                if ($itemWeight > $maximumWeight) {
                    return false;
                }
                $weight += $itemWeight;
                if ($weight > $maximumWeight) {
                    $numPackages++;
                    $weight = $itemWeight;
                }
            }
            
        }
        return 1; //return $numPackages;
    }
    
    
    /**
     * <p>Gets cached DPD API instance for specified PrestaShop store id and shipping method code.</p>
     * @param string $storeId store id to fetch the api for
     * @param string $code shipping method code to fetch the api for
     * @return balticode_dpd_parcelstore_dpd_api
     */
    public function getApi($storeId = null, $code = balticode_dpd_parcelstore::CONST_PREFIX) {
        echo "string";
        die();
        if ($storeId === null) {
            $storeId = Context::getContext()->shop->id;
        }
        if (isset($this->_apis[$code]) && isset($this->_apis[$code][$storeId])) {
            return $this->_apis[$code][$storeId];
        }
        if (!isset($this->_apis[$code])) {
            $this->_apis[$code] = array();
        }
        if (!class_exists('balticode_dpd_parcelstore_dpd_api', false)) {
            require_once(_PS_MODULE_DIR_ . 'balticode_dpd_parcelstore' . '/dpd_api.php');
        }
        $api = new balticode_dpd_parcelstore_dpd_api();
        $api->setStore($storeId);
        $api->setCode($code);
        $this->_apis[$code][$storeId] = $api;
        return $this->_apis[$code][$storeId];
    }
    
    

}
