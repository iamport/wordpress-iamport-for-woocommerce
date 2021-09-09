<?php
class WC_Gateway_Iamport_Kakao extends Base_Gateway_Iamport {

    const GATEWAY_ID = 'iamport_kakao';

	public function __construct() {
		parent::__construct();

		//settings
		$this->method_title = __( '아임포트(카카오페이)', 'iamport-for-woocommerce' );
		$this->method_description = __( '=> 아임포트 서비스를 이용해 결제모듈을 연동할 수 있습니다.<br>=> [아임포트] X PG사 제휴할인혜택을 받아보세요! <a href="http://www.iamport.kr/pg#promotion" target="_blank">PG 신규계약 프로모션 안내</a><br>=> 아임포트의 최신 공지사항도 놓치지 마세요! <a href="http://www.iamport.kr/notice" target="_blank">공지사항보기</a>', 'iamport-for-woocommerce' );
		$this->has_fields = true;
		$this->supports = array( 'products', 'refunds', 'subscriptions', 'subscription_reactivation'/*이것이 있어야 subscription 후 active상태로 들어갈 수 있음*/, 'subscription_suspension', 'subscription_cancellation', 'subscription_date_changes', 'subscription_amount_changes', 'subscription_payment_method_change_customer', 'multiple_subscriptions' );

		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->use_new_version = $this->settings['use_new_version'];

		//actions
		// add_action( 'woocommerce_thankyou_'.$this->id, array( $this, 'iamport_order_detail' ) ); woocommerce_order_details_after_order_table 로 대체. 중복으로 나오고 있음

        add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
        $this->init();
	}

	protected function get_gateway_id() {
		return self::GATEWAY_ID;
	}

