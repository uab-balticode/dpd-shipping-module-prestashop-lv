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
 * <p>Base class for carriers, which ask customer to pick parcel terminal of choice from dropdown list.</p>
 * <p>Offers following business functions:</p>
 * <ul>
     <li>Chosen parcel terminal is forwarded to the Merchant</li>
     <li>Offers auto update functionality for parcel terminals if subclasses implement actual parcel terminal fetch procedure</li>
 </ul>
 * @author Sarunas Narkevicius
 */
class Balticode_Postoffice extends Module {
    protected static $_carrierModules;
    private static $clientGroups;
    protected static $_carriersByCode;
    protected static $_officesInSession;
    
    
    
    /**
     * <p>For adding ability to overload getPostoffices function in the deeper modules</p>
     * @var ModuleCore
     */
    protected $_shippingModel;
    /**
     * <p>Cached helper instances</p>
     * @var array
     */
    protected static $_helpers = array();
    
    
    /**
     * Default constructor
     */
    public function __construct() {
        $this->tab = 'shipping_logistics';
        $this->name = 'balticode_postoffice';
        $this->version = '0.3';
        $this->module_key = "099e4b94b94d62de16433027dc34d010";
        $this->author = "Balticode";
        $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.7');
        parent::__construct();
        $this->displayName = $this->l('Balticode Generic Office plugin');
        $this->description = $this->l('Base plugin for shipping methods, where customer has to pick a parcel terminal from a list');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
        
    }
    
    
    /**
     * <p>Performs following actions:</p>
     * <ul>
         <li>Creates database table <code>balticode_carriermodule</code> for holding list of carriers using this class</li>
         <li>Creates database table <code>balticode_postoffice</code> for holding pickup points for the carrier modules</li>
         <li>Creates database table <code>balticode_cart_shipping</code> for holding information which shipping address in the cart has selected which parcel terminal</li>
         <li>Adds hook <code>actionCarrierProcess</code> - for validation, that terminal was selected</li>
         <li>Adds hook <code>orderConfirmation</code> - for displaying selected parcel terminal in some emails</li>
     </ul>
     * @return boolean
     */
    public function install() {
        if (!parent::install()
                || !$this->_createCarrierModuleTable()
                || !$this->_createPostofficeTable()
                || !$this->_createSelectedShippingOptionInCartTable()
                || !$this->_createIndexes()
                || !$this->registerHook('actionCarrierProcess')
                || !$this->registerHook('orderConfirmation')) {
            return false;
        }
        return true;
    }
    
    /**
     * <p>Drops all the information that was created in the install process.</p>
     * @return boolean
     */
    public function uninstall() {
        if (!parent::uninstall()
                || !$this->unregisterHook('actionCarrierProcess')
                || !$this->unregisterHook('orderConfirmation')
                || !$this->_dropTables()) {
            return false;
        }
        return true;
    }
    
    /**
     * <p>Updates <code>carrier_lang.delay</code> descriptions, required for the carrier to be displayed.</p>
     * @param string $code carrier.external_module_name
     * @param string $displayName new name
     * @return bool
     */
    public function setDisplayName($code, $displayName) {
        $db = Db::getInstance();
        if (!$displayName) {
            $displayName = $code;
        }
        
        $sql = "UPDATE "._DB_PREFIX_ ."carrier_lang ".
                " SET delay = '{$db->escape($displayName)}' " .
                " WHERE id_carrier IN (SELECT id_carrier from "._DB_PREFIX_ ."carrier WHERE external_module_name = '{$db->escape($code)}' and deleted = 0)"
                ;
        return $db->execute($sql);
    }
    
    /**
     * <p>Sets tax group for current carrier.external_module_name</p>
     * @param string $code shipping method code
     * @param int $tax tax id
     */
    public function setTaxGroup($code, $tax) {
        $db = Db::getInstance();
        
        $qu = 'UPDATE `' . _DB_PREFIX_ .
                "carrier` set id_tax_rules_group = '{$db->escape($tax)}' where external_module_name = '{$db->escape($code)}' and deleted = 0;";

        $db->execute($qu);
        
        $qu = 'SELECT * FROM `' . _DB_PREFIX_ .
                "carrier` where external_module_name = '{$db->escape($code)}' and deleted = 0;";

        $res = $db->executeS($qu);
        if (count($res) !== 1) {
            Tools::displayError(sprintf('Carrier for code %s could not be found', $code));
        }
        $id = $res[0]['id_carrier'];
        
        
        //check the carrier_shops
        $res = $db->executeS('select id_shop from ' . _DB_PREFIX_ . 'shop where active = 1');
        $qu = 'DELETE FROM `' . _DB_PREFIX_ . "carrier_tax_rules_group_shop` WHERE id_carrier = $id";
        $db->execute($qu);
        foreach ($res as $re) {
            //insert the langs

            if ($tax > 0) {
                $qu = 'REPLACE INTO `' . _DB_PREFIX_ . "carrier_tax_rules_group_shop` (id_carrier, id_tax_rules_group, id_shop)";
                $qu .= " values ($id, $tax, " . $re['id_shop'] . ")";
                $db->execute($qu);
            } else {
                $qu = 'REPLACE INTO `' . _DB_PREFIX_ . "carrier_tax_rules_group_shop` (id_carrier, id_tax_rules_group, id_shop)";
                $qu .= " values ($id, 0, " . $re['id_shop'] . ")";
                $db->execute($qu);
                /*
                $qu = 'DELETE FROM `' . _DB_PREFIX_ . "carrier_tax_rules_group_shop` ";
                $qu .= " WHERE id_carrier = $id and id_shop =  " . $re['id_shop'] . "";
                $db->execute($qu);
                 */
            }
        }
        
        
    }
    

    /**
     * <p>For allowing to overload <code>getPostoffices</code> method in shipping model instance with name <code>__getPostoffices</code></p>
     * @param ModuleCore $shippingModel
     * @return Balticode_Postoffice
     */
    public function setShippingModel($shippingModel) {
        $this->_shippingModel = $shippingModel;
        return $this;
    }
    
