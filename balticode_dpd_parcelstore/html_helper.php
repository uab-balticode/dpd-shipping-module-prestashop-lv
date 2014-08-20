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
 * <p>Allows to create label and input combinations by <code>$_html_template</code> where each <code>get*Html</code> function creates exactly one input row.</p>
 * <p>each <code>get*Html</code> function takes following arguments:</p>
 * <ul>
  <li><code>$fieldName</code> - name attribute for designated input element</li>
  <li><code>$fieldData</code> - assoc array containing data how to build the input element</li>
  <li><code>$value</code> - if this field needs to set a value, then supply it here. Multiselects accept comma separated string values</li>
  </ul>
 * <p><code>$fieldData</code> is in following format:</p>
 * <ul>
  <li>
  <pre>
  'title' =&gt; label attribute for the form element,
  'type' =&gt; text,textarea,select,multiselect,password are allowed field types.
  'description' =&gt; if this field is filled, then it is displayed next to input,
  'default' =&gt; default value for the current field,
  'css' =&gt; form field elements style attribute,
  'validate-if' =&gt; validation can be invoked based on conditions defined in assoc array('form-field-name' => 'expected-value', 'another-form-field-name' => 'another-value'), which are processed as <b>validate-if-any</b> is true.
  'validate' =&gt; array of validation routine names,
  'options' =&gt; assoc array of select,multiselect options,
 * 
 *  </pre>
  </li>
  </ul>
 * <p>When using methods, where <code>smarty</code> is required, then context and module instance need to be set.</p>
 *
 * @author Sarunas Narkevicius
 */
class balticode_dpd_parcelstore_html_helper {

    /**
     *
     * @var Balticode_Postoffice
     */
    protected static $_helperModuleInstance;
    
    /**
     * <p>Default HTML template, where ${LABEL} will be replaced with desired form element label and ${INPUT} will be replaced with desired input element.</p>
     * @var string
     */
    protected $_html_template = '<tr width="130" style="min-height: 35px;">
        <td class="label">${LABEL}</td>
        <td class="value">${INPUT}</td>
    </tr>';
    
    /**
     *
     * @var ContextCore
     */
    protected $_context;
    
    /**
     *
     * @var ModuleCore
     */
    protected $_moduleInstance;
    
    public function __construct() {
        ;
    }

    /**
     * <p>When you need to use smarty, then you need to supply the current module as parameter</p>
     * @param ModuleCore $moduleInstance
     * @return balticode_dpd_parcelstore_html_helper
     */
    public function setModuleInstance($moduleInstance) {
        $this->_moduleInstance = $moduleInstance;
        return $this;
    }

    /**
     * <p>Current modules context object, when needing to fetch templates from right directory.</p>
     * @param ContextCore $context
     * @return balticode_dpd_parcelstore_html_helper
     */
    public function setContext($context) {
        $this->_context = $context;
        return $this;
    }


    /**
     * Gets Text Html
     * @param string $fieldName HTML name attribute for the input field
     * @param array $fieldData information how to build the form element
     * @param mixed $value supplied form field value
     * @return string resulting field html
     * @see balticode_dpd_parcelstore_html_helper
     */    
    public function getTextHtml($fieldName, $fieldData, $value = false) {
        $label = $fieldData['title'];
        $description = isset($fieldData['description'])?$fieldData['description']:'';
        $custom_attributes = isset($fieldData['custom_attributes']) && is_array($fieldData['custom_attributes'])?$fieldData['custom_attributes']:array();
        $styles = isset($fieldData['css'])?$fieldData['css']:'';
        if (!$value && isset($fieldData['default']) && $fieldData['default']) {
            $value = $fieldData['default'];
        }
        $finalCustomAttributes = array();
        foreach ($custom_attributes as $attributeKey => $attributeValue) {
            $finalCustomAttributes[] = $attributeKey.'="'.  htmlspecialchars($attributeValue).'"';
        }
        $_input = '<input type="text" name="'.$fieldName.'" style="'.$styles.'" value="'.htmlspecialchars($value).'" '.implode(' ', $finalCustomAttributes).'/>';
        if ($description) {
            $_input .= '<p class="description">'.($description).'</p>';
        }
        return str_replace(array('${LABEL}', '${INPUT}'), array($label, $_input), $this->_html_template);
        
    }
    