	public function init_form_fields() {
		parent::init_form_fields();

		$allCategories = IamportHelper::get_all_categories();

		$this->form_fields = array_merge( array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( '아임포트(카카오페이) 결제 사용. (카카오페이를 사용하시려면, <a href="https://admin.iamport.kr/settings" target="_blank">아임포트 관리자페이지의 PG설정화면</a>에서 "추가PG사"로 카카오페이를 추가 후 사용해주세요.)', 'iamport-for-woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( '카카오페이 결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'description' => array(
				'title' => __( 'Customer Message', 'woocommerce' ),
				'type' => 'textarea',
				'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
				'default' => __( '주문확정 버튼을 클릭하시면 카카오페이 결제창이 나타나 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
			),
			'show_button_on_categories' => array(
				'title' => __( '카카오페이 구매버튼을 출력할 상품카테고리', 'iamport-for-woocommerce' ),
				'description' => __( '일부 상품 카테고리에만 카카오페이 구매버튼을 출력하도록 설정할 수 있습니다.', 'iamport-for-woocommerce' ),
				'type' => 'multiselect',
				'options' => array('all'=>__('[모든 카테고리]', 'iamport-for-woocommerce')) + $allCategories,
				'default' => 'all',
			),
			'hide_button_on_categories' => array(
				'title' => __( '카카오페이 구매버튼을 비활성화시킬 상품카테고리', 'iamport-for-woocommerce' ),
				'description' => __( '일부 상품 카테고리에만 카카오페이 구매버튼을 출력하지 않도록 설정할 수 있습니다.', 'iamport-for-woocommerce' ),
				'type' => 'multiselect',
				'options' => array('none'=>__('[비활성화할 카테고리 없음]', 'iamport-for-woocommerce'), 'all'=>__('[모든 카테고리]', 'iamport-for-woocommerce')) + $allCategories,
				'default' => 'none',
			),
			'use_new_version' => array(
				'title' => __( '신규 카카오페이', 'iamport-for-woocommerce' ),
				'description' => __( '신규 카카오페이 방식으로 사용하시겠습니까? (주)카카오페이와 계약이 필요하니 사전에 확인바랍니다.', 'iamport-for-woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'LGCNS계약 종료 후 카카오페이 계약된 경우 체크해주세요.', 'iamport-for-woocommerce' ),
				'default' => 'yes'
			),
			'onetime_mid' => array(
				'title' => __( '일반결제용 CID', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( '카카오페이 일반결제에 사용될 가맹점코드(CID)를 입력해주세요.', 'iamport-for-woocommerce' ),
			),
			'recurring_mid' => array(
				'title' => __( '정기결제용 CID', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( '카카오페이 정기결제에 사용될 가맹점코드(CID)를 입력해주세요.', 'iamport-for-woocommerce' ),
			),
		), $this->form_fields, array(
			'use_manual_pg' => array(
                'title' => __( 'PG설정 구매자 선택방식 사용', 'woocommerce' ),
                'type' => 'checkbox',
                'description' => __( '(신규 카카오페이 버전에서만 사용가능) 아임포트 계정에 설정된 여러 PG사 / MID를 사용자의 선택에 따라 적용하는 기능을 활성화합니다. 카카오페이 결제수단 선택 시, 세부 결제수단 선택창이 추가로 출력됩니다.', 'iamport-for-woocommerce' ),
                'default' => 'no',
            ),
            'manual_pg_id' => array(
                'title' => __( 'PG설정 구매자 선택', 'woocommerce' ),
                'type' => 'textarea',
                'description' => __( '"{PG사 코드}.{PG상점아이디} : 구매자에게 표시할 텍스트" 의 형식으로 여러 줄 입력가능합니다.', 'iamport-for-woocommerce' ),
            ),
        ));
	}

	public function iamport_order_detail( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		$paymethod = get_post_meta($order_id, '_iamport_paymethod', true);
		$receipt_url = get_post_meta($order_id, '_iamport_receipt_url', true);
		$vbank_name = get_post_meta($order_id, '_iamport_vbank_name', true);
		$vbank_num = get_post_meta($order_id, '_iamport_vbank_num', true);
		$vbank_date = get_post_meta($order_id, '_iamport_vbank_date', true);
		$tid = $order->get_transaction_id();

        ob_start();
		?>
		<h2><?=__( '결제 상세', 'iamport-for-woocommerce' )?></h2>
		<table class="shop_table order_details">
			<tbody>
				<tr>
					<th><?=__( '결제수단', 'iamport-for-woocommerce' )?></th>
					<td><?=__( '카카오페이', 'iamport-for-woocommerce' )?></td>
				</tr>
				<tr>
					<th><?=__( '매출전표', 'iamport-for-woocommerce' )?></th>
					<td><a target="_blank" href="<?=$receipt_url?>"><?=sprintf( __( '영수증보기(%s)', 'iamport-for-woocommerce' ), $tid )?></a></td>
				</tr>
			</tbody>
		</table>
		<?php
		ob_end_flush();
	}

	public function iamport_payment_info( $order_id ) {
		require_once(dirname(__FILE__).'/lib/IamportHelper.php');

		$response = parent::iamport_payment_info($order_id);
		$useNewVersion = $this->use_new_version === "yes";
		$useManualPg = filter_var($this->settings['use_manual_pg'], FILTER_VALIDATE_BOOLEAN);
		
		if ( $useNewVersion ) {
			$response['pg'] = 'kakaopay';
			if ( $this->has_subscription($order_id) ) { //정기결제
				if ( $this->settings["recurring_mid"] )	$response["pg"] = sprintf("%s.%s", $response["pg"], $this->settings["recurring_mid"]);

				//customer_uid를 생성해서 js에 전달 및 post_meta에 미리 저장해둠
				$order = new WC_Order( $order_id );
				$customer_uid = IamportHelper::get_customer_uid($order);

				$this->_iamport_post_meta($order_id, '_customer_uid_reserved', $customer_uid); //post meta에 저장(예비용 customer_uid. 아직 빌링키까지 등록안됐으므로)
				$response['customer_uid'] = $customer_uid; //js에 전달
			} else { //1회 결제
				if ( $this->settings["onetime_mid"] )		$response["pg"] = sprintf("%s.%s", $response["pg"], $this->settings["onetime_mid"]);
			}
		} else {
			$response['pg'] = 'kakao';
		}

		$response['pay_method'] = $useNewVersion & $useManualPg ? 'kakaopay' : 'card';
		
		return $response;
	}

	public function payment_fields()
    {
        parent::payment_fields(); //description 출력

        $useManualPg = filter_var($this->settings['use_manual_pg'], FILTER_VALIDATE_BOOLEAN);
		$useNewVersion = $this->use_new_version === "yes";

        if ($useManualPg && $useNewVersion) {
            echo IamportHelper::htmlSecondaryPaymentMethod($this->settings['manual_pg_id']);
        }
    }

	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		require_once(dirname(__FILE__).'/lib/IamportHelper.php');
		require_once(dirname(__FILE__).'/lib/iamport.php');

		global $wpdb;

		error_log('######## SCHEDULED ########');
		$creds = $this->getRestInfo(null, false); //this->imp_rest_key, this->imp_rest_secret사용하도록
		$customer_uid = IamportHelper::get_customer_uid( $renewal_order );
		$response = $this->doPayment($creds, $renewal_order, $amount_to_charge, $customer_uid, $renewal_order->suspension_count );

		if ( is_wp_error( $response ) ) {
			$old_status = $renewal_order->get_status();
			$renewal_order->update_status( 'failed', sprintf( __( '정기결제승인에 실패하였습니다. (상세 : %s)', 'iamport-for-woocommerce' ), $response->get_error_message() ) );

			//fire hook
			do_action('iamport_order_status_changed', $old_status, $renewal_order->get_status(), $renewal_order);
		} else {
			$recur_imp_uid = 'unknown imp_uid';

			if ($response instanceof WooIamportPayment) {
				$recur_imp_uid = $response->imp_uid;

				$order_id = $renewal_order->id;

				$this->_iamport_post_meta($order_id, '_iamport_rest_key', $creds['imp_rest_key']);
				$this->_iamport_post_meta($order_id, '_iamport_rest_secret', $creds['imp_rest_secret']);
				$this->_iamport_post_meta($order_id, '_iamport_provider', $response->pg_provider);
				$this->_iamport_post_meta($order_id, '_iamport_paymethod', $response->pay_method);
				$this->_iamport_post_meta($order_id, '_iamport_pg_tid', $response->pg_tid);
				$this->_iamport_post_meta($order_id, '_iamport_receipt_url', $response->receipt_url);
				$this->_iamport_post_meta($order_id, '_iamport_customer_uid', $customer_uid);
				$this->_iamport_post_meta($order_id, '_iamport_recurring_md', date('md'));
			}

			try {
				$wpdb->query("BEGIN");
				//lock the row
				$synced_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$order_id} FOR UPDATE");

				if ( !$this->has_status($synced_row->post_status, wc_get_is_paid_statuses()) ) {
					$renewal_order->payment_complete( $recur_imp_uid ); //imp_uid

					$wpdb->query("COMMIT");

					//fire hook
					do_action('iamport_order_status_changed', $synced_row->post_status, $renewal_order->get_status(), $renewal_order);

					$renewal_order->add_order_note( sprintf( __( '정기결제 회차 과금(%s차결제)에 성공하였습니다. (imp_uid : %s)', 'iamport-for-woocommerce' ) , $renewal_order->suspension_count, $recur_imp_uid ) );

					//fire hook
					do_action('iamport_order_status_changed', $synced_row->post_status, $renewal_order->get_status(), $renewal_order);
				} else {
					$wpdb->query("ROLLBACK");
					//이미 이뤄진 주문

					$renewal_order->add_order_note( sprintf( __( '이미 결제완료처리된 주문에 대해서 결제가 발생했습니다. (imp_uid : %s)', 'iamport-for-woocommerce' ) , $recur_imp_uid ) );
				}

				return;
			} catch(Exception $e) {
				$wpdb->query("ROLLBACK");
			}
		}
	}

	private function doPayment($creds, $order, $total, $customer_uid, $number_of_tried = 0) {
		if ( $total == 0 )	return true;

		require_once(dirname(__FILE__).'/lib/iamport.php');

		$is_initial_payment = $number_of_tried === 0; //항상 false 임

		$order_suffix = $is_initial_payment ? '_sf' : date('md');//빌링키 발급때 사용된 merchant_uid중복방지
		$tax_free_amount = IamportHelper::get_tax_free_amount($order);
		$notice_url = IamportHelper::get_notice_url();

		$iamport = new WooIamport($creds['imp_rest_key'], $creds['imp_rest_secret']);
		$pay_data = array(
			'amount' => $total,
			'merchant_uid' => $order->get_order_key() . $order_suffix,
			'customer_uid' => $customer_uid,
			'name' => $this->get_order_name($order, $is_initial_payment),
			'buyer_name' => trim($order->get_billing_last_name() . $order->get_billing_first_name()),
			'buyer_email' => $order->get_billing_email(),
			'buyer_tel' => $order->get_billing_phone()
		);
		if ( empty($pay_data["buyer_name"]) )	$pay_data["buyer_name"] = $this->get_default_user_name();
		if ( wc_tax_enabled() )	$pay_data["tax_free"] = $tax_free_amount;
		if ( $notice_url )			$pay_data["notice_url"] = $notice_url;

		$result = $iamport->sbcr_again($pay_data);

		$payment_data = $result->data;
		if ( $result->success ) {
			if ( $payment_data->status == 'paid' ) {
				return $payment_data;
			} else {
				if ( $is_initial_payment ) {
					$message = sprintf( __( '정기결제 최초 과금(signup fee)에 실패하였습니다(%s). (사유 : %s)', 'iamport-for-woocommerce' ) , $payment_data->status, $payment_data->fail_reason );
				} else {
					$message = sprintf( __( '정기결제 회차 과금(%s차결제)에 실패하였습니다(%s). (사유 : %s)', 'iamport-for-woocommerce' ) , $number_of_tried, $payment_data->status, $payment_data->fail_reason );
				}

				return new WP_Error( 'iamport_error', $message );
			}
		} else {
			if ( $is_initial_payment ) {
				$message = sprintf( __( '정기결제 최초 과금(signup fee)에 실패하였습니다(%s). (사유 : %s)', 'iamport-for-woocommerce' ) , $payment_data->status, $result->error['message'] );
			} else {
				$message = sprintf( __( '정기결제 회차 과금(%s차결제)에 실패하였습니다(%s). (사유 : %s)', 'iamport-for-woocommerce' ) , $number_of_tried, $payment_data->status, $result->error['message'] );
			}

			return new WP_Error( 'iamport_error', $message );
		}

		return new WP_Error( 'iamport_error', 'unknown error' );
	}

	public function is_paid_confirmed($order, $payment_data) {
		add_action( 'iamport_post_order_completed', array($this, 'update_customer_uid'), 10, 2 ); //불필요하게 hook이 많이 걸리지 않도록(naver-gateway객체를 여러 군데에서 생성한다.)

		return parent::is_paid_confirmed($order, $payment_data);
	}

	public function update_customer_uid($order, $payment_data) {
		$customer_uid = get_post_meta($order->get_id(), '_customer_uid_reserved', true);
		$this->_iamport_post_meta($order->get_id(), '_customer_uid', $customer_uid); //성공한 customer_uid저장
	}

	public function get_display_categories() {
		if ( !isset($this->settings['show_button_on_categories']) )		return 'all';

		$categories = $this->settings['show_button_on_categories'];
		if ( $categories === 'all' || in_array('all', $categories) )	return 'all';

		return $categories;
	}

	public function get_disabled_categories() {
		if ( !isset($this->settings['hide_button_on_categories']) )	return array();

		$categories = $this->settings['hide_button_on_categories'];
		if ( $categories === 'all' || in_array('all', $categories) )		return 'all';
		if ( $categories === 'none' || in_array('none', $categories) )	return array();

		return $categories;
	}

	protected function get_order_name($order, $initial_payment=true) {
		if ( $this->has_subscription($order->get_id()) ) {

			if ( $initial_payment ) {
				$order_name = "#" . $order->get_order_number() . "번 주문 정기결제(최초과금)";
			} else {
				$order_name = "#" . $order->get_order_number() . sprintf("번 주문 정기결제(%s회차)", $order->suspension_count);
			}

			$order_name = apply_filters('iamport_recurring_order_name', $order_name, $order, $initial_payment);

			return $order_name;

		}

		return parent::get_order_name($order);
	}

	private function has_subscription( $order_id ) {
		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) );
	}

