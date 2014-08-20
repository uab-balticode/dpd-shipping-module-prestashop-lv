
<script type="text/javascript">
     <![CDATA[
    window.updateDpdEeTimes = function(elem) {
        var option = $(elem).val(),
                availableTimes = "1";
                console.log('updateDpdEeTimes');

        if (option) {
            jQuery('#balticodedpdee_courier_times').html(availableTimes[option]);
        }

    };
    window.updateDpdEeTimes($('#Po_date').eq(0));
     ]]>
</script>
<div class="shipment_info">
    <label for="Po_parcel_qty"><input type="text" name="Po_parcel_qty" id="Po_parcel_qty" value="0"
                                      class="validate-not-negative-number validate-digits"/>{l s='Parcels (≤ 31,5kg)' mod='balticode_dpd_parcelstore'}</label>
    <label for="Po_pallet_qty"><input type="text" name="Po_pallet_qty" id="Po_pallet_qty" value="0"
                                      class="validate-not-negative-number validate-digits"/>{l s='Pallets' mod='balticode_dpd_parcelstore'}</label>
</div>
<div class="shipment_comment">
    <textarea name="Po_remark" id="Po_remark" cols="30" rows="3" title="{l s='Comment to courier' mod='balticode_dpd_parcelstore'}"></textarea>
</div>
<input type="hidden" name="order_ids" id="balticode_dpdee_order_ids" value=""/>
{$requests}