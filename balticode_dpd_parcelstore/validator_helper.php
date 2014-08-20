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
 * <p>Each validation function returns true or string to be translated with error message.</p>
 * <p>Arguments:</p>
 * <ul>
     <li><code>$field_name</code> - field name that needs to be validated from <code>$data</code> POST array</li>
     <li><code>$data</code> - Possibly $_POST of $_REQUEST, where data to be validated is looked up</li>
     <li><code>$validateIfs</code> - assoc array of rules, when this validation should be triggered</li>
     <li><code>$formFieldData</code> - information how to render the form element.</li>
 </ul>
 * @see balticode_dpd_parcelstore_html_helper
 *
 * @author Sarunas Narkevicius
 */
class balticode_dpd_parcelstore_validator_helper {
    
    
    /**
     * <p>Returns true if entry is not blank</p>
     * @param type $field_name field name, that needs to be validated
     * @param array $data post or request data
     * @param array $validateIfs validation if rules
     * @param array $formFieldData supplied form field data
     * @return string|boolean
     * @see balticode_dpd_parcelstore_validator_helper
     */
    public function required_entry($field_name, array $data, array $validateIfs, array $formFieldData) {
        if ($this->_shouldValidate($field_name, $data, $validateIfs)) {
            $validationResult = isset($data[$field_name]) && trim($data[$field_name]) != '';
            if (!$validationResult) {
                return $this->l('%s required entry');
            }
            return true;
        }
        return true;
    }
    
    
    /**
     * <p>Returns true if entry is integer of float number</p>
     * @param type $field_name field name, that needs to be validated
     * @param array $data post or request data
     * @param array $validateIfs validation if rules
     * @param array $formFieldData supplied form field data
     * @return string|boolean
     * @see balticode_dpd_parcelstore_validator_helper
     */
    public function validate_number($field_name, array $data, array $validateIfs, array $formFieldData) {
        if ($this->_shouldValidate($field_name, $data, $validateIfs)) {
            $validationResult = (!isset($data[$field_name]) || $data[$field_name] == '') || isset($data[$field_name]) && is_numeric($data[$field_name]);
            if (!$validationResult) {
                return $this->l('%s should be number');
            }
            return true;
        }
        return true;
    }
    
    /**
     * <p>Returns true if all characters are numbers between 0-9</p>
     * @param type $field_name field name, that needs to be validated
     * @param array $data post or request data
     * @param array $validateIfs validation if rules
     * @param array $formFieldData supplied form field data
     * @return string|boolean
     * @see balticode_dpd_parcelstore_validator_helper
     */
    public function validate_digit($field_name, array $data, array $validateIfs, array $formFieldData) {
        if ($this->_shouldValidate($field_name, $data, $validateIfs)) {
            $validationResult = (!isset($data[$field_name]) || $data[$field_name] == '') || (isset($data[$field_name]) && ctype_digit($data[$field_name]));
            if (!$validationResult) {
                return $this->l('%s should be number');
            }
            return true;
        }
        return true;
    }
    
    /**
     * <p>Returns true if we are dealing with select/multiselect fields and submitted value matches options</p>
     * @param type $field_name field name, that needs to be validated
     * @param array $data post or request data
     * @param array $validateIfs validation if rules
     * @param array $formFieldData supplied form field data
     * @return string|boolean
     * @see balticode_dpd_parcelstore_validator_helper
     */
    public function validate_select($field_name, array $data, array $validateIfs, array $formFieldData) {
        if ($this->_shouldValidate($field_name, $data, $validateIfs)) {
            if (!in_array($formFieldData['type'], array('select', 'multiselect'))) {
                //perform validation only on select and multiselect fields
                return true;
            }
            //if possible options are not set, then validation failed.
            if (!isset($formFieldData['options'])) {
                return $this->l('%s has no possible options defined');
            }
            $valuesToValidate = isset($data[$field_name])?$data[$field_name]:array();
            if (!is_array($valuesToValidate) && $formFieldData['type'] != 'multiselect') {
                //convert single select to array if required
                $valuesToValidate = array($valuesToValidate);
            }
            
                
            if ($formFieldData['type'] == 'select') {
                if (count($valuesToValidate) > 1) {
                    //regular select menu can only have one option
                    return $this->l('%s can only be one of possible options');
                }
            }
            foreach ($valuesToValidate as $valueToValidate) {
                $validationResult = in_array($valueToValidate, array_keys($formFieldData['options']));
                if (!$validationResult) {
                    //return error if any of the selected options does not exist in array
                    return $this->l('%s can be only be the options from the select');
                }
            }
            return true;
        }
        return true;
        
    }
    
