<?php
class IamportSettingTab
{

	public function __construct()
	{
		add_action( 'woocommerce_settings_tabs_iamport', array($this, 'settings') );
		add_action( 'woocommerce_update_options_iamport', array($this, 'save') );
		// add_action( 'woocommerce_sections_' . 'iamport', array($this, 'tab') );
	}

	public function label($settings_tabs)
	{
		$settings_tabs['iamport'] = __( '아임포트', 'iamport-for-woocommerce' );
		return $settings_tabs;
	}

	public function settings()
	{
		woocommerce_admin_fields( $this->getSettings() );
	}

	public function save()
	{
		woocommerce_update_options( $this->getSettings() );
	}

	private function getSettings()
	{
        require_once(dirname(dirname(__FILE__)) . '/lib/IamportHelper.php');

        $custom_statuses = array_merge(array('none'=>'사용안함'), IamportHelper::getCustomStatuses());

		$settings = array(
			array(
				'name'		=> __( '아임포트 공통 결제 설정', 'iamport-for-woocommerce' ),
				'type'		=> 'title',
				'desc'		=> '',
				'id'		=> 'wc_settings_tab_iamport_section_title'
			),
			array(
				'title'		=> __( '자동 완료됨 처리', 'iamport-for-woocommerce' ),
				'desc'    => __( '처리중(배송준비중) 상태를 거치지 않고 완료됨으로 자동 변경하시겠습니까?<br>우커머스에서 "처리중(배송처리중)"상태는 결제가 완료되었음을, "완료됨"상태는 상품발송이 완료되었음을 의미합니다. 아래의 경우 사용하시면 유용합니다.<br> ㄴ 온라인 강의와 같이 발송될 상품이 없어 결제가 되면 곧 서비스가 개시되어야 하는 경우<br> ㄴ 구매자가 직접 환불을 못하도록 막아야 하는 경우("처리중(배송준비중)"상태에서는 구매자가 환불이 가능하므로 "완료됨"상태로 변경하여 환불을 방지)', 'iamport-for-woocommerce' ),
				'id'		=> 'woocommerce_iamport_auto_complete',
				'default'	=> 'no',
				'type'		=> 'checkbox'
			),
			array(
				'title'		=> __( '커스텀 처리중(배송준비중) 주문 상태', 'iamport-for-woocommerce' ),
				'desc'    => __( '결제승인된 주문 건을 "처리중(배송준비중)" 상태를 대신해 별도로 정의한 상태로 변경하고 싶으시다면 설정해주세요. <br> ㄴ 위의 "자동 완료됨 처리"가 설정돼있으면 본 설정은 무시됩니다.<br> ㄴ 설정된 커스텀 주문상태는 "처리중(배송준비중)"과 마찬가지로 고객이 환불 가능한 주문상태입니다.', 'iamport-for-woocommerce' ),
				'id'		=> 'woocommerce_iamport_custom_status_as_paid',
				'default'	=> 'none',
				'type'		=> 'select',
                'options'   => $custom_statuses,
			),
			array(
				'title'		=> __( '교환요청 활성화', 'iamport-for-woocommerce' ),
				'desc'		=> __( '결제된 주문이 "완료됨" 상태일 때에도 구매자가 교환요청을 할 수 있도록 버튼을 생성합니다.' ),
				'id'		=> 'woocommerce_iamport_exchange_capable',
				'default'	=> 'yes',
				'type'		=> 'checkbox'
			),
            array(
                'title'		=> __( '환불요청 활성화', 'iamport-for-woocommerce' ),
                'desc'		=> __( '결제된 주문이 "완료됨" 상태일 때에도 구매자가 환불요청을 할 수 있도록 버튼을 생성합니다.' ),
                'id'		=> 'woocommerce_iamport_refund_capable',
                'default'	=> get_option('woocommerce_iamport_exchange_capable') === 'no' ? 'no':'yes', //[2020-05-05] 나중에 분리되었으므로 woocommerce_iamport_exchange_capable 값을 따라간다.
                'type'		=> 'checkbox'
            ),
			array(
				'title'		=> __( '교환요청 기한(일자)', 'iamport-for-woocommerce' ),
				'desc'		=> __( '구매자 교환요청버튼을 몇 일간 노출할지 결정합니다. (구매 시점 기준으로 기한이 지나면 교환 불가). 빈 값이거나 0이면 기한을 설정하지 않습니다.' ),
				'id'		=> 'woocommerce_iamport_exchange_limit',
				'default'	=> '',
				'type'		=> 'number'
			),
            array(
                'title'		=> __( '환불요청 기한(일자)', 'iamport-for-woocommerce' ),
                'desc'		=> __( '구매자 환불요청버튼을 몇 일간 노출할지 결정합니다. (구매 시점 기준으로 기한이 지나면 환불 불가). 빈 값이거나 0이면 기한을 설정하지 않습니다.' ),
                'id'		=> 'woocommerce_iamport_refund_limit',
                'default'	=> get_option('woocommerce_iamport_exchange_limit', ''), //[2020-05-05] 나중에 분리되었으므로 woocommerce_iamport_exchange_limit 값을 따라간다.
                'type'		=> 'number'
            ),
//			array(
//				'title'		=> __( '아임포트 웹훅(Notification) 통지 URL', 'iamport-for-woocommerce' ),
//				'desc'		=> sprintf( __( '가상계좌 입금통지, 거래정보 동기화 등을 위해 아임포트 웹훅을 통지받을 URL을 지정할 수 있습니다.<br>(예시 : %s)' ), home_url() . '?wc-api=WC_Gateway_Iamport_Vbank' ),
//				'id'		=> 'woocommerce_iamport_notice_url',
//				'default'	=> '',
//				'type'		=> 'url'
//			),
            array(
                'title'		=> __( '아임포트 결제주소 처리', 'iamport-for-woocommerce' ),
                'desc'		=> __( '결제주소 내 줄바꿈(Carriage Return) 모두 제거', 'iamport-for-woocommerce' ),
                'id'		=> 'woocommerce_iamport_strip_line_feed_in_address',
                'default'	=> 'yes',
                'type'		=> 'checkbox'
            ),

			array(
				 'type' => 'sectionend',
				 'id' => 'wc_settings_tab_iamport_section_title'
			),
			array(
				'name'     => __( '아임포트 정기 결제 설정', 'iamport-for-woocommerce' ),
				'type'     => 'title',
				'desc'     => '',
				'id'       => 'wc_settings_tab_iamport_subscription_section_title'
			),
			array(
				'title'		=> __( '카드 유효성 테스트 금액', 'iamport-for-woocommerce' ),
				'desc'		=> __( '0보다 큰 숫자인 경우에만 테스트 결제를 수행합니다.<br>정기결제 카드정보 등록 시, 카드정보의 유효성 검사는 이뤄지지만 결제가 가능한 카드인지 판별하기 어려운 예외적인 경우가 있습니다.(ex. 분실정지된 카드, 한도초과 카드)<br>카드정보 확인과 동시에 테스트 결제를 진행해봄으로써 분실정지되거나 한도초과된 카드의 등록을 사전에 방지할 수 있습니다.<br>테스트결제는 결제 후 3~4초 후에 자동 취소됩니다.' ),
				'id'		=> 'woocommerce_iamport_subscription_checking_amount',
				'default'	=> '0',
				'type'		=> 'text'
			),
            array(
                'title'		=> __( '무이자 할부 안내', 'iamport-for-woocommerce' ),
                'desc'		=> __( '정기결제 최초결제 및 KEY-IN결제 시 결제금액이 5만원이상인 경우 할부 개월 선택이 가능합니다. 카드사마다 무이자 할부 제공기준이 모두 달라, 별도의 문구를 통해 고객에게 안내가 필요합니다.' ),
                'id'		=> 'woocommerce_iamport_subscription_quota_description',
                'default'	=> '이용하시는 카드사 무이자 할부 프로모션에 따라 무이자할부가 자동 적용됩니다.',
                'type'		=> 'textarea'
            ),
			array(
				 'type' => 'sectionend',
				 'id' => 'wc_settings_tab_iamport_subscription_section_title'
			),
			array(
				'name'     => __( '아임포트 기타 설정', 'iamport-for-woocommerce' ),
				'type'     => 'title',
				'desc'     => '',
				'id'       => 'wc_settings_tab_iamport_miscellaneous_section_title'
			),
            array(
                'title'		=> __( '신용카드 최대 할부개월 제한', 'iamport-for-woocommerce' ),
                'desc'		=> __( '5만원 이상 거래건에 대해, PG사 결제창 내 할부선택 옵션이 나타나게 되는데 사용자가 선택가능한 최대 할부개월 수를 제한하는 기능입니다.' ),
                'id'		=> 'woocommerce_iamport_card_max_quota',
                'default'	=> 'none',
                'type'		=> 'select',
                'options'   => array(
                    'none'   => '제한하지 않음(PG사 기본 설정에 따름)',
                    'month1' => '일시불만 가능',
                    'month2' => '최대 2개월로 제한',
                    'month3' => '최대 3개월로 제한',
                    'month4' => '최대 4개월로 제한',
                    'month5' => '최대 5개월로 제한',
                    'month6' => '최대 6개월로 제한',
                    'month7' => '최대 7개월로 제한',
                    'month8' => '최대 8개월로 제한',
                    'month9' => '최대 9개월로 제한',
                    'month10' => '최대 10개월로 제한',
                    'month11' => '최대 11개월로 제한',
                    'month12' => '최대 12개월로 제한',
                ),
            ),
			array(
				'title'		=> __( '[가상계좌 입금대기 중] 주문 상태 라벨 설정', 'iamport-for-woocommerce' ),
				'desc'		=> __( '가상계좌 발급 후 아직 입금되지 않은 주문 건에 대한 상태값 명칭을 변경할 수 있습니다. (기본값 : 가상계좌 입금대기 중)' ),
				'id'		=> 'woocommerce_iamport_awaiting_vbank_status_label',
				'default'	=> '가상계좌 입금대기 중',
				'type'		=> 'text'
			),
			array(
				'title'		=> __( '[반품요청] 주문 상태 라벨 설정', 'iamport-for-woocommerce' ),
				'desc'		=> __( '반품요청된 주문 건에 대한 상태값 명칭을 변경할 수 있습니다. (기본값 : 반품요청)' ),
				'id'		=> 'woocommerce_iamport_refund_status_label',
				'default'	=> '반품요청',
				'type'		=> 'text'
			),
			array(
				'title'		=> __( '[교환요청] 주문 상태 라벨 설정', 'iamport-for-woocommerce' ),
				'desc'		=> __( '교환요청된 주문 건에 대한 상태값 명칭을 변경할 수 있습니다. (기본값 : 교환요청)' ),
				'id'		=> 'woocommerce_iamport_exchange_status_label',
				'default'	=> '교환요청',
				'type'		=> 'text'
			),
			array(
				 'type' => 'sectionend',
				 'id' => 'wc_settings_tab_iamport_miscellaneous_section_title'
			),
		);

		return $settings;
	}

}
