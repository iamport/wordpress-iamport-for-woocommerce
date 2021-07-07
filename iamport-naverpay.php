<?php

class WC_Gateway_Iamport_NaverPay extends Base_Gateway_Iamport {

	const GATEWAY_ID = 'iamport_naverpay';
	const PLUGIN_EXTRA_PRODUCT_OPTION = 'thwepof';
    const OPTION_EXTRA_PRODUCT_OPTION = 'thwepof_options';

    const PLUGIN_TM_EXTRA_PRODUCT_OPTION = 'tmextra';
    const OPTION_TM_EXTRA_PRODUCT_OPTION = 'tmdata';
	const OPTION_TM_EXTRA_PRODUCT_OPTION_REPO = 'tmcartepo';

	const PLUGIN_YITH_PRODUCT_OPTION = 'yith';
	const OPTION_YITH_PRODUCT_OPTION = 'yith_wapo_options';

    //WooShipping => Naver
    private $logisCompany = array(
        'fedex' => 'FEDEX', //Fedex
        'ems' => 'EMS', //우체국EMS
        'CVSnet' => 'CVSNET', // 편의점
        'ilyanglogis' => 'ILYANG', //일양로지스
        'kgbls' => 'KGB', //KGB택배
        'ocskorea' => 'CH1', // OCS코리아
        'dhl' => 'DHL', //DHL
        'ups' => 'UPS', //UPS
        'tnt' => 'TNT', //TNT익스프레스
        'innogis' => 'INNOGIS', //GTX 로지스
        'epost' => 'EPOST', //우체국택배
        'hlc' => 'HYUNDAI', //현대택배
        'hanjin' => 'HANJIN', // 한진택배
        'hanips' => 'HPL', // 한의사랑택배
        'dongbuexpress' => 'DONGBU', //동부택배
        'kdexp' => 'KDEXP', //경동택배
        'kglogis' => 'DONGBU', //KG로지스
        'epantos' => 'PANTOS', //범한판토스
        'ilogen' => 'KGB', // 로젠택배
        'koreanair' => 'KOREXG', //대한한공배송
        'doortodoor' => 'CJGLS', // 대한통운
    );

	public function __construct() {
		parent::__construct();

		//settings
		$this->title = __( '네이버페이', 'iamport-for-woocommerce' );
		$this->method_title = __( '아임포트(네이버페이)', 'iamport-for-woocommerce' );
		$this->method_description = __( '=> 아임포트 서비스를 이용해 네이버페이를 연동할 수 있습니다. <strong style="background:#ccc"><a href="http://www.iamport.kr/download/naverpay-woocommerce-manual.pdf" target="iamport-woocommerce-manual">[우커머스-네이버페이 설정관련 매뉴얼보기]</a></strong><br>=> [아임포트] X PG사 제휴할인혜택을 받아보세요! <a href="http://www.iamport.kr/pg#promotion" target="_blank">PG 신규계약 프로모션 안내</a><br>=> 아임포트의 최신 공지사항도 놓치지 마세요! <a href="http://www.iamport.kr/notice" target="_blank">공지사항보기</a>', 'iamport-for-woocommerce' );
		$this->has_fields = true;
		$this->supports = array( 'products' );

		add_action( 'woocommerce_api_naver-product-info', array( $this, 'naverProductsAsXml' ) );
		add_action( 'woocommerce_api_iamport-naver-product-xml', array( $this, 'naverProductsAsXml' ) );

		add_filter( 'woocommerce_available_payment_gateways', array($this, 'eliminateMySelf') );
		add_filter( 'woocommerce_cancel_unpaid_order', array($this, 'can_clear_unpaid_order'), 10, 2);

		add_action( "iamport_naverpay_sync_review", array($this, "sync_review") );

		//shipping tel 추가
		add_filter( "woocommerce_admin_shipping_fields" , array($this, "admin_shipping_fields") );

		//과거 구매평 동기화 기능
		add_action( 'wp_ajax_iamport_naver_review_sync', array($this, 'ajax_naver_review_sync') );

		//shipping정보 네이버 연동
        add_action( 'wooshipping_delivery_order_items_sended', array($this, 'wooshipping_sended'), 10, 4 );
	}