    /**
     * <p>Returns true if input is not empty and validates value as e-mail</p>
     * @param type $field_name field name, that needs to be validated
     * @param array $data post or request data
     * @param array $validateIfs validation if rules
     * @param array $formFieldData supplied form field data
     * @return string|boolean
     * @see balticode_dpd_parcelstore_validator_helper
     */
    public function validate_email($field_name, array $data, array $validateIfs, array $formFieldData) {
        if ($this->_shouldValidate($field_name, $data, $validateIfs)) {
            $validationResult = (!isset($data[$field_name]) || $data[$field_name] == '') || (isset($data[$field_name]) && filter_var($data[$field_name], FILTER_VALIDATE_EMAIL));
            if (!$validationResult) {
                return $this->l('%s should be e-mail');
            }
            return true;
        }
        return true;
    }
    
    /**
     * <p>Returns true if all the fields in price-definitions-based-on-country validate correctly</p>
     * @param type $field_name field name, that needs to be validated
     * @param array $data post or request data
     * @param array $validateIfs validation if rules
     * @param array $formFieldData supplied form field data
     * @return string|boolean
     * @see balticode_dpd_parcelstore_validator_helper
     */
    public function validate_handling_fee_country($field_name, array $data, array $validateIfs, array $formFieldData) {
        if ($this->_shouldValidate($field_name, $data, $validateIfs)) {
            $initialValidationResult = (!isset($data[$field_name]) || $data[$field_name] == '');
            if ($initialValidationResult) {
                //empty field, exit validation routine
                return true;
            }
            if (is_array($data[$field_name])) {
                $subValidationRoutine = array(
                    'base_price' => array('required_entry', 'validate_number'),
                    'kg_price' => array('required_entry', 'validate_number'),
                    'free_shipping_from' => array('validate_number'),
                );
                foreach ($data[$field_name] as $validationDataSet) {
                    //country_id
                    //base_price
                    //kg_price
                    //free_shipping_from
                    foreach ($subValidationRoutine as $subFieldName => $subValidations) {
                        foreach ($subValidations as $subValidation) {
                            $subValidationResult = $this->validate($subValidation, $subFieldName, $validationDataSet, array(), $formFieldData);
                            if (is_string($subValidationResult)) {
                                return $subValidationResult;
                            }
                        }
                    }
                }
            }
            //all passed, return true
            return true;
        }
        return true;
    }

    
    /**
     * <p>Wrapper class for calling out validation methods by it's name.</p>
     * @param string $function validation function to call.
     * @param string $field_name field name, that needs to be validated
     * @param array $data post or request data
     * @param array $validateIfs validation if rules
     * @param array $formFieldData supplied form field data
     * @return string|boolean
     * @see balticode_dpd_parcelstore_validator_helper
     */
    public function validate($function, $field_name, array $data, array $validateIfs, array $formFieldData) {
        return $this->$function($field_name, $data, $validateIfs, $formFieldData);
    }
    
    /**
     * <p>Determines if current field needs to be validated based on the <code>validate-if</code> rules</p>
     * @param string $field_name form field name
     * @param array $data form submitted data
     * @param array $validateIfs array of validation condition rules
     * @return boolean
     */
    private function _shouldValidate($field_name, array $data, array $validateIfs) {
        $shouldValidate = false;
        if (count($validateIfs)) {
            foreach ($validateIfs as $fieldToCheck => $valueToMatch) {
                $fieldToCheck = strtoupper($fieldToCheck);
                if (isset($data[$fieldToCheck]) && $data[$fieldToCheck] === $valueToMatch) {
                    $shouldValidate = true;
                    break;
                }
            }
            
        } else {
            $shouldValidate = true;
        }
        return $shouldValidate;
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
