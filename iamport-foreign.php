<?php
class WC_Gateway_Iamport_Foreign extends WC_Payment_Gateway {

    const GATEWAY_ID = 'iamport_foreign';

	public function __construct() {
		//settings
		$this->id = self::GATEWAY_ID; //id가 먼저 세팅되어야 init_setting가 제대로 동작
		$this->method_title = __( '아임포트(해외카드결제)', 'iamport-for-woocommerce' );
		$this->method_description = __( '아임포트를 통해 VISA/MASTER/JCB 카드결제를 사용하실 수 있습니다.(JTNet PG사 가입이 필요하며 KRW 원화로 결제가 됩니다)', 'iamport-for-woocommerce' );
		$this->has_fields = true;
		$this->supports = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->settings['title'];

		$this->imp_rest_key = $this->settings['imp_rest_key'];
		$this->imp_rest_secret = $this->settings['imp_rest_secret'];

		//woocommerce action
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_iamport_script') );
	}

	public function enqueue_iamport_script() {
		wp_register_script( 'iamport_rsa', plugins_url( '/assets/js/rsa.bundle.js',plugin_basename(__FILE__) ));
		wp_register_script( 'iamport_script_for_woocommerce_rsa', plugins_url( '/assets/js/iamport.woocommerce.rsa.js',plugin_basename(__FILE__) ));
		wp_enqueue_script('iamport_rsa');
		wp_enqueue_script('iamport_script_for_woocommerce_rsa');
	}

