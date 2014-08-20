<td class="carrier_action radio">
            <input {if (!$carrierValue)}disabled="disabled" title="{l s='Please select pick-up point from the right' mod='balticode_postoffice'}"{/if} type="radio" name="id_carrier" onclick="{$carrier.js}" value="{$carrierValue}" id="id_carrier{$carrier.id_carrier|intval}" {if $carrier.isSelected == 1}checked="checked"{/if}
                                        onchange="{if $carrier.opc}updateCarrierSelectionAndGift();{else}updateExtraCarrier($('#id_carrier{$carrier.id_carrier}').val(), {$carrier.id_address});{/if}"  
                                        onclick="{if $carrier.opc}updateCarrierSelectionAndGift();{else}updateExtraCarrier($('#id_carrier{$carrier.id_carrier}').val(), {$carrier.id_address});{/if}"  
                                        />
</td>
<td class="carrier_name">
    <label for="id_carrier{$carrier.id_carrier|intval}">
{if $carrier.img}<img src="{$carrier.img|escape:'htmlall':'UTF-8'}" alt="{$carrier.name|escape:'htmlall':'UTF-8'}" />{else}{$carrier.name|escape:'htmlall':'UTF-8'}{/if}
    </label>
</td>
<td class="carrier_infos">    <label for="id_carrier{$carrier.id_carrier|intval}">
        {$carrier.delay}</label>
    {if ($ERROR_MESSAGE)}<p class='error'>{$ERROR_MESSAGE}</p>{/if}
</td>
<td class="carrier_price">
    <label for="id_carrier{$carrier.id_carrier|intval}">

        {if $carrier.price}
    {if $priceDisplay == 1}{convertPrice price=$carrier.price}{else}{convertPrice price=$carrier.price}{/if}
    {if $priceDisplay == 1} {l s='(tax excl.)' mod='balticode_postoffice'}{else} {l s='(tax incl.)' mod='balticode_postoffice'}{/if}
{else}
    {l s='Free!' mod='balticode_postoffice'}
{/if}
</label>
</td>

<script type="text/javascript">
                                            /* <![CDATA[ */
                                            jQuery('#{$carrierId}').val('');
                                            jQuery.ajax('{$url}', {
                                                'type': 'POST',
                                                data: {
                                                    carrier_id: '{$carrierId}',
                                                    carrier_code: '{$carrierCode}',
                                                    div_id: '{$divId}',
                                                    address_id: '{$addressId}'
                                                },
                                                success: function(transport) {
                                                    jQuery('#{$divId}').html(transport);
                                                }
                                            });
    {$extraJs}
                                            /* ]]> */
</script>