    public function wooshipping_sended($order, $new_item_names, $company_id, $tracking_no)
    {
        if ($this->is_available()) { //네이버페이 Gateway 활성화인지
            if ($order->get_payment_method() == self::GATEWAY_ID) { //네이버페이 Gateway 로 처리된 주문만
                $impUid = $order->get_transaction_id();
                if ($impUid) {
                    require_once(dirname(__FILE__).'/lib/iamport.php');

                    $iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);

                    $company = array_key_exists($company_id, $this->logisCompany) ? $this->logisCompany[$company_id] : 'CH1';
                    $result = $iamport->postNaverShipping($impUid, $company, $tracking_no);

                    if ($result->success) {
                        $order->add_order_note(sprintf( __( '네이버페이에 배송정보가 접수되었습니다.(아임포트 거래번호 : %s)', 'iamport-for-woocommerce' ), $impUid ));
                    } else {
                        $order->add_order_note(sprintf( __( '네이버페이에 배송정보 접수실패하였습니다.(아임포트 거래번호 : %s, 사유 : %s)', 'iamport-for-woocommerce' ), $impUid, $result->error['message'] ));
                    }
                }
            }

        }
    }

	public function sync_review() {
		$turnedOn = isset($this->settings["sync_review"]) && filter_var($this->settings["sync_review"], FILTER_VALIDATE_BOOLEAN);
		if ( !$turnedOn )	return;

		$now = time();
		$last_executed = get_option("iamport_naverpay_sync_review_last_executed", 0);
		if ( $last_executed == 0 )	$last_executed = $now - 24*60*60; //하루 전부터 가져오기 시작

		if ( $now - $last_executed < 5*60 )	return; //5분 이내이면 중단

		update_option("iamport_naverpay_sync_review_last_executed", $now);

		$this->do_sync_review($last_executed);
	}

	public function generate_naver_review_sync_html($key, $config) {
		$sanitizedID = esc_attr( $key );
		$fromID   = $sanitizedID . "-from";
		$toID     = $sanitizedID . "-to";
		$buttonID = $sanitizedID . "-button";

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?=$fromID?>"><?=esc_html( $config['title'] )?></label>
			</th>
			<td class="forminp">
				<input type="date" style="width:180px" id="<?=$fromID?>" value="<?=date('Y-m-d')?>"> (00:00:00) 부터
				<input type="date" style="width:180px" id="<?=$toID?>" value="<?=date('Y-m-d')?>"> (23:59:59) 까지
				<button id="<?=$buttonID?>" class="button" href="#"><?=__( '동기화', 'iamport-for-woocommerce' )?></button>

				<p class="description"><?=$config["description"]?></p>

				<script type="text/javascript">
					jQuery("#<?=$buttonID?>").click(function() {
						var from = moment( jQuery("#<?=$fromID?>").val() ),
								to   = moment( jQuery("#<?=$toID?>").val() );

						if ( !from.isValid() )	return alert("시작 일자가 유효하지 않습니다.");
						if ( !to.isValid()   )	return alert("종료 일자가 유효하지 않습니다.");

						from = from.startOf("day");
						to   = to.endOf("day").set("millisecond", 0); //millisecond까지 endOf로 바뀌므로 0으로 바꿔야 함
						var tomorrow = moment().add(1, "day").startOf("day");

						if ( tomorrow.diff(to, "second") < 0 )	return alert("종료 일자는 오늘보다 미래일자일 수 없습니다.");

						var diffInMonth = to.diff(from, 'months'),
								diffInDay   = to.diff(from, 'second');
						if ( diffInMonth > 0 )	return alert("한 번에 최대 1개월까지 동기화가 가능합니다.");
						if ( diffInDay < 0  )	return alert("시작 일자는 종료 일자와 같거나 작아야 합니다.");

						var data = {
							action: "iamport_naver_review_sync",
							from:   from.format("YYYY-MM-DD"),
							to:     to.format("YYYY-MM-DD")
						};

						var that = this;
						that.disabled = true;
						jQuery(that).html("동기화 중 ...");

						jQuery.post(ajaxurl, data, function(rsp) {
							that.disabled = false;
							jQuery(that).html("동기화");

							if ( rsp.ok === true ) {
								alert("동기화가 완료되었습니다.");
							} else {
								alert(rsp.error)
							}
						});
					});
				</script>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	public function ajax_naver_review_sync() {
		header('Content-type: application/json');

		if ( empty($_POST["from"]) || empty($_POST["to"]) ) {
			echo json_encode(array(
				"error" => "기간을 지정해야 합니다."
			));

			wp_die();
		}

		$offset = 9 * HOUR_IN_SECONDS; // Seoul
		$from = strtotime($_POST["from"] . " 00:00:00"); //YYYY-MM-DD
		$to   = strtotime($_POST["to"]   . " 23:59:59");   //YYYY-MM-DD

		if ( $from === false || $to === false || $from >= $to ) {
			echo json_encode(array(
				"error" => "기간 범위가 유효하지 않습니다."
			));

			wp_die();
		}

		//1개월 초과 체크
		/*
		-1 month 는 31일인 경우 전월 30일을 찾지 못하여 문제가 됨 ( ex. 7월 31 기준으로 -1 month 하면 7월 1일이 반환됨 )
		$monthAgo = strtotime("-1 month", $to);
		if ( $from < $monthAgo ) {
			echo json_encode(array(
				"error" => "기간은 최대 1개월로 제한됩니다."
			));

			wp_die();
		}
		*/
		//최대 31일이 넘는지만 체크
		if ( ($to - $from) > 31*24*60*60 ) {
			echo json_encode(array(
				"error" => "기간은 최대 1개월로 제한됩니다."
			));

			wp_die();
		}

		$from = $from - $offset;
		$to   = min(time(), $to - $offset);

		$this->do_sync_review($from, $to);

		echo json_encode(array(
			"ok" => true,
		));

		wp_die();
	}

	private function do_sync_review($fromInUTC, $toInUTC=null) { //fromInUTC : timestamp
		require_once(dirname(__FILE__).'/lib/iamport.php');

		$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);
		$safe_break = 10;
		$interval = 24*60*60;
		$offset = 9 * HOUR_IN_SECONDS; // Seoul
		$zoneOffsetHour = get_option( 'gmt_offset' );
		$nowInUTC = time();

		if ( $toInUTC == null )	$toInUTC = $nowInUTC;

		$generalReviews = array();
		$premiumReviews = array();

		while ($fromInUTC < $toInUTC && --$safe_break >= 0) {
			$fromDT = new DateTime();
			$fromDT->setTimestamp( $fromInUTC ); //offset 을 더하지 않고 보내야 함
			$toDT   = new DateTime();
			$toDT->setTimestamp( min($fromInUTC+$interval, $nowInUTC) ); //offset 을 더하지 않고 보내야 함

			$result = $iamport->getNaverReviews($fromDT, $toDT, "general");
			if ( $result->success ) {
				$generalReviews = array_merge($generalReviews, $result->data);
			}

			usleep(300);

			$result = $iamport->getNaverReviews($fromDT, $toDT, "premium");
			if ( $result->success ) {
				$premiumReviews = array_merge($premiumReviews, $result->data);
			}

			usleep(300);

			$fromInUTC += $interval;
		}

		//product_id 기준으로 그루핑
		$productCommentsMap = array();
		$allReviews = array_merge($generalReviews, $premiumReviews);
		foreach ($allReviews as $rev) {
			$productId = $rev->product_id;

			if ( !isset($productCommentsMap[ $productId ]) )	$productCommentsMap[ $productId ] = array();

			$productCommentsMap[ $productId ][] = $rev;
		}

		foreach ($productCommentsMap as $productId => $reviews) {
			// The Query
			$writtenComments = get_comments(array(
				"post_id"    => $productId,
				"meta_query" => array(
					"key"     => "review_id",
					"value"   => array_map(function($t){ return $t->review_id; }, $allReviews),
					"compare" => "EXISTS"
				)
			));

			$writtenIds = array();
			foreach ($writtenComments as $wc) {
				$writtenIds[] = get_comment_meta($wc->comment_ID, "review_id", true);
			}

			foreach ($reviews as $rev) {
				if ( !in_array($rev->review_id, $writtenIds) ) {
					wp_insert_comment(array(
						"comment_post_ID"  => $productId,
						"comment_author"   => $rev->writer,
						"comment_content"  => $rev->content ? sprintf("[%s] %s", $rev->title, $rev->content) : $rev->title,
						"comment_date"     => date("Y-m-d H:i:s", $rev->created_at + $zoneOffsetHour * HOUR_IN_SECONDS),
						"comment_date_gmt" => date("Y-m-d H:i:s", $rev->created_at),
						"comment_type" => "review",
						"comment_meta"     => array(
							"review_id" => $rev->review_id,
							"rating"    => $rev->score, //[2018-11-15] 일반/프리미엄 구매평 score 체계 통일 됨 (1 ~ 5)
							// "rating"    => $rev->content ? /*premium*/$rev->score - 8 : /*general*/($rev->score+1)*2 - 1,
						)
					));
				}
			}
		}
	}

	public function eliminateMySelf($gateways) {
		if ( is_checkout() )	unset($gateways[ $this->get_gateway_id() ]); //checkout 페이지에서 네이버페이 결제수단 노출되지 않도록 수정

		return $gateways;
	}

	public function admin_shipping_fields($fields) {
		if ( !isset($fields['phone1']) ) {
			$fields['phone1'] = array(
				'label' => __( '배송지 전화번호 1', 'iamport-for-woocommerce' ),
			);
		}

		if ( !isset($fields['phone2']) ) {
			$fields['phone2'] = array(
				'label' => __( '배송지 전화번호 2', 'iamport-for-woocommerce' ),
			);
		}

		return $fields;
	}

	public function can_clear_unpaid_order($checkout_order_get_created_via, $order) {
		if ( $checkout_order_get_created_via )	return true;

		$pg_provider = get_post_meta($order->get_id(), '_iamport_provider', true);

		return $pg_provider == "naverco";
	}

	protected function get_gateway_id() {
		return self::GATEWAY_ID;
	}

	public function init_form_fields() {
		parent::init_form_fields();

		$allCategories = IamportHelper::get_all_categories();
		$allProducts = IamportHelper::get_all_products();

		//Shipping Zones
        $zones = WC_Shipping_Zones::get_zones();
        $zoneOptions = array();
        foreach ($zones as $zoneId => $zone) {
            $zoneOptions[$zoneId] = $zone['zone_name'];
        }

		$this->form_fields = array_merge( array(
			// 'enabled' => array(
			// 	'title' => __( 'Enable/Disable', 'woocommerce' ),
			// 	'type' => 'checkbox',
			// 	'label' => __( '아임포트(네이버페이) 결제 사용', 'iamport-for-woocommerce' ),
			// 	'default' => 'yes'
			// ),
			// 'title' => array(
			// 	'title' => __( 'Title', 'woocommerce' ),
			// 	'type' => 'text',
			// 	'description' => __( '주문목록에 표시될 결제수단명', 'iamport-for-woocommerce' ),
			// 	'default' => __( '네이버페이', 'iamport-for-woocommerce' ),
			// 	'desc_tip'      => true,
			// ),
			// 'description' => array(
			// 	'title' => __( 'Customer Message', 'woocommerce' ),
			// 	'type' => 'textarea',
			// 	'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
			// 	'default' => __( '네이버페이 결제창으로 이동하여 결제를 진행하실 수 있습니다. 네이버페이 특성상, 결제정보를 동기화하는데 다소 시간이 소요될 수 있습니다(최대 1분). 결제정보 동기화가 완료되면 자동으로 우커머스 주문상태가 "결제완료됨"으로 변경됩니다.', 'iamport-for-woocommerce' )
			// ),
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( '네이버페이 결제 사용', 'iamport-for-woocommerce' ),
				'default' => 'yes'
			),
			'show_button' => array(
				'title' => __( '네이버페이 구매버튼 보이기', 'iamport-for-woocommerce' ),
				'description' => __( '상품페이지, 장바구니에 네이버페이 구매버튼을 출력할지 여부를 결정합니다.', 'iamport-for-woocommerce' ),
				'type' => 'checkbox',
				'label' => __( '네이버페이 구매버튼 보이기', 'iamport-for-woocommerce' ),
				'default' => 'no'
			),
			'debug_mode' => array(
				'title' => __( '네이버페이 검수모드', 'iamport-for-woocommerce' ),
				'description' => __( '네이버페이 검수단계에서는 일반 사용자에게 네이버페이 구매버튼이 보여지면 안됩니다. 위의 "네이버페이 구매버튼 보이기"는 체크해제하시고 "네이버페이 검수모드"만 체크해주세요. 네이버페이 검수팀에는 URL파라메터로 "naver-debug=iamport"를 추가하면 네이버페이 버튼이 출력된다고 전달하시면 됩니다.', 'iamport-for-woocommerce' ),
				'type' => 'checkbox',
				'label' => __( '네이버페이 검수모드', 'iamport-for-woocommerce' ),
				'default' => 'no'
			),
			'sync_review' => array(
				'title' => __( '네이버페이 구매평 자동 동기화', 'iamport-for-woocommerce' ),
				'description' => __( '네이버페이 구매자가 작성한 구매평을 일정 주기로 가져와 우커머스 상품리뷰창에 추가해줍니다.', 'iamport-for-woocommerce' ),
				'type' => 'checkbox',
				'label' => __( '네이버페이 구매평 자동 동기화', 'iamport-for-woocommerce' ),
				'default' => 'no'
			),
			'sync_review_by_range' => array(
				'title'		    => __( '네이버페이 구매평 동기화', 'iamport-for-woocommerce' ),
				'description'	=> __( '특정 기간에 대하여 네이버페이 구매평 동기화를 진행할 수 있습니다.(한 번에 최대 1개월) 여러 번 동기화를 하더라도 이미 등록된 구매평에 대해서는 추가등록되지 않습니다. 기간이 긴 경우 다소 시간이 소요됩니다.', 'iamport-for-woocommerce' ),
				'type'		=> 'naver_review_sync',
				'label'   => 'xxxx'
			),
			'show_button_on_categories' => array(
				'title' => __( '네이버페이 구매버튼을 출력할 상품카테고리', 'iamport-for-woocommerce' ),
				'description' => __( '"네이버페이 구매버튼 보이기"가 체크되어있을 때, 일부 상품 카테고리에만 네이버페이 구매버튼을 출력하도록 설정할 수 있습니다.', 'iamport-for-woocommerce' ),
				'type' => 'multiselect',
				'options' => array('all'=>__('[모든 카테고리]', 'iamport-for-woocommerce')) + $allCategories,
				'default' => 'all',
			),
			'disable_button_on_categories' => array(
				'title' => __( '네이버페이 구매버튼을 비활성화시킬 상품카테고리', 'iamport-for-woocommerce' ),
				'description' => __( '일부 상품 카테고리에만 네이버페이 구매버튼을 비활성상태로 출력하도록 설정할 수 있습니다.', 'iamport-for-woocommerce' ),
				'type' => 'multiselect',
				'options' => array('none'=>__('[비활성화할 카테고리 없음]', 'iamport-for-woocommerce'), 'all'=>__('[모든 카테고리]', 'iamport-for-woocommerce')) + $allCategories,
				'default' => 'none',
			),
            'calc_shipping_tax' => array(
                'title' => __( '네이버페이 배송비 부가세 추가', 'iamport-for-woocommerce' ),
                'description' => __( '고정요금 배송비가 적용될 때, [세금상태] 값이 [과세가능]인 경우 입력된 배송비에 10%부가세를 추가하여 네이버페이에 청구합니다.', 'iamport-for-woocommerce' ),
                'type' => 'checkbox',
                'label' => __( '네이버페이 배송비 부가세 추가', 'iamport-for-woocommerce' ),
                'default' => 'no'
            ),
            'shipping_zone' => array(
                'title' => __( '네이버페이 배송방법 적용될 배송구역', 'iamport-for-woocommerce' ),
                'type' => 'select',
                'default' => '0',
                'options' => array(
                    '0' => __('Locations not covered by your other zones', 'woocommerce'),
                ) + $zoneOptions,
            ),
			'button_pc_style' => array(
				'title' => __( '네이버페이 PC버튼 스타일', 'iamport-for-woocommerce' ),
				'type' => 'select',
				'default' => 'C2',
				'options' => array(
					'A' => 'A',
					'B' => 'B',
					'C1' => 'C1',
					'C2' => 'C2',
					'C3' => 'C3',
					'D1' => 'D1',
					'D2' => 'D2',
					'D3' => 'D3',
					'E1' => 'E1',
					'E2' => 'E2',
					'E3' => 'E3',
				)
			),
			'button_mobile_style' => array(
				'title' => __( '네이버페이 모바일버튼 스타일', 'iamport-for-woocommerce' ),
				'type' => 'select',
				'default' => 'MA',
				'options' => array(
					'MA' => 'MA',
					'MB' => 'MB',
				)
			),
			'button_position_on_product' => array(
				'title' => __( '상품페이지 내 네이버페이 버튼 위치', 'iamport-for-woocommerce' ),
				'type' => 'select',
				'default' => 'below',
				'options' => array(
					'below' => '장바구니버튼의 아래 라인',
					'after' => '장바구니버튼과 동일 라인(오른쪽)',
				)
			),
			'button_position_on_cart' => array(
				'title' => __( '장바구니페이지 내 네이버페이 버튼 위치', 'iamport-for-woocommerce' ),
				'type' => 'select',
				'default' => 'above',
				'options' => array(
					'above' => '체크아웃버튼 위',
					'below' => '체크아웃버튼 아래',
				)
			),
			'button_key' => array(
				'title' => __( '네이버페이 구매버튼 생성키', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( '네이버페이에서 발급된 버튼 생성 키를 입력해주세요.', 'iamport-for-woocommerce' ),
				'label' => __( '네이버페이 구매버튼 생성키', 'iamport-for-woocommerce' ),
			),
			'pg_id' => array(
				'title' => __( '네이버페이 파트너 ID', 'woocommerce' ),
				'type' => 'text',
				'description' => __( '하나의 아임포트 계정에 여러 개의 네이버페이 파트너 ID를 설정한 경우, 사용될 네이버 파트너 ID를 특정할 수 있습니다.', 'iamport-for-woocommerce' ),
			),
			'inflow_account' => array(
				'title' => __( '네이버 유입경로 분석 계정', 'iamport-for-woocommerce' ),
				'type' => 'text',
				'description' => __( '네이버페이센터 가입 시 네이버로부터 이메일로 전달된 "네이버공통인증키"입니다. "가맹점센터(네이버페이센터)>내정보>가맹점가입정보>쇼핑몰정보" 에서 유입경로 Account ID와 같습니다.', 'iamport-for-woocommerce' ),
				'label' => __( '네이버 유입경로 분석 계정', 'iamport-for-woocommerce' ),
			),
			'default_css' => array(
				'title' => __( '네이버페이 버튼 관련 기본 CSS', 'iamport-for-woocommerce' ),
				'type' => 'textarea',
				'default' => '.iamport-naverpay-container { }
.iamport-naverpay-product-button {padding:15px 0;text-align:right;}
#iamport-naverpay-cart-button {padding:15px 0;text-align:right;}',
				'label' => __( '네이버페이 버튼 관련 기본 CSS', 'iamport-for-woocommerce' ),
			),
			'culture_products' => array(
				'title'		=> __( '도서공연비 소득공제대상 (상품)', 'iamport-for-woocommerce' ),
				'type'		=> 'multiselect',
				'default' => 'none',
				'description'		=> __( '문화체육관광부의 도서공연비 소득공제대상 상품을 선택해주세요. 선택된 상품 또는 아래 선택된 카테고리에 해당되는 경우 소득공제주문으로 적용됩니다. 소득공제대상 상품과 비대상 상품의 혼합결제는 불가능합니다.' ),
				'options' => array(
                    "none" => "[적용대상없음]",
                    "all" => "[모든 상품]",
                ) + $allProducts,
			),
			'culture_categories' => array(
				'title'		=> __( '도서공연비 소득공제대상 (카테고리)', 'iamport-for-woocommerce' ),
				'type'		=> 'multiselect',
				'default' => 'none',
				'description'		=> __( '문화체육관광부의 도서공연비 소득공제대상 상품을 선택해주세요. 선택된 카테고리 또는 위에서 선택된 상품에 해당되는 경우 소득공제주문으로 적용됩니다. 소득공제대상 상품과 비대상 상품의 혼합결제는 불가능합니다.' ),
				'options' => array(
                    "none" => "[적용대상없음]",
                    "all" => "[모든 카테고리]",
                ) + $allCategories,
			)
		), $this->form_fields);
	}

	/**
	 *
	 * Override
	 *
	 */
	public function process_admin_options() {
		$post_data = $this->get_post_data();
		$key = $this->get_field_key("sync_review");

		if ( isset($post_data[$key]) && filter_var($post_data[$key], FILTER_VALIDATE_BOOLEAN) === true ) { //turn on
			wp_schedule_event(time(), "hourly", "iamport_naverpay_sync_review");
		} else { //turn off
			$timestamp = wp_next_scheduled( 'iamport_naverpay_sync_review' );
			wp_unschedule_event( $timestamp, 'iamport_naverpay_sync_review' );
		}

		parent::process_admin_options();
	}

	public function get_user_code() {
		if ( !isset($this->settings['imp_user_code'])	)	return "";

		return $this->settings['imp_user_code'];
	}

	public function get_button_key() {
		if ( !isset($this->settings['button_key']) )	return "";

		return $this->settings['button_key'];
	}

	public function can_show_button() {
		return $this->enabled == "yes" && (isset($this->settings['show_button']) && $this->settings['show_button'] === "yes") || (isset($this->settings['debug_mode']) && $this->settings['debug_mode'] === "yes" && isset($_GET["naver-debug"]) && $_GET["naver-debug"] === "iamport");
	}

	public function is_debug_mode() {
		return isset($this->settings['debug_mode']) && $this->settings['debug_mode'] === "yes";
	}

	public function get_display_categories() {
		if ( !isset($this->settings['show_button_on_categories']) )		return array();

		$categories = $this->settings['show_button_on_categories'];
		if ( $categories === 'all' || in_array('all', $categories) )	return 'all';

		return $categories;
	}

	public function get_disabled_categories() {
		if ( !isset($this->settings['disable_button_on_categories']) )	return array();

		$categories = $this->settings['disable_button_on_categories'];
		if ( $categories === 'all' || in_array('all', $categories) )		return 'all';
		if ( $categories === 'none' || in_array('none', $categories) )	return array();

		return $categories;
	}

	public function get_attribute($key) {
		if ( !in_array($key, array("calc_shipping_tax", "shipping_zone", "button_pc_style", "button_mobile_style", "button_position_on_product", "button_position_on_cart", "inflow_account", "default_css", "culture_products", "culture_categories", "pg_id")) )	return null;

		$value = isset($this->settings[$key]) ? $this->settings[$key] : null;
		if ( $value == null && isset($this->form_fields[$key]['default']) )	$value = $this->form_fields[$key]['default'];

		return $value;
	}

	public function iamport_order_detail( $order_id ) {
		global $woocommerce;

		//TODO
		$naverPayLink = get_post_meta($order_id, '_iamport_naverpay_paylink', true);

        ob_start();
		?>
		<h2><?=__( '결제 상세', 'iamport-for-woocommerce' )?></h2>
		<table class="shop_table order_details">
			<tbody>
				<tr>
					<th><?=__( '결제수단', 'iamport-for-woocommerce' )?></th>
					<td><?=__( '네이버페이', 'iamport-for-woocommerce')?></td>
				</tr>
			</tbody>
		</table>
		<?php
		ob_end_flush();
	}

	public function is_paid_confirmed($order, $payment_data) {
		add_action( 'iamport_pre_order_completed', array($this, 'update_shipping_amount'), 10, 2 ); //불필요하게 hook이 많이 걸리지 않도록(naver-gateway객체를 여러 군데에서 생성한다.)

		return $payment_data->status === 'paid'; //이미 paid인 건에 대해서만 is_paid_confirmed가 호출되기는 하지만 한 번 더 체크
	}

	public function update_shipping_amount($order, $payment_data) {
		$shipping_amount = $payment_data->amount - $order->get_total();

		if ( $shipping_amount > 0 )	$order->add_order_note( sprintf( __( '네이버페이 주문시 상품외 추가 배송비 결제가 이뤄졌습니다.(배송비 : %s원)', 'iamport-for-woocommerce' ), number_format($shipping_amount) ) );

		$item = new WC_Order_Item_Shipping();
		$item->set_props( array(
			'method_title' => $shipping_amount > 0 ? "네이버페이 배송비":"무료배송",
			'method_id'    => 0,
			'total'        => wc_format_decimal( $shipping_amount ),
			// 'taxes'        => $shipping_rate->taxes,
			'order_id'     => $order->get_id(),
		) );
		// $item->add_meta_data( $key, $value, true ); (TODO : 배송비 항목 상품 추가하는 방법)

		try {
			// 네이버페이 상품주문정보 조회
			$impUid = $payment_data->imp_uid;

			$iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);
			$result = $iamport->getNaverProductOrders($impUid);

			if ( $result->success ) {
				$productOrders = $result->data;

				$shippingNotes = array();
				foreach ($productOrders as $idx=>$po) {
				    //product line item
                    $productLineItem = IamportHelper::findProductItem($order, $po->product_id, self::getVariationIdFromQuery($po->product_option_id), self::getAttributesFromQuery($po->product_option_id));
                    if ($productLineItem) {
                        $productLineItem->add_meta_data('naver_product_order_id', $po->product_order_id);
                        $productLineItem->add_meta_data('naver_product_order_status', $po->product_order_status);
                        $productLineItem->add_meta_data('product_amount', $po->product_amount);
                        $productLineItem->add_meta_data('delivery_amount', $po->delivery_amount);
                        $productLineItem->add_meta_data('shipping_memo', $po->shipping_memo ? $po->shipping_memo : '없음');
                        $productLineItem->add_meta_data('shipping_due', $po->shipping_due ? date('Y-m-d H:i:s', $po->shipping_due + get_option('gmt_offset')*HOUR_IN_SECONDS) : '없음');
                        $productLineItem->save_meta_data();
                    }

                    //legacy
					$shippingNotes[] = sprintf( __( '[상품명 : %s] 배송요청사항 : %s (배송기한 : %s)', 'iamport-for-woocommerce' ),
																								$po->product_name,
																								$po->shipping_memo ? $po->shipping_memo : "없음",
																								$po->shipping_due ? date('Y-m-d H:i:s', $po->shipping_due + get_option('gmt_offset')*HOUR_IN_SECONDS) : "없음");

					if ( $idx == 0 ) { //첫번째 상품정보에서 orderer / shipping 정보 추출
						$order->set_billing_first_name( $po->orderer->name );
						$order->set_billing_email( $po->orderer->id . "@naver.com" );
						$order->set_billing_phone( $po->orderer->tel );

						$order->set_shipping_first_name( $po->shipping_address->name );
						$order->add_meta_data( "_shipping_phone1", $po->shipping_address->tel1, true ); //구리지만 어쩔 수 없음
						$order->add_meta_data( "_shipping_phone2", $po->shipping_address->tel2, true ); //구리지만 어쩔 수 없음
						$order->set_shipping_address_1( $po->shipping_address->base );
						$order->set_shipping_address_2( $po->shipping_address->detail );
						$order->set_shipping_postcode( $po->shipping_address->postcode );
					}
				}

				$orderComment = implode(",\n", $shippingNotes);

				$order->add_order_note( $orderComment );
				$order->set_customer_note( $orderComment );
			} else {
                $order->add_order_note( '[네이버페이-상세조회실패] ' . $result->error['message'] );
                error_log('[네이버페이-상세조회실패] ' . $result->error['message']);
            }
		} catch (Exception $e) {
			$order->add_order_note( '[네이버페이-상세조회실패] ' . $e->getMessage() );
			error_log($e);
		}

		$item->save();
		$order->add_item( $item );

		$order->set_shipping_total( $shipping_amount ); //배송비 지정
		$order->set_billing_address_1( $payment_data->buyer_addr );
		$order->set_billing_postcode( $payment_data->buyer_postcode );

		$order->set_total( $payment_data->amount );
		$order->save();
	}

	public function naverProductsAsXml() {
		require_once(dirname(__FILE__).'/lib/Array2xml.php');
		require_once(dirname(__FILE__).'/includes/naver/NaverProduct.php');
		require_once(dirname(__FILE__).'/includes/naver/NaverOptionItem.php');
		require_once(dirname(__FILE__).'/includes/naver/NaverCombination.php');
		require_once(dirname(__FILE__).'/includes/naver/NaverShippingPolicy.php');

		/* 상품의 옵션을 조회해올 때
		(
	    	[0] => Array
	        	(
	            	[id] => XXX
	            	[optionManageCodes] => X_X,X_X
	            	[supplementIds] => XXX
	        	)
	    )
		*/

		/* 본 상품만 조회할 때
		(
	        [0] => Array
	        	(
	            	[id] => XXX
	        	)
	    )
		*/
		$isSearchingOption     = isset($_GET['optionSearch']) && filter_var($_GET['optionSearch'], FILTER_VALIDATE_BOOLEAN);
		$isSearchingSupplement = isset($_GET['supplementSearch']) && filter_var($_GET['supplementSearch'], FILTER_VALIDATE_BOOLEAN);
		$merchantCustomCode1   = isset($_GET['merchantCustomCode1']) ? intval($_GET['merchantCustomCode1']) : 0; //user ID
		$refOrderId            = isset($_GET['merchantCustomCode2']) ? wc_get_order_id_by_order_key($_GET['merchantCustomCode2']) : 0; //order key : 2.1.1 이전 버전에서는 없음
		$queryProducts         = isset($_GET['product']) ? $_GET['product'] : array();

        $refOrder = new WC_Order($refOrderId);

        WC()->session->set('chosen_shipping_methods', get_post_meta($refOrderId, '_chosen_shipping_methods', true));

		if ($merchantCustomCode1 > 0 && IamportHelper::supportMembershipPlugin()) {
		    $loginId = $merchantCustomCode1;
            $loginUser = get_user_by( 'id', $loginId );
            if ($loginUser) {
                wp_set_current_user($loginId); //가격계산을 위해 login 처리

                //membership instance 다시 초기화
                wc_memberships()->get_member_discounts_instance()->init();
            }
        }

		$xmlProducts = array();

		if ( $queryProducts ) {
		    $fakeCart = new WC_Cart();
            $fakeCartMaxFee = 0;

			foreach ($queryProducts as $p) {
				$wooProduct = wc_get_product( $p['id'] );
				$optionManageCodes = explode(",", $p['optionManageCodes']); // variation id를 optionManageCode로 사용

				$naverProduct = new NaverProduct();
				$naverProduct->id   = $p['id'];
				$naverProduct->name = $wooProduct->get_title(); //line_item의 name으로 사용됨
				$naverProduct->taxType = $wooProduct->is_taxable() ? "TAX" : "TAX_FREE";
				$naverProduct->basePrice = $wooProduct->get_price();
				$naverProduct->infoUrl = get_post_permalink( $p['id'] );
				$naverProduct->imageUrl = IamportNaverPayButton::getProductImageSrc($wooProduct);
				$naverProduct->status = $wooProduct->is_purchasable() ? "ON_SALE" : "NOT_SALE";

                $useWoocommerceShippingCalc = WC_Gateway_Iamport_NaverPay::useWoocommerceShippingCalc($p['id']); //parent product id
                $calcShippingTax = filter_var($this->get_attribute('calc_shipping_tax'), FILTER_VALIDATE_BOOLEAN);
                $defaultZoneId = intval($this->get_attribute('shipping_zone'));
				$shippingMethods = WC_Gateway_Iamport_NaverPay::getShippingMethodsForProduct($wooProduct, $defaultZoneId); // 지정된 shipping method가 없으면 무료배송
				$naverProduct->shippingPolicy = WC_Gateway_Iamport_NaverPay::toShippingPolicy($wooProduct, $shippingMethods, $calcShippingTax);

				//3rd parth shipping policy 적용 후 surcharge 적용

				if ( $isSearchingOption && $wooProduct->get_type() === "variable" ) {
					/*
					Array
					(
					    [pa_color] => Array
					        (
					            [0] => red
					            [1] => blue
					        )

					)
					*/
					$variations = $wooProduct->get_available_variations(); //옵션 조합
					$attributes = $wooProduct->get_variation_attributes(); //선택가능한 옵션들
					$variationPrices = $wooProduct->get_variation_prices(true); //낮은 가격순으로 정렬되어 있음

					//optionItem
					foreach ($attributes as $attribute_name => $options) {
						$attribute_key = urldecode( $attribute_name ); //한글 encoding된 경우를 대비해 decode먼저 해줌

						$optionItem = new NaverOptionItem("SELECT", wc_attribute_label( $attribute_key, $wooProduct ));

						// $terms = wc_get_product_terms( $wooProduct->get_id(), $attribute_key, array( 'fields' => 'all' ) );
						$terms = IamportNaverPayButton::get_product_attributes($wooProduct, $attribute_key);//term이 아닌 경우 대응

						foreach ($terms as $term) {
							$optionItem->addCase(IamportNaverPayButton::sanitize_slug_for_naver($term->slug), $term->name);
						}

						$naverProduct->addOptionItem($optionItem);
					}

					//combination
                    foreach($optionManageCodes as $optionCode) {
                        $variationId = self::getVariationIdFromQuery($optionCode);
                        $optionAttributes = self::getAttributesFromQuery($optionCode);
                        $variation = wc_get_product( $variationId );

                        if ( !$variation instanceof WC_Product_Variation ) {
                            continue;
                        }

                        if ( !$variation->variation_is_active() || !$variation->variation_is_visible() ) {
                            continue;
                        }

                        $combination = new NaverCombination($optionCode);
                        $combination->setPrice($variationPrices["price"][ $variationId ] - $wooProduct->get_price());

                        //attributes 의 loop 순서는 결제요청단계와 같으므로 $optionCode 를 | 구분자로 잘랐을 때 순서대로 매칭
                        $attrIndex = 0;
                        foreach ($attributes as $attribute_name => $options) {
                            if (!isset($optionAttributes[$attrIndex])) {
                                $attrIndex++;
                                continue;
                            }

                            $attribute_key = urldecode($attribute_name); //한글 encoding된 경우를 대비해 decode먼저 해줌
                            $terms = IamportNaverPayButton::get_product_attributes($wooProduct, $attribute_key);//term이 아닌 경우 대응

                            foreach ($terms as $term) {
                                $optionSlug = IamportNaverPayButton::sanitize_slug_for_naver($term->slug);
                                if ($optionSlug == $optionAttributes[$attrIndex]) {
                                    $combination->addOption(wc_attribute_label($attribute_key, $wooProduct), $optionSlug);
                                }
                            }

                            $attrIndex++;
                        }

                        $naverProduct->addCombination($combination);

                        //우커머스 자체 배송비 계산
                        if ($useWoocommerceShippingCalc) {
                            $lineItem = IamportHelper::findProductItem($refOrder, $p['id'], $variationId);

                            if ($lineItem) {
                                $qty = $lineItem->get_quantity();
                                $internalShippingPolicy = WC_Gateway_Iamport_NaverPay::internalShippingPolicy($fakeCart, $p['id'], $qty, $variationId);

                                $naverProduct->shippingPolicy = $internalShippingPolicy;
                                $fakeCartMaxFee = max($fakeCartMaxFee, $internalShippingPolicy->getBaseFee());
                            }
                        }
                    }
				} else {
				    if ($isSearchingOption) { //[2020-05-05] 3rd party product add on
                        foreach($optionManageCodes as $optionCode) {
                            if (self::isYITHOption($optionCode)) {
                                $itemId = intval( substr($optionCode, strlen(self::PLUGIN_YITH_PRODUCT_OPTION)) );

                                $productLineItem = $refOrder->get_item($itemId);
                                $productLineItem->read_meta_data();

                                $combination = new NaverCombination($optionCode);

                                $yithMeta = $productLineItem->get_meta(WC_Gateway_Iamport_NaverPay::OPTION_YITH_PRODUCT_OPTION);
                                if (!empty($yithMeta)) {
                                    $yithOptionPrice = 0;
                                    foreach($yithMeta as $yithOption) {
                                        $optionItem = new NaverOptionItem("SELECT", $yithOption['name']);
                                        $optionItem->addCase(IamportNaverPayButton::sanitize_slug_for_naver(sanitize_title($yithOption['value'])), $yithOption['value']);
                                        $naverProduct->addOptionItem($optionItem);

                                        $combination->addOption($yithOption['name'], IamportNaverPayButton::sanitize_slug_for_naver(sanitize_title($yithOption['value'])));

                                        $yithOptionPrice += intval($yithOption['price']);
                                    }

                                    $combination->setPrice($yithOptionPrice);
                                    $naverProduct->addCombination($combination);
                                }
                            } else if (self::isExtraProductOption($optionCode)) {
                                $itemId = intval( substr($optionCode, strlen(self::PLUGIN_EXTRA_PRODUCT_OPTION)) );

                                $productLineItem = $refOrder->get_item($itemId);
                                $productLineItem->read_meta_data();

                                $combination = new NaverCombination($optionCode);

                                $epMeta = $productLineItem->get_meta(self::OPTION_EXTRA_PRODUCT_OPTION);
                                if (!empty($epMeta)) {
                                    foreach ($epMeta as $epName=>$epData) {
                                        if (isset($epData["value"])) {
                                            $optionItem = new NaverOptionItem("SELECT", $epData["label"]);
                                            $optionItem->addCase(IamportNaverPayButton::sanitize_slug_for_naver(sanitize_title($epData['value'])), $epData['value']);
                                            $naverProduct->addOptionItem($optionItem);

                                            $combination->addOption($epData["label"], IamportNaverPayButton::sanitize_slug_for_naver(sanitize_title($epData['value'])));
                                        }
                                    }

                                    $naverProduct->addCombination($combination);
                                }
                            } else if (self::isTMExtraProductOption($optionCode)) {
                                $itemId = intval( substr($optionCode, strlen(self::PLUGIN_TM_EXTRA_PRODUCT_OPTION)) );

                                $productLineItem = $refOrder->get_item($itemId);
                                $productLineItem->read_meta_data();

                                $combination = new NaverCombination($optionCode);

                                $tmRepo = $productLineItem->get_meta(WC_Gateway_Iamport_NaverPay::OPTION_TM_EXTRA_PRODUCT_OPTION_REPO);
                                if (!empty($tmRepo)) {
                                    $tmOptionPrice = 0;
                                    foreach($tmRepo as $tmOptionItem) {
                                        $optionItem = new NaverOptionItem("SELECT", $tmOptionItem['name']);
                                        $optionItem->addCase(IamportNaverPayButton::sanitize_slug_for_naver(sanitize_title($tmOptionItem['value'])), $tmOptionItem['value']);
                                        $naverProduct->addOptionItem($optionItem);

                                        $combination->addOption($tmOptionItem['name'], IamportNaverPayButton::sanitize_slug_for_naver(sanitize_title($tmOptionItem['value'])));

                                        $tmOptionPrice += intval($tmOptionItem['price']);
                                    }

                                    if (WC_Gateway_Iamport_NaverPay::isTMExtraPriceOverride($p['id'])) {
                                        $naverProduct->basePrice = 0;
                                    }

                                    $combination->setPrice($tmOptionPrice);
                                    $naverProduct->addCombination($combination);
                                }
                            }
                        }
                    }

				    //우커머스 자체 배송비 계산
                    if ($useWoocommerceShippingCalc) {
                        $lineItem = IamportHelper::findProductItem($refOrder, $p['id']);

                        if ($lineItem) {
                            $qty = $lineItem->get_quantity();
                            $internalShippingPolicy = WC_Gateway_Iamport_NaverPay::internalShippingPolicy($fakeCart, $p['id'], $qty);

                            $naverProduct->shippingPolicy = $internalShippingPolicy;
                            $fakeCartMaxFee = max($fakeCartMaxFee, $internalShippingPolicy->getBaseFee());
                        }
                    }
                }

				if (!$useWoocommerceShippingCalc) {
                    //도서산간 배송비
                    $surchargeAreas = WC_Gateway_Iamport_NaverPay::getAreasForSurcharge($wooProduct);
                    if ( $surchargeAreas ) {
                        $naverProduct->shippingPolicy->setSurcharge( new NaverSurchargesByArea($surchargeAreas) );
                    }
                }

				$xmlProducts[] = $naverProduct;
			}

            //우커머스 자체 배송비 계산에 대한 clean up
            foreach ($xmlProducts as $idx=>$xp) {
                if ($xp->shippingPolicy->getGroupId() == 'woocommerce') {
                    $xmlProducts[$idx]->shippingPolicy->setBaseFee($fakeCartMaxFee);
                }
            }
		}

		header("Content-type: application/xml; charset=utf-8");
		exit( self::toXml($xmlProducts) );
	}

	public function isCultureBenefitOrder($products) {
		$cultureProducts = $this->get_attribute("culture_products");
		$cultureCategories = $this->get_attribute("culture_categories");
		if ( $cultureProducts || $cultureCategories ) {
			$isProductNone = $cultureProducts === "none" || in_array("none", $cultureProducts);
			$isCategoryNone = $cultureCategories === "none" || in_array("none", $cultureCategories);

			$isProductAll = $cultureProducts === "all"  || in_array("all", $cultureProducts);
			$isCategoryAll = $cultureCategories === "all"  || in_array("all", $cultureCategories);

			if ( $isProductNone && $isCategoryNone )	return false;
			if ( $isProductAll  || $isCategoryAll  )	return true;

			$inspects = array_map(function($p) { return $p["product_id"]; }, $products);
			$excluded = array_values( array_diff($inspects, $cultureProducts) );
			$intersect = array_values( array_intersect($inspects, $cultureProducts) );

			//category 체크해서 excluded중에서 intersect로 옮길 product가 있는지 확인
			foreach ($excluded as $idx=>$pid) {
				if ( IamportHelper::is_product_in_categories($pid, $cultureCategories) ) {
					$intersect[] = $pid;
					unset($excluded[$idx]);
				}
			}

			//1개 상품인 경우는 exception 이 발생하면 안됨
			if ( count($products) > 1 && !empty($excluded) && !empty($intersect) )	throw new Exception( __("도서공연비 소득공제대상 상품 / 비대상 상품은 혼합하여 결제가 불가능합니다.", "iamport-for-woocommerce") );

			return empty($excluded);
		}

		return false;
	}

	public static function toShippingPolicy($wooProduct, $shippingMethods, $calcShippingTax=false) {
		//기본적으로 무료배송 적용해둔다.
		$policy = new NaverShippingPolicy();
		$policy->setGroupId( "none" );
		$policy->setMethod( $wooProduct->is_virtual() ? "NOTHING" : "DELIVERY" );
		$policy->setFeePayType( "FREE" );
		$policy->setBaseFee( 0 );
		$policy->setFeeRule( null );

		$free_shipping = $shippingMethods["free_shipping"];
		$defaultShipping = $shippingMethods["default"];

		if ( $free_shipping instanceof WC_Shipping_Method ) { //무료배송조건이 있는 경우
			//기본배송비를 지정하지 않으면 무료배송비 적용
			if ( $defaultShipping && $defaultShipping->id == "flat_rate" ) {
				if ( $free_shipping->min_amount > 0 ) {
					$rule = self::getShippingRuleFromFlatRate($wooProduct, $defaultShipping, $calcShippingTax);
					if ( $rule && $rule["type"] == "fixed" ) {
						$policy->setGroupId( $free_shipping->get_instance_id()."-".$defaultShipping->get_instance_id() );
						$policy->setBaseFee( $rule["baseFee"] );
						$policy->setFeePayType( "PREPAYED" );
						$policy->setFeeRule( new NaverFreeByThreshold($free_shipping->min_amount) );
					}
				}
			}
		} else if ( $free_shipping === "depend" && $defaultShipping && $defaultShipping->id == "flat_rate" ) { //무료배송조건이 없는 경우
			$rule = self::getShippingRuleFromFlatRate($wooProduct, $defaultShipping, $calcShippingTax);
			if ( $rule && $rule["type"] == "fixed" ) {
				$policy->setGroupId( $defaultShipping->get_instance_id() );
				$policy->setFeePayType( "PREPAYED" );
				$policy->setBaseFee( $rule["baseFee"] );
			} else if ( $rule && $rule["type"] == "quantity" ) {
				$policy->setGroupId( $defaultShipping->get_instance_id() );
				$policy->setFeePayType( "PREPAYED" );
				$policy->setBaseFee( $rule["unitFee"] ); // unitFee가 반복배송비의 기준이 된다.
				$policy->setFeeRule( new NaverRangesByQuantity(1) );
			}
		}

		return $policy;
	}

	public static function internalShippingPolicy($fakeCart, $productId, $quantity, $variationId=0)
    {
        $fakeCart->add_to_cart($productId, $quantity, $variationId);
        $fee = intval($fakeCart->get_shipping_total());

        $policy = new NaverShippingPolicy();
        $policy->setGroupId( 'woocommerce' );
        $policy->setMethod( "DELIVERY" );
        $policy->setFeePayType( $fee > 0 ? 'PREPAYED':'FREE' );
        $policy->setBaseFee( $fee );
        $policy->setFeeRule( null );

        return $policy;
    }

    public static function useWoocommerceShippingCalc($productId)
    {
        return get_post_meta($productId, 'iamport_naverpay_use_woocommerce_shipping_calc', true) === 'yes';
    }

	public static function getShippingMethodsForProduct($wooProduct, $zoneId=0) {
		//네이버 정책상 feeType이 고정되므로 우선순위를 두고 하나의 method를 반환한다.
		//상품설정에서 zone별로 적용가능한 shipping method를 지정하도록 한다.
		return self::getAppliedShippingMethods( $wooProduct->get_id(), $zoneId );
	}

	public static function getAreasForSurcharge($wooProduct) {
		$productId = $wooProduct->get_id();

		$surchargeIsland = intval( get_post_meta($productId, 'iamport_naverpay_shipping_surcharge_area_island', true) );
		$surchargeJeju = intval( get_post_meta($productId, 'iamport_naverpay_shipping_surcharge_area_jeju', true) );

		if ( $surchargeIsland > 0 || $surchargeJeju > 0 ) {
			$island = new stdClass();
			$island->area = "island";
			$island->surcharge = $surchargeIsland;

			$areas = array(
				$island,
			);

			if ( $surchargeIsland != $surchargeJeju ) { //제주도만 배송비가 0원일 수도 있을까?
				$jeju = new stdClass();
				$jeju->area = "jeju";
				$jeju->surcharge = $surchargeJeju;
				$areas[] = $jeju;
			}

			return $areas;
		}

		return null;
	}

	private static function getAppliedShippingMethods($productId, $zoneId=0) {
		$appliedMethods = array(
			"free_shipping" => null,
			"default" => null,
		);

		$defaultShippingZone = new WC_Shipping_Zone($zoneId);
		$methods = $defaultShippingZone->get_shipping_methods(true); //enabled only

		$freeInstanceId = get_post_meta($productId, 'iamport_naverpay_free_shipping_method_zone_'.$zoneId, true);
		if ( !in_array($freeInstanceId, array("depend", "always")) && $freeInstanceId > 0 ) {
			$appliedMethods["free_shipping"] = $methods[ $freeInstanceId ];
		} else {
			$appliedMethods["free_shipping"] = $freeInstanceId;
		}

		$defaultInstanceId = get_post_meta($productId, 'iamport_naverpay_shipping_method_zone_'.$zoneId, true);
		if ( $defaultInstanceId != "none" && $defaultInstanceId > 0 ) {
			$appliedMethods["default"] = $methods[ $defaultInstanceId ];
		}

		return $appliedMethods;
	}

	public static function getShippingRuleFromFlatRate($wooProduct, $flatMethod, $calcShippingTax=false) {
		$feeType = null;
		$costString = preg_replace( '/\s+/', '', $flatMethod->get_option("cost") );
		$taxable = $calcShippingTax && $flatMethod->get_option("tax_status") === 'taxable';
		$baseFee = 0;

		//costString을 먼저 파싱한다.
		if ( preg_match('/^(\[qty\]\*(\d+))$/', $costString, $matches) > 0 || preg_match('/^((\d+)\*\[qty\])$/', $costString, $matches) > 0 ) { //[qty]*가격
			$unitFee = intval($matches[2]);
			if ($taxable) {
			    $unitFee = round($unitFee * 1.1);
            }

			return array("type"=>"quantity", "baseFee"=>0, "unitFee"=>$unitFee);
		} else if ( preg_match('/^(\d+)$/', $costString, $matches) ) { //가격
			$baseFee = intval($matches[1]);
		}

		//costString이 비어있거나 숫자로 모두 간주(즉, baseFee가 0이거나 숫자로 지정된 상황)
		$class_id = $wooProduct->get_shipping_class_id();
		if ( $class_id ) {
			$shippingClassTerm = get_term_by( 'id', $class_id, 'product_shipping_class' );

			if ( $shippingClassTerm && $shippingClassTerm->term_id ) {
				$classCostString = $flatMethod->get_option( 'class_cost_' . $shippingClassTerm->term_id, $flatMethod->get_option( 'class_cost_' . $slug, '' ) );
			}
		} else {
			$classCostString = $flatMethod->get_option( 'no_class_cost', '' );
		}

		$classCostString = preg_replace( '/\s+/', '', $classCostString );
		if ( $baseFee == 0 ) { //baseFee가 0원이면 classCost의 수식을 인정해준다.

			if ( preg_match('/^(\[qty\]\*(\d+))$/', $classCostString, $matches) > 0 || preg_match('/^((\d+)\*\[qty\])$/', $classCostString, $matches) > 0 ) { //[qty]*가격
				$unitFee = intval($matches[2]);
				if ($taxable) {
				    $unitFee = round($unitFee * 1.1);
                }

				return array("type"=>"quantity", "baseFee"=>0, "unitFee"=>$unitFee);
			} else if ( preg_match('/^(\d+)$/', $classCostString, $matches) ) { //가격
				$baseFee += intval($matches[1]);
				if ($taxable) {
				    $baseFee = round($baseFee * 1.1);
                }

				return array("type"=>"fixed", "baseFee"=>$baseFee, "unitFee"=>0);
			}

		} else { //baseFee가 0보다크면 classCost는 숫자만 인정한다.

			if ( preg_match('/^(\d+)$/', $classCostString, $matches) ) { //가격
				$baseFee += intval($matches[1]);
			}

			if ($taxable) {
			    $baseFee = round($baseFee * 1.1);
            }

			//classCostString가 무시되더라도 baseFee가 있으므로
			return array("type"=>"fixed", "baseFee"=>$baseFee, "unitFee"=>0);

		}

		return null;
	}