    /**
     * Gets input[type=password] HTML element
     * @param string $fieldName HTML name attribute for the input field
     * @param array $fieldData information how to build the form element
     * @param mixed $value supplied form field value
     * @return string resulting field html
     * @see balticode_dpd_parcelstore_html_helper
     */    
    public function getPasswordHtml($fieldName, $fieldData, $value = false) {
        $label = $fieldData['title'];
        $description = isset($fieldData['description'])?$fieldData['description']:'';
        $custom_attributes = isset($fieldData['custom_attributes']) && is_array($fieldData['custom_attributes'])?$fieldData['custom_attributes']:array();
        $styles = isset($fieldData['css'])?$fieldData['css']:'';
        if (!$value && isset($fieldData['default']) && $fieldData['default']) {
            $value = $fieldData['default'];
        }
        $finalCustomAttributes = array();
        foreach ($custom_attributes as $attributeKey => $attributeValue) {
            $finalCustomAttributes[] = $attributeKey.'="'.  htmlspecialchars($attributeValue).'"';
        }
        $_input = '<input type="password" name="'.$fieldName.'" style="'.$styles.'" value="'.htmlspecialchars($value).'" '.implode(' ', $finalCustomAttributes).'/>';
        if ($description) {
            $_input .= '<p class="description">'.($description).'</p>';
        }
        return str_replace(array('${LABEL}', '${INPUT}'), array($label, $_input), $this->_html_template);
        
    }
    
    
    /**
     * <p>Returns textarea form element HTML</p>
     * @param string $fieldName HTML name attribute for the input field
     * @param array $fieldData information how to build the form element
     * @param mixed $value supplied form field value
     * @return string resulting field html
     * @see balticode_dpd_parcelstore_html_helper
     */    
    public function getTextareaHtml($fieldName, $fieldData, $value = false) {
        $label = $fieldData['title'];
        $description = isset($fieldData['description'])?$fieldData['description']:'';
        $custom_attributes = isset($fieldData['custom_attributes']) && is_array($fieldData['custom_attributes'])?$fieldData['custom_attributes']:array();
        $styles = isset($fieldData['css'])?$fieldData['css']:'';
        if (!$value && isset($fieldData['default']) && $fieldData['default']) {
            $value = $fieldData['default'];
        }
        $finalCustomAttributes = array();
        foreach ($custom_attributes as $attributeKey => $attributeValue) {
            $finalCustomAttributes[] = $attributeKey.'="'.  htmlspecialchars($attributeValue).'"';
        }
        $_input = '<textarea name="'.$fieldName.'" style="'.$styles.'" '.implode(' ', $finalCustomAttributes).'>'.htmlspecialchars($value).'</textarea>';
        if ($description) {
            $_input .= '<p class="description">'.($description).'</p>';
        }
        return str_replace(array('${LABEL}', '${INPUT}'), array($label, $_input), $this->_html_template);
        
    }
    
