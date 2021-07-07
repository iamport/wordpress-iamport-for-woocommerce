jQuery(function($) {

  var addToCartForms = $('form.cart');
  if ( addToCartForms.length > 0 ) {
    IMP.init(iamport_naverpay.user_code); //order 페이지에서 init이 두 번 불리지 않도록

    addToCartForms.each( function() {

      var form = $(this);
      if ( form.is('.variations_form') ) { //variation

        var variationData = form.data( 'product_variations' );
        attach_naverpay_button_in_product(form, variationData);

      } else { //simple, group
        attach_naverpay_button_in_product(form);
      }

    });
  }

  var cartForms = $('form.woocommerce-cart-form');
  if ( cartForms.length > 0 || (window.wc_add_to_cart_params && window.wc_add_to_cart_params.is_cart == 1) ) { //form태그에서 class속성을 제거한 theme들이 있어서 js 변수로 판단
    IMP.init(iamport_naverpay.user_code); //order 페이지에서 init이 두 번 불리지 않도록

    attach_naverpay_button_in_cart(cartForms);
  }

  function getButtonFormData(form) {
    var s = "action=iamport_naver_info&" + form.serialize() + "&product-id=" + form.find("[name='add-to-cart']").val();

    return s.replace(/&add-to-cart=[0-9]+/i, "");
  }

  function attach_naverpay_button_in_product(form, variationData) {
    var naver_buttons = $(".iamport-naverpay-product-button[naverpay-button-attached!=yes]");

    for (var i = 0; i < naver_buttons.length; i++) {
      var nBtn = $(naver_buttons[i]);

      nBtn.attr("naverpay-button-attached", "yes"); //한 번 변환된 것은 더이상 변환되지 않도록

      var enabled = nBtn.is('.enabled');

      naver.NaverPayButton.apply({
        BUTTON_KEY: iamport_naverpay.button_key,
        TYPE: iamport_naverpay.button_type || "C",
        COLOR: iamport_naverpay.button_color || 1,
        COUNT: 2,
        ENABLE: enabled ? "Y":"N",
        EMBED_ID: nBtn.attr('id'),
        BUY_BUTTON_HANDLER: function(url) {
          if ( !enabled ) return alert("네이버페이로 구매가 불가능한 상품입니다.");

          var products = [];
          if ( form.find( '.single_add_to_cart_button.disabled' ).length > 0 ) { //woocommerce form 을 중복해서 만든 다음 <장바구니> 버튼 enabled/disabled여부에 따라 선택적으로 form 을 출력하는 theme을 발견. 때문에 globally disabled button의 개수를 찾을 것이 아니라 해당 form 내에서 찾아야 함
            if ( variationData ) {
              return alert('상품 옵션을 선택해주세요.');
            }

            return alert('구매할 수 없는 상품입니다.');
          }

          $.ajax({
            type: 'POST',
            url: iamport_naverpay.ajax_info_url,
            data: getButtonFormData(form), //add-to-cart가 포함되어있으면 자동으로 장바구니에 추가됨(serialize 전에 명시적으로 add-to-cart제거하기)
            dataType: 'json',
            dataFilter : function(data) {
              var regex_json = /{(.*)}/;
              var m = data.match(regex_json);
              if ( m )  return m[0];

              return data;
            },
            success: function( result ) {
              if ( result.error ) return alert(result.error);

              var param = {
                pg: "naverco",
                amount: result.amount,
                name: result.name,
                merchant_uid: result.merchant_uid,
                naverProducts: result.naverProducts,
                naverInterface: result.naverInterface,
                naverCultureBenefit: result.naverCultureBenefit,
                notice_url: result.notice_url
              };

              if ( result.pg_id ) param.pg = "naverco." + result.pg_id;

              IMP.request_pay(param, function(rsp) {
                if ( !rsp.success ) return alert(rsp.error_msg);
              });
            }
          });
        },

        WISHLIST_BUTTON_HANDLER: function(url) {
          var products = [];

          if ( variationData ) {
            products.push({
              product : $('input[name="product_id"]').val()
            })
          } else {
            products.push({
              product : $('[name="add-to-cart"]').val()
            });
          }

          $.ajax({
            type: 'GET',
            url: iamport_naverpay.ajax_info_url,
            data: {
              action : "iamport_naver_zzim_info",
              products : products
            },
            dataType: 'json',
            dataFilter : function(data) {
              var regex_json = /{(.*)}/;
              var m = data.match(regex_json);
              if ( m )  return m[0];

              return data;
            },
            success: function( result ) {
              // uri encode(한글부분만)
              for (var i = result.naverProducts.length - 1; i >= 0; i--) {
                result.naverProducts[i]["url"] = encodeURI(result.naverProducts[i]["url"]);
                result.naverProducts[i]["image"] = encodeURI(result.naverProducts[i]["image"]);
              };

              var param = {
                naverProducts: result.naverProducts
              };

              if ( result.pg_id ) param.pg = "naverco." + result.pg_id;

              IMP.naver_zzim(param);
            }
          });
        }
      });
    } //end if
  }

  function attach_naverpay_button_in_cart(form) {

    function initCartButton() {
      var naver_buttons = $("#iamport-naverpay-cart-button[naverpay-button-attached!=yes]");

      if ( naver_buttons.length > 0 ) {
        naver_buttons.attr("naverpay-button-attached", "yes"); //한 번 변환된 것은 더이상 변환되지 않도록

        var enabled = naver_buttons.is('.enabled');

        naver.NaverPayButton.apply({
          BUTTON_KEY: iamport_naverpay.button_key,
          TYPE: iamport_naverpay.button_type || "C",
          COLOR: iamport_naverpay.button_color || 1,
          COUNT: 1,
          ENABLE: enabled ? "Y":"N",
          EMBED_ID: "iamport-naverpay-cart-button",
          BUY_BUTTON_HANDLER: function(url) {
            if ( !enabled ) return alert("네이버페이로 구매가 불가능한 상품이 포함되어있습니다.");

            $.ajax({
              type: 'GET',
              url: iamport_naverpay.ajax_info_url,
              data: {
                action : "iamport_naver_carts"
              },
              dataType: 'json',
              dataFilter : function(data) {
                var regex_json = /{(.*)}/;
                var m = data.match(regex_json);
                if ( m )  return m[0];

                return data;
              },
              success: function( result ) {
                if ( result.error ) return alert(result.error);

                var param = {
                  pg: "naverco",
                  amount: result.amount,
                  name: result.name,
                  merchant_uid: result.merchant_uid,
                  naverProducts: result.naverProducts,
                  naverInterface: result.naverInterface,
                  naverCultureBenefit: result.naverCultureBenefit,
                  notice_url: result.notice_url
                };

                if ( result.pg_id ) param.pg = "naverco." + result.pg_id;

                IMP.request_pay(param, function(rsp) {
                  if ( !rsp.success ) return alert(rsp.error_msg);
                });
              }
            });
          }
        });
      }
    }

    $( document.body ).on( 'updated_wc_div', function() {
      initCartButton();
    });

    initCartButton();
  }

});