/*
	private function getBestFreeShippingMethod() {
		$method = null;

		$freeMethods = $this->getShippingMethodsByType("free_shipping");
		if ( empty($freeMethods) )	return null;

		usort($freeMethods, function($a, $b) {
			return $a->min_amount - $b->min_amount;
		});

		return $freeMethods[0]; //가장 조건이 좋은 freeMethod를 적용한다.
	}

	private function getFlatShippingMethod($slug=null) {
		$flatMethods = $this->getShippingMethodsByType("flat_rate");
		if ( empty($flatMethods) )	return null;

		return $flatMethods[0]; //첫 번째 flat rate를 적용한다

		// $methods = array();
		// foreach ($flatMethods as $m) {
		// 	if ( $m->type == "order" ) {
		// 		$methods[] = $m;
		// 	} else {
		// 		$shippingClassTerm = get_term_by( 'slug', $slug, 'product_shipping_class' );
		// 		if ( $shippingClassTerm && $shippingClassTerm->term_id ) {
		// 			$classCostString = $m->get_option( 'class_cost_' . $shippingClassTerm->term_id, $m->get_option( 'class_cost_' . $slug, '' ) );
		// 		}

		// 		if ( "" !== $classCostString ) {
		// 			$methods[] = $m;
		// 		}
		// 	}
		// }

		// return $methods;
	}

	private function getShippingMethodsByType($methodType) { //flat, free
		$shippingMethods = WC()->shipping()->load_shipping_methods();

		$methods = array();
		foreach ($shippingMethods as $m) {
			if ( $m->get_id() == $methodType )	$methods[] = $m;
		}

		return $methods;
	}
*/

	private static function toXml($products) {
		$response = array();
		foreach ($products as $idx => $p) {
			$response['product'.$idx] = $p->getAttribute();
		}

		$array2xml = new Array2xml();
		$array2xml->setRootName('products');
		$array2xml->setFilterNumbersInTags(array('product', 'selectedItem', 'optionItem', 'value', 'options', 'combination'));
		$array2xml->setCDataKeys(array('name', 'infoUrl', 'imageUrl', 'backUrl', 'giftName', 'address1', 'address2', 'sellername', 'contact1', 'contact2', 'text', 'manageCode'));

		return $array2xml->convert( $response );
	}

	public static function getVariationIdFromQuery($optionManageCode)
    {
        $arr = explode("|", $optionManageCode);

        if (!is_numeric($arr[0])) { //[2020-05-05] 3rd party product add on에 의한 manageCode 인 경우 findProductItem() 호출 시 simple product 로 간주되도록 null 반환
            return null;
        }

        return $arr[0];
    }

    public static function getAttributesFromQuery($optionManageCode)
    {
        $arr = explode("|", $optionManageCode);

        if (!is_numeric($arr[0])) { //[2020-05-05] 3rd party product add on에 의한 manageCode 인 경우 findProductItem() 호출 시 simple product 로 간주되도록 null 반환
            return null;
        }

        return array_slice($arr, 1);
    }

    public static function isYITHOption($optionManageCode)
    {
        return strpos($optionManageCode, self::PLUGIN_YITH_PRODUCT_OPTION) === 0;
    }

    public static function isExtraProductOption($optionManageCode)
    {
        return strpos($optionManageCode, self::PLUGIN_EXTRA_PRODUCT_OPTION) === 0;
    }

    public static function isTMExtraProductOption($optionManageCode)
    {
        return strpos($optionManageCode, self::PLUGIN_TM_EXTRA_PRODUCT_OPTION) === 0;
    }

    public static function isTMExtraPriceOverride($productId)
    {
        $override_id = floatval( THEMECOMPLETE_EPO_WPML()->get_original_id( $productId, 'product' ) );
        $tm_meta_cpf = themecomplete_get_post_meta( $override_id, 'tm_meta_cpf', TRUE );
        $price_override = ( THEMECOMPLETE_EPO()->tm_epo_global_override_product_price == 'no' )
            ? 0
            : ( ( THEMECOMPLETE_EPO()->tm_epo_global_override_product_price == 'yes' )
                ? 1
                : ( ! empty( $tm_meta_cpf['price_override'] ) ? 1 : 0 ) );

        return $price_override;
    }
}