    /**
     * <p>Returns select form element HTML</p>
     * @param string $fieldName HTML name attribute for the input field
     * @param array $fieldData information how to build the form element
     * @param mixed $value supplied form field value
     * @return string resulting field html
     * @see balticode_dpd_parcelstore_html_helper
     */    
    public function getSelectHtml($fieldName, $fieldData, $value = false) {
        $label = $fieldData['title'];
        $description = isset($fieldData['description'])?$fieldData['description']:'';
        $custom_attributes = isset($fieldData['custom_attributes']) && is_array($fieldData['custom_attributes'])?$fieldData['custom_attributes']:array();
        $styles = isset($fieldData['css'])?$fieldData['css']:'';
        $options = isset($fieldData['options']) && is_array($fieldData['options'])?$fieldData['options']:array();
        if (!$value && isset($fieldData['default']) && $fieldData['default']) {
            $value = $fieldData['default'];
        }
        $finalCustomAttributes = array();
        foreach ($custom_attributes as $attributeKey => $attributeValue) {
            $finalCustomAttributes[] = $attributeKey.'="'.  htmlspecialchars($attributeValue).'"';
        }
        $_input = '<select name="'.$fieldName.'" style="'.$styles.'" value="'.htmlspecialchars($value).'" '.implode(' ', $finalCustomAttributes).'>';
        foreach ($options as $optionKey => $optionValue) {
            $_input .= '<option value="'.  htmlspecialchars($optionKey).'" ';
            if ($optionKey == $value) {
                $_input .= ' selected="selected"';
            }
            $_input .= '>';
            $_input .= htmlspecialchars($optionValue);
            $_input .= '</option>';
        }
        $_input .= '</select>';
        if ($description) {
            $_input .= '<p class="description">'.($description).'</p>';
        }
        return str_replace(array('${LABEL}', '${INPUT}'), array($label, $_input), $this->_html_template);
        
    }
    
    
    /**
     * <p>Returns select[multiple=multiple] form element HTML</p>
     * @param string $fieldName HTML name attribute for the input field
     * @param array $fieldData information how to build the form element
     * @param mixed $value supplied form field value
     * @return string resulting field html
     * @see balticode_dpd_parcelstore_html_helper
     */    
    public function getMultiselectHtml($fieldName, $fieldData, $value = false) {
        $label = $fieldData['title'];
        $description = isset($fieldData['description'])?$fieldData['description']:'';
        $custom_attributes = isset($fieldData['custom_attributes']) && is_array($fieldData['custom_attributes'])?$fieldData['custom_attributes']:array();
        $styles = isset($fieldData['css'])?$fieldData['css']:'';
        $options = isset($fieldData['options']) && is_array($fieldData['options'])?$fieldData['options']:array();
        if (!$value && isset($fieldData['default']) && $fieldData['default']) {
            $value = $fieldData['default'];
        }
        $finalCustomAttributes = array();
        foreach ($custom_attributes as $attributeKey => $attributeValue) {
            $finalCustomAttributes[] = $attributeKey.'="'.  htmlspecialchars($attributeValue).'"';
        }
        
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        if (!is_array($value)) {
            $value = array();
        }
        
        $_input = '<select multiple="multiple" name="'.$fieldName.'[]" style="'.$styles.'" value="'.htmlspecialchars(implode(',', $value)).'" '.implode(' ', $finalCustomAttributes).'>';
        foreach ($options as $optionKey => $optionValue) {
            $_input .= '<option value="'.  htmlspecialchars($optionKey).'" ';
            if (in_array($optionKey, $value)) {
                $_input .= ' selected="selected"';
            }
            $_input .= '>';
            $_input .= htmlspecialchars($optionValue);
            $_input .= '</option>';
        }
        $_input .= '</select>';
        if ($description) {
            $_input .= '<p class="description">'.($description).'</p>';
        }
        return str_replace(array('${LABEL}', '${INPUT}'), array($label, $_input), $this->_html_template);
        
    }
    
    
    
    
    
    /**
     * <p>Returns template, which renders price depeding on country form element HTML</p>
     * <p>includes template file: <code>views/templates/hook/countryprice.tpl</code></p>
     * <p>includes javascript file: <code>js/balticode_dpd_parcelstore.js</code></p>
     * <p>includes javascript file: <code>js/plugins/jquery.loadTemplate.js</code></p>
     * @param string $fieldName HTML name attribute for the input field
     * @param array $fieldData information how to build the form element
     * @param mixed $value supplied form field value
     * @return string resulting field html
     * @see balticode_dpd_parcelstore_html_helper
     */    
    public function getCountrypriceHtml($fieldName, $fieldData, $value = false) {
        $label = $fieldData['title'];
        $description = isset($fieldData['description'])?$fieldData['description']:'';
        $custom_attributes = isset($fieldData['custom_attributes']) && is_array($fieldData['custom_attributes'])?$fieldData['custom_attributes']:array();
        $styles = isset($fieldData['css'])?$fieldData['css']:'';
        if (!$value && isset($fieldData['default']) && $fieldData['default']) {
            $value = $fieldData['default'];
        }
        $finalCustomAttributes = array();
        foreach ($custom_attributes as $attributeKey => $attributeValue) {
            $finalCustomAttributes[] = $attributeKey.'="'.  htmlspecialchars($attributeValue).'"';
        }
        
//        $_input = '<input type="text" name="'.$fieldName.'" style="'.$styles.'" value="'.htmlspecialchars($value?$value:'').'" '.implode(' ', $finalCustomAttributes).'/>';
        
        $unserializedValue = array();
        if (is_string($value)) {
            $unserializedValue = @unserialize($value);
            if (!is_array($unserializedValue)) {
                $unserializedValue = array();
            }
        } else if (is_array($value)) {
            $unserializedValue = $value;
        }


        $this->_context->smarty->assign(array(
            'formFieldId' => uniqid(),
            'formFieldName' => $fieldName,
            'formFieldValue' => json_encode($unserializedValue),
            'countryOptions' => $this->_getHelperModule()->getOptionList($this->_getHelperModule()->getCountriesAsOptions($this->_moduleInstance->l(' -- select -- ')), false),
        ));
        
        $_input = $this->_moduleInstance->display($this->_moduleInstance->name .'.php', 'countryprice.tpl');
        if ($description) {
            $_input .= '<p class="description">'.htmlspecialchars($description).'</p>';
        }
        return str_replace(array('${LABEL}', '${INPUT}'), array($label, $_input), $this->_html_template);
        
    }
    
