<script type="text/javascript">
    // <![CDATA[
    window.updateDpdEeTimes = function(elem) {
        var option = $(elem).val(),
//                availableTimes = {$availableTimes};

       // if (option && availableTimes[option]) {
        //    jQuery('#balticodedpdee_courier_times').html(availableTimes[option]);
        //}

    };
    window.updateDpdEeTimes($('#Po_date').eq(0));
    // ]]>
</script>
<div class="shipment_info">
    <label for="Po_envelope_qty"><input type="text" name="Po_envelope_qty" id="Po_envelope_qty" value="0"
                                        class="validate-not-negative-number validate-digits"/>{l s='Envelopes (≤ 0,5kg)' mod='balticode_dpd_courier'}</label>
    <label for="Po_parcel_qty"><input type="text" name="Po_parcel_qty" id="Po_parcel_qty" value="1"
                                      class="validate-not-negative-number validate-digits"/>{l s='Parcels (≤ 31,5kg)' mod='balticode_dpd_courier'}</label>
    <label for="Po_pallet_qty"><input type="text" name="Po_pallet_qty" id="Po_pallet_qty" value="0"
                                      class="validate-not-negative-number validate-digits"/>{l s='Pallets' mod='balticode_dpd_courier'}</label>
</div>
<div class="shipment_comment">
    <textarea name="Po_remark" id="Po_remark" cols="40" rows="2" title="{l s='Comment to courier' mod='balticode_dpd_courier'}"></textarea>
</div>
<input type="hidden" name="order_ids" id="balticode_dpdee_order_ids" value=""/>