    /**
     *  <p>Returns assoc array which should contain the actual postoffices
     * which belong to the selected group_id in alplabetically sorted order.</p>
     * <p>If no $groupId is supplied, then all the postoffices are returned.</p>
     * <p>Offices are sorted by</p>
     * <ul>
         <li>group_sort descending</li>
         <li>group_name ascending</li>
         <li>name ascending</li>
     </ul>
     * 
     * @param string $code carrier.external_module_name
     * @param int $groupId
     * @param int $officeId when only requesting one specific office
     * @param int $addressId when supplied then only offices from the addressId country are returned.
     * @return array
     */
    public function getPostOffices($code, $groupId = null, $officeId = null, $addressId = null) {
        if ($this->_shippingModel && method_exists($this->_shippingModel, '__getPostoffices')) {
            $this->_shippingModel->__getPostOffices($code, $groupId, $officeId, $addressId);
        }

        $db = Db::getInstance();
        $sql = "SELECT * FROM " . _DB_PREFIX_ . "balticode_postoffice "
                . " WHERE remote_module_name = '{$db->escape($code)}'"
        ;

        if ($groupId !== null) {
            $sql .= " AND group_id = '{$db->escape($groupId)}'";
        }
        if ($addressId) {
            $address = new AddressCore($addressId);
            if ($address->id_country) {
                $sql .= " AND country = '{$db->escape(Country::getIsoById($address->id_country))}'";
            }
        }


        if ($officeId !== null) {
            $sql .= " AND remote_place_id = '{$db->escape($officeId)}'";
        }
        
        
        if ($groupId == null && $officeId == null) {
            $sql .= " ORDER BY group_sort DESC, group_name ASC, name ASC";
        } else {
            $sql .= " ORDER BY group_sort DESC, name ASC";
        }
                
        $res = $db->executeS($sql);

        return $res;
        
    }
    
    
    /**
     *  <p>Returns distinct group_name,group_id,group_sort as Balticode_Postoffice_Model_Mysql4_Office_Collection of 'balticode_postoffice/office' models</p>
     * <p>Result of this function is used to render the first select menu (county/city) for this carrier.</p>
     * <p>If no groups can be found, then this function returns boolean false.</p>
     * 
     * @param string $code carrier.external_module_name
     * @param int $addressId when supplied then only groups from the addressId country are returned
     * @return boolean|array
     */
    public function getPostOfficeGroups($code, $addressId = null) {
        $db = Db::getInstance();
        $sql = "SELECT DISTINCT group_id, group_name, group_sort FROM "._DB_PREFIX_."balticode_postoffice "
                ." WHERE remote_module_name = '{$db->escape($code)}'"
                ." ORDER BY group_sort DESC, group_name ASC"
                ;
                
        if ($addressId) {
            $address = new Address($addressId);
            if ($address->id_country) {
                $sql = "SELECT DISTINCT group_id, group_name, group_sort FROM " . _DB_PREFIX_ . "balticode_postoffice "
                        . " WHERE remote_module_name = '{$db->escape($code)}'"
                        . " AND country = '{$db->escape(Country::getIsoById($address->id_country))}'"
                        . " ORDER BY group_sort DESC, group_name ASC"
                ;
            }
        }
                
        $res = $db->executeS($sql);
        if (count($res) <= 1) {
            return false;
        }
        return $res;
    }
    
    

    /**
     * <p>Once the user selects the actual office, an AJAX callback is performed and this one inserts the selected office to the database <code>balticode_cart_shipping</code>
     * and also to the session, in order the customer would easily reach latest selected offices and the order itself could be placed.</p>
     * 
     * @param type $code
     * @param type $addressId
     * @param type $placeId
     * @param type $groupId
     */
    public function setOfficeToSession($code, $addressId, $placeId, $groupId = null) {
        /*
         * 
	`id_cart` int(11) unsigned NOT NULL,
	`id_address` int(11) unsigned NOT NULL,
	`remote_module_id` int(11) unsigned NOT NULL,
	`remote_place_id` int(11) unsigned NOT NULL,
         * ps_balticode_cart_shipping
         */
        $id_cart = Context::getContext()->cart->id;
        $db = Db::getInstance();
        $sql = "SELECT * FROM ". _DB_PREFIX_ ."balticode_cart_shipping ";
        $sql .= " WHERE id_cart = '{$db->escape($id_cart)}' ";
//        $sql .= " AND id_address = '{$db->escape($addressId)}' ";
        $oldInserts = $db->executeS($sql);
        $data = array(
            'id_cart' => $db->escape($id_cart),
            'id_address' => $db->escape($addressId),
            'remote_module_id' => $db->escape($this->idFromCode($code)),
            'remote_place_id' => $db->escape($placeId),
        );
        if (is_array(self::$_officesInSession) && isset(self::$_officesInSession[$addressId])) {
            unset(self::$_officesInSession[$addressId]);
        }
        if (count($oldInserts)) {
            //update
//            $db->update('balticode_cart_shipping', $data, " id_cart = '{$db->escape($id_cart)}' AND id_address = '{$db->escape($addressId)}' ");
            $db->update('balticode_cart_shipping', $data, " id_cart = '{$db->escape($id_cart)}' ");
        } else {
            //insert
            $db->insert('balticode_cart_shipping', $data);
        }
        
    }
    
    
    
    /**
     * <p>Returns array of selected parcel terminal names for the specified id_cart</p>
     * <p>Array keys are address ids and array values are parcel terminal names</p>
     * @param int $id_cart
     * @return array
     */
    public function getOfficesFromCart($id_cart) {
        $db = Db::getInstance();
        $sql = "SELECT * FROM " . _DB_PREFIX_ . "balticode_cart_shipping ";
        $sql .= " WHERE id_cart = '{$db->escape($id_cart)}' ";
        $officesInCart = $db->executeS($sql);
        $resultingPlaces = array();
        foreach ($officesInCart as $officeInCart) {
            $code = $this->codeFromId($officeInCart['remote_module_id']);
            $place = array(
                'group_id' => null,
            );
            $places = $this->getPostOffices($code, null, $officeInCart['remote_place_id']);
            if (count($places)) {
                $resultingPlaces[$officeInCart['id_address']] = $places[0];
            }
        }
        return $resultingPlaces;
    }
    