	public function init() {
        add_filter( 'woocommerce_available_payment_gateways', array($this, 'kakao_unset_gateway_by_category'));
    }

	private function all_products_in_categories($product_ids, $categories) {
		$all_match = true;
		foreach ($product_ids as $id) {
			$all_match = $all_match && IamportHelper::is_product_in_categories($id, $categories);
		}

		return $all_match;
	}

    private static function is_product_purchasable($product, $disabled_categories) {
        $is_disabled = $disabled_categories === 'all' || IamportHelper::is_product_in_categories($product->get_id(), $disabled_categories);

        return 	!$is_disabled;
    }

    public function kakao_unset_gateway_by_category($available_gateways) {
        if ( !is_checkout() ) return $available_gateways;
        $cart_items = WC()->cart->get_cart();

		// 정기결제 결제수단 변경의 경우 subscription id 가 change_payment_method에 포함되어 URL이 세팅됨
		if (class_exists('WC_Subscriptions_Change_Payment_Gateway') && WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment) {
			$subscription_obj = wcs_get_subscription($_GET['change_payment_method']);
			$cart_items = $subscription_obj ->get_items();
		}

        $categories = $this->get_display_categories();
        $disabled_categories = $this->get_disabled_categories();
        $product_ids = array();
        $enabled = true;

        foreach ($cart_items as $key => $item) {
            $product = wc_get_product($item["product_id"]);
            $product_ids[] = $product->get_id();

            if (!self::is_product_purchasable($product, $disabled_categories)) {
                $enabled = false;
                break;
            }

        }

        if (!$enabled || !($categories === 'all' || $this->all_products_in_categories($product_ids, $categories))) unset($available_gateways['iamport_kakao']);

        return $available_gateways;
    }
}
