<?php
class WC_Gateway_Iamport_Alipay extends Base_Gateway_Iamport {

    const GATEWAY_ID = 'iamport_alipay';

	public function __construct() {
		parent::__construct();

		//settings
		$this->method_title = __( '아임포트(알리페이)', 'iamport-for-woocommerce' );
		$this->method_description = __( '=> 아임포트 서비스를 이용해 결제모듈을 연동할 수 있습니다.<br>=> [아임포트] X PG사 제휴할인혜택을 받아보세요! <a href="http://www.iamport.kr/pg#promotion" target="_blank">PG 신규계약 프로모션 안내</a><br>=> 아임포트의 최신 공지사항도 놓치지 마세요! <a href="http://www.iamport.kr/notice" target="_blank">공지사항보기</a>', 'iamport-for-woocommerce' );
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
				'label' => __( '아임포트(알리페이) 결제 사용. (알리페이를 사용하시려면, <a href="https://admin.iamport.kr/settings" target="_blank">아임포트 관리자페이지의 PG설정화면</a>에서 "추가PG사"로 알리페이를 추가 후 사용해주세요.)', 'iamport-for-woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( '알리페이 결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'description' => array(
				'title' => __( 'Customer Message', 'woocommerce' ),
				'type' => 'textarea',
				'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
				'default' => __( '주문확정 버튼을 클릭하시면 알리페이 결제창이 나타나 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
			),
			// 'currency' => array(
			// 	'title' => __( '결제가능 화폐단위', 'woocommerce' ),
			// 	'type' => 'select',
			// 	'options' => array(
			// 		'KRW' => '원화결제',
			// 		'USD' => 'USD결제',
			// 	),
			// 	'description' => __( '결제가 가능한 화폐단위를 지정합니다. 알리페이 사용을 위해 나이스페이먼츠와 계약시, 결제/정산 화폐가 지정되니 맞춰서 설정해주세요. (주문 건의 화폐단위와 일치하는 경우 결제수단으로 노출됩니다.)', 'iamport-for-woocommerce' ),
			// 	'default' => __( 'KRW', 'iamport-for-woocommerce' )
			// ),
		), $this->form_fields, array(
		    'manual_pg_id' => array(
                'title' => __( '알리페이 결제수단 제공 PG설정', 'woocommerce' ),
                'type' => 'text',
                'description' => __( '알리페이 결제수단을 실제 적용할 PG사에 해당되는 정보를 직접 수동설정하실 수 있습니다. "{PG사 코드}.{PG상점아이디}" 의 형식으로 입력하실 수 있습니다. (예시 : alipay.IM_xxxx, kcp.IP123)', 'iamport-for-woocommerce' ),
            ),
        ));
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
					<td><?=__( '알리페이', 'iamport-for-woocommerce' )?></td>
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
		$response['pg'] = 'alipay';
		// $response['currency'] = isset($this->settings['currency']) ? $this->settings['currency'] : 'KRW';

		$manualPgString = trim($this->settings['manual_pg_id']);
        if ($manualPgString) {
            $response['pg'] = $manualPgString;
            $manualPg = explode('.', $manualPgString);

            $pgProvider = $manualPg[0];
            if ($pgProvider != 'alipay') { // Alipay 직접계약 외 PG사를 통한 HUB형 Alipay
                $response['pay_method'] = 'alipay';
            }
        }

		return $response;
	}

}