    /**
     * <p>Returns information about selected parcel terminal for the specified address, if any exist.</p>
     * <p>Return format is following:</p>
     * <ul>
         <li><code>code</code> - carrier method code for selected parcel terminal</li>
         <li><code>place_id</code> - selected parcel terminal remote id</li>
         <li><code>address_id</code> - address id reflected back</li>
         <li><code>group_id</code> - group_id where this terminal belongs to.</li>
     </ul>
     * <p>If no parcel terminal is found, then same element is returned, but it's values are empty strings</p>
     * @param int $addressId
     * @return array
     */
    public function getOfficeFromSession($addressId) {
        if (is_null(self::$_officesInSession)) {
            self::$_officesInSession = array();
        }
        if (isset(self::$_officesInSession[$addressId])) {
            return self::$_officesInSession[$addressId];
        }
        $id_cart = Context::getContext()->cart->id;
        $db = Db::getInstance();
        $sql = "SELECT * FROM " . _DB_PREFIX_ . "balticode_cart_shipping ";
        $sql .= " WHERE id_cart = '{$db->escape($id_cart)}' ";
        $sql .= " AND id_address = '{$db->escape($addressId)}' ";
        $oldInserts = $db->executeS($sql);
        if (count($oldInserts)) {
            $code = $this->codeFromId($oldInserts[0]['remote_module_id']);
            $place = array(
                'group_id' => null,
            );
            $places = $this->getPostOffices($code, null, $oldInserts[0]['remote_place_id']);
            if (count($places)) {
                $place = $places[0];
            }
            self::$_officesInSession[(string)$addressId] = array(
                'code' => $code,
                'place_id' => $oldInserts[0]['remote_place_id'],
                'address_id' => $oldInserts[0]['id_address'],
                'group_id' => $place['group_id'],
            );
            return self::$_officesInSession[$addressId];
            
        }
        self::$_officesInSession[(string)$addressId] = array(
                'code' => null,
                'place_id' => null,
                'address_id' => null,
                'group_id' => null,
        );
        return self::$_officesInSession[$addressId];
    }
    
    
    /**
     * <p>Registers carrier to <code>balticode_carriermodule</code> table as:</p>
     * <ul>
         <li>Carrier based on price with range from €0 to €10000</li>
         <li>Adds Carrier to every shop</li>
         <li>Adds Carrier to every available language</li>
         <li>Adds Carrier to every available zone</li>
         <li>Adds Carrier to every available delivery option</li>
     </ul>
     * @param string $code
     * @param string $class
     * @param string $trackingUrl
     * @return boolean
     */
    public function addCarrierModule($code, $class, $trackingUrl = '') {
        $db = Db::getInstance();
        /*
         * 
        `id_balticode_carriermodule` int(11) unsigned NOT NULL auto_increment,
        `carrier_code` varchar(255) NOT NULL,
        `class_name` varchar(255) NOT NULL,
        `update_time` datetime NULL,
         * 
         */
        //check if module exists
        $res = $db->getRow('select * from ' . _DB_PREFIX_ . 'balticode_carriermodule where carrier_code = \''.$db->escape($code)."'");
        if (!$res) {
            //if module does not exist, then insert it
            $data = array(
                'carrier_code' => $db->escape($code),
                'class_name' => $db->escape($class),
                'update_time' => $db->escape(date('Y-m-d H:i:s')),
            );
            $res = $db->insert('balticode_carriermodule', $data);

            if (!$res) {
                return false;
            }
        }



        $tax = 0;
        $qu = 'INSERT INTO `' . _DB_PREFIX_ .
                "carrier` (id_tax_rules_group, id_reference, name, active, shipping_handling, range_behavior, is_module, external_module_name, shipping_external, shipping_method, need_range, url)";
        $qu .= " values (0, 0, '{$code}', 1, 0, 0, 1, '{$code}', 1, 2, 1, '{$db->escape($trackingUrl)}')";

        $db->execute($qu);
        $id = $db->Insert_ID();
        if ($id == '' || $id == 0) {
            echo 'error getting next insert id';
            Tools::p($qu);
            //						print_r($qu);
            die();
        }
        //insert default price range for current carrier
        $res = $db->insert('range_price', array(
            'id_carrier' => $db->escape($id),
            'delimiter1' => 0,
            'delimiter2' => 10000,
        ));
        if (!$res) {
            return false;
        }
        $range_price_id = $db->Insert_ID();

        //check the groups and enter group id-s
        $res = $db->executeS('SELECT id_group FROM ' . _DB_PREFIX_ . 'group');
        foreach ($res as $re) {
            //insert the langs
            $qu = 'INSERT INTO `' . _DB_PREFIX_ . "carrier_group` (id_carrier, id_group)";
            $qu .= " values ($id, " . $re['id_group'] . ")";
            $db->execute($qu);
        }

        //check the langs, and get the lang id-s
        $res = $db->executeS('SELECT id_lang FROM ' . _DB_PREFIX_ . 'lang WHERE active=1');
        foreach ($res as $re) {
            //insert the langs
            $qu = 'INSERT INTO `' . _DB_PREFIX_ . "carrier_lang` (id_carrier, id_lang, delay)";
            $qu .= " values ($id, " . $re['id_lang'] . ", '" . $db->escape($code) . "')";
            $db->execute($qu);
        }

        //check the carrier_shops
        $res = $db->executeS('select id_shop from ' . _DB_PREFIX_ . 'shop where active = 1');
        foreach ($res as $re) {
            //insert the langs
            $qu = 'INSERT INTO `' . _DB_PREFIX_ . "carrier_shop` (id_carrier, id_shop)";
            $qu .= " values ($id, " . $re['id_shop'] . ")";
            $db->execute($qu);

            if ($tax > 0) {
                $qu = 'INSERT INTO `' . _DB_PREFIX_ . "carrier_tax_rules_group_shop` (id_carrier, id_tax_rules_group, id_shop)";
                $qu .= " values ($id, $tax, " . $re['id_shop'] . ")";
                $db->execute($qu);
            }
        }


        //insert the zones
        $res = $db->executeS('select id_zone from ' . _DB_PREFIX_ . 'zone where active = 1');
        foreach ($res as $re) {
            //insert the langs
            $qu = 'INSERT INTO `' . _DB_PREFIX_ . "carrier_zone` (id_carrier, id_zone)";
            $qu .= " values ($id, " . $re['id_zone'] . ")";
            $db->execute($qu);

            //insert ps_delivery
            $res = $db->insert('delivery', array(
                'id_carrier' => $db->escape($id),
                'id_range_price' => $range_price_id,
                'id_zone' => $re['id_zone'],
                'price' => 0,
            ));
            if (!$res) {
                return false;
            }
        }
        
        return true;
        
    }
    
