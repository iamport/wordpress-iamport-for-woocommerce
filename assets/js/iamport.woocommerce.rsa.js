jQuery.fn.serializeObject = function() {
	var obj = null;
	try {
		if ( this[0].tagName && this[0].tagName.toUpperCase() == "FORM" ) {
			var arr = this.serializeArray();
			if ( arr ) {
				obj = {};
				jQuery.each(arr, function() {
					obj[this.name] = this.value;
				});
			}//if ( arr ) {
		}
	}
	catch(e) {alert(e.message);}
	finally  {}

	return obj;
};

jQuery(function($) {
	var iamport_gateways = [
		'iamport_subscription',
		'iamport_foreign'
	];

	var iamport_checkout_types = (function() {
		var arr = [],
			len = iamport_gateways.length;
		for (var i = 0; i < len; i++) {
			arr.push( 'checkout_place_order_' + iamport_gateways[i] );
		};

		return arr;
	}());

	function in_iamport_gateway(gateway) {
		for (var i = iamport_gateways.length - 1; i >= 0; i--) {
			if ( gateway === iamport_gateways[i] )	return true;
		};

		return false;
	}

	function handle_error($form, err) {
		// Reload page
		if ( err.reload === 'true' ) {
			window.location.reload();
			return;
		}

		// Remove old errors
		//error.messages가 plain text일 수도 있고, <ul class="woocommerce-error"></ul>일 수도 있어서 한 번 더 감싼다
		$( '.iamport-error-wrap' ).remove();

		// Add new errors
		$form.prepend( '<div class="iamport-error-wrap">' + err.messages + '</div>' );

		// Cancel processing
		$form.removeClass( 'processing' ).unblock();

		// Lose focus for all fields
		$form.find( '.input-text, select' ).blur();

		// Scroll to top
		$( 'html, body' ).animate({
			scrollTop: ( $form.offset().top - 100 )
		}, 1000 );

		// Trigger update in case we need a fresh nonce
		if ( err.refresh === 'true' )
			$( 'body' ).trigger( 'update_checkout' );

		$( 'body' ).trigger( 'checkout_error' );
	}

	function error_html(plain_message) {
		return '<ul class="woocommerce-error">\n\t\t\t<li>' + plain_message + '<\/li>\n\t<\/ul>\n';
	}

	function check_required_card_field(gateway, param, $form) {
		var requireds = [];

		jQuery.each($form.find('.iamport-card-form.iamport-required'), function(idx, item) {
			requireds.push( jQuery(item).attr('name') );
		});

		if ( gateway == 'iamport_subscription' ) {
			var validated = false;

			for (var i = 0; i < requireds.length; i++) {
				validated = (i == 0 || validated) && !!param[ requireds[i] ];
			}

			return validated;
		} else if ( gateway == 'iamport_foreign' ) {
			return 	param['iamport_foreign-card-number'] &&
				 	param['iamport_foreign-card-expiry'] &&
				 	param['iamport_foreign-card-cvc'];
		}

		return false;
	}

	function encrypt_card_info(gateway, param) {
		if ( gateway == 'iamport_subscription' ) {
			var holder = $('#iamport-subscription-card-holder'),
				module = holder.data('module'),
				exponent = holder.data('exponent');

			var rsa = new RSAKey();
			rsa.setPublic(module, exponent);

			// encrypt using public key
			var enc_card_number = rsa.encrypt( param['iamport_subscription-card-number'] || '' );
			var enc_card_expiry = rsa.encrypt( param['iamport_subscription-card-expiry'] || '' );
			var enc_card_birth 	= rsa.encrypt( param['iamport_subscription-card-birth'] || '' );
			var enc_card_pwd 	= rsa.encrypt( param['iamport_subscription-card-pwd'] || '' );
			var enc_card_cvc    = rsa.encrypt( param['iamport_subscription-card-cvc'] || '' );

			param['enc_iamport_subscription-card-number'] 	= enc_card_number;
			param['enc_iamport_subscription-card-expiry'] 	= enc_card_expiry;
			param['enc_iamport_subscription-card-birth'] 	= enc_card_birth;
			param['enc_iamport_subscription-card-pwd']	 	= enc_card_pwd;
			param['enc_iamport_subscription-card-cvc']      = enc_card_cvc;


			delete param['iamport_subscription-card-number'];
			delete param['iamport_subscription-card-expiry'];
			delete param['iamport_subscription-card-birth'];
			delete param['iamport_subscription-card-pwd'];
			delete param['iamport_subscription-card-cvc'];
		} else if ( gateway == 'iamport_foreign' ) {
			var holder = $('#iamport-foreign-card-holder'),
				module = holder.data('module'),
				exponent = holder.data('exponent');

			var rsa = new RSAKey();
			rsa.setPublic(module, exponent);

			// encrypt using public key
			var enc_card_number = rsa.encrypt( param['iamport_foreign-card-number'] );
			var enc_card_expiry = rsa.encrypt( param['iamport_foreign-card-expiry'] );
			var enc_card_cvc 	= rsa.encrypt( param['iamport_foreign-card-cvc'] );

			param['enc_iamport_foreign-card-number'] 	= enc_card_number;
			param['enc_iamport_foreign-card-expiry'] 	= enc_card_expiry;
			param['enc_iamport_foreign-card-cvc'] 		= enc_card_cvc;

			delete param['iamport_foreign-card-number'];
			delete param['iamport_foreign-card-expiry'];
			delete param['iamport_foreign-card-cvc'];
		}
	}

	function get_rsa_ajax_url() {
		var url = wc_checkout_params.checkout_url;

		var f = getUrlParameter("change_payment_method"),
				k = getUrlParameter("key"),
				a = getUrlParameter("pay_for_order"); //json response할지 체크하기 위해 보내줘야 함

		if ( f && k ) {
			var sep;
			if ( url.indexOf("?") > -1 ) {
				sep = "&";
			} else {
				sep = "?";
			}

			url += (sep + "key=" + k + "&change_payment_method=" + f + "&pay_for_order=" + a);
		}

		return url;
	}

	function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
	}

	// <form id="order_review" name="checkout"></form>인 테마가 존재할 수 있음.
	// 중복으로 event handler가 등록되지 않도록
	// key-in결제를 고려하면 form#order_review의 handler가 처리하는 것이 맞을 듯
	$('form[name="checkout"]:not(#order_review)').on(iamport_checkout_types.join(' '), function() {
		//woocommerce의 checkout.js의 기본동작을 그대로..woocommerce 버전바뀔 때마다 확인 필요
		var $form = $(this),
			gateway_name = $form.find('input[name="payment_method"]:checked').val();

		var form_param = $form.serializeObject();

		if ( !check_required_card_field(gateway_name, form_param, $form) ) {
			handle_error($form, {
				"result": "failure",
				"messages": error_html('카드정보를 입력해주세요.'),
				"refresh": false,
				"reload": false
			});

			return false;
		}

		encrypt_card_info(gateway_name, form_param);

		$form.addClass( 'processing' );
		var form_data = $form.data();

		if ( 1 !== form_data['blockUI.isBlocked'] ) {
			$form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		}

		$.ajax({
			type: 	'POST',
			url: 	wc_checkout_params.checkout_url,
			data: 	jQuery.param( form_param ),
			dataType: 'json',
			dataFilter : function(data) {
				var regex_json = /{(.*)}/;
				var m = data.match(regex_json);
				if ( m )	return m[0];

				return data;
			},
			success: function( result ) {
				try {
					if ( result.result === 'success' ) {
						if ( -1 === result.redirect.indexOf( 'https://' ) || -1 === result.redirect.indexOf( 'http://' ) ) {
							window.location = result.redirect;
						} else {
							window.location = decodeURI( result.redirect );
						}
					} else if ( result.result === 'failure' ) {
						throw result;
					} else {
						throw result;
					}
				} catch( err ) {
					handle_error($form, err);
                }
            },
            error:  function( jqXHR, textStatus, errorThrown ) {
            	alert(errorThrown);
            	window.location.reload();
            }
        });

		return false; //기본 checkout 프로세스를 중단
	});


	$('form#order_review').on('submit', function(e) {
		var $form = $(this),
			gateway_name = $( '#order_review input[name=payment_method]:checked' ).val(),
			prefix = 'iamport_';

		if ( !in_iamport_gateway(gateway_name) )	return true; //다른 결제수단이 submit될 수 있도록

		e.preventDefault(); // iamport와 관련있는 것일 때만
		e.stopImmediatePropagation(); //theme에 따라서 다른 submit handler에서 submit을 시켜버리는 경우가 있음

		var form_param = $form.serializeObject();

		if ( !check_required_card_field(gateway_name, form_param, $form) ) {
			handle_error($form, {
				"result": "failure",
				"messages": error_html('카드정보를 입력해주세요.'),
				"refresh": false,
				"reload": false
			});

			return false;
		}

		encrypt_card_info(gateway_name, form_param);

		$.ajax({
			type: 	'POST',
			url: 	get_rsa_ajax_url(),
			data: 	jQuery.param( form_param ),
			dataType: 'json',
			dataFilter : function(data) {
				var regex_json = /{(.*)}/;
				var m = data.match(regex_json);
				if ( m )	return m[0];

				return data;
			},
			success: function( result ) {
				try {
					if ( result.result === 'success' ) {
						if ( -1 === result.redirect.indexOf( 'https://' ) || -1 === result.redirect.indexOf( 'http://' ) ) {
							window.location = result.redirect;
						} else {
							window.location = decodeURI( result.redirect );
						}
					} else if ( result.result === 'failure' ) {
						throw result;
					} else {
						throw result;
					}
				} catch( err ) {
					handle_error($form, err);
        }
      },
      error:  function( jqXHR, textStatus, errorThrown ) {
        alert(errorThrown);
        window.location.reload();
      }
    });

		return false; //form submit 중단
	})
})