//init

class IamportNaverPayButton {

	private $gateway;

	public function __construct($gateway) {
		$this->gateway = $gateway;
	}

	public function init() {
		if ( !$this->gateway->can_show_button() && !$this->gateway->is_debug_mode() )	return; //버튼도 안보이고, 검수도 안하면 비활성화

		if ( $this->gateway->get_attribute("button_position_on_product") == "after" ) {
			add_action( 'woocommerce_after_add_to_cart_button', array($this, 'display'), 20 );
		} else {
			add_action( 'woocommerce_after_add_to_cart_form', array($this, 'display') );
		}

		if ( $this->gateway->get_attribute("button_position_on_cart") == "below" ) {
			add_action( 'woocommerce_proceed_to_checkout', array($this, 'display_cart'), 20 );
		} else {
			add_action( 'woocommerce_proceed_to_checkout', array($this, 'display_cart'), 5 );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_iamport_script'), 5 ); //아임포트 결제버튼 생성플러그인, EDD와 같이 설치돼있을 때 먼저 불리도록(다른 것은 모두 1.1.2사용)

		add_action('wp_ajax_iamport_naver_info', array($this, 'ajax_iamport_naver_product') );
		add_action('wp_ajax_nopriv_iamport_naver_info', array($this, 'ajax_iamport_naver_product') );
		add_action('wp_ajax_iamport_naver_zzim_info', array($this, 'ajax_iamport_naver_zzim') );
		add_action('wp_ajax_nopriv_iamport_naver_zzim_info', array($this, 'ajax_iamport_naver_zzim') );
		add_action('wp_ajax_iamport_naver_carts', array($this, 'ajax_iamport_naver_carts') );
		add_action('wp_ajax_nopriv_iamport_naver_carts', array($this, 'ajax_iamport_naver_carts') );

		//유입 스크립트
		add_action( 'wp_head', array($this, 'init_inflow_script'), 50 );
		add_action( 'wp_footer', array($this, 'log_inflow_script'), 50 );
	}

	private static function is_product_purchasable($product, $disabled_categories) {
		$is_subscription = class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product );
		$is_disabled = $disabled_categories === 'all' || IamportHelper::is_product_in_categories($product->get_id(), $disabled_categories);

		return 	!$is_disabled &&
						!$is_subscription &&
						$product->is_purchasable() &&
//						$product->get_price() > 0 && 옵션 상품의 경우 base price 가 0원일 수 있다.
						$product->is_in_stock() &&
						$product->needs_shipping() &&
						!$product->is_downloadable();
	}