    /**
     * <p>Removes carrier from the system, only <code>balticode_carriermodule</code> entry is kept so when reinstalling carrier, then selected parcel terminal names would be restored</p>
     * @param string $code
     * @return boolean
     */
    public function removeCarrierModule($code) {
        $db = Db::getInstance();
        $id_carrier = $this->idFromCode($code);
        $res = $db->delete('balticode_postoffice', 'remote_module_id = \''.$db->escape($id_carrier).'\'');
        if (!$res) {
            return false;
        }
        
        $db->execute('DELETE FROM `' . _DB_PREFIX_ .
                "carrier_zone` WHERE id_carrier in (select id_carrier FROM `" . _DB_PREFIX_ .
                "carrier` WHERE name like '{$code}')");
        //delete only carries, which have not been used to place orders
        $db->execute('DELETE FROM `' . _DB_PREFIX_ .
                "carrier_shop` WHERE id_carrier in (select id_carrier FROM `" . _DB_PREFIX_ .
                "carrier` WHERE name like '{$code}')");
        $db->execute('DELETE FROM `' . _DB_PREFIX_ .
                "carrier_group` WHERE id_carrier in (select id_carrier FROM `" . _DB_PREFIX_ .
                "carrier` WHERE name like '{$code}')");


        $db->execute('DELETE FROM `' . _DB_PREFIX_ .
                "carrier_lang` WHERE id_carrier in (select id_carrier FROM `" . _DB_PREFIX_ .
                "carrier` WHERE name like '{$code}' and id_carrier not in (select id_carrier from `" .
                _DB_PREFIX_ . "cart`) and id_carrier not in (select id_carrier from `" .
                _DB_PREFIX_ . "orders`))");
        $db->execute('DELETE FROM `' . _DB_PREFIX_ .
                "carrier_tax_rules_group_shop` WHERE id_carrier in (select id_carrier FROM `" . _DB_PREFIX_ .
                "carrier` WHERE name like '{$code}')");


        //and the ones which have been used to place orders, update them to "deleted=1"
        $db->execute('UPDATE `' . _DB_PREFIX_ .
                "carrier` set deleted = 1, active = 0 WHERE name like '{$code}' and deleted = 0");
        
        return true;
    }
    
    
    /**
     * <p>If cart contains addresses, which require parcel terminal to be selected (Use help of this module) then parcel terminals are validated.</p>
     * <p>If onepage checkout is active, then response is returned as json string and displayed like alert in the checkout.</p>
     * <p>When checkout is 5 steps, then on error script exits and user is redirected back to shipping method page, where get parameter <code>shipping_method_id</code> with erroneus <code>carrier.external_module_name</code> is returned</p>
     * <p>This PrestaShop hook uses following parameters:</p>
     * <ul>
         <li><code>cart</code> - Current shopping cart instance for the customer</li>
     </ul>
     * @param array $params
     * @return boolean|string
     */
    public function hookActionCarrierProcess($params) {
        //
        $cart = $params['cart'];
        
        $shipping_method = $cart->id_carrier;
        $this->l('-- select --');
        
        //detect if shipping method is registered and enabled.....
        $carrier = new Carrier($shipping_method);
        $id_address = $cart->id_address_delivery;
        $errors = array();
        if ($shipping_method && $id_address && $carrier->is_module && $carrier->external_module_name && $this->_verifyCode($carrier->external_module_name)) {
            $selected_parcel_terminal = $this->getOfficeFromSession($id_address);
            
            //clear terminal, when carrier names do not match with the one in the session.
            if ($selected_parcel_terminal['code'] != $carrier->external_module_name) {
                $selected_parcel_terminal['place_id'] = false;
            }
//            echo '<pre>'.htmlspecialchars(print_r($v, true)).'</pre>';
            
            
            //check if only one carrier instance
            if (!$selected_parcel_terminal['place_id'] && ($singleParcelTerminal = $this->_getSingleTerminal($carrier->external_module_name))) {
                if ($singleParcelTerminal) {
                    $this->setOfficeToSession($carrier->external_module_name, $id_address, $singleParcelTerminal['remote_place_id']);
                    $selected_parcel_terminal = $this->getOfficeFromSession($id_address);
                }
            }

            if (!$this->_verifySelectedTerminal($carrier->external_module_name, $selected_parcel_terminal['place_id'])) {

                //0 = standard
                //1 = onestep checkout
                if (Configuration::get('PS_ORDER_PROCESS_TYPE') === '0') {
                    $errors[] = Tools::displayError($this->l('Please select parcel terminal'));
                    Tools::redirectLink(Tools::redirectLink('index.php?controller=order&step=2&shipping_method_id='.$carrier->external_module_name));
                } else {
                    $errors[] = Tools::displayError($this->l('Please select parcel terminal'));
                    die('{"hasError" : true, "errors" : ["' . implode('\',\'', $errors) . '"]}');
                }

                return false;
            }
            $module = Module::getInstanceByName($carrier->external_module_name);
            if (!$module->active) {
                $errors[] = Tools::displayError(sprintf($this->l('Module %s is not active'), $carrier->external_module_name));
                die('{"hasError" : true, "errors" : ["'.implode('\',\'', $errors).'"]}');
                return false;
            }
            
            
        } else if (!$cart->isVirtualCart() && $cart->id_carrier == 0) {
                $errors[] = Tools::displayError(sprintf($this->l('Shipping is required for this order %s'), ''));
                die('{"hasError" : true, "errors" : ["'.implode('\',\'', $errors).'"]}');
                return false;
        }
    }
    
    
    /**
     * <p>Renders ajax select menu for the current carrier specified by template parameters.</p>
     * <p>Params are in following format:</p>
     * <pre>
     * $params = array(
     *  id_address_delivery
     *  price
     *  title
     *  logo
     *  id_address_invoice
     *  error_message
     *  is_default
     * )
     * </pre>
     * 
     * @param string $code carrier code
     * @param array $params supplied parametes
     * @param bool $shouldHide true, when carrier should not be available to the customer
     * @return string html
     */
    public function displayExtraCarrier($code, $params, $shouldHide = false) {
        
        /* @var $prestaCarrierValue CarrierCore */
        $prestaCarrierValue = $this->getCarrierFromCode($code);
        $implodedJs = 'input[name=delivery_option\\\[' . $params['id_address_delivery'] . '\\\]][value="' . $prestaCarrierValue->id . ',"]:visible';
        

        if ($shouldHide) {
            return <<<EOT
   <script type="text/javascript">
//       <![CDATA[
    (function() {
        var implodedJs = \$('{$implodedJs}');
        jQuery.each(implodedJs, function(i, val) {
            jQuery(val).parent().hide();
        });
        
            \$('div.delivery_option').removeClass('item').removeClass('alternate_item');
            \$('div.delivery_option:visible:even').addClass('item');
            \$('div.delivery_option:visible:odd').addClass('alternate_item');

    }());
//       ]>
   </script>
  
EOT;
            
        }
        
        $this->refresh($code);
        
        
        $js = '';
        $isOnepageCheckout = false;
        if (Configuration::get('PS_ORDER_PROCESS_TYPE') == '1') {
            $isOnepageCheckout = true;
            $js = 'updateCarrierSelectionAndGift();';
        }
        
        $list = '<div id="balticode_carrier_' . $code . '" style="display:inline-block;"></div>';
        
        //make the price
        $finalPrice = $params['price'];
        $carrier_tax = 0;
        if (!Tax::excludeTaxeOption()) {
            if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
                $taxAddressId = $params['id_address_invoice'];
            } else {
                $taxAddressId = $params['id_address_delivery'];
            }
            if (!Address::addressExists($taxAddressId)) {
                $taxAddressId = null;
            }
            $carrier_tax = $prestaCarrierValue->getTaxesRate(new Address((int) $taxAddressId));
            // Apply tax
            if (isset($carrier_tax)) {
                $finalPrice *= 1 + ($carrier_tax / 100);
            }
        }
        $isDefault = (isset($params['is_default']) && $params['is_default'])?true:false;
        $errorMessage = (isset($params['error_message']) && $params['error_message'])?$params['error_message']:'';
        if (!$errorMessage && Tools::getValue('shipping_method_id', '') === $code) {
            $errorMessage = $this->l('Please select parcel terminal');
        }


        $arr = array(
            'price' => round($finalPrice, 2),
            'id_address' => $params['id_address_delivery'],
            'name' => $this->l($params['title']),
            'delay' => $list,
            'img' => $params['logo'],
            'id_carrier' => '_' . $code . '',
            'isDefault' => $isDefault,
            'js' => $js,
            'opc' => $isOnepageCheckout,
        );
        
        $isSelected = false;
        
        $selectedOffice = $this->getOfficeFromSession($params['id_address_delivery']);
        $containsSelection = false;
        if ($selectedOffice['place_id'] != '' && $selectedOffice['code'] == $code) {
            $containsSelection = true;
        }
        $cart = Context::getContext()->cart;
        if ($prestaCarrierValue->id == $cart->id_carrier) {
            $isSelected = true;
        }
        
