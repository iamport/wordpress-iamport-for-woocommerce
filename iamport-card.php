<?php
class WC_Gateway_Iamport_Card extends Base_Gateway_Iamport {

    const GATEWAY_ID = 'iamport_card';

	public function __construct() {
		parent::__construct();

		//settings
		$this->method_title = __( '아임포트(신용카드)', 'iamport-for-woocommerce' );
		$this->method_description = __( '=> 아임포트 서비스를 이용해 결제모듈을 연동할 수 있습니다.<br>=> [아임포트] X PG사 제휴할인혜택을 받아보세요! <a href="http://www.iamport.kr/pg#promotion" target="_blank">PG 신규계약 프로모션 안내</a><br>=> 아임포트의 최신 공지사항도 놓치지 마세요! <a href="http://www.iamport.kr/notice" target="_blank">공지사항보기</a>', 'iamport-for-woocommerce' );
		$this->has_fields = true;
		$this->supports = array( 'products', 'refunds' );

		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->pg_provider = $this->settings['pg_provider'];
		$this->pg_id 	     = $this->settings['pg_id'];

		//actions
		// add_action( 'woocommerce_thankyou_'.$this->id, array( $this, 'iamport_order_detail' ) ); woocommerce_order_details_after_order_table 로 대체. 중복으로 나오고 있음
	}

	protected function get_gateway_id() {
		return self::GATEWAY_ID;
	}

	public function init_form_fields() {
		parent::init_form_fields();

		$allProducts = array(
			"all" => "[모든 상품]",
		);
		$allProducts += IamportHelper::get_all_products();

		$allCategories = array(
			"none" => "[적용 카테고리 없음]",
			"all" => "[모든 카테고리]",
		);
		$allCategories += IamportHelper::get_all_categories();

		$this->form_fields = array_merge( array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( '아임포트(신용카드) 결제 사용', 'iamport-for-woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
				'default' => __( '신용카드 결제', 'iamport-for-woocommerce' ),
				'desc_tip'      => true,
			),
			'description' => array(
				'title' => __( 'Customer Message', 'woocommerce' ),
				'type' => 'textarea',
				'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
				'default' => __( '주문확정 버튼을 클릭하시면 신용카드 결제창이 나타나 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
			),
        ), $this->form_fields, array(
			'_pg_auto_title' => array(
                'title' => __('(1) 신용카드 결제 시, 자동 적용될 PG설정값 세팅'),
			    'type' => 'title',
                'description' => '아임포트 관리자페이지 내 복수의 PG설정이 되어있을 때, 구매상품 / 카테고리에 따라 자동으로 설정될 PG값을 지정합니다.',
            ),
			'pg_provider' => array(
				'title' => __( 'PG사 설정', 'iamport-for-woocommerce' ),
				'type' => 'select',
				'default' => '',
				'description' => __( '2개 이상의 PG사를 이용 중이라면, 신용카드를 서비스할 PG사를 선택해주세요. 선택된 PG사의 결제창이 호출됩니다.', 'iamport-for-woocommerce' ),
				'options' => array(
					'none' => '해당사항없음',
					'html5_inicis' => 'KG이니시스-웹표준결제',
					'kcp' => 'NHN KCP',
					'uplus' => 'LGU+',
					'nice' => '나이스정보통신',
					'jtnet' => 'JTNet',
					'danal_tpay' => '다날-신용카드',
					'mobilians' => '모빌리언스-신용카드',
					'kicc' => 'KICC',
					'daou' => '다우데이타(페이조아)'
				)
			),
			'pg_id' => array(
				'title' => __( 'PG상점아이디', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '동일한 PG사에서 여러 개의 상점아이디(MID)를 사용하는 경우 원하시는 PG상점아이디(MID)를 지정하여 결제할 수 있습니다.', 'iamport-for-woocommerce' ),
			),
			'pg_products' => array(
				'title'		=> __( 'PG설정 적용대상(상품)', 'iamport-for-woocommerce' ),
				'type'		=> 'multiselect',
				'default' => 'all',
				'description'		=> __( '위에서 설정한 [PG사 설정] 및 [PG상점아이디] 가 적용될 상품을 선택합니다. 선택한 상품 또는 아래에서 선택한 카테고리에 해당되지 않는 경우 [PG사 설정] 및 [PG상점아이디] 는 적용되지 않습니다.' ),
				'options' => $allProducts,
			),
			'pg_categories' => array(
				'title'		=> __( 'PG설정 적용대상(카테고리)', 'iamport-for-woocommerce' ),
				'type'		=> 'multiselect',
				'default' => 'none',
				'description'		=> __( '위에서 설정한 [PG사 설정] 및 [PG상점아이디] 가 적용될 카테고리를 선택합니다. 선택한 카테고리 또는 위에서 선택한 상품에 해당되지 않는 경우 [PG사 설정] 및 [PG상점아이디] 는 적용되지 않습니다.' ),
				'options' => $allCategories,
			),
            '_pg_manual_title' => array(
                'title' => __('(2) 신용카드 결제 시, 적용될 PG설정값을 고객이 직접 지정'),
                'type' => 'title',
                'description' => '체크아웃(Checkout)페이지에서 구매자가 신용카드 결제수단 선택 후 세부 결제수단을 한 번 더 선택할 수 있습니다. 아래 기능을 사용하면 위에 설정된 [신용카드 결제 시, 자동 적용될 PG설정값 세팅] 값은 모두 무시됩니다.',
            ),
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

	public function iamport_payment_info( $order_id ) {
		$iamport_info = parent::iamport_payment_info( $order_id );

        $useManualPg = filter_var($this->settings['use_manual_pg'], FILTER_VALIDATE_BOOLEAN);

        if (!$useManualPg) { //Manual 모드이면 굳이 루프돌며 찾지 않음
            if ( !empty($this->pg_provider) && $this->pg_provider != 'none' ) {
                $iamport_info['pg'] = $this->pg_provider;

                if ( !empty($this->pg_id) ) {
                    $iamport_info['pg'] = sprintf("%s.%s", $this->pg_provider, $this->pg_id);
                }

                //조건에 해당되지 않으면 pg 파라메터 unset
                $allAllowedInProduct  = !empty($this->settings['pg_products'])   && ( $this->settings['pg_products']   === "all" || (is_array($this->settings['pg_products'])   && in_array("all", $this->settings['pg_products'])) );
                $allAllowedInCategory = !empty($this->settings['pg_categories']) && ( $this->settings['pg_categories'] === "all" || (is_array($this->settings['pg_categories']) && in_array("all", $this->settings['pg_categories'])) );

                if ( !($allAllowedInProduct || $allAllowedInCategory) ) {
                    //타겟이 특정되어있을 때에만 검사한다.
                    $productList  = empty($this->settings['pg_products'])   || !is_array($this->settings['pg_products'])   ? array() : $this->settings['pg_products'];
                    $categoryList = empty($this->settings['pg_categories']) || !is_array($this->settings['pg_categories']) ? array() : $this->settings['pg_categories'];

                    if ( IamportHelper::has_excluded_product($order_id, $productList, $categoryList) )	unset($iamport_info['pg']);
                }
            }
        }

		return $iamport_info;
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
					<td><?=__( '신용카드', 'iamport-for-woocommerce')?></td>
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

	public function payment_fields()
    {
        parent::payment_fields(); //description 출력

        $useManualPg = filter_var($this->settings['use_manual_pg'], FILTER_VALIDATE_BOOLEAN);
        if ($useManualPg) {
            echo IamportHelper::htmlSecondaryPaymentMethod($this->settings['manual_pg_id']);
        }
    }

}