	private function all_products_in_categories($product_ids, $categories) {
		$all_match = true;
		foreach ($product_ids as $id) {
			$all_match = $all_match && IamportHelper::is_product_in_categories($id, $categories);
		}

		return $all_match;
	}

	public function display() {
		global $product;

		if ( $this->gateway->can_show_button() ) {
			$categories = $this->gateway->get_display_categories();
			$disabled_categories = $this->gateway->get_disabled_categories();

			if ( $categories === 'all' || IamportHelper::is_product_in_categories($product->get_id(), $categories) ) {
				if ( $product->get_type() == "grouped" ) {
					$enabled = true;
					foreach ($product->get_children() as $child_id) {
						$child = wc_get_product($child_id);
						$enabled = $enabled && self::is_product_purchasable($child, $disabled_categories);
					}
				} else {
					$enabled = self::is_product_purchasable($product, $disabled_categories);
				}

				echo '<div class="iamport-naverpay-container"><div id="iamport-naverpay-button-' . sprintf('%05d', mt_rand(1, 10000)) . '" class="iamport-naverpay-product-button ' . ($enabled ? "enabled":"") . '"></div></div><style type="text/css">'.$this->gateway->get_attribute("default_css").'</style>';
			}
		}
	}

	public function display_cart() {
		$cart_items = WC()->cart->get_cart();
		if ( count($cart_items) == 0 )	return; //장바구니가 비어있으면 패스

		if ( $this->gateway->can_show_button() ) {
			$categories = $this->gateway->get_display_categories();
			$disabled_categories = $this->gateway->get_disabled_categories();
			$product_ids = array();
			$enabled = true;

			foreach ($cart_items as $key => $item) {
				$product = wc_get_product($item["product_id"]);
				$product_ids[] = $product->get_id();

				$enabled = $enabled && self::is_product_purchasable($product, $disabled_categories);
			}

			if ( $categories === 'all' || $this->all_products_in_categories($product_ids, $categories) ) {
				echo '<div class="iamport-naverpay-container"><div id="iamport-naverpay-cart-button" class="' . ($enabled ? "enabled":"") . '"></div></div><style type="text/css">'.$this->gateway->get_attribute("default_css").'</style>';
			}
		}
	}

	public function init_inflow_script() {
		$url = parse_url(home_url());

		ob_start();?>
			<script type="text/javascript">
				if(!wcs_add) var wcs_add = {};
				wcs_add["wa"] = "<?=$this->gateway->get_attribute('inflow_account')?>";
				wcs.inflow("<?=preg_replace('#^www\.(.+\.)#i', '$1', $url['host'])?>");
			</script>
		<?php
		echo ob_get_clean();
	}

	public function log_inflow_script() {
		ob_start();?>
			<script type="text/javascript">
				wcs_do();
			</script>
		<?php
		echo ob_get_clean();
	}