        $templateName = $this->name;
        $jsSelector = '.parent()';
        $extraJs = <<<EOT
   jQuery('#id_carrier_{$code}').attr('checked', 'checked');
   if (!window.balticode_carrier_checked) {
        jQuery('#id_carrier_{$code}').click();
        window.balticode_carrier_checked = true;
   }
EOT;
        if (substr(_PS_VERSION_, 0, 3) == "1.6") {
            $templateName = $this->name .'_16';
            $jsSelector = ".parentsUntil('tbody')";
        $extraJs = <<<EOT
   jQuery('span.checked').removeClass('checked');
   jQuery('#id_carrier_{$code}').attr('checked', 'checked');
   jQuery('#id_carrier_{$code}').parent().addClass('checked');
   if (!window.balticode_carrier_checked) {
        jQuery('#id_carrier_{$code}').click();
        window.balticode_carrier_checked = true;
   }
EOT;
            
        }
        
        $balticodeCarrierValue = $prestaCarrierValue->id . ',';



        $smarty = Context::getContext()->smarty;
        $smarty->assign(array(
            'balticode_carrier' => $arr,
            'balticode_ERROR_MESSAGE' => $errorMessage,
            'balticode_url' => str_replace('/'.Language::getIsoById(Context::getContext()->language->id).'/', '/', $this->context->link->getModuleLink($this->name, 'postoffice', array(), true)),
            'balticode_divId' => 'balticode_carrier_' . $code,
            'balticode_carrierId' => $code . '\\\\,',
            'balticode_carrierCode' => $code,
            'balticode_addressId' => $params['id_address_delivery'],
            'balticode_carrierValue' => $containsSelection ? $balticodeCarrierValue : '',
            'balticode_extraJs' => $isSelected ? $extraJs : '',
            'balticode_priceDisplay' => $carrier_tax == 0,
        ));
        
        $html = '';
        $html .= <<<JS
   <script type="text/javascript">
//       <![CDATA[
    (function() {
        var implodedJs = \$('{$implodedJs}'),
            firstItem = false;
//        console.log(implodedJs);
        jQuery.each(implodedJs, function(i, val) {
            \$(val).parent().hide();
            if (!firstItem) {
                firstItem = \$(val){$jsSelector};
//                console.log(firstItem);
            }
        });
        if (firstItem) {
            //html update for....
            \$(firstItem).html({$this->encodeToJson($this->display(__FILE__, $templateName . '.tpl'))});
            //make the alternation
                


            \$(firstItem).show();
            \$('div.delivery_option').removeClass('item').removeClass('alternate_item');
            \$('div.delivery_option:visible:even').addClass('item');
            \$('div.delivery_option:visible:odd').addClass('alternate_item');
            
        }
        


    })();
//       ]>
   </script>
JS;


