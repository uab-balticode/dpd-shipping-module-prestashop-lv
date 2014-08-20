<td class="delivery_option_radio">
    <div class="radio" id="uniform-delivery_option_{$carrier.id_address}_{$carrier.id_carrier}">
        <span>
            <input {if (!$carrierValue)}disabled="disabled" title="{l s='Please select pick-up point from the right' mod='balticode_postoffice'}"{/if} class="delivery_option_radio" type="radio" name="delivery_option[{$carrier.id_address}]" onclick="{$carrier.js}" value="{$carrierValue}" id="id_carrier{$carrier.id_carrier}" {if $carrier.isDefault == 1 && false}checked="checked"{/if}
                                        data-id_address="{$carrier.id_address}"
                                        data-key="{$carrierValue}"
                                        onchange="{if $carrier.opc}updateCarrierSelectionAndGift();{else}updateExtraCarrier($('#id_carrier{$carrier.id_carrier}').val(), {$carrier.id_address});{/if}"  
                                        onclick="{if $carrier.opc}updateCarrierSelectionAndGift();{else}updateExtraCarrier($('#id_carrier{$carrier.id_carrier}').val(), {$carrier.id_address});{/if}"  
                                        />
        </span>
    </div>
</td>
<td class="delivery_option_logo">
{if $carrier.img}<img src="{$carrier.img|escape:'htmlall':'UTF-8'}" alt="{$carrier.name|escape:'htmlall':'UTF-8'}" />{else}{$carrier.name|escape:'htmlall':'UTF-8'}{/if}
</td>
<td>{$carrier.delay}
    {if ($ERROR_MESSAGE)}<p class='error'>{$ERROR_MESSAGE}</p>{/if}
</td>
<td class="delivery_option_price">
    <div class="delivery_option_price">

        {if $carrier.price}
    {if $priceDisplay == 1}{convertPrice price=$carrier.price}{else}{convertPrice price=$carrier.price}{/if}
    {if $priceDisplay == 1} {l s='(tax excl.)' mod='balticode_postoffice'}{else} {l s='(tax incl.)' mod='balticode_postoffice'}{/if}
{else}
    {l s='Free!' mod='balticode_postoffice'}
{/if}
</div>
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
