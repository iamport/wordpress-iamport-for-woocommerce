jQuery(function($) {
    $('#refund_amount').closest('tr').after('<tr>' +
        '<td class="label">' +
        '<label for="iamport_refund_taxfree"><span class="woocommerce-help-tip" data-tip="결제상품에 면세상품이 포함돼있다면, 환불금액 중 면세공급가액을 입력해주세요"></span> 환불금액 중 면세금액</label>' +
        '</td>' +
        '<td class="total">' +
        '<input type="text" id="iamport_refund_taxfree" name="iamport_refund_taxfree" class="wc_input_price">' +
        '<div class="clear"></div>' +
        '</td>' +
        '</tr>');

    if ($('#_payment_method').val() == 'iamport_vbank') {
        //TODO : 가상계좌 환불
    }

    $.ajaxSetup({
        beforeSend: function(xhr, setting) {
            if (!setting.data)  return;

            var refundTaxFree = $('#iamport_refund_taxfree').val();
            if (isNaN(refundTaxFree) || refundTaxFree <= 0) return;

            if (typeof setting.data == 'string' && setting.data.match(/action=woocommerce_refund_line_items($|&)/i)) {
                setting.data += ('&iamport_refund_taxfree=' + refundTaxFree);
            }
        }
    });
});