        //hide old terminals
        return $html;
        
        
        
        
    }

    
    /**
     * <p>Renders information about chosen parcel terminal as HTML string.</p>
     * <p>This PrestaShop hook reads following parameters:</p>
     * <ul>
         <li><code>objOrder</code> - Order instance which should render selected parcel terminal</li>
     </ul>
     * @param array $params
     * @return string|boolean
     */
    public function hookOrderConfirmation($params) {
        if (isset($params['objOrder']) && $params['objOrder'] && Validate::isLoadedObject($params['objOrder'])) {
            $objOrderExists = true;
            $order = $params['objOrder'];
            $id_carrier = $order->id_carrier;
            $id_cart = $order->id_cart;
        } else {
            $objOrderExists = false;
        }
        if (!$objOrderExists) {
            //try to load the carrier from cart
            $id_carrier = Context::getContext()->cart->id_carrier;
            $id_cart = Context::getContext()->cart->id;
        }
        if ($id_carrier) {
            $carrier = new Carrier($id_carrier);
            if ($carrier->is_module && $carrier->external_module_name && $this->_verifyCode($carrier->external_module_name)) {
                $carrierInstance = Module::getInstanceByName($carrier->external_module_name);
//                $data = $carrierInstance->displayInfoByCart($order->id_cart);

                $offices = $this->getOfficesFromCart($id_cart);
                $terminals = array();
                foreach ($offices as $address_id => $office) {
                    $terminals[] = $carrierInstance->getAdminTerminalTitle($office);
                }
                $data = '<p>'.$this->l('Chosen parcel terminal:') . ' <b>' . implode(', ', $terminals) . '</b></p>';

                return $data;
            }
        }
        return false;
    }
    
    /**
     * If there is only one terminal in the list and it is not in the session, then it would be loaded using this function.
     * In every other case this function returns false
     * @param string $code
     * @return array|bool
     */
    protected function _getSingleTerminal($code) {
        $db = Db::getInstance();
        $sql = 'SELECT count(id_balticode_postoffice) as cnt FROM `' . _DB_PREFIX_ . 'balticode_postoffice` where `remote_module_name` = \''.$db->escape($code).'\' ';
        $res = $db->executeS($sql);
        if (is_array($res) && count($res) && $res[0]['cnt'] == '1') {
            //fetch
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'balticode_postoffice` where `remote_module_name` = \''.$db->escape($code).'\' LIMIT 1';
            $terminal = $db->executeS($sql);
            return $terminal[0];
        }
        return false;
    }
    

    /**
     * <p>Returns true if pickup point id is registered in local databse</p>
     * @param string $code shipping method code
     * @param int $terminal remote place id for the parcel terminal
     * @return boolean
     */
    protected function _verifySelectedTerminal($code, $terminal) {
        $db = Db::getInstance();
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'balticode_postoffice` where `remote_module_name` = \''.$db->escape($code).'\' and remote_place_id = \''.$db->escape($terminal).'\' LIMIT 1';
        $res = $db->executeS($sql);
        if (is_array($res) && count($res)) {
            return true;
        }
        if ($code == 'balticode_dpd_courier'){
            return true;
        }
        return false;
    }
    
    /**
     * <p>Returns carrier module id from its remote code</p>
     * @param string $code carrier code
     * @return int
     */
    public function idFromCode($code) {
        if (is_null(self::$_carrierModules)) {
            $db = Db::getInstance();
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'balticode_carriermodule`';
            $res = $db->executeS($sql);
            self::$_carrierModules = array();
            foreach ($res as $r) {
                self::$_carrierModules[$r['carrier_code']] = $r['id_balticode_carriermodule'];
            }
        }
        if (isset(self::$_carrierModules[$code])) {
            return self::$_carrierModules[$code];
        }
        return null;
    }
    
    /**
     * <p>Returns <code>carrier</code> table entry from external_module_name</p>
     * <p>Returns false, when not found</p>
     * @param string $code
     * @return array|boolean
     */
    public function getCarrierFromCode($code) {
        if (is_null(self::$_carriersByCode)) {
            self::$_carriersByCode = array();
        }
        if (isset(self::$_carriersByCode[$code])) {
            return self::$_carriersByCode[$code];
        }
        $db = Db::getInstance();
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . "carrier` where external_module_name = '{$code}' and deleted = 0 ORDER BY id_carrier DESC";
        $res = $db->executeS($sql);
        if (count($res)) {
            self::$_carriersByCode[$code] = new Carrier($res[0]['id_carrier']);
            return self::$_carriersByCode[$code];
        }
        return false;
    }

    /**
     * <p>Returns carrier_code for the selected carrier id, if it is registered with this module</p>
     * @param int $id
     * @return string
     */
    public function codeFromId($id) {
        if (is_null(self::$_carrierModules)) {
            $db = Db::getInstance();
            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'balticode_carriermodule`';
            $res = $db->executeS($sql);
            self::$_carrierModules = array();
            foreach ($res as $r) {
                self::$_carrierModules[$r['carrier_code']] = $r['id_balticode_carriermodule'];
            }
        }
        foreach (self::$_carrierModules as $code => $internalId) {
            if ($internalId == $id ) {
                return $code;
            }
        }
        return null;
    }
    
    
    
    /**
     * 
     * <p>Attempts to synchronize list of parcel terminals with remote server if update time was earlier than update interval for the current carrier.</p>
     * @param string $code carrier code
     * @param bool $byPassTimeCheck when set to true, then data is updated anyway
     * @return null
     */
    public function refresh($code, $byPassTimeCheck = false) {
        $carrier = Module::getInstanceByName($code);
        $lastUpdated = $carrier->getLastUpdated();
        $updateInterval = $carrier->getUpdateInterval();
        $date = time();
        if ($lastUpdated + ($updateInterval * 60) < $date || $byPassTimeCheck) {
            $officeList = $carrier->getOfficeList();
            $oldData = array();
            $db = Db::getInstance();

            //sql query....
            $oldDataCollection = $db->executeS("select * from " . _DB_PREFIX_ . "balticode_postoffice where remote_module_name = '" . $db->escape($code) . "'");
            $groups = array();

            foreach ($oldDataCollection as $oldDataElement) {
                $oldData[(string) $oldDataElement['remote_place_id']] = $oldDataElement;

                if ($oldDataElement['group_name'] != '' && $oldDataElement['group_id'] > 0) {
                    $groups[(string) $oldDataElement['group_id']] = $oldDataElement['group_name'];
                }
            }

            if (!is_array($officeList)) {
                $carrier->setLastUpdated($date);
                return;
            } else {
                $processedPlaceIds = array();

                foreach ($officeList as $newDataElement) {

                    if (!isset($newDataElement['group_id']) || !isset($newDataElement['group_name'])
                            || $newDataElement['group_id'] == '' || $newDataElement['group_name'] == '') {
                        $this->assignGroup($newDataElement, $groups);
                    }

                    if (!isset($newDataElement['group_sort'])) {
                        $newDataElement['group_sort'] = $carrier->getGroupSort($newDataElement['group_name']);
                    }

                    if (!isset($oldData[(string) $newDataElement['place_id']])) {

                        $oldData[(string) $newDataElement['place_id']] = $this->fromOfficeElement($newDataElement, $code);
                    } else {
                        $oldData[(string) $newDataElement['place_id']] = $this->fromOfficeElement($newDataElement, $code, $oldData[(string) $newDataElement['place_id']]);
                    }

                    $processedPlaceIds[(string) $newDataElement['place_id']] = (string) $newDataElement['place_id'];
                }
                foreach ($oldData as $placeId => $oldDataElement) {
                    if (!isset($processedPlaceIds[(string) $placeId])) {
                        //delete oldDataElement
                        $db->execute("delete from " . _DB_PREFIX_ . "balticode_postoffice where id_balticode_postoffice = " . $db->escape($oldDataElement['id_balticode_postoffice']));
                    } else {
                        //save OldDataElement
                        if (!isset($oldDataElement['id_balticode_postoffice'])) {
                            //insert
                            $dataToInsert = array();
                            $oldDataElement['created_time'] = date("Y-m-d H:i:s");
                            $oldDataElement['update_time'] = date("Y-m-d H:i:s");
                            foreach ($oldDataElement as $key => $value) {
                                $dataToInsert[$key] = "'" . $db->escape($value) . "'";
                            }
                            $db->execute("insert into " . _DB_PREFIX_ . "balticode_postoffice (" . implode(',', array_keys($dataToInsert)) . ") VALUES (" . implode(',', $dataToInsert) . ");");
                            
                        } else {
                            //update
                            $dataToInsert = array();
                            $oldDataElement['update_time'] = date("Y-m-d H:i:s");
                            foreach ($oldDataElement as $key => $value) {
                                $dataToInsert[$key] = $key . " = '" . $db->escape($value) . "'";
                            }
                            $db->execute("update " . _DB_PREFIX_ . "balticode_postoffice set " . implode(', ', $dataToInsert) . " where id_balticode_postoffice = " . $db->escape($oldDataElement['id_balticode_postoffice']));
//                            exit;
                        }
                    }
                }
                $carrier->setLastUpdated($date);
            }
        }
    }
    
    /**
     * <p>Synchronizes remote pickup point with old data for specified module code.</p>
     * @param array $officeElement
     * @param string $moduleCode
     * @param array $oldData
     * @return array
     * @throws Exception
     */
    protected function fromOfficeElement($officeElement, $moduleCode, $oldData = null) {
        $db = DB::getInstance();
        $newData = array();
        if (is_array($oldData)) {
            if (isset($oldData['id_balticode_postoffice'])) {
                $newData['id_balticode_postoffice'] = $oldData['id_balticode_postoffice'];
                
            }
        }
        if ($moduleCode != '') {
            //load the remote module
            
            $remoteModule = $db->executeS("select * from ". _DB_PREFIX_. "balticode_carriermodule where carrier_code = '".$db->escape($moduleCode)."'");
            
            if (!count($remoteModule)) {
                throw new Exception('Carrier could not be detected');
            }
            $newData['remote_module_id'] = $remoteModule[0]['id_balticode_carriermodule'];
            $newData['remote_module_name'] = $remoteModule[0]['carrier_code'];
            
            
        } else {
            if (!is_array($oldData) || !isset($oldData['remote_module_id']) 
                    ||!isset($oldData['remote_module_name'])) {
                throw new Exception('Remote module ID and remote module name have to be defined');
            }
            $newData['id'] = $oldData['id'];
            $newData['remote_module_id'] = $oldData['remote_module_id'];
            $newData['remote_module_name'] = $oldData['remote_module_name'];
        }
        
        $newData['remote_place_id'] = $officeElement['place_id'];
        $newData['name'] = $officeElement['name'];
        

        if (isset($officeElement['servicing_place_id'])) {
            $newData['remote_servicing_place_id'] = $officeElement['servicing_place_id'];
        }
        if (isset($officeElement['city'])) {
            $newData['city'] = $officeElement['city'];
        }
        if (isset($officeElement['county'])) {
            $newData['county'] = $officeElement['county'];
        }
        if (isset($officeElement['zip'])) {
            $newData['zip_code'] = $officeElement['zip'];
        }
        if (isset($officeElement['country'])) {
            $newData['country'] = $officeElement['country'];
        }
        if (isset($officeElement['description'])) {
            $newData['description'] = $officeElement['description'];
        }
        if (isset($officeElement['group_id']) && isset($officeElement['group_name'])) {
            $newData['group_id'] = $officeElement['group_id'];
            $newData['group_name'] = $officeElement['group_name'];
            if (isset($officeElement['group_sort'])) {
                $newData['group_sort'] = $officeElement['group_sort'];
            }
        }

        if (isset($officeElement['extra']) && is_array($officeElement['extra'])) {
            $newData['cached_attributes'] = serialize($officeElement['extra']);
        }
        



        return $newData;
    }
    
    /**
     * 
     * <p>Keeps track of generated group_id-s based on the group_names, making sure that each group has it's unique id.</p>
     * @param array $dataElement
     * @param type $groups
     */
    protected function assignGroup(array &$dataElement, &$groups) {
        $groupNames = array();
        if (isset($dataElement['county']) && !empty($dataElement['county'])) {
            $groupNames[] = $dataElement['county'];
        }
        if (isset($dataElement['city']) && !empty($dataElement['city'])) {
            $groupNames[] = $dataElement['city'];
        }
        if (count($groupNames) > 0) {
            $groupName = implode('/', $groupNames);
            if (in_array($groupName, $groups)) {
                $dataElement['group_name'] = $groupName;
                $dataElement['group_id'] = array_search($groupName, $groups);
            } else {
                $new_id = 1;
                if (count($groups) > 0) {
                    $new_id = max(array_keys($groups)) + 1;
                    
                }
                $groups[(string)$new_id] = $groupName;
                $dataElement['group_name'] = $groupName;
                $dataElement['group_id'] = array_search($groupName, $groups);
            }
        }
    }

    /**
     * <p>Makes sure that carrier is registered with this module</p>
     * @param string $code carrier code
     * @return boolean
     */
    protected function _verifyCode($code) {
        $db = Db::getInstance();
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'balticode_carriermodule` where `carrier_code` = \''.$db->escape($code).'\' LIMIT 1';
        $res = $db->executeS($sql);
        if (!$res) {
            return false;
        }
        if (!count($res)) {
            return false;
        }
        return true;
    }
    
    
    
    /**
     * <p>Drops all the tables which were created during the install process</p>
     * @return bool
     */
    private function _dropTables() {
		$sql = 'DROP TABLE
			`'._DB_PREFIX_.'balticode_carriermodule`,
			`'._DB_PREFIX_.'balticode_postoffice`,
			`'._DB_PREFIX_.'balticode_cart_shipping`
			';

		return Db::getInstance()->execute($sql);
        
    }
    
    private function _createSelectedShippingOptionInCartTable() {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'balticode_cart_shipping`(
	`id_balticode_cart_shipping` int(11) unsigned NOT NULL auto_increment,
	`id_cart` int(11) unsigned NOT NULL,
	`id_address` int(11) unsigned NOT NULL,
	`remote_module_id` int(11) unsigned NOT NULL,
	`remote_place_id` int(11) unsigned NOT NULL,
	PRIMARY KEY (`id_balticode_cart_shipping`)
	) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
        $result = Db::getInstance()->execute($sql);
        if ($result === false) {
            return false;
        }
        $indexSql = "ALTER TABLE `"._DB_PREFIX_."balticode_cart_shipping` ADD INDEX ( `id_cart` , `id_address` ) ;";
        $indexSqlResult = Db::getInstance()->execute($indexSql);
        return $indexSqlResult;
    }

    private function _createCarrierModuleTable() {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'balticode_carriermodule`(
        `id_balticode_carriermodule` int(11) unsigned NOT NULL auto_increment,
        `carrier_code` varchar(255) NOT NULL,
        `class_name` varchar(255) NOT NULL,
        `update_time` datetime NULL,
	PRIMARY KEY (`id_balticode_carriermodule`)
	) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
        return Db::getInstance()->execute($sql);
        
    }
    
    private function _createPostofficeTable() {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'balticode_postoffice`(
        `id_balticode_postoffice` int(11) unsigned NOT NULL auto_increment,
        `remote_module_id` int(11) unsigned NOT NULL,
        `remote_module_name` varchar(255) NOT NULL,
        `remote_place_id` int(11) unsigned NOT NULL,
        `remote_servicing_place_id` int(11) unsigned NULL,

        `name` varchar(255) NOT NULL,
        `city` varchar(255) NULL,
        `county` varchar(255) NULL,
        `zip_code` varchar(255) NULL,
        `country` varchar(2) NULL,
        `description` text NULL,

        `group_id` int(11) unsigned NULL,
        `group_name` varchar(255) NULL,
        `group_sort` int(11) unsigned NULL,

        `local_carrier_id` int(11) unsigned NULL,
    
        `created_time` datetime NULL,
        `update_time` datetime NULL,
        `cached_attributes` text NULL,
	PRIMARY KEY (`id_balticode_postoffice`)
	) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
        return Db::getInstance()->execute($sql);
        
    }
    
    private function _createIndexes() {
        $sql = '    ALTER TABLE `' . _DB_PREFIX_ . 'balticode_postoffice` ADD UNIQUE (
		`remote_module_id`,
		`remote_place_id`
	);
        ';
        return Db::getInstance()->execute($sql);
        
    }
    
    /**
     * <p>Returns HTML select list based on assoc array inputs and marks <code>$selected</code> as active option</p>
     * @param array $array
     * @param mixed $selected
     * @return string html select list
     */
    public function getOptionList($array, $selected) {
        $r = '';
        foreach ($array as $k => $v) {
            $r .= '<option value="' . $k . '"';
            if ($k == $selected) {
                $r .= ' selected="selected"';
            }
            $r .= '>' . htmlspecialchars($v) . '</option>' . "\r\n";
        }
        return $r;
    }

    /**
     * <p>Returns HTML select[multiple=multiple] list based on assoc array inputs and marks <code>$selected</code> as active option</p>
     * @param array $array
     * @param mixed $selected
     * @return string html select list
     */
    public function getMultiselectList($array, $selected) {
        if (is_string($selected)) {
            $selectedValues = explode(',', $selected);
        } else {
            $selectedValues = $selected;
        }
        $r = '';
        if (!is_array($selectedValues)) {
            $selectedValues = array();
        }
        foreach ($array as $k => $v) {
            $r .= '<option value="' . $k . '"';
            if (in_array($k, $selectedValues)) {
                $r .= ' selected="selected"';
            }
            $r .= '>' . htmlspecialchars($v) . '</option>' . "\r\n";
        }
        return $r;
    }
    
    
    /**
     * <p>Returns list of alphabetically sorted countries as assoc array where array keys are country ISO codes and values are localized country names</p>
     * @param bool $emptyOption -  adds text to the first place of countries list designated as empty option
     * @return array
     */
    public function getCountriesAsOptions($emptyOption = false) {
        $countries = Country::getCountries($this->context->language->id);
        $countriesInSelect = array();
        if ($emptyOption) {
            $countriesInSelect[''] = $emptyOption;
        }
        foreach ($countries as $country) {
            $countriesInSelect[$country['iso_code']] = $country['name'];
        }
        asort($countriesInSelect, SORT_STRING);
        return $countriesInSelect;
    }
    
    /**
     * <p>Returns all the available tax groups as assoc array where array keys are tax_ids and values are tax names</p>
     * @return array
     */
    public function getTaxes() {
        $qu = "SELECT p.id_tax_rules_group as id_tax, p.name FROM `" . _DB_PREFIX_ . "tax_rules_group` p WHERE p.active = 1";

        $res = Db::getInstance()->executeS($qu);
        $taxes = array('0' => $this->l('No Tax'));
        foreach ($res as $r) {
            $taxes[$r['id_tax']] = $r['name'];
        }
        return $taxes;
    }
    
    /**
     * <p>Returns assoc array of client groups where array keys are group_ids and values are client group names</p>
     * @return array
     */
    public function getClientGroups() {
        if (self::$clientGroups == null) {
            //fetch the client groups from database
            $id_lang = $this->context->language->id;
            $qu = "SELECT g.id_group, gl.name FROM `" . _DB_PREFIX_ . "group` g,`" . _DB_PREFIX_ .
                    "group_lang` gl  WHERE g.id_group = gl.id_group and gl.id_lang = " .
                    DB::getInstance()->escape($id_lang);
            $res = Db::getInstance()->ExecuteS($qu);
            self::$clientGroups = array();
            foreach ($res as $r) {
                self::$clientGroups[$r['id_group']] = $r['name'];
            }
        }
        return self::$clientGroups;
    }
    
    /**
     * <p><code>json_encode</code> wrapper for using in <code>heredoc</code> syntax</p>
     * @param mixed $input
     * @return string
     */
    public function encodeToJson($input) {
        return json_encode($input);
    }
    
    /**
     * <p>Wraps input string with div.conf+confirm html string</p>
     * @param string $message
     * @return string
     */
    public function addSuccess($message) {
        return '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="' .
                $this->l('ok') . '" /> ' . $message . '</div>';
    }
    
    
    /**
     * <p>Attempts to fetch data from order that is stored by carriers extending Balticode_Postoffice_Model_Carrier_Abstract</p>
     * <p>Data is stored in not visible on front order comment base64 encoded form and starting with specified prefix.</p>
     * <p>If matching comment is found, it is decoded and returned as assoc array</p>
     * @param OrderCore $order instance to look up the data for
     * @param string $prefix unique string prefix order comment should start with.
     * @return array
     */
    public function getDataFromOrder($order, $prefix) {
        $db = Db::getInstance();
        $orderComments = $db->executeS("SELECT * FROM `"._DB_PREFIX_."message` where id_order = {$db->escape($order->id)}");
        
        
        $orderData = array();
        foreach ($orderComments as $statusHistory) {
//            echo '<pre>'.htmlspecialchars(print_r($statusHistory, true)).'</pre>';
            /* @var $statusHistory Mage_Sales_Model_Order_Status_History */
            if ($statusHistory['message'] && $statusHistory['private']) {
                if ($this->_commentContainsValidData($statusHistory['message'], $prefix)) {
                    $orderData = @json_decode(@gzuncompress(@base64_decode($this->_getFromComment($statusHistory['message'], $prefix))), true);
                    if (!is_array($orderData)) {
                        //unserialize error on recognized pattern, should throw error or at least log
                        $orderData = array();
                    }
                }
            }
            
            
        }
        return $orderData;
        
    }
    
    
    
    /**
     * <p>Stores extra data for specified order in single order comment, which will start with specified prefix.</p>
     * <p>If no matching order comment is found, then it is created automatically, otherwise old one is updated.</p>
     * <p>Order comment is stored using following procedure:</p>
     * <ul>
         <li>If old data is found, then it is merged with new data</li>
         <li>Data is json encoded and after that gzcompressed</li>
         <li>Now it is base64 encoded and divided into 40 char long lines and prefixed with $prefix</li>
         <li>Result is stored to one of the comments contained within the order.</li>
     </ul>
     * @param OrderCore $order Magento order instance to set up the data for
     * @param array $data
     * @param string $prefix
     * @return array
     */
    public function setDataToOrder($order, array $data, $prefix) {
        $db = Db::getInstance();
        $oldOrderData = $this->getDataFromOrder($order, $prefix);
        if (isset($data['comment_id'])) {
            unset($data['comment_id']);
        }
        if (count($oldOrderData) && isset($oldOrderData['comment_id'])) {
            //we have old data
            $history = $db->getRow("SELECT * FROM `"._DB_PREFIX_."message` where id_message = {$db->escape($oldOrderData['comment_id'])}");
            
            if ($history && $history['id_message']) {
                foreach ($data as $k => $v) {
                    $oldOrderData[$k] = $v;
                }
                $history['message'] = $this->_getCommentFromData($oldOrderData, $prefix);
                $db->update('message', $history, "id_message = {$db->escape($oldOrderData['comment_id'])}");
                
            }
            //comment id for example.....
        } else {
            //we do not have old data, so add new comment
            //set the id also
            $history = array(
                'id_order' => $order->id,
                'private' => 1,
                'id_customer' => $order->id_customer,
                'date_add' => date('Y-m-d H:i:s'),
                'message' => $db->escape($this->_getCommentFromData($data, $prefix)),
            );

            $db->insert('message', $history);
            $commentId = $db->Insert_ID();
            
            $data['comment_id'] = $commentId;
            $history['message'] = $db->escape($this->_getCommentFromData($data, $prefix));
            $db->update('message', $history, "id_message = {$db->escape($data['comment_id'])}");
        }



        return $history;
    }
    
    
    
    protected function _getCommentFromData($data, $prefix) {
        return $prefix ."\n". chunk_split(base64_encode(gzcompress(json_encode($data))), 40, "\n");
    }
    
    protected function _getFromComment($comment, $prefix) {
        return str_replace($prefix, '', str_replace("\n", '', $comment));
    }


    
    protected function _commentContainsValidData($comment, $prefix) {
        //TODO: refactor to something better
        return strpos($comment, $prefix) === 0 && strlen($comment) > strlen($prefix);
    }


    
    /**
     * <p>Returns new or cached class instance by prestashop module name and helper name</p>
     * <p>How to build helper class?</p>
     * <ul>
         <li>Create PHP file with name $name.php</li>
         <li>Give it class name asi $prefix_$name</li>
         <li>Place given file in the modules root folder</li>
     </ul>
     * @param string $name requested helper name
     * @param type $prefix PrestaShop  module name, where helper should belong.
     * @return false|mixed  false, when helper could not be found. Class instance otherwise.
     */
    public function helper($name, $prefix) {
        //check if instance exists
        //auto load class

        if (!isset(self::$_helpers[$name])) {
            $helper = false;
            if (file_exists(_PS_MODULE_DIR_ . $prefix . '/' . $name . '.php')) {
                require_once(_PS_MODULE_DIR_ . $prefix . '/' . $name . '.php');
                $executorClass = $prefix . '_' . $name;

                //if after require class does not exist, then exit
                if (!class_exists($executorClass, false)) {
                    exit('Class could not be loaded');
                }

                $helper = new $executorClass();
            }
            self::$_helpers[$name] = $helper;
        }

        return self::$_helpers[$name];
    }

}

