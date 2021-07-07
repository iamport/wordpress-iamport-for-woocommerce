<?php
class WC_Gateway_Iamport_Subscription_Ex extends Base_Gateway_Iamport {

    const GATEWAY_ID = 'iamport_subscription_ex';

	public function __construct() {
		parent::__construct();

		//settings
		$this->method_title = __( '아임포트(정기결제-결제창방식)', 'iamport-for-woocommerce' );
		$this->method_description = __( 'KG이니시스, JTNet, 다날과 같이 결제창을 통해 빌링키 발급이 이뤄지는 정기결제의 경우 본 결제 방식을 통해 정기결제를 이용하실 수 있습니다. (정기결제 기능을 위해서는 Woocommerce-Subscription 플러그인 설치가 필요합니다.)', 'iamport-for-woocommerce' );
		$this->has_fields = true;
		$this->supports = array( 'subscriptions', 'subscription_reactivation'/*이것이 있어야 subscription 후 active상태로 들어갈 수 있음*/, 'subscription_suspension', 'subscription_cancellation' , 'refunds', 'subscription_date_changes', 'subscription_amount_changes', 'multiple_subscriptions' );

		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->pg_provider = $this->settings['pg_provider'];
		$this->pg_id 	     = $this->settings['pg_id'];

		//woocommerce action
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_cancelled_' . $this->id, array( $this, 'cancelled_subscription' ), 10, 1 );
	}

	protected function get_gateway_id() {
		return self::GATEWAY_ID;
	}

