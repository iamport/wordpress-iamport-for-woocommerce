<?php
class WC_Gateway_Iamport_Kpay extends Base_Gateway_Iamport {

    const GATEWAY_ID = "iamport_kpay";

	public function __construct() {
		parent::__construct();

		//settings
		$this->method_title = __( '아임포트(KPay)', 'iamport-for-woocommerce' );
		$this->method_description = __( '=> 아임포트 서비스를 이용해 KG이니시스의 간편결제인 KPay를 연동할 수 있습니다.<br>=> [아임포트] X PG사 제휴할인혜택을 받아보세요! <a href="http://www.iamport.kr/pg#promotion" target="_blank">PG 신규계약 프로모션 안내</a><br>=> 아임포트의 최신 공지사항도 놓치지 마세요! <a href="http://www.iamport.kr/notice" target="_blank">공지사항보기</a>', 'iamport-for-woocommerce' );
		$this->has_fields = true;
		$this->supports = array( 'products', 'refunds' );

		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];

		//actions
		// add_action( 'woocommerce_thankyou_'.$this->id, array( $this, 'iamport_order_detail' ) ); woocommerce_order_details_after_order_table 로 대체. 중복으로 나오고 있음
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
				'label' => __( '아임포트(KPay) 결제 사용', 'iamport-for-woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( 'KPay 결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'description' => array(
				'title' => __( 'Customer Message', 'woocommerce' ),
				'type' => 'textarea',
				'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
				'default' => __( '주문확정 버튼을 클릭하시면 신용카드 결제창이 나타나 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
			)
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
					<td><?=__( '신용카드(KPay)', 'iamport-for-woocommerce' )?></td>
				</tr>
				<tr>
					<th><?=__( '매출전표', 'iamport-for-woocommerce' )?></th>
					<td><a target="_blank" href="<?=$receipt_url?>"><?=sprintf( __( '영수증보기(%s)', 'iamport-for-woocommerce'), $tid )?></a></td>
				</tr>
			</tbody>
		</table>
		<?php 
		ob_end_flush();
	}

	public function iamport_payment_info( $order_id ) {
		$response = parent::iamport_payment_info($order_id);
		$response['pg'] = 'html5_inicis';
		$response['pay_method'] = 'kpay';

		return $response;
	}

}