	public function enqueue_iamport_script() {
		if ( wp_is_mobile() ) {
			$style = $this->gateway->get_attribute("button_mobile_style");
			$button_type = $style;
			$button_color = "";
		} else {
			$style = $this->gateway->get_attribute("button_pc_style");
			$button_type = substr($style, 0, 1);
			$button_color = strlen($style) > 1 ? substr($style, 1, 1) : "1";
		}

		wp_register_script( 'woocommerce_iamport_script', 'https://cdn.iamport.kr/js/iamport.payment-1.1.7.js', array('jquery'), '20190812' );
		wp_register_script( 'naver_inflow_script', '//wcs.naver.net/wcslog.js' );

		if ( wp_is_mobile() ) { //mobile
			if ( $this->gateway->is_debug_mode() ) {
				wp_register_script( 'naverpay_script', '//test-pay.naver.com/customer/js/mobile/naverPayButton.js' );
			} else {
				wp_register_script( 'naverpay_script', '//pay.naver.com/customer/js/mobile/naverPayButton.js' );
			}
		} else { //PC
			if ( $this->gateway->is_debug_mode() ) {
				wp_register_script( 'naverpay_script', '//test-pay.naver.com/customer/js/naverPayButton.js' );
			} else {
				wp_register_script( 'naverpay_script', '//pay.naver.com/customer/js/naverPayButton.js' );
			}
		}

		wp_register_script( 'iamport_naverpay_for_woocommerce', plugins_url( '/assets/js/iamport.woocommerce.naverpay.js',plugin_basename(__FILE__) ), array('jquery', 'woocommerce_iamport_script', 'naverpay_script'), '20210122');
		wp_localize_script( 'iamport_naverpay_for_woocommerce', 'iamport_naverpay', array(
			'ajax_info_url' => admin_url( 'admin-ajax.php' ),
			'user_code' => $this->gateway->get_user_code(),
			'button_key' => trim($this->gateway->get_button_key()),
			'button_type' => $button_type,
			'button_color' => $button_color,
		));

		wp_enqueue_script('woocommerce_iamport_script');
		wp_enqueue_script('naver_inflow_script');
		wp_enqueue_script('naverpay_script');
		wp_enqueue_script('iamport_naverpay_for_woocommerce');
	}

	private function response_products($products) {
		header('Content-type: application/json');

        require_once(dirname(__FILE__).'/lib/Array2xml.php');
        require_once(dirname(__FILE__).'/includes/naver/NaverProduct.php');
        require_once(dirname(__FILE__).'/includes/naver/NaverOptionItem.php');
        require_once(dirname(__FILE__).'/includes/naver/NaverCombination.php');
        require_once(dirname(__FILE__).'/includes/naver/NaverShippingPolicy.php');

        //우커머스 주문 생성 후 응답
        $new_order = self::create_order($products);

        //상품정보 XML 생성 시작
		$naverProducts = array();
		$amount = 0;

		$isCultureBenefitOrder = $this->gateway->isCultureBenefitOrder($products);

		if ( !empty($products) && is_array($products) ) {
		    $fakeCart = new WC_Cart();
		    $fakeCartMaxFee = 0;

			foreach ($products as $p) {
			    $clearBasePrice = false;

				if ( isset($p["variation_id"]) ) { //variation
					$variation = new WC_Product_Variation( $p["variation_id"] );
					$wooProduct = wc_get_product( wp_get_post_parent_id( $variation->get_id() ) );
					$variationPrices = $wooProduct->get_variation_prices(true); //낮은 가격순으로 정렬되어 있음
					/*
					(
					    [attribute_size] => 160
					    [attribute_color] => Red
					)
					*/
					if ( !empty($p["variations"]) ) { //속성값이 POST로 전송된 경우
						$attributes = $p["variations"];
					} else {
						$attributes = $variation->get_variation_attributes();

					}

					$selections = array();
					$detailedSelectionCodes = array();
					foreach ($attributes as $k=>$v) {
						$attribute_name = urldecode( str_replace("attribute_", "", $k) ); //한글인 경우 encoding된 것과 혼재될 수 있어 decode를 먼저 해준다.

						// $terms = wc_get_product_terms( $wooProduct->get_id(), $attribute_name, array( 'fields' => 'all' ) );
						$terms = self::get_product_attributes($wooProduct, $attribute_name);//term이 아닌 경우 대응

						foreach ($terms as $term) {
							if ( $term->slug === sanitize_title($v) ) { //[v2.0.30]term->slug는 sanitize_title 된 결과를 가지고 있으므로 $v도 sanitize_title 한 결과와 비교해야 함
							    $optionCode = self::sanitize_slug_for_naver( $term->slug );
								$selections[] = array(
									"code"  => $optionCode,
									"label" => mb_substr(wc_attribute_label( $attribute_name, $wooProduct ), 0, 20, 'UTF-8') ,
									"value" => $term->name,
								);

								$detailedSelectionCodes[] = $optionCode;
							}
						}
					}

					$options = array(
						"optionPrice"    => $variationPrices["price"][$variation->get_id()] - $wooProduct->get_price(),
						"selectionCode"  => $p["variation_id"] . "|" . implode("|", $detailedSelectionCodes), //[2020-04-08] version 2.1.16 : variation_id는 1개로 설정돼있고 attribute POST data 로 결정되는 경우 상세 정보가 전달되어야 XML조회 시 대응이 가능. | 는 우커머스 속성값으로 사용이 안되므로 | 를 구분자로 사용
						"selections"     => $selections,
					);

					$amount += $variationPrices["price"][$variation->get_id()] * intval( $p["quantity"] );
				} else { //simple
					$wooProduct = wc_get_product( $p["product_id"] );
					$options = null;

					//[2020-04-29] support 3rd-party product add-on (simple product 에만 지원하자)
                    $productLineItem = IamportHelper::findProductItem($new_order, $p["product_id"]);
                    $productLineItem->read_meta_data();
                    //1. YITH
                    $yithMeta = $productLineItem->get_meta(WC_Gateway_Iamport_NaverPay::OPTION_YITH_PRODUCT_OPTION);
                    if (!empty($yithMeta)) {
                        $yithSelections = [];
                        $yithOptionPrice = 0;
                        foreach($yithMeta as $yithOption) {
                            $yithSelections[] = array(
                                "code" => IamportNaverPayButton::sanitize_slug_for_naver(sanitize_title($yithOption['name'])),
                                "label" => $yithOption['name'],
                                "value" => $yithOption['value'],
                            );

                            $yithOptionPrice += intval($yithOption['price']);
                        }

                        //set options
                        $options = array(
                            "optionPrice" => $yithOptionPrice,
                            "selectionCode" => WC_Gateway_Iamport_NaverPay::PLUGIN_YITH_PRODUCT_OPTION . $productLineItem->get_id(), //prefix 로 약속
                            "selections" => $yithSelections,
                        );
                    }

                    //2. Extra Product Option
                    $epMeta = $productLineItem->get_meta(WC_Gateway_Iamport_NaverPay::OPTION_EXTRA_PRODUCT_OPTION);
                    if (!empty($epMeta)) {
                        $epSelections = [];
                        foreach ($epMeta as $epName=>$epData) {
                            if (isset($epData["value"])) {
                                $epSelections[] = array(
                                    "code" => IamportNaverPayButton::sanitize_slug_for_naver($epName),
                                    "label" => $epData["label"],
                                    "value" => $epData["value"],
                                );
                            }
                        }

                        //set options
                        $options = array(
                            "optionPrice" => 0,
                            "selectionCode" => WC_Gateway_Iamport_NaverPay::PLUGIN_EXTRA_PRODUCT_OPTION . $productLineItem->get_id(), //prefix 로 약속
                            "selections" => $epSelections,
                        );
                    }

                    //3. TM Extra Product Option
                    $tmRepo = $productLineItem->get_meta(WC_Gateway_Iamport_NaverPay::OPTION_TM_EXTRA_PRODUCT_OPTION_REPO);
                    if (!empty($tmRepo)) {
                        $tmSelections = [];
                        $tmOptionPrice = 0;
                        foreach($tmRepo as $tmOptionItem) {
                            $tmSelections[] = array(
                                "code" => IamportNaverPayButton::sanitize_slug_for_naver(sanitize_title($tmOptionItem['value'])),
                                "label" => $tmOptionItem['name'],
                                "value" => $tmOptionItem['value'],
                            );

                            $tmOptionPrice += $tmOptionItem['price'];
                        }

                        //set options
                        if (WC_Gateway_Iamport_NaverPay::isTMExtraPriceOverride($p["product_id"])) {
                            $clearBasePrice = true;
                        }

                        $options = array(
                            "optionPrice" => $tmOptionPrice,
                            "selectionCode" => WC_Gateway_Iamport_NaverPay::PLUGIN_TM_EXTRA_PRODUCT_OPTION . $productLineItem->get_id(), //prefix 로 약속
                            "selections" => $tmSelections,
                        );
                    }

                    ///end

					$amount += $wooProduct->get_price() * intval( $p["quantity"] ); //TODO : yithOptionPrice가 반영되지 않았으나 동작에는 문제가 없음
				}

                $np = array(
                    'id'        => $wooProduct->get_id(),
                    'name'      => $wooProduct->get_title(),
                    'basePrice' => $clearBasePrice ? 0 : $wooProduct->get_price(),
                    'taxType'   => $wooProduct->is_taxable() ? "TAX" : "TAX_FREE",
                    'quantity'  => intval( $p["quantity"] ),
                    'infoUrl'   => get_post_permalink( $wooProduct->get_id() ),
                    'imageUrl'  => self::getProductImageSrc( $wooProduct ),
                );

				if ( $options ) {
					$np['option'] = $options;
				}

				//shipping
                $useWoocommerceShippingCalc = WC_Gateway_Iamport_NaverPay::useWoocommerceShippingCalc($p['product_id']); //parent product id

                if ($useWoocommerceShippingCalc) { //우커머스 자체 배송비 계산
                    $internalShippingPolicy = WC_Gateway_Iamport_NaverPay::internalShippingPolicy($fakeCart, $p['product_id'], $p['quantity'], $p['variation_id']);
                    $fakeCartMaxFee = max($fakeCartMaxFee, $internalShippingPolicy->getBaseFee());

                    $np['shipping'] = array(
                        'groupId'  	 => $internalShippingPolicy->getGroupId(),
                        'method' 	 => $internalShippingPolicy->getMethod(),
                        'baseFee' 	 => $internalShippingPolicy->getBaseFee(),
                        'feeType' 	 => $internalShippingPolicy->getFeeType(),
                        'feePayType' => $internalShippingPolicy->getFeePayType(),
                    );
                } else {
                    $calcShippingTax = filter_var($this->gateway->get_attribute('calc_shipping_tax'), FILTER_VALIDATE_BOOLEAN);
                    $defaultZoneId = intval($this->gateway->get_attribute('shipping_zone'));
                    $shippingMethods = WC_Gateway_Iamport_NaverPay::getShippingMethodsForProduct($wooProduct, $defaultZoneId); // 지정된 shipping method가 없으면 무료배송
                    $shippingPolicy = WC_Gateway_Iamport_NaverPay::toShippingPolicy($wooProduct, $shippingMethods, $calcShippingTax);
                    $surchargeAreas = WC_Gateway_Iamport_NaverPay::getAreasForSurcharge($wooProduct);

                    $np['shipping'] = array(
                        'groupId'  	 => $shippingPolicy->getGroupId(),
                        'method' 	 => $shippingPolicy->getMethod(),
                        'baseFee' 	 => $shippingPolicy->getBaseFee(),
                        'feeType' 	 => $shippingPolicy->getFeeType(),
                        'feePayType' => $shippingPolicy->getFeePayType(),
                    );

                    $feeRule = $shippingPolicy->getFeeRule();
                    if( $feeRule instanceof NaverFreeByThreshold ) {
                        $np['shipping']['feeRule'] = array('freeByThreshold'=>intval($feeRule->getThreshold()));
                    } else if ( $feeRule instanceof NaverRangesByQuantity ) {
                        $np['shipping']['feeRule'] = array('repeatByQty'=>1);
                    }

                    if ( $surchargeAreas ) { //지역 권역별 추가배송비
                        $np['shipping']['feeRule']['surchargesByArea'] = $surchargeAreas;
                    }
                }

                $naverProducts[] = $np;
			}

			//우커머스 자체 배송비 계산에 대한 clean up
            foreach ($naverProducts as $idx=>$np) {
                if ($np['shipping']['groupId'] == 'woocommerce') {
                    $naverProducts[$idx]['shipping']['baseFee'] = $fakeCartMaxFee;
                }
            }
		}

        //유입경로 관련
        $naverInterface = array(
            "cpaInflowCode" => $_COOKIE["CPAValidator"],
            "naverInflowCode" => $_COOKIE["NA_CO"],
            "saClickId" => $_COOKIE["NVADID"],
        );

        //WoocommerceMembership 지원체크
        if (is_user_logged_in() && IamportHelper::supportMembershipPlugin()) {
            $loginId = get_current_user_id();
            if (wc_memberships_is_user_active_member($loginId)) { //membership 에 속한 active 유저인지 체크
                $naverInterface['merchantCustomCode1'] = $loginId;
            }
        }

        //weight base shipping 때문에 추가. 그 외에도 범용적으로 쓸 수 있을 듯. order_key
        $naverInterface['merchantCustomCode2'] = $new_order->get_order_key();

        return array(
            'pg_id' => $this->gateway->get_attribute("pg_id"),
            'amount' => $amount,
            'name' => $this->get_order_name($new_order),
            'merchant_uid' => $new_order->get_order_key(),
            'naverProducts' => $naverProducts,
            'naverInterface' => $naverInterface,
            'naverCultureBenefit' => $isCultureBenefitOrder,
            'notice_url' => add_query_arg( array('wc-api'=>WC_Gateway_Iamport_NaverPay::class), $new_order->get_checkout_payment_url()), //notice_url 자동 설정
        );
	}