	public function init_form_fields() {
		parent::init_form_fields();

		$this->form_fields = array_merge( array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( '아임포트(정기결제-결제창방식) 결제 사용', 'iamport-for-woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( '아임포트 정기결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'description' => array(
				'title' => __( 'Customer Message', 'woocommerce' ),
				'type' => 'textarea',
				'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
				'default' => __( '주문확정 버튼을 클릭하시면 PG사에서 제공하는 신용카드 정보 입력 결제창이 나타나 정기결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
			),
			'pg_provider' => array(
				'title' => __( 'PG사 설정', 'iamport-for-woocommerce' ),
				'type' => 'select',
				'default' => '',
				'description' => __( '이용 중인 PG사를 반드시 선택해주세요.', 'iamport-for-woocommerce' ),
				'options' => array(
					'none' => '해당사항없음',
					'html5_inicis' => 'KG이니시스(신용카드)-빌링결제',
					'kcp_billing' => 'KCP-빌링결제',
					'jtnet' => 'JTNet(신용카드)-빌링결제',
					'danal_tpay' => '다날(신용카드)-빌링결제',
					'danal' => '다날(휴대폰)-빌링결제',
					'mobilians' => '모빌리언스(휴대폰)-빌링결제',
				)
			),
			'pg_id' => array(
				'title' => __( 'PG상점아이디', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '동일한 PG사에서 일반 신용카드 결제도 함께 사용하는 경우 정기결제용으로 발급받은 PG상점아이디(MID)를 별도로 기재해주셔야 합니다.', 'iamport-for-woocommerce' ),
			)
		), $this->form_fields);
	}

	public function iamport_order_detail( $order_id ) {
        $order = wc_get_order( $order_id );

        $paymethod = get_post_meta($order_id, '_iamport_paymethod', true);
        $receipt_url = get_post_meta($order_id, '_iamport_receipt_url', true);
        $tid = $order->get_transaction_id();

        ob_start();
        ?>

        <h2><?=__( '결제 상세', 'iamport-for-woocommerce' )?></h2>
        <table class="shop_table order_details">
            <tbody>
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
		$iamport_info = parent::iamport_payment_info( $order_id );

		//customer_uid를 생성해서 js에 전달 및 post_meta에 미리 저장해둠
		$order = new WC_Order( $order_id );
		$customer_uid = $this->get_customer_uid($order);

		if ( !$this->isEmptyProvider() && !empty($this->pg_id) ) $iamport_info['pg'] = $this->pg_provider.'.'.$this->pg_id; //TODO : [2019-08-12] 왜 pg_id까지 체크하지...
		$iamport_info['customer_uid'] = $customer_uid; //js에 전달

		if ( $this->isMobile() && $this->pg_provider === 'html5_inicis' ) {
			$iamport_info['amount'] = 0; //JTNet, 다날-휴대폰, 다날-신용카드, 이니시스-PC결제의 경우 원래 가격 그대로 설정
		}

		if (!$this->isEmptyProvider() && $this->pg_provider == 'mobilians') {
		    $iamport_info['pay_method'] = 'phone';
        }

		$this->_iamport_post_meta($order_id, '_customer_uid_reserved', $customer_uid); //post meta에 저장(예비용 customer_uid. 아직 빌링키까지 등록안됐으므로)

        //[2019-12-02] 빌링키 발급 후 SignupFee 결제해야하는데, 웹훅이 먼저 도달하는 경우(통상적으로 wc-api=WC_Gateway_Iamport_Vbank 를 지정하므로 this.check_payment_response() 를 타지 못하고 IamportPlugin.check_payment_response 를 타서 결제완료처리만 되어버리는 경우가 생김)
        // 정상적인 Webhook 주소를 강제로 지정하여 this.check_payment_response() 를 타도록 수정
        $iamport_info['notice_url']= add_query_arg( array('wc-api'=>get_class( $this )), $order->get_checkout_payment_url());

		return $iamport_info;
	}

	public function is_available() {
		return parent::is_available() && !empty( WC()->cart->recurring_carts );
	}

	// 빌링키 발급이 성공적으로 이뤄졌는지 체크하는 부분
	// #1. woocommerce 결제 프로세스시 전달되는 데이터
	/**
	* 	[pay_for_order] => true
	* 	[key] => wc_order_5747ba9d89c1c
	* 	[order_id] => 628
	*  	[wc-api] => WC_Gateway_Iamport_Card
	*	[imp_uid] => imp_414622838033
	*/

	// #2. Notification URL에 의해 전달되는 데이터
	/**
	*	[imp_uid] => imp_414622838033
	* 	[merchant_uid] => wc_orderx_65723e22924514023
	*/
	public function check_payment_response() {
		global $woocommerce, $wpdb;

		$http_method = $_SERVER['REQUEST_METHOD'];
		$http_param = array(
			'imp_uid' => $this->http_param('imp_uid', $http_method),
			'merchant_uid' => $this->http_param('merchant_uid', $http_method),
			'order_id' => $this->http_param('order_id', $http_method)
		);

		$called_from_iamport = empty($http_param['order_id']); //wp_redirect 안하기 위해서 boolean 기록

		if ( !empty($http_param['imp_uid']) ) {
			//결제승인 결과조회
			require_once(dirname(__FILE__).'/lib/iamport.php');

			$imp_uid = $http_param['imp_uid'];

			//Gateway마다 다른 key/secret을 가질 수 있으므로 현재 Gateway를 확인하고처리
			$creds = $this->getRestInfo($http_param['merchant_uid'], $called_from_iamport);

			$iamport = new WooIamport($creds['imp_rest_key'], $creds['imp_rest_secret']);
			$result = $iamport->findByImpUID($imp_uid);

			$shouldRetry = true;

			if ( $result->success ) {
				$payment_data = $result->data;

				//보안상 REST API로부터 받아온 merchant_uid에서 order_id를 찾아내야한다.(GET파라메터의 order_id를 100%신뢰하지 않도록)
				$order_id = wc_get_order_id_by_order_key( $payment_data->merchant_uid );
                $gateway = wc_get_payment_gateway_by_order($order_id);

				$order = new WC_Order( $order_id );

				//Back버튼 등으로 결제완료 후 해당 페이지에 다시 진입할 수도 있다.
				if ( $this->isAlreadyPaidOrder($order_id) )		return wp_redirect( $this->get_return_url($order) );

				$this->_iamport_post_meta($order_id, '_iamport_rest_key', $creds['imp_rest_key']);
				$this->_iamport_post_meta($order_id, '_iamport_rest_secret', $creds['imp_rest_secret']);

				if ( $payment_data->status === 'paid' ) {
					if ( $called_from_iamport )	exit('Ignored'); //빌링키 발급 후 전송되는 Notification 은 무시

					// 1. 빌링키 발급에 성공
					$customer_uid = get_post_meta($order_id, '_customer_uid_reserved', true);
					$this->_iamport_post_meta($order_id, '_customer_uid', $customer_uid); //성공한 customer_uid저장

					if ( in_array($payment_data->pg_provider, array('jtnet', 'danal', 'danal_tpay', 'mobilians')) ) { //JTNet, 다날-휴대폰 소액결제 정기결제, 다날-신용카드, 모빌리언스-휴대폰 소액결제 정기결제는 이미 결제를 하고 돌아옴
						$response = $this->doFakePayment( $payment_data, $order->get_total() );
					} else {
						$response = $this->doPayment( $creds, $order, $order->get_total(), $customer_uid );
						if ($response === true) { //signup fee == 0 and free trial 인 경우 true가 반환됨
						    $response = $payment_data;
                        }
					}

					//$response 는 (1) WP_Error (2) WooIamportPayment 둘 중 하나여야 한다.(직전 라인에서 true는 WooIamportPayment로 대체되었음)
					if ( is_wp_error($response) ) {
						$old_status = $order->get_status();

						$order->add_order_note( $response->get_error_message() );
						$order->update_status( 'failed', $response->get_error_message() );

						//fire hook
						do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);
					} else {
						// 2. 발급된 빌링키로 SIGNUP-FEE 결제성공
                        $shouldRetry = false;
						$signup_imp_uid = $response->imp_uid;

						$this->_iamport_post_meta($order_id, '_iamport_provider', $response->pg_provider);
						$this->_iamport_post_meta($order_id, '_iamport_paymethod', $response->pay_method);
						$this->_iamport_post_meta($order_id, '_iamport_pg_tid', $response->pg_tid);
						$this->_iamport_post_meta($order_id, '_iamport_receipt_url', $response->receipt_url);

						try {
							$wpdb->query("BEGIN");
							//lock the row
							$synced_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$order_id} FOR UPDATE");

							if ( !$this->has_status($synced_row->post_status, wc_get_is_paid_statuses()) ) {
                                $order->set_payment_method( $gateway );
                                $order->payment_complete( $signup_imp_uid ); //imp_uid

								$wpdb->query("COMMIT");

								//fire hook
								do_action('iamport_order_status_changed', $synced_row->post_status, $order->get_status(), $order);

								if ($order->get_total() == 0) {
                                    $note = sprintf( __( '정기결제 결제수단 등록에 성공하였습니다. (imp_uid : %s)', 'iamport-for-woocommerce' ) , $signup_imp_uid );
                                } else {
                                    $note = sprintf( __( '정기결제 최초 과금(signup fee)에 성공하였습니다. (imp_uid : %s)', 'iamport-for-woocommerce' ) , $signup_imp_uid );
                                }
								$order->add_order_note( $note );
								wc_add_notice($note);

								//fire hook
								do_action('iamport_order_status_changed', $synced_row->post_status, $order->get_status(), $order);

								wp_redirect( $this->get_return_url($order) );
							} else {
								$wpdb->query("ROLLBACK");
								//이미 이뤄진 주문

								$note = sprintf( __( '이미 결제완료처리된 주문에 대해서 결제가 발생했습니다. (imp_uid : %s)', 'iamport-for-woocommerce' ) , $signup_imp_uid );
								$order->add_order_note( $note );
								wc_add_notice($note, 'error');

								wp_redirect( $this->get_return_url($order) );
							}

							return;
						} catch(Exception $e) {
							$wpdb->query("ROLLBACK");
						}
					}
				} else if ( $payment_data->status == 'ready' ) {
					$note = __( '빌링키 발급에 실패하였습니다.', 'iamport-for-woocommerce' );

					$order->add_order_note( $note );
					wc_add_notice( $note, 'error');
				} else if ( $payment_data->status == 'failed' ) {
					$note = __( '빌링키 발급에 실패하였습니다.', 'iamport-for-woocommerce' ) . "({$payment_data->fail_reason})";

					$order->add_order_note( $note );
					wc_add_notice( $note, 'error');
				} else if ( $payment_data->status == 'cancelled' ) {
					//아임포트 관리자 페이지에서 취소하여 Notification이 발송된 경우도 대응
					try {
						$wpdb->query("BEGIN");
						//lock the row
						$synced_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$order_id} FOR UPDATE");

						$order = new WC_Order( $order_id ); //lock잡은 후 호출(2017-01-16 : 의미없음. [1.6.8] synced_row의 값을 활용해서 status체크해야 함)

						if ( !$this->has_status($synced_row->post_status, array('cancelled', 'refunded')) ) {
							$amountLeft = $payment_data->amount > $payment_data->cancel_amount; //취소할 잔액이 남음

							if ( $amountLeft ) { //한 번 더 환불이 가능함. 다음 번 환불이 가능하도록 status는 바꾸지 않음
								$len = count($payment_data->cancel_history); // always > 0
								$increment = $len - count($order->get_refunds());

								for ($i=0; $i < $increment; $i++) {
									$cancelItem = $payment_data->cancel_history[$len-$increment+$i];

									// 취소내역을 만들어줌 (부분취소도 대응가능)
									$refund = wc_create_refund( array(
										'amount'     => $cancelItem->amount,
										'reason'     => $cancelItem->reason,
										'order_id'   => $order_id
									) );

									if ( is_wp_error( $refund ) ) {
										$order->add_order_note( $refund->get_error_message() );
									} else {
										$order->add_order_note( sprintf(__( '아임포트 관리자 페이지(https://admin.iamport.kr)에서 부분취소(%s원)하였습니다.', 'iamport-for-woocommerce' ), number_format($cancelItem->amount)) );
									}
								}
							} else {
								$order->update_status( 'refunded' ); //imp_uid
								$order->add_order_note( __( '아임포트 관리자 페이지(https://admin.iamport.kr)에서 취소하여 우커머스 결제 상태를 "환불됨"으로 수정합니다.', 'iamport-for-woocommerce' ));

								//fire hook
								do_action('iamport_order_status_changed', $synced_row->post_status, $order->get_status(), $order);
							}

							$wpdb->query("COMMIT");

							do_action('iamport_order_status_changed', $synced_row->post_status, $order->get_status(), $order);
						} else {
							$wpdb->query("ROLLBACK");
						}

						$called_from_iamport ? exit('Refund Information Saved') : wp_redirect( $this->get_return_url($order) );
						return;
					} catch(Exception $e) {
						$wpdb->query("ROLLBACK");
					}
				}
			} else {
				$note = sprintf(__( '결제승인정보를 받아오지 못했습니다. 관리자에게 문의해주세요. %s', 'iamport-for-woocommerce' ), $result->error['message']);

				if ( !empty($http_param['order_id']) ) {
					$order = new WC_Order( $http_param['order_id'] );

					$old_status = $order->get_status();

					$order->update_status('failed');
					$order->add_order_note( $note );

					//fire hook
					do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);
				}
				wc_add_notice($note, 'error');
			}

			if ( !empty($order) ) {
				$default_redirect_url = $order->get_checkout_payment_url( true );
			} else {
				$default_redirect_url = '/';
			}

			if ($called_from_iamport) {
                exit('IamportForWoocommerce 2.2.17');
            } else {
			    if ($shouldRetry) {
			        $default_redirect_url = add_query_arg(array('pay_for_order'=>'true'), $default_redirect_url);
                }

                wp_redirect( $default_redirect_url );
            }
		} else {
			//just test(아임포트가 지원하는대로 호출되지 않음)
			exit( 'IamportForWoocommerce 2.2.17' );
		}
	}

	private function doPayment($creds, $order, $total, $customer_uid, $number_of_tried = 0) {
		if ( $total == 0 )	return true;

		require_once(dirname(__FILE__).'/lib/iamport.php');

		$is_initial_payment = $number_of_tried === 0;

		$order_suffix = $is_initial_payment ? '_sf' : date('md');//빌링키 발급때 사용된 merchant_uid중복방지

		$iamport = new WooIamport($creds['imp_rest_key'], $creds['imp_rest_secret']);
		$notice_url = IamportHelper::get_notice_url();
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
		if ( wc_tax_enabled() )	$pay_data["tax_free"] = IamportHelper::get_tax_free_amount($order);
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

	private function doFakePayment($payment_data, $total) {
		//빌링키 발급과 동시에 결제가 이뤄져버린 경우에는 결제금액이 맞는지만 체크하고, 원거래 수신데이터를 빌링키를 통해 결제된 것처럼 반환한다.

		if ( $payment_data->amount == $total ) {
			return $payment_data; //원 거래를 빌링키 발급 후 결제인 것처럼 반환해 process가 이어질 수 있도록 처리한다
		}

		return new WP_Error( 'iamport_error', sprintf( __( '정기결제 최초 과금(signup fee)시 금액 검증에 실패하였습니다(요청 결제금액 : %s, 실제 결제금액 : %s)', 'iamport-for-woocommerce' ) , number_format($total), number_format($payment_data->amount) ) );
	}

	private function isAlreadyPaidOrder($order_id) {
		global $wpdb;

		$alreadyPaid = false;

		try {
			$wpdb->query("BEGIN");
			//lock the row
			$synced_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$order_id} FOR UPDATE");

			$alreadyPaid = $this->has_status($synced_row->post_status, wc_get_is_paid_statuses());
		} catch(Exception $e) {} //finally 는 5.5부터 지원됨. 5.5미만 환경이 있을 수 있으므로 finally block은 사용하지 않는다.

		$wpdb->query("ROLLBACK");

		return $alreadyPaid;
	}

	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		require_once(dirname(__FILE__).'/lib/iamport.php');

		global $wpdb;

		error_log('######## SCHEDULED ########');
		$creds = $this->getRestInfo(null, false); //this->imp_rest_key, this->imp_rest_secret사용하도록
		$customer_uid = $this->get_customer_uid( $renewal_order );
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

	//common for refund
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

        $refundTaxFree = intval($_POST['iamport_refund_taxfree']);
        if ($refundTaxFree > 0) {
            $cancel_data['tax_free'] = $refundTaxFree;
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

	public function cancelled_subscription($subscription) {
		/* 동일한 사람이 여러 상품의 subscription도 할 수 있으므로 삭제하지 않는다.
		require_once(dirname(__FILE__).'/lib/iamport.php');

		$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);
		$customer_uid 	 = $this->get_customer_uid($subscription);

		$result = $iamport->customer_delete($customer_uid);
		if ( $result->success ) {
			$subscription->add_order_note( '정기결제 등록정보를 삭제하였습니다.' );
		} else {
			$subscription->add_order_note( '정기결제 등록정보 삭제에 실패하였습니다.' );
		}
		*/
	}

	protected function get_order_name($order, $initial_payment=true) {
	    $base_order_name = parent::get_order_name($order);

		if ( $initial_payment ) {
		    //[2018-12-28] 최초 결제 금액이 0원인 경우 "최초과금"이란 표현대신 "카드등록" 이라는 표현 사용
            $total = $order->get_total();
            if ($total == 0) {
                $order_name = $base_order_name . "-정기결제(카드등록)";
            } else {
                $order_name = $base_order_name . "-정기결제(최초과금)";
            }
		} else {
			$order_name = sprintf("%s-정기결제(%s회차)", $base_order_name, $order->suspension_count);
		}

		$order_name = apply_filters('iamport_recurring_order_name', $order_name, $order, $initial_payment);

		return $order_name;
	}

	private function get_customer_uid($order) {
		$prefix = get_option('_iamport_customer_prefix');
		if ( empty($prefix) ) {
			require_once( ABSPATH . 'wp-includes/class-phpass.php');
			$hasher = new PasswordHash( 8, false );
			$prefix = md5( $hasher->get_random_bytes( 32 ) );

			if ( !add_option( '_iamport_customer_prefix', $prefix ) )	throw new Exception( __( "정기결제 구매자정보 생성에 실패하였습니다.", 'iamport-for-woocommerce' ), 1);
		}

		$user_id = $order->get_user_id(); // wp_cron에서는 get_current_user_id()가 없다.
		if ( empty($user_id) )		throw new Exception( __( "정기결제기능은 로그인된 사용자만 사용하실 수 있습니다.", 'iamport-for-woocommerce' ), 1);

		return $prefix . 'c' . $user_id;
	}

	private function isEmptyProvider() {
		return empty($this->pg_provider) || $this->pg_provider == 'none';
	}

}
