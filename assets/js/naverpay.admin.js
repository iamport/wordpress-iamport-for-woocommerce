jQuery(function($) {
    $('.naverpay-refund-each').click(function() {
        var $this = $(this);

        var wcOrderId = $this.data('wcOrderId'),
            productOrderId = $this.data('productOrderId'),
            impUid = $this.data('impUid'),
            productName = $this.data('productName');

        if (confirm('[' + productName + '] 상품을 네이버페이-환불처리하시겠습니까?')) {
            $this.attr('disabled', true);
            var buttonText = $this.text();
            $this.text('처리중..');

            $.ajax({
                type : 'POST',
                url : ajaxurl,
                data : {
                    'action' : 'iamport_naver_admin_cancel_order',
                    'wcOrderId' : wcOrderId,
                    'impUid' : impUid,
                    'productOrderId' : productOrderId
                },
                dataType : 'json',
                success : function(result) {
                    if (result.error) {
                        alert(result.error);
                        $this.attr('disabled', false).text(buttonText);
                    } else {
                        alert('환불처리가 완료되었습니다.');
                        location.reload();
                    }
                },
                error : function(jqXHR, textStatus, errorThrown) {
                    alert(errorThrown);
                    $this.attr('disabled', false).text(buttonText);
                }
            });
        }
    });

    $('.naverpay-refund-all').click(function() {
        var $this = $(this);

        var wcOrderId = $this.data('wcOrderId'),
            impUid = $this.data('impUid');

        if (confirm('전체 상품에 대해 네이버페이-환불처리하시겠습니까?')) {
            $this.attr('disabled', true);
            var buttonText = $this.text();
            $this.text('처리중..');

            $.ajax({
                type : 'POST',
                url : ajaxurl,
                data : {
                    'action' : 'iamport_naver_admin_cancel_order',
                    'wcOrderId' : wcOrderId,
                    'impUid' : impUid
                },
                dataType : 'json',
                success : function(result) {
                    if (result.error) {
                        alert(result.error);
                        $this.attr('disabled', false).text(buttonText);
                    } else {
                        alert('환불처리가 완료되었습니다.');
                        location.reload();
                    }
                },
                error : function(jqXHR, textStatus, errorThrown) {
                    alert(errorThrown);
                    $this.attr('disabled', false).text(buttonText);
                }
            });
        }
    });

    $('.naverpay-sync').click(function () {
        var $this = $(this);

        var wcOrderId = $this.data('wcOrderId'),
            impUid = $this.data('impUid');

        $.ajax({
            type : 'POST',
            url : ajaxurl,
            data : {
                'action' : 'iamport_naver_admin_sync_order',
                'wcOrderId' : wcOrderId,
                'impUid' : impUid
            },
            dataType : 'json',
            success : function(result) {

            },
            error : function(jqXHR, textStatus, errorThrown) {

            }
        });
    });

    //배송정보 설정
    var calc_check_elem = $("#iamport_naverpay_use_woocommerce_shipping_calc");
    var use_woocommerce_shipping_calc = calc_check_elem.is(':checked');

    function disable_naver_shipping_calc(disabled) {
        $("[id^='iamport_naverpay_free_shipping_method_zone_']").attr('disabled', disabled);
        $("[id^='iamport_naverpay_shipping_method_zone_']").attr('disabled', disabled);
        $("#iamport_naverpay_shipping_surcharge_area_island").attr('disabled', disabled);
        $("#iamport_naverpay_shipping_surcharge_area_jeju").attr('disabled', disabled);

    }
    calc_check_elem.click(function() {
        disable_naver_shipping_calc($(this).is(':checked'));
    });

    if (use_woocommerce_shipping_calc) {
        disable_naver_shipping_calc(true);
    }
});