	public function init_form_fields() {
		//iamport기본 플러그인에 해당 정보가 세팅되어있는지 먼저 확인
		$default_api_key = get_option('iamport_rest_key');
		$default_api_secret = get_option('iamport_rest_secret');

		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( '아임포트(해외카드결제) 결제 사용', 'iamport-for-woocommerce' ),
				'default' => 'no'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( '해외카드결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'imp_rest_key' => array(
				'title' => __( '[아임포트] REST API 키', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( 'https://admin.iamport.kr에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.', 'iamport-for-woocommerce' ),
				'label' => __( '[아임포트] REST API 키', 'iamport-for-woocommerce' ),
				'default' => $default_api_key
			),
			'imp_rest_secret' => array(
				'title' => __( '[아임포트] REST API Secret', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( 'https://admin.iamport.kr에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.', 'iamport-for-woocommerce' ),
				'label' => __( '[아임포트] REST API Secret', 'iamport-for-woocommerce' ),
				'default' => $default_api_secret
			)
		);
	}

	public function payment_fields() {
		ob_start();

		$private_key = $this->get_private_key();
		$public_key = $this->get_public_key($private_key, $this->keyphrase());
		?>
		<div id="iamport-foreign-card-holder" data-module="<?=$public_key['module']?>" data-exponent="<?=$public_key['exponent']?>">
			<input type="hidden" name="enc_iamport_foreign-card-number" value="">
			<input type="hidden" name="enc_iamport_foreign-card-expiry" value="">
			<input type="hidden" name="enc_iamport_foreign-card-cvc" value="">
			<?php $this->credit_card_form( array( 'fields_have_names' => true ) ); ?>
		</div>
		<?php
		ob_end_flush();
	}

	public function credit_card_form( $args = array(), $fields = array() ) {
		//woocommerce 2.6부터는 WC_Payment_Gateway_CC->form을 사용해야 함
		if ( class_exists('WC_Payment_Gateway_CC') ) {
			$cc_form = new WC_Payment_Gateway_CC;
			$cc_form->id       = $this->id;
			$cc_form->supports = $this->supports;
			$cc_form->form();
		} else {
			parent::credit_card_form( $args, $fields );
		}
	}

	public function process_payment( $order_id ) {
		require_once(dirname(__FILE__).'/lib/iamport.php');

		global $woocommerce;

		$order = new WC_Order( $order_id );
		//JTNet비인증 KRW외에는 지원이 안되기 때문에 currency 를 보고 막아야 함
		$currency = strtoupper( $order->get_currency() );
		if ( $currency !== "KRW" )	throw new Exception( __( '원화(KRW)로만 결제가 가능합니다. (Only KRW currency is supported.)', 'iamport-for-woocommerce' ) );

		$private_key = $this->get_private_key();

		$card_number 	= $_POST['enc_iamport_foreign-card-number'];
		$expiry 		= $_POST['enc_iamport_foreign-card-expiry'];
		$cvc	 		= $_POST['enc_iamport_foreign-card-cvc'];

		$dec_card_number 	= $this->decrypt( $card_number, $private_key );
		$dec_expiry			= $this->decrypt( $expiry, $private_key );
		$dec_cvc			= $this->decrypt( $cvc, $private_key );

		if ( empty($dec_card_number) || empty($dec_expiry) || empty($dec_cvc) ) {
			throw new Exception( __( '암호화되어 전송된 카드정보를 복호화하는데 실패하였습니다. 관리자에게 문의해주세요.', 'iamport-for-woocommerce' ));
		}
	
		$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);
		$buyer_name = trim($order->get_billing_last_name() . $order->get_billing_first_name());
		if ( empty($buyer_name) )	$buyer_name = $this->get_default_user_name();

		$result = $iamport->sbcr_foreign(array(
			'amount' => $order->get_total(),
			'merchant_uid' => $order->get_order_key(),
			'card_number' => $dec_card_number,
			'expiry' => $this->format_expiry($dec_expiry),
			'cvc' => $dec_cvc,
			'name' => $this->get_order_name($order),
			'buyer_name' => $buyer_name, //name
			'buyer_email' => $order->get_billing_email(), //email
			'buyer_tel' => $order->get_billing_phone() //tel
		));

		if ( $result->success ) {
			$payment_data = $result->data;

			if ( $payment_data->status == 'paid' ) {
				$this->_iamport_post_meta($order_id, '_iamport_rest_key', $this->imp_rest_key);
				$this->_iamport_post_meta($order_id, '_iamport_rest_secret', $this->imp_rest_secret);
				$this->_iamport_post_meta($order_id, '_iamport_provider', $payment_data->pg_provider);
				$this->_iamport_post_meta($order_id, '_iamport_paymethod', $payment_data->pay_method);
				$this->_iamport_post_meta($order_id, '_iamport_pg_tid', $payment_data->pg_tid);
				$this->_iamport_post_meta($order_id, '_iamport_receipt_url', $payment_data->receipt_url);

				if ( !$order->has_status(wc_get_is_paid_statuses()) ) {
					$old_status = $order->get_status();
					$order->payment_complete( $payment_data->imp_uid ); //imp_uid 

					//fire hook
					do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);
				}

				return array(
					'result' => 'success',
					'redirect'	=> $this->get_return_url($order),
				);
			} else {
				$message = sprintf( __( '해외카드결제에 실패하였습니다. (status : %s)', 'iamport-for-woocommerce' ), $payment_data->status );
				$order->add_order_note( $message );
				throw new Exception( $message );
			}
		} else {
			//$redirect_url = $order->get_checkout_payment_url( false );
			$order->add_order_note( $result->error['message'] );
			throw new Exception( $result->error['message'] );
		}
	}

	//common for refund(나중에 합치자...)
	public function process_refund($order_id, $amount = null, $reason = '') {
		require_once(dirname(__FILE__).'/lib/iamport.php');

		global $woocommerce;
		$order = new WC_Order( $order_id );

		$imp_uid = $order->get_transaction_id();
		$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);

		// 만약 데이터 동기화에 실패하는 상황이 되어 imp_uid가 없더라도 order_key가 있으면 취소를 시도해볼 수 있다.
		if ( empty($imp_uid) ) {
			//회차결제를 위해 md가 뒤에 붙도록 설계되어있음
			$merchant_uid = $order->get_order_key();
			$recurring_md_suffix = get_post_meta($order->get_id(), '_iamport_recurring_md', true);
			if ( !empty($recurring_md_suffix) )	$merchant_uid = $merchant_uid . $recurring_md_suffix;

			$cancel_data = array(
				'merchant_uid'=>$merchant_uid,
				'reason'=>$reason,
				'amount'=>$amount
			);
		} else {
			$cancel_data = array(
				'imp_uid'=>$imp_uid,
				'reason'=>$reason,
				'amount'=>$amount
			);
		}

		$result = $iamport->cancel($cancel_data);

		if ( $result->success ) {
			$payment_data = $result->data;
			$order->add_order_note( sprintf(__( '%s 원 환불완료', 'iamport-for-woocommerce'), number_format($amount)) );
			if ( $payment_data->amount == $payment_data->cancel_amount ) {
				$old_status = $order->get_status();
				$order->update_status('refunded');

				//fire hook
				do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);
			}
			return true;
		} else {
			$order->add_order_note($result->error['message']);
			return false;
		}

		return false;
	}

	private function keyphrase() {
		$keyphrase = get_option('_iamport_rsa_keyphrase');
		if ( $keyphrase )		return $keyphrase;

		require_once( ABSPATH . 'wp-includes/class-phpass.php');
		$hasher = new PasswordHash( 8, false );
		$keyphrase = md5( $hasher->get_random_bytes( 16 ) );

		if ( add_option('_iamport_rsa_keyphrase', $keyphrase) )		return $keyphrase;

		return false;
	}

	private function get_private_key() {
		$private_key = get_option('_iamport_rsa_private_key');

		if ( $private_key )		return $private_key; //있으면 기존 것을 반환

		$config = array(
			"digest_alg" => "sha256",
			"private_key_bits" => 4096,
			"private_key_type" => OPENSSL_KEYTYPE_RSA
		);

		// Create the private key
		$res = openssl_pkey_new($config);
		$success = openssl_pkey_export($res, $private_key, $this->keyphrase()); //-------BEGIN RSA PRIVATE KEY...로 시작되는 문자열을 $private_key에 저장

		if ( $success && add_option('_iamport_rsa_private_key', $private_key) )		return $private_key;

		return false;
	}

	private function get_public_key($private_key, $keyphrase) {
		$res = openssl_pkey_get_private($private_key, $keyphrase);
		$details = openssl_pkey_get_details($res);

		return array('module'=>$this->to_hex($details['rsa']['n']), 'exponent'=>$this->to_hex($details['rsa']['e']));
	}

	private function to_hex($data) {
		return strtoupper(bin2hex($data));
	}

	private function decrypt($encrypted, $private_key) {
		$payload = pack('H*', $encrypted);
		$pk_info = openssl_pkey_get_private($private_key, $this->keyphrase());
		if ( $pk_info && openssl_private_decrypt($payload, $decrypted, $pk_info) ) {
			return $decrypted;
		}

		return false;
	}

	protected function get_default_user_name() {
		$current_user = wp_get_current_user();

		if ( $current_user->ID > 0 ) {
			$name = $current_user->user_lastname . $current_user->user_firstname;
			if ( !empty($name) )	return $name;

			$name = $current_user->display_name;
			if ( !empty($name) )	return $name;

			$name = $current_user->user_login;
			if ( !empty($name) )	return $name;
		}

		return "구매자";
	}

	protected function get_order_name($order) {
		$order_name = "#" . $order->get_order_number() . "번 주문";

		$cart_items = $order->get_items();
		$cnt = count($cart_items);

		if (!empty($cart_items)) {
			$index = 0;
			foreach ($cart_items as $item) {
				if ( $index == 0 ) {
					$order_name = $item['name'];
				} else if ( $index > 0 ) {
					
					$order_name .= ' 외 ' . ($cnt-1);
				}

				$index++;
			}
		}

		$order_name = apply_filters('iamport_simple_order_name', $order_name, $order);

		return $order_name;
	}

	protected function format_expiry($expiry) {
		$refined = preg_replace('/[^0-9]/', '', $expiry);
		$len = strlen($refined);

		if ($len == 4 || $len == 6) {
			$month = substr($refined, 0, 2);
			$year = str_pad(substr($refined, 2), 4, '20', STR_PAD_LEFT);

			return $year . '-' . $month;
		}

		return false;
	}

	protected function _iamport_post_meta($order_id, $meta_key, $meta_value) {
		if ( !add_post_meta($order_id, $meta_key, $meta_value, true) ) {
			update_post_meta($order_id, $meta_key, $meta_value);
		}

		do_action('iamport_order_meta_saved', $order_id, $meta_key, $meta_value);
	}

}