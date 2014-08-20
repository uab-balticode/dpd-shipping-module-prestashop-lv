<td class="delivery_option_radio">
    <div class="radio" id="uniform-delivery_option_{$balticode_carrier.id_address}_{$balticode_carrier.id_carrier}">
        <span>
            <input {if (!$balticode_carrierValue)}disabled="disabled" title="{l s='Please select pick-up point from the right' mod='balticode_postoffice'}"{/if} class="delivery_option_radio" type="radio" name="delivery_option[{$balticode_carrier.id_address}]" onclick="{$balticode_carrier.js}" value="{$balticode_carrierValue}" id="id_carrier{$balticode_carrier.id_carrier}" {if $balticode_carrier.isDefault == 1 && false}checked="checked"{/if}
                                        data-id_address="{$balticode_carrier.id_address}"
                                        data-key="{$balticode_carrierValue}"
                                        onchange="{if $balticode_carrier.opc}updateCarrierSelectionAndGift();{else}updateExtraCarrier($balticode_('#id_carrier{$balticode_carrier.id_carrier}').val(), {$balticode_carrier.id_address});{/if}"  
                                        onclick="{if $balticode_carrier.opc}updateCarrierSelectionAndGift();{else}updateExtraCarrier($balticode_('#id_carrier{$balticode_carrier.id_carrier}').val(), {$balticode_carrier.id_address});{/if}"  
                                        />
        </span>
    </div>
</td>
<td class="delivery_option_logo">
{if $balticode_carrier.img}<img src="{$balticode_carrier.img|escape:'htmlall':'UTF-8'}" alt="{$balticode_carrier.name|escape:'htmlall':'UTF-8'}" />{else}{$balticode_carrier.name|escape:'htmlall':'UTF-8'}{/if}
</td>
<td>{$balticode_carrier.delay}
    {if ($balticode_ERROR_MESSAGE)}<p class='error'>{$balticode_ERROR_MESSAGE}</p>{/if}
</td>
<td class="delivery_option_price">
    <div class="delivery_option_price">

        {if $balticode_carrier.price}
    {if $balticode_priceDisplay == 1}{convertPrice price=$balticode_carrier.price}{else}{convertPrice price=$balticode_carrier.price}{/if}
    {if $balticode_priceDisplay == 1} {l s='(tax excl.)' mod='balticode_postoffice'}{else} {l s='(tax incl.)' mod='balticode_postoffice'}{/if}
{else}
    {l s='Free!' mod='balticode_postoffice'}
{/if}
</div>
</td>

<script type="text/javascript">
                                            /* <![CDATA[ */
                                            jQuery('#{$balticode_carrierId}').val('');
                                            jQuery.ajax('{$balticode_url}', {
                                                'type': 'POST',
                                                data: {
                                                    carrier_id: '{$balticode_carrierId}',
                                                    carrier_code: '{$balticode_carrierCode}',
                                                    div_id: '{$balticode_divId}',
                                                    address_id: '{$balticode_addressId}'
                                                },
                                                success: function(transport) {
                                                    jQuery('#{$balticode_divId}').html(transport);
                                                }
                                            });
    {$balticode_extraJs}
                                            /* ]]> */
</script>