	private function response_zzim($products) {
		header('Content-type: application/json');

		$naverProducts = array();

		if ( !empty($products) && is_array($products) ) {
			foreach ($products as $p) {
				$wooProduct = wc_get_product( $p['product'] );

				$naverProducts[] = array(
					'id'        => $wooProduct->get_id(),
					'name'      => $wooProduct->get_title(),
					'desc' 			=> $wooProduct->get_title(),
					'uprice'   	=> intval($wooProduct->get_price()),
					'url'   		=> urldecode( get_post_permalink( $wooProduct->get_id() ) ), //한글이 포함된 경우 이미 encoding된 상태로 저장되어있을 수도 있으므로 한 번 decode하고 반환. js에서 encode하도록 돼있음
					'image'  		=> self::getProductImageSrc( $wooProduct, 'thumbnail' ),
				);
			}
		}

		echo json_encode(array(
			'pg_id'  => $this->gateway->get_attribute("pg_id"),
	  	'naverProducts' => $naverProducts
		));

		wp_die();
	}

	private function get_order_name($order) {
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
					break;
				}

				$index++;
			}
		}

		$order_name = apply_filters('iamport_simple_order_name', $order_name, $order);

		return $order_name;
	}

//	private function getExtraFieldsOnPreparing()
//    {
//        //1. Extra Product Options
//        if (class_exists('WEPOF_Extra_Product_Options')) {
//            if (isset($_POST['thwepof_product_fields'])) {
//                $final_fields = array();
//                $product_fields  = wc_clean( $_POST['thwepof_product_fields'] );
//                $prod_fields = $product_fields ? explode(",", $product_fields) : array();
//
//                $extra_options = THWEPOF_Utils::get_product_fields_full();
//                foreach($prod_fields as $name) {
//                    if(isset($extra_options[$name])){
//                        $final_fields[$name] = $extra_options[$name];
//                    }
//                }
//
//                if (!empty($final_fields)) {
//                    foreach($final_fields as $name => $field) {
//                        if (!empty($_POST[$name])) {
//                            $posted_value = $_POST[$name];
//
//                            if (is_array($posted_value)) {
//
//                            }
//                        }
//                    }
//                }
//            }
//        }
//
//        return null;
//    }

	public function ajax_iamport_naver_carts() {
		$products = array();
		foreach (WC()->cart->get_cart() as $key => $item) {
			$p = array("product_id"=>$item["product_id"], "quantity"=>$item["quantity"]);

			if ( $item["variation_id"] > 0 )	$p["variation_id"] = $item["variation_id"];
			if ( !empty($item["variation"]) )	$p["variations"]    = $item["variation"];

			$products[] = $p;
		}

		try {
			$response = $this->response_products($products);

			echo json_encode($response);
			wp_die();
		} catch (Exception $e) {
			echo json_encode(array(
				"error" => $e->getMessage()
			));
			wp_die();
		}
	}

	public function ajax_iamport_naver_product() {
		$product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_REQUEST['product-id'] ) );
		$adding_to_cart = wc_get_product( $product_id );

		$add_to_cart_handler = apply_filters( 'woocommerce_add_to_cart_handler', $adding_to_cart->get_type(), $adding_to_cart );

		if ( 'variable' === $add_to_cart_handler || 'variation' === $add_to_cart_handler ) {
			$products = self::get_variable_products( $product_id );
			/*
			Array
			(
			    [0] => Array
			        (
			            [product_id] => 31
			            [variation_id] => 1082
			            [quantity] => 1
			            [variations] => Array
			                (
			                    [attribute_size] => 160
			                    [attribute_color] => Red
			                )

			        )

			)
			*/
		} elseif ( 'grouped' === $add_to_cart_handler ) {
			$products = self::get_grouped_products( $product_id );
		} else {
			$products = self::get_simple_products( $product_id );
		}

		try {
			$response = $this->response_products($products);

			echo json_encode($response);
			wp_die();
		} catch (Exception $e) {
			echo json_encode(array(
				"error" => $e->getMessage()
			));
			wp_die();
		}
	}

	public function ajax_iamport_naver_zzim() {
		$products = isset($_GET['products']) ? $_GET['products'] : array();

		$this->response_zzim($products);
	}

	public static function getProductImageSrc($wooProduct, $size="post-thumbnail") {
		if ( has_post_thumbnail( $wooProduct->get_id() ) ) {
			$image = get_the_post_thumbnail_url( $wooProduct->get_id(), $size );
		} elseif ( ( $parent_id = wp_get_post_parent_id( $wooProduct->get_id() ) ) && has_post_thumbnail( $parent_id ) ) {
			$image = get_the_post_thumbnail_url( $parent_id, $size );
		}/* elseif ( $placeholder ) {
			$image = wc_placeholder_img_src();
		}*/ else {
			$image = '';
		}

		return $image;
	}

	public static function get_product_attributes($product, $attribute_name) {
		$attributes = $product->get_attributes();
		$attribute  = sanitize_title( $attribute_name );

		if ( isset( $attributes[ $attribute ] ) ) {
			$attribute_object = $attributes[ $attribute ];
		} elseif ( isset( $attributes[ 'pa_' . $attribute ] ) ) {
			$attribute_object = $attributes[ 'pa_' . $attribute ];
		} else {
			return '';
		}

		if ( $attribute_object->is_taxonomy() )	return wc_get_product_terms( $product->get_id(), $attribute_object->get_name(), array( 'fields' => 'all' ) );

		$options = $attribute_object->get_options();

		$response = array(); //WP_Term인 것처럼 데이터 만들어서 넘겨줌(필요한 정보만)
		foreach ($options as $name) {
			$obj = new stdClass();
			$obj->slug = sanitize_title( $name );
			$obj->name = $name;
			$response[] = $obj;
		}

		return $response;
	}

	public static function sanitize_slug_for_naver($code) {
		//네이버 상품 code로 사용되는 값은 띄워쓰기, 특수문자 제거되도록 수정하기
		return preg_replace('/[^\pL0-9!\+\-\/=_\|]/u', '', $code);
	}

	private static function get_variable_products($product_id) {
		/*
		attribute_pa_size-2:l%ec%86%8c%eb%9f%89%ec%9e%ac%ea%b3%a0%eb%ac%b8%ec%9d%98
		attribute_pa_color-3:%eb%a0%88%eb%93%9c
		attribute_pa_rong-short:rong1
		quantity:1
		add-to-cart:1731
		product_id:1731
		variation_id:4227
		*/
		try {
			$variation_id       = empty( $_REQUEST['variation_id'] ) ? '' : absint( $_REQUEST['variation_id'] );
			$quantity           = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( $_REQUEST['quantity'] );
			$missing_attributes = array();
			$variations         = array();
			$adding_to_cart     = wc_get_product( $product_id );

			// If the $product_id was in fact a variation ID, update the variables.
			if ( $adding_to_cart->is_type( 'variation' ) ) {
				$variation_id   = $product_id;
				$product_id     = $adding_to_cart->get_parent_id();
				$adding_to_cart = wc_get_product( $product_id );

				if ( ! $adding_to_cart ) {
					return false;
				}
			}

			// If no variation ID is set, attempt to get a variation ID from posted attributes.
			if ( empty( $variation_id ) ) {
				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $adding_to_cart, array_map( 'sanitize_title', wp_unslash( $_REQUEST ) ) );
			}

			// Validate the attributes.
			if ( empty( $variation_id ) ) {
				throw new Exception( __( 'Please choose product options&hellip;', 'woocommerce' ) );
			}

			$variation_data = wc_get_product_variation_attributes( $variation_id );

			foreach ( $adding_to_cart->get_attributes() as $attribute ) {
				if ( ! $attribute['is_variation'] ) {
					continue;
				}

				$taxonomy = 'attribute_' . sanitize_title( $attribute['name'] );

				if ( isset( $_REQUEST[ $taxonomy ] ) ) {
					if ( $attribute['is_taxonomy'] ) {
						// Don't use wc_clean as it destroys sanitized characters.
						$value = sanitize_title( wp_unslash( $_REQUEST[ $taxonomy ] ) );
					} else {
						$value = wc_clean( wp_unslash( $_REQUEST[ $taxonomy ] ) );
					}

					// Get valid value from variation data.
					$valid_value = isset( $variation_data[ $taxonomy ] ) ? $variation_data[ $taxonomy ] : '';

					// Allow if valid or show error.
					if ( $valid_value === $value ) {
						$variations[ $taxonomy ] = $value;
					} elseif ( '' === $valid_value && in_array( $value, $attribute->get_slugs() ) ) {
						// If valid values are empty, this is an 'any' variation so get all possible values.
						$variations[ $taxonomy ] = $value;
					} else {
						throw new Exception( sprintf( __( 'Invalid value posted for %s', 'woocommerce' ), wc_attribute_label( $attribute['name'] ) ) );
					}
				} else {
					$missing_attributes[] = wc_attribute_label( $attribute['name'] );
				}
			}
			if ( ! empty( $missing_attributes ) ) {
				throw new Exception( sprintf( _n( '%s is a required field', '%s are required fields', count( $missing_attributes ), 'woocommerce' ), wc_format_list_of_items( $missing_attributes ) ) );
			}

			return array(
				array(
					"product_id" => $product_id,
					"variation_id" => $variation_id,
					"quantity" => $quantity,
					"variations" => $variations,
				)
			);
		} catch(Exception $e) {
			return array();
		}
	}

	private static function get_grouped_products($product_id) {
		/*
		quantity[1457]:0
		quantity[1450]:0
		quantity[1544]:0
		quantity[1461]:0
		quantity[1465]:1
		quantity[1470]:0
		quantity[1475]:0
		quantity[1480]:0
		quantity[1550]:0
		quantity[1553]:0
		quantity[1557]:0
		quantity[1561]:0
		quantity[1566]:0
		quantity[1571]:0
		quantity[1578]:0
		quantity[1584]:0
		quantity[1590]:0
		add-to-cart:1444
		*/

		$adding_products = array();

		if ( ! empty( $_REQUEST['quantity'] ) && is_array( $_REQUEST['quantity'] ) ) {

			foreach ( $_REQUEST['quantity'] as $item => $quantity ) {
				if ( $quantity <= 0 ) {
					continue;
				}

				$adding_products[] = array(
					"product_id" => $item,
					"variation_id" => null,
					"quantity" => $quantity,
				);
			}

		}

		return $adding_products;
	}

	private static function get_simple_products($product_id) {
		$quantity = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( $_REQUEST['quantity'] );

		$adding_products = array(
			array(
				"product_id" => $product_id,
				"quantity" => $quantity,
			)
		);

		return $adding_products;
	}

	private static function create_order($products) {
		$payment_gateways = WC()->payment_gateways->payment_gateways();

		$order_data = array(
	  	"status" => apply_filters( 'woocommerce_default_order_status', 'pending' ),
	  	"customer_id" => get_current_user_id(),
	  );
	  $new_order = wc_create_order($order_data);
	  $new_order->set_payment_method( $payment_gateways[WC_Gateway_Iamport_NaverPay::GATEWAY_ID] ); //gateway 지정

	  // REST API key, secret찾을 때 필요함(getRestInfo)
	  add_post_meta($new_order->get_id(), "_iamport_paymethod", "card");
	  add_post_meta($new_order->get_id(), "_iamport_provider", "naverco");

	  //XML 응답시 우커머스 자체배송비 계산적용하려면 필요함
	  add_post_meta($new_order->get_id(), '_chosen_shipping_methods', WC()->session->get( 'chosen_shipping_methods' ));

	  foreach ($products as $p) {
	  	if ( !empty($p["variation_id"]) ) { //variable product
	  		$var_product = new WC_Product_Variation($p["variation_id"]);

	  		$args = array( "variation"=>array() );
	  		if ( !empty($p["variations"]) ) {
					$attributes = $p["variations"];

					foreach ($attributes as $k=>$v) {
						$attribute_name = str_replace("attribute_", "", $k);

						$args["variation"][ $attribute_name ] = $v;
					}
				}

	  		$item_id = $new_order->add_product( $var_product, $p["quantity"], $args);

            //YITH product meta 연동을 위해 호출
            self::bind_3rd_party_product_add_on($item_id, $p["product_id"], $p["variation_id"], $p["quantity"]);
	  	} else { //simple product
	  		$item_id = $new_order->add_product( wc_get_product( $p["product_id"] ), $p["quantity"] );

            //YITH product meta 연동을 위해 호출
            self::bind_3rd_party_product_add_on($item_id, $p["product_id"], 0, $p["quantity"]);
	  	}
	  }

	  $new_order->calculate_totals();

	  return $new_order;
	}

    /**
     *
     * YITH, Extra Product Options
     * @param $item_id
     * @param $product_id
     * @param $variation_id
     * @param $quantity
     */
    private static function bind_3rd_party_product_add_on($item_id, $product_id, $variation_id, $quantity)
    {
        $item = new WC_Order_Item_Product($item_id);
        $cart_item_meta = (array) apply_filters( 'woocommerce_add_cart_item_data', array(), $product_id, $variation_id, $quantity );

        foreach ($cart_item_meta as $metaKey=>$metaValue) {
            $item->add_meta_data($metaKey, $metaValue, true);
        }

        $item->legacy_values = $cart_item_meta;
        $item->save();

        do_action( 'woocommerce_new_order_item', $item->get_id(), $item, $item->get_order_id() ); //YITH, Extra Product Option DB저장을 위해 호출 : order->add_product() 를 사용하기 때문에 DataStore.create hook 호출 시 meta 데이터가 전달될 수 있는 기회를 놓침(YITH는 create hook 에서만 DB저장을 함)
    }

}

