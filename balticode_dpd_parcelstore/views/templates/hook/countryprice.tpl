<div class="grid" id="grid_{$formFieldId}">
    <table cellpadding="0" cellspacing="0" class="border">
        <tbody>

            <tr class="headings" id="headings_{$formFieldId}">
                <th>{l s='Country' mod='balticode_dpd_parcelstore'}</th>
                <th>{l s='Base shipping price' mod='balticode_dpd_parcelstore'}</th>
                <th>{l s='Price per additional 10kg over base 10kg' mod='balticode_dpd_parcelstore'}</th>
                <th>{l s='Free shipping from price' mod='balticode_dpd_parcelstore'}</th>
                <th ></th>
            </tr>

            <tr id="addRow_{$formFieldId}">
                <td colspan="4"></td>
                <td >
                    <button style="" onclick="jQuery('#template_{$formFieldId}').balticode_dpdee().add({});" class="button scalable add" type="button" id="addToEndBtn_{$formFieldId}">
                        <span>{l s='Add shipping country' mod='balticode_dpd_parcelstore'}</span>
                    </button>
                </td>
            </tr>

        </tbody>
    </table>
</div>
<script type="text/html" id="template_{$formFieldId}">
    <tr id="#{literal}{_id}{/literal}" data-template-bind='{literal}[{"attribute": "id", "value":"id"}]{/literal}'>
        <td>
            <select  data-template-bind='{literal}[{"attribute": "name", "value":"id", "formatter": "nameFormatter", "formatOptions" : "#{_id}[country_id]"}]{/literal}' name=""   data-value="country_id"  class="input-text" style="width:120px">
                {$countryOptions}
            </select>
        </td>
        <td>
            <input type="text" data-template-bind='{literal}[{"attribute": "name", "value":"id", "formatter": "nameFormatter", "formatOptions" : "#{_id}[base_price]"}]{/literal}' data-value="base_price"  class="validate-number" style="width:120px"/>
        </td>
        <td>
            <input type="text" data-template-bind='{literal}[{"attribute": "name", "value":"id", "formatter": "nameFormatter", "formatOptions" : "#{_id}[kg_price]"}]{/literal}' data-value="kg_price"  class="validate-number" style="width:120px"/>
        </td>
        <td>
            <input type="text" data-template-bind='{literal}[{"attribute": "name", "value":"id", "formatter": "nameFormatter", "formatOptions" : "#{_id}[free_shipping_from]"}]{/literal}' data-value="free_shipping_from"  class="input-text" style="width:120px"/>
        </td>
        <td>
            <button onclick="jQuery('#template_{$formFieldId}').balticode_dpdee().remove(this);" class="button scalable delete" type="button"><span>{l s='Delete' mod='balticode_dpd_parcelstore'}</span></button>
        </td>
    </tr>
</script>
<script type="text/javascript">
    // <![CDATA[
    jQuery(document).ready(function() {
         jQuery('#template_{$formFieldId}').balticode_dpdee({ 
             form_field_name: '{$formFieldName}',
             form_field_id: '{$formFieldId}'
         },{$formFieldValue} );
    });


    //]]>
</script>