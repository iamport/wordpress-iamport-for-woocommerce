<?php
class WC_Gateway_Iamport_Paypal extends Base_Gateway_Iamport {

    const GATEWAY_ID = 'iamport_paypal';

	public function __construct() {
		parent::__construct();

		//settings
		$this->method_title = __( '아임포트(Paypal)', 'iamport-for-woocommerce' );
		$this->method_description = __( '아임포트 서비스를 이용해 Paypal결제를 사용할 수 있습니다.', 'iamport-for-woocommerce' );
		$this->has_fields = true;
		$this->supports = array( 'products', 'refunds');

		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];

		//actions
		// add_action( 'woocommerce_thankyou_'.$this->id, array( $this, 'iamport_order_detail' ) ); woocommerce_order_details_after_order_table 로 대체. 중복으로 나오고 있음

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
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
				'label' => __( '아임포트(Paypal) 결제 사용. (Paypal을 사용하시려면, <a href="https://www.paypal.com/kr/webapps/mpp/merchant">Paypal 홈페이지</a>에서 판매자 계정 생성 후, <a href="https://admin.iamport.kr/settings" target="_blank">아임포트 관리자페이지의 PG설정화면</a>에서 "추가PG사"로 Paypal을 추가 후 사용해주세요.)', 'iamport-for-woocommerce' ),
				'default' => 'no'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( 'Paypal 결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'description' => array(
				'title' => __( 'Customer Message', 'woocommerce' ),
				'type' => 'textarea',
				'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
				'default' => __( '주문확정 버튼을 클릭하시면 Paypal 결제페이지로 이동하여 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
			),
		), $this->form_fields);
	}

	public function iamport_order_detail( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		$paymethod = get_post_meta($order_id, '_iamport_paymethod', true);
		$receipt_url = get_post_meta($order_id, '_iamport_receipt_url', true);
		$tid = $order->get_transaction_id();

        ob_start();
		?>
		<h2><?=__( '결제 상세', 'iamport-for-woocommerce' )?></h2>
		<table class="shop_table order_details">
			<tbody>
				<tr>
					<th><?=__( '결제수단', 'iamport-for-woocommerce' )?></th>
					<td><?=__( 'Paypal', 'iamport-for-woocommerce' )?></td>
				</tr>
			</tbody>
		</table>
		<?php
		ob_end_flush();
	}

	public function iamport_payment_info( $order_id ) {
		require_once(dirname(__FILE__).'/lib/IamportHelper.php');

		$supportedCurrency = array('AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'INR', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'THB', 'USD');

        $order = wc_get_order($order_id);
        if ( !in_array($order->get_currency(), $supportedCurrency) ) {
            throw new Exception('PayPal does not support your order currency.');
        }

		$response = parent::iamport_payment_info($order_id);
		$response['pg'] = 'paypal';
        $response['notice_url']= add_query_arg( array('wc-api'=>get_class( $this )), $order->get_checkout_payment_url());

		return $response;
	}

}