    /**
     * <p>Renders input[type=text] for default setting and then additional per language.</p>
     * <p>Values are posted back as assoc array, where array key is id_language and string '0' for default setting</p>
     * @param string $fieldName HTML name attribute for the input field
     * @param array $fieldData information how to build the form element
     * @param mixed $value supplied form field value
     * @return string resulting field html
     * @see balticode_dpd_parcelstore_html_helper
     */    
    public function getMultilangHtml($fieldName, $fieldData, $value = false) {
        $label = $fieldData['title'];
        $description = isset($fieldData['description'])?$fieldData['description']:'';
        $custom_attributes = isset($fieldData['custom_attributes']) && is_array($fieldData['custom_attributes'])?$fieldData['custom_attributes']:array();
        $styles = isset($fieldData['css'])?$fieldData['css']:'';
        if (!$value && isset($fieldData['default']) && $fieldData['default']) {
            $value = $fieldData['default'];
        }
        $finalCustomAttributes = array();
        foreach ($custom_attributes as $attributeKey => $attributeValue) {
            $finalCustomAttributes[] = $attributeKey . '="' . htmlspecialchars($attributeValue) . '"';
        }
        $languages = $this->_getLanguages();
        
        $unserializedValue = array();
        if (is_string($value)) {
            $unserializedValue = @unserialize($value);
            if (!is_array($unserializedValue)) {
                $unserializedValue = array(
                    '0' => $value,
                );
            }
        } else if (is_array($value)) {
            $unserializedValue = $value;
        }
        

        $_input = '<input type="text" name="' . $fieldName . '[0]" style="' . $styles . '" value="' . htmlspecialchars($unserializedValue[0]) . '" ' . implode(' ', $finalCustomAttributes) . '/>';
        $_input .= '<br/>';
        foreach ($languages as $id_lang => $language) {
            $langValue = isset($unserializedValue[$id_lang])?$unserializedValue[$id_lang]:$unserializedValue[0];
            $_input .= '<input type="text" name="' . $fieldName . '[' . $id_lang . ']" style="' . $styles . '" value="' . htmlspecialchars($langValue) . '" ' . implode(' ', $finalCustomAttributes) . '/> ('.  htmlspecialchars($language) .')';
            $_input .= '<br/>';
        }
        if ($description) {
            $_input .= '<p class="description">'.($description).'</p>';
        }
        return str_replace(array('${LABEL}', '${INPUT}'), array($label, $_input), $this->_html_template);
        
    }
    
    /**
     * <p>Reads available active languages from Database and returns them in assoc array where</p>
     * <ul>
         <li>array key is language_id</li>
         <li>Array value is langauge_name</li>
     </ul>
     * @return array list of languages
     */
    private function _getLanguages() {
        $db = Db::getInstance();
        //check the langs, and get the lang id-s
        $res = $db->executeS('SELECT id_lang, name FROM ' . _DB_PREFIX_ . 'lang WHERE active=1');
        $languages = array();
        foreach ($res as $re) {
            //insert the langs
            $languages[(string)$re['id_lang']] = $re['name'];
        }
        return $languages;
    }
    
    
    /**
     * 
     * @return Balticode_Postoffice
     */
    public function _getHelperModule() {
        if (is_null(self::$_helperModuleInstance)) {
            self::$_helperModuleInstance = Module::getInstanceByName('balticode_postoffice');
        }
        return self::$_helperModuleInstance;
    }
    
    
}