class IamportNaverPayAdmin {

	public function __construct($gateway) {
		$this->gateway = $gateway;
	}

	public function init() {
		if ( !$this->gateway->can_show_button() && !$this->gateway->is_debug_mode() )	return; //버튼도 안보이고, 검수도 안하면 비활성화

		//https://www.proy.info/how-to-add-woocommerce-custom-fields/
		add_action( 'woocommerce_product_options_shipping', array( $this, 'hook_shipping_meta' ), 1, 2 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_shipping_meta' ) );

		add_action( 'woocommerce_after_order_itemmeta', array($this, 'order_item_meta_actions'), 10, 3 );
		add_action( 'woocommerce_order_item_add_action_buttons', array($this, 'woocommerce_order_item_add_action_buttons') );

        //meta box 기능버튼 추가 : TODO(가상계좌 환불 등)
//        add_action( 'woocommerce_admin_order_items_after_refunds', array($this, 'woocommerce_admin_order_items_after_refunds') );

        //meta box 동작을 위한 스크립트 로드
        add_action( 'admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );

        add_filter( 'woocommerce_order_item_display_meta_key', array($this, 'woocommerce_order_item_display_meta_key'), 20, 3 );

        //ajax script
        add_action('wp_ajax_iamport_naver_admin_sync_order', array($this, 'ajax_iamport_naver_admin_sync_order') );
        add_action('wp_ajax_iamport_naver_admin_cancel_order', array($this, 'ajax_iamport_naver_admin_cancel_order') );
	}

	public function hook_shipping_meta() {
		$defaultZoneId = intval($this->gateway->get_attribute('shipping_zone'));
		$zones = array( new WC_Shipping_Zone($defaultZoneId) );

		foreach ($zones as $zone) {
			$methods = $zone->get_shipping_methods(true); //enabled only

			$methods_etc = array("none" => "== 없음 ==");
			$methods_free_shipping = array("always" => "== 항상무료배송 ==", "depend" => "== (B)정책에 따라 배송비부과 ==");

			foreach ($methods as $method) {
				if ( $method->id === "free_shipping" ) {
					$methods_free_shipping[ $method->get_instance_id() ] = $method->get_title();
				} else {
					$methods_etc[ $method->get_instance_id() ] = $method->get_title();
				}
			}

            woocommerce_wp_checkbox(
                array(
                    'id' => 'iamport_naverpay_use_woocommerce_shipping_calc',
                    'label' => __( '[네이버페이] 배송비 자체계산', 'iamport-for-woocommerce' ),
                    'description' => '우커머스 장바구니 계산방식을 따르려면 체크',
                )
            );

			woocommerce_wp_select(
				array(
					'id' => 'iamport_naverpay_free_shipping_method_zone_'.$zone->get_id(),
					'label' => __( '[네이버페이] (A)무료배송조건', 'iamport-for-woocommerce' ),
					'options' => $methods_free_shipping,
					// 'description' => 'xxx',
					// 'desc_tip' => true
				)
			);

			woocommerce_wp_select(
				array(
					'id' => 'iamport_naverpay_shipping_method_zone_'.$zone->get_id(),
					'label' => __( '[네이버페이] (B)무료배송조건 미달시 배송비', 'iamport-for-woocommerce' ),
					'options' => $methods_etc,
					// 'description' => 'xxx',
					// 'desc_tip' => true
				)
			);

			woocommerce_wp_text_input(
				array(
					'id' => 'iamport_naverpay_shipping_surcharge_area_island',
					'label' => __( '[네이버페이] 도서산간지역 배송비(제주도제외)', 'iamport-for-woocommerce' ),
					'description' => '제주도를 제외한 일반 도서산간지역에 추가될 배송비 금액을 기재합니다. (A), (B)에 의해 계산된 배송비에 추가되어 계산됩니다. 지역별 배송비 차등이 없다면 비워두거나 0으로 설정하시면 됩니다.',
					'desc_tip' => true
				)
			);

			woocommerce_wp_text_input(
				array(
					'id' => 'iamport_naverpay_shipping_surcharge_area_jeju',
					'label' => __( '[네이버페이] 제주도 배송비', 'iamport-for-woocommerce' ),
					'description' => '일반 도서산간지역과 달리 제주도지역에 추가될 배송비 금액을 기재합니다. (A), (B)에 의해 계산된 배송비에 추가되어 계산됩니다. 제주지역 배송비도 일반 도서산간지역과 동일하다면 같은 금액을 기재합니다. 지역별 배송비 차등이 없다면 비워두거나 0으로 설정하시면 됩니다.',
					'desc_tip' => true
				)
			);
		}
	}

	public function save_shipping_meta($post_id) {
        $defaultZoneId = intval($this->gateway->get_attribute('shipping_zone'));
		$zones = array( new WC_Shipping_Zone($defaultZoneId) );
		$prefixes = array("iamport_naverpay_free_shipping_method_zone_", "iamport_naverpay_shipping_method_zone_");

		foreach ($zones as $zone) {
			foreach ($prefixes as $pf) {
				$field_name = $pf.$zone->get_id();

				if (isset($_POST[$field_name])) { //iamport_naverpay_use_woocommerce_shipping_calc 에 의해 disabled 처리될 수도 있으므로 isset check
                    $field = $_POST[$field_name];
                    update_post_meta( $post_id, $field_name, esc_attr( $field ) );
                }
			}
		}

		//나머지 필드(iamport_naverpay_use_woocommerce_shipping_calc 에 의해 disabled 처리될 수도 있으므로 isset check)
		$others = array("iamport_naverpay_shipping_surcharge_area_island", "iamport_naverpay_shipping_surcharge_area_jeju");
		foreach ($others as $k) {
		    if (isset($_POST[$k])) {
                update_post_meta( $post_id, $k, esc_attr($_POST[$k]) );
            }
		}

		$k = "iamport_naverpay_use_woocommerce_shipping_calc";
        update_post_meta( $post_id, $k, esc_attr($_POST[$k]) );
	}

	public function order_item_meta_actions($item_id, $item, $product)
    {
        if ($item->get_type() != 'line_item') {
            return;
        }

        $gateway_id = $item->get_order()->get_payment_method();
        $imp_uid = $item->get_order()->get_transaction_id();

        if ($gateway_id == WC_Gateway_Iamport_NaverPay::GATEWAY_ID) {
            $product_order_id = $item->get_meta('naver_product_order_id');

            $disabledToCancel = $item->get_meta('naver_product_order_status') != 'PAYED';

            ob_start();?>
            <?php if ($product_order_id) : //기존에 product_order_id가 저장되지 않은 주문 대응 ?>
            <button type="button" class="button naverpay-refund-each" <?=$disabledToCancel ? 'disabled':''?> data-wc-order-id="<?=$item->get_order_id()?>" data-imp-uid="<?=$imp_uid?>" data-product-order-id="<?=$product_order_id?>" data-product-name="<?=$item->get_name()?>">네이버페이-환불</button>
            <?php endif; ?>
        <?php
            ob_end_flush();
        }
    }

    public function woocommerce_order_item_add_action_buttons($order)
    {
        $gateway_id = $order->get_payment_method();
        $imp_uid = $order->get_transaction_id();

        if ($gateway_id == WC_Gateway_Iamport_NaverPay::GATEWAY_ID) {

            $disabledToCancel = floatval($order->get_total()) - floatval($order->get_total_refunded()) <= 0;
            ob_start();?>
            <!--<button type="button" class="button naverpay-sync" data-wc-order-id="<?=$order->get_id()?>" data-imp-uid="<?=$imp_uid?>">네이버페이 주문상태 동기화</button>-->
            <button type="button" class="button naverpay-refund-all" <?=$disabledToCancel ? 'disabled':''?> data-wc-order-id="<?=$order->get_id()?>" data-imp-uid="<?=$imp_uid?>">네이버페이-전체환불</button>
        <?php
            ob_end_flush();
        }
    }

    public function woocommerce_order_item_display_meta_key($key, $meta, $item)
    {
        switch($key) {
            case 'naver_product_order_id' :
                return '네이버 상품주문번호';
                break;

            case 'naver_product_order_status' :
                return '네이버 상품주문상태';
                break;

            case 'product_amount' :
                return '네이버 상품주문금액';
                break;

            case 'delivery_amount' :
                return '네이버 상품배송비(중복적용)';
                break;

            case 'shipping_memo' :
                return '네이버 상품별 배송요청사항';
                break;

            case 'shipping_due' :
                return '네이버 상품 배송기한';
                break;
        }

        return $key;
    }

    public function admin_enqueue_scripts()
    {
        wp_register_script( 'iamport_naverpay_admin', plugins_url( '/assets/js/naverpay.admin.js',plugin_basename(__FILE__) ), array(), '20191126' );
        wp_enqueue_script( 'iamport_naverpay_admin' );
    }

    public function ajax_iamport_naver_admin_sync_order()
    {
        header('Content-type: application/json');

        $wc_order_id = $_POST['wcOrderId'];
        $imp_uid = $_POST['impUid'];

        if (empty($imp_uid) || empty($wc_order_id)) {
            echo json_encode(array(
                "error" => "올바르지 않은 요청입니다."
            ));

            wp_die();
        }

        $iamport = new WooIamport($this->gateway->imp_rest_key, $this->gateway->imp_rest_secret);
        $result = $iamport->getNaverProductOrders($imp_uid);

        if ( $result->success ) {
            $product_orders = $result->data;
            $order = new WC_Order( $wc_order_id );

            foreach ($product_orders as $idx=>$po) {
                //product line item
                $productLineItem = IamportHelper::findProductItem($order, $po->product_id, WC_Gateway_Iamport_NaverPay::getVariationIdFromQuery($po->product_option_id), WC_Gateway_Iamport_NaverPay::getAttributesFromQuery($po->product_option_id));
                if ($productLineItem) {
                    $productLineItem->add_meta_data('naver_product_order_id', $po->product_order_id);
                    $productLineItem->add_meta_data('naver_product_order_status', $po->product_order_status);
                    $productLineItem->add_meta_data('product_amount', $po->product_amount);
                    $productLineItem->add_meta_data('delivery_amount', $po->delivery_amount);
                    $productLineItem->add_meta_data('shipping_memo', $po->shipping_memo ? $po->shipping_memo : '없음');
                    $productLineItem->add_meta_data('shipping_due', $po->shipping_due ? date('Y-m-d H:i:s', $po->shipping_due + get_option('gmt_offset') * HOUR_IN_SECONDS) : '없음');
                    $productLineItem->save_meta_data();
                }
            }
        }
    }

    public function ajax_iamport_naver_admin_cancel_order()
    {
        require_once(dirname(__FILE__).'/lib/iamport.php');

        header('Content-type: application/json');

        $wc_order_id = $_POST['wcOrderId'];
        $imp_uid = $_POST['impUid'];
        $product_order_id = $_POST['productOrderId'];

        if (empty($imp_uid) || empty($wc_order_id)) {
            echo json_encode(array(
                "error" => "올바르지 않은 요청입니다."
            ));

            wp_die();
        }

        if (empty($product_order_id)) { //전체취소
            $arr_product_order_id = null;
        } else { //1건 취소
            if (is_array($product_order_id)) {
                $arr_product_order_id = $product_order_id;
            } else {
                $arr_product_order_id = array($product_order_id);
            }
        }

        $iamport = new WooIamport($this->gateway->imp_rest_key, $this->gateway->imp_rest_secret);
        $result = $iamport->cancelNaverOrder($imp_uid, $arr_product_order_id);

        if ( $result->success ) {
            $productOrders = $result->data;
            $order = new WC_Order( $wc_order_id );

            //성공한 것만 응답됨
            foreach ($productOrders as $idx=>$po) {
                //product line item
                $productLineItem = IamportHelper::findProductItem($order, $po->product_id, $po->product_option_id);
                $productLineItem->update_meta_data('naver_product_order_status', $po->product_order_status);
                $productLineItem->save_meta_data();
            }

            //TODO : 현재 환불상태 변경은 아임포트 웹훅에 의한 변경에 의존하고 있음. IamportPlugin.php 의 wc_create_refund 부분. 로직 정리가 되어야 함

            echo json_encode(array(
                "error" => null,
            ));
        } else {
            echo json_encode(array(
                "error" => "환불실패 : " . $result->error['message'],
            ));
        }

        wp_die();
    }
}

add_action( 'init', function() {
	$gateway = new WC_Gateway_Iamport_NaverPay(); //TODO : 더 좋은 방법을 고민해야 함

	$instPayButton = new IamportNaverPayButton($gateway);
	$instPayAdmin = new IamportNaverPayAdmin($gateway);

	$instPayButton->init();
	$instPayAdmin->init();
});
