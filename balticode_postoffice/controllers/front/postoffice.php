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
 * Description of postoffice
 *
 * @author Sarunas Narkevicius
 */
class Balticode_PostofficePostofficeModuleFrontController extends ModuleFrontController {
    public $ssl = true;
    
    
    /**
     *  <p>Returns the contents for the selected carriers.</p>
     * <p>Mainly it returns two select menus, where first select menu contains all the groups and
     * second select menu contains actual offices, which belong to the selected group</p>
     * @throws Exception when request is not POST request
     */
    public function initContent() {
        try {
            if (isset($_POST) && count($_POST)) {

                $post = $_POST;
                $url = str_replace('/'.Language::getIsoById(Context::getContext()->language->id).'/', '/', $this->context->link->getModuleLink('balticode_postoffice', 'postoffice', array(), true));
                $addressId = $post['address_id'];
                $carrierCode = $post['carrier_code'];
                $carrierId = $post['carrier_id'];
                $divId = $post['div_id'];
                $groupId = isset($post['group_id']) ? ((int) $post['group_id']) : 0;
                $placeId = isset($post['place_id']) ? ((int) $post['place_id']) : 0;
                $shippingModel = Module::getInstanceByName($carrierCode);
                if (!Validate::isLoadedObject($shippingModel)) {
                    throw new Exception(sprintf('Shipping model not found for code %s', $carrierCode));
                }
            
                
                if (!$shippingModel->active) {
                    throw new Exception('Invalid Shipping method');
                }
                /* @var $handlerModel Balticode_Postoffice */
                $handlerModel = Module::getInstanceByName('balticode_postoffice');
                
                if (!Validate::isLoadedObject($handlerModel)) {
                    throw new Exception(sprintf('Handler model not found for code %s', 'balticode_postoffice'));
                }
                
                
                if (!$handlerModel->idFromCode($carrierCode)) {
                    throw new Exception('Invalid Shipping method');
                }
                $handlerModel = clone $handlerModel;
                $handlerModel->setShippingModel($shippingModel);
                
                if ($placeId > 0) {
                    $places = $handlerModel->getPostOffices($carrierCode, null, $placeId, null);
                    $place = isset($places[0])?$places[0]:false;
                    if ($place) {
                        $handlerModel->setOfficeToSession($carrierCode, $addressId, $place['remote_place_id'], $groupId);
                        echo 'true';
                        exit;
                    } else {
                        echo 'false';
                        exit;
                    }
                }

                $selectedOffice = $handlerModel->getOfficeFromSession($addressId);

                $groups = $handlerModel->getPostOfficeGroups($carrierCode, $addressId);
                $html = '';
                if ($shippingModel->getConfigData('DIS_FIRST') == 'yes') {
                    $groups = false;
                }
                if (isset($shippingModel->text) && $shippingModel->text) {
                    $html .= '<p class="balticode_shipping_method_title">' . htmlspecialchars($shippingModel->text) . '</p>';
                } else {
                    $title = $shippingModel->getConfigData('TITLE');
                    $finalTitle = $title;
                    if ($this->_isSerialized($title)) {
                        $title = @unserialize($title);
                        if (is_array($title)) {
                            $finalTitle = isset($title[$this->context->language->id]) ? $title[$this->context->language->id] : $title[0];
                        }
                    }
                    $html .= '<p class="balticode_shipping_method_title">' . htmlspecialchars($finalTitle) . '</p>';
                }

                if ($groups) {
                    $groupSelectWidth = (int)$shippingModel->getConfigData('GR_WIDTH');
                    $style = '';
                    if ($groupSelectWidth > 0) {
                        $style = ' style="width:'.$groupSelectWidth.'px"';
                    }
                    $html .= '<select onclick="return false;" '.$style.' name="' . $carrierCode . '_select_group" onchange="$.ajax(\'' . $url . '\',{\'type\':\'POST\',data:{carrier_id:\'' . $carrierId . '\',carrier_code:\'' . $carrierCode . '\',div_id:\'' . $divId . '\',address_id:\'' . $addressId . '\',group_id: $(this).val()},success:function(a){$(\'#' . $divId . '\').html(a)}});">';
                    $html .= '<option value="">';
                    $html .= htmlspecialchars($handlerModel->l('-- select --'));
                    $html .= '</option>';

                    foreach ($groups as $group) {
                        $html .= '<option value="' . $group['group_id'] . '"';
                        if ($groupId > 0 && $groupId == $group['group_id']) {
                            $html .= ' selected="selected"';
                        }
                        if ($groupId <= 0 && $selectedOffice['code'] == $carrierCode
                                && $selectedOffice['group_id'] && $group['group_id'] == $selectedOffice['group_id']) {
                            $html .= ' selected="selected"';
                            $groupId = $selectedOffice['group_id'];
                        }
                        $html .= '>';
                        $html .= $shippingModel->getGroupTitle($group);
                        $html .= '</option>';
                    }
                    $html .= '</select>';
                }

                //get the group values
                if ($groupId > 0 || $groups === false) {
                    $terminals = array();
                    if ($groups !== false) {
                        $terminals = $handlerModel->getPostOffices($carrierCode, $groupId, null, $addressId);
                    } else {
                        $terminals = $handlerModel->getPostOffices($carrierCode, null, null, $addressId);
                    }
                    $officeSelectWidth = (int)$shippingModel->getConfigData('OF_WIDTH');
                    $style = '';
                    if ($officeSelectWidth > 0) {
                        $style = ' style="width:'.$officeSelectWidth.'px"';
                    }
                    $carrierIde = 'id_carrier_'.$carrierCode;
                    $prestaShopCarrierCode = $handlerModel->getCarrierFromCode($carrierCode);
                    $terminalsCount = count($terminals);

                    if ($terminalsCount === 0 && $groups === false) {
                        $terminal = array_shift($terminals);
                        $html .= '<span>';
                          $html .= $shippingModel->getTerminalTitle($terminal);
                        $html .= '</span>';

                            $html .= <<<HTML
   <script type="text/javascript">
       // <![CDATA[
       jQuery('input[id=\'{$carrierIde}\']').val('{$prestaShopCarrierCode->id},'); 
       jQuery('input[id={$carrierIde}]').removeAttr('disabled').removeAttr('title');
       // ]]>
   </script>
HTML;
                    } else {
                        $html .= '<select onclick="return false;" ' . $style . ' name="' . $carrierCode . '_select_office"  onchange="var sel = $(this); $.ajax(\'' . $url . '\',{\'type\':\'POST\',data:{carrier_id:\'' . $carrierId . '\',carrier_code:\'' . $carrierCode . '\',div_id:\'' . $divId . '\',address_id:\'' . $addressId . '\',place_id: sel.val()},success:function(a){   if (a == \'true\') {   $(\'input[id=\\\'' . $carrierIde . '\\\']\').val(\'' . $prestaShopCarrierCode->id . ',\'); $(\'input[id=' . $carrierIde . ']\').attr(\'checked\',\'checked\').removeAttr(\'disabled\').change(); }}});">';
                        $html .= '<option value="">';
                        $html .= htmlspecialchars($handlerModel->l('-- select --'));
                        $html .= '</option>';

                        $optionsHtml = '';
                        $previousGroup = false;
                        $optGroupHtml = '';
                        $groupCount = 0;

                        foreach ($terminals as $terminal) {
                            if ($shippingModel->getGroupTitle($terminal) != $previousGroup && $shippingModel->getConfigData('DIS_GR_TITLE') != 'yes') {
                                if ($previousGroup != false) {
                                    $optionsHtml .= '</optgroup>';
                                    $optionsHtml .= '<optgroup label="' . $shippingModel->getGroupTitle($terminal) . '">';
                                } else {
                                    $optGroupHtml .= '<optgroup label="' . $shippingModel->getGroupTitle($terminal) . '">';
                                }
                                $groupCount++;
                            }
                            $optionsHtml .= '<option value="' . $terminal['remote_place_id'] . '"';
                            if ($selectedOffice['code'] == $carrierCode && $selectedOffice['place_id'] == $terminal['remote_place_id']) {
                                $optionsHtml .= ' selected="selected"';
                            }
                            $optionsHtml .= '>';
                            $optionsHtml .= $shippingModel->getTerminalTitle($terminal);
                            $optionsHtml .= '</option>';

                            $previousGroup = $shippingModel->getGroupTitle($terminal);
                        }
                        if ($groupCount > 1) {
                            $html .= $optGroupHtml . $optionsHtml . '</optgroup>';
                        } else {
                            $html .= $optionsHtml;
                        }

                        $html .= '</select>';
                    }
                }

                echo $html;
            } else {
                throw new Exception('Invalid request method');
            }
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal error');
            header('Status: 500 Internal error');
//            echo $e->getMessage();
            throw $e;
        }
        exit;
    }
    
    protected function _isSerialized($input) {
        return preg_match('/^([adObis]):/', $input);
    }
    
    
    
}

