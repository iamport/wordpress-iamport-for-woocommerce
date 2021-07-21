<?php
class WC_Gateway_Iamport_Payco extends Base_Gateway_Iamport {

    const GATEWAY_ID = 'iamport_payco';

	public function __construct() {
		parent::__construct();

		//settings
		$this->method_title = __( '아임포트(PAYCO)', 'iamport-for-woocommerce' );
		$this->method_description = __( '=> 아임포트 서비스를 이용해 결제모듈을 연동할 수 있습니다.<br>=> [아임포트] X PG사 제휴할인혜택을 받아보세요! <a href="http://www.iamport.kr/pg#promotion" target="_blank">PG 신규계약 프로모션 안내</a><br>=> 아임포트의 최신 공지사항도 놓치지 마세요! <a href="http://www.iamport.kr/notice" target="_blank">공지사항보기</a>', 'iamport-for-woocommerce' );
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
				'label' => __( '아임포트(PAYCO) 결제 사용. (PAYCO를 사용하시려면, <a href="https://admin.iamport.kr/settings" target="_blank">아임포트 관리자페이지의 PG설정화면</a>에서 "추가PG사"로 PAYCO를 추가 후 사용해주세요.)', 'iamport-for-woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( 'PAYCO 결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'description' => array(
				'title' => __( 'Customer Message', 'woocommerce' ),
				'type' => 'textarea',
				'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
				'default' => __( '주문확정 버튼을 클릭하시면 PAYCO 결제창이 나타나 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
			),
		), $this->form_fields, array(
			'use_manual_pg' => array(
                'title' => __( 'PG설정 구매자 선택방식 사용', 'woocommerce' ),
                'type' => 'checkbox',
                'description' => __( '아임포트 계정에 설정된 여러 PG사 / MID를 사용자의 선택에 따라 적용하는 기능을 활성화합니다. 신용카드 결제수단 선택 시, 세부 결제수단 선택창이 추가로 출력됩니다.', 'iamport-for-woocommerce' ),
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
		$order = new WC_Order( $order_id );

		$receipt_url = get_post_meta($order_id, '_iamport_receipt_url', true);
		$tid = $order->get_transaction_id();

        ob_start();
		?>
		<h2><?=__( '결제 상세', 'iamport-for-woocommerce' )?></h2>
		<table class="shop_table order_details">
			<tbody>
				<tr>
					<th><?=__( '결제수단', 'iamport-for-woocommerce' )?></th>
					<td><?=__( 'PAYCO', 'iamport-for-woocommerce' )?></td>
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
		
		$response = parent::iamport_payment_info($order_id);
		$useManualPg = filter_var($this->settings['use_manual_pg'], FILTER_VALIDATE_BOOLEAN);
		if(!$useManualPg){
			$response['pg'] = 'payco';
			$response['pay_method'] = 'payco'; //gateway ID가 iamport_payco로 돼있어서 기본적으로 pay_method : payco 적용되는 프론트 이슈 대응
		}
		return $response;
	}

	public function payment_fields()
    {
        parent::payment_fields(); //description 출력

        $useManualPg = filter_var($this->settings['use_manual_pg'], FILTER_VALIDATE_BOOLEAN);
        if ($useManualPg) {
            echo IamportHelper::htmlSecondaryPaymentMethod($this->settings['manual_pg_id']);
        }
    }
}