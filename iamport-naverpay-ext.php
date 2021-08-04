<?php
class WC_Gateway_Iamport_NaverPayExt extends Base_Gateway_Iamport {

  const GATEWAY_ID = 'iamport_naverpay_ext';

  public static $PRODUCT_CATEGORIES = array(
    "NONE"                       => "해당사항없음",
    "BOOK_GENERAL"               => "[도서] 일반",
    "BOOK_EBOOK"                 => "[도서] 전자책",
    "BOOK_USED"                  => "[도서] 중고",
    "MUSIC_CD"                   => "[음악] CD",
    "MUSIC_LP"                   => "[음악] LP",
    "MUSIC_USED"                 => "[음악] 중고 음반",
    "MOVIE_DVD"                  => "[영화] DVD",
    "MOVIE_BLUERAY"              => "[영화] 블루레이",
    "MOVIE_VOD"                  => "[영화] VOD",
    "MOVIE_TICKET"               => "[영화] 티켓",
    "MOVIE_USED"                 => "[영화] 중고 DVD, 블루 레이등",
    "PRODUCT_GENERAL"            => "[상품] 일반",
    "PRODUCT_CASHABLE"           => "[상품] 환금성",
    "PRODUCT_CLAIM"              => "[상품] 클레임",
    "PRODUCT_DIGITAL_CONTENT"    => "[상품] 디지털 컨텐츠",
    "PRODUCT_SUPPORT"            => "[상품] 후원",
    "PLAY_TICKET"                => "[공연/전시] 티켓",
    "TRAVEL_DOMESTIC"            => "[여행] 국내 숙박",
    "TRAVEL_OVERSEA"             => "[여행] 해외 숙박",
    "INSURANCE_CAR"              => "[보험] 자동차보험",
    "INSURANCE_DRIVER"           => "[보험] 운전자보험",
    "INSURANCE_HEALTH"           => "[보험] 건강보험",
    "INSURANCE_CHILD"            => "[보험] 어린이보험",
    "INSURANCE_TRAVELER"         => "[보험] 여행자보험",
    "INSURANCE_GOLF"             => "[보험] 골프보험",
    "INSURANCE_ANNUITY"          => "[보험] 연금보험",
    "INSURANCE_ANNUITY_SAVING"   => "[보험] 연금저축보험",
    "INSURANCE_SAVING"           => "[보험] 저축보험",
    "INSURANCE_VARIABLE_ANNUITY" => "[보험] 변액적립보험",
    "INSURANCE_CANCER"           => "[보험] 암보험",
    "INSURANCE_DENTIST"          => "[보험] 치아보험",
    "INSURANCE_ACCIDENT"         => "[보험] 상해보험",
    "INSURANCE_SEVERANCE"        => "[보험] 퇴직연금",
    "FLIGHT_TICKET"              => "[항공] 티켓",
    "FOOD_DELIVERY"              => "[음식] 배달",
    "ETC_ETC"                    => "[기타]",
  );

  public function __construct() {
    parent::__construct();

    //settings
    $this->method_title = __( '아임포트(결제형-네이버페이)', 'iamport-for-woocommerce' );
    $this->method_description = __( '<b>네이버페이 정책상, 결제형-네이버페이는 사전 승인된 일부 가맹점에 한하여 제공되고 있으며 일반적으로는 "아임포트(네이버페이)"를 사용해주셔야 합니다. 결제형-네이버페이 가입기준에 대해서는 아임포트 고객센터(1670-5176)으로 문의 부탁드립니다.</b>', 'iamport-for-woocommerce' );
    $this->has_fields = true;
    $this->supports = array( 'products', 'refunds' );

    $this->title = $this->settings['title'];
    $this->description = $this->settings['description'];

    add_filter( 'woocommerce_available_payment_gateways', array($this, 'eliminateUnderInspection') );

    //실패한 경우 결제페이지에서 order-receipt.php 가 include되지 않도록 override
    add_filter( "wc_get_template", array($this, "my_template"), 10, 3 );
  }

  protected function get_gateway_id() {
    return self::GATEWAY_ID;
  }

  public function eliminateUnderInspection($gateways) {
    if ( isset($this->settings["debug_mode"]) && $this->settings["debug_mode"] === "yes" ) { //검수모드 체크상태

      $debuggers = isset($this->settings["debuggers"]) ? strval($this->settings["debuggers"]) : "";
      $allowed = explode(",", $debuggers);
      $login = wp_get_current_user();

      if ( 0 == $login->ID || !in_array($login->user_login, $allowed) ) {
        unset($gateways[ $this->get_gateway_id() ]);
      }
    }

    return $gateways;
  }

  public function init_form_fields() {
    parent::init_form_fields();

    $this->form_fields = array_merge( array(
      'enabled' => array(
        'title' => __( 'Enable/Disable', 'woocommerce' ),
        'type' => 'checkbox',
        'label' => __( '아임포트(결제형-네이버페이) 사용. (결제형-네이버페이 설정을 위해서는 네이버페이 가입승인 후 아임포트 고객센터로 연락부탁드립니다.)', 'iamport-for-woocommerce' ),
        'default' => 'no'
      ),
      'title' => array(
        'title' => __( 'Title', 'woocommerce' ),
        'type' => 'text',
        'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
        'default' => __( '네이버페이(결제형)', 'iamport-for-woocommerce' ),
        'desc_tip'      => true,
      ),
      'description' => array(
        'title' => __( 'Customer Message', 'woocommerce' ),
        'type' => 'textarea',
        'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
        'default' => __( '주문확정 버튼을 클릭하시면 네이버페이 결제창이 나타나 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
      ),
      'debug_mode' => array(
        'title' => __( '네이버페이 검수모드', 'iamport-for-woocommerce' ),
        'description' => __( '네이버페이 검수단계에서는 일반 사용자에게 네이버페이 결제수단이 보여지면 안됩니다. "검수모드" [체크]하시면 특정 사용자에게만 네이버페이 결제수단이 노출되며, [체크해제]하시면 모든 사용자에게 노출됩니다. 아래에서 네이버페이 검수용 사용자 아이디를 지정하시면 됩니다.', 'iamport-for-woocommerce' ),
        'type' => 'checkbox',
        'label' => __( '네이버페이 검수모드', 'iamport-for-woocommerce' ),
        'default' => 'no'
      ),
      'debuggers' => array(
        'title' => __( '네이버페이 검수용 사용자명', 'iamport-for-woocommerce' ),
        'label' => __( '네이버페이 검수용 사용자명', 'iamport-for-woocommerce' ),
        'description' => __( '네이버페이 검수단계에서는 특정 사용자에게만 네이버페이 결제수단을 노출합니다. (콤마로 구분하여 여러 명 지정 가능)', 'iamport-for-woocommerce' ),
        'type' => 'text',
        'default' => "",
      ),
      'naverUseCfm' => array(
        'title' => __( '이용완료일 설정', 'iamport-for-woocommerce' ),
        'type' => 'text',
        'description' => __( '이용완료일을 입력해주세요. (yyyyMMdd) 과거 일자를 넣을 수 없으며 오늘 날짜는 가능합니다.', 'iamport-for-woocommerce' ),
      ),
    ), $this->form_fields, array(
        'use_manual_pg' => array(
            'title' => __( 'PG설정 구매자 선택방식 사용', 'woocommerce' ),
            'type' => 'checkbox',
            'description' => __( '아임포트 계정에 설정된 여러 PG사 / MID를 사용자의 선택에 따라 적용하는 기능을 활성화합니다. 네이버페이(결제형) 결제수단 선택 시, 세부 결제수단 선택창이 추가로 출력됩니다.', 'iamport-for-woocommerce' ),
            'default' => 'no',
        ),
        'manual_pg_id' => array(
            'title' => __( 'PG설정 구매자 선택', 'woocommerce' ),
            'type' => 'textarea',
            'description' => __( '"{PG사 코드}.{PG상점아이디} : 구매자에게 표시할 텍스트" 의 형식으로 여러 줄 입력가능합니다.', 'iamport-for-woocommerce' ),
        ),
    ));
  }

  public static function render_edit_product_category( $tag ) {
    self::render_product_category( $tag, "edit" );
  }

  public static function render_add_product_category( $tag ) {
    self::render_product_category( $tag, "add" );
  }

  public static function save_edit_product_category( $term_id ) {
    self::save_product_category( $term_id, "edit" );
  }

  public static function save_add_product_category( $term_id ) {
    self::save_product_category( $term_id, "add" );
  }

  private static function render_product_category( $tag, $mode ) {
    $term_id = $tag->term_id;
    $term_meta = get_option( "taxonomy_{$term_id}" );
    $iamport_naver_ctgr = empty($term_meta["iamport_naver_ctgr"]) ? "" : $term_meta["iamport_naver_ctgr"];

    ob_start();
    ?>
    <select name="term_meta[iamport_naver_ctgr]" id="term_meta[iamport_naver_ctgr]">
        <?php foreach (self::$PRODUCT_CATEGORIES as $key => $label) : ?>
        <option <?php echo $key === $iamport_naver_ctgr ? "selected":""?> value="<?=$key?>"> <?=$label?> </option>
        <?php endforeach; ?>
    </select>
    <?php
    $select_node = ob_get_clean();
    ?>

    <?php if ($mode === "edit") : ?>
    <tr class="form-field">
        <th scope="row">
            <label for="term_meta[iamport_naver_ctgr]"><?=__('네이버상품 카테고리', "iamport-for-woocommerce") ?></label>
            <td><?=$select_node?></td>
        </th>
    </tr>
    <?php elseif ($mode === "add") : ?>
    <div class="form-field term-naver-product-category-wrap">
      <label for="term_meta[iamport_naver_ctgr]"><?=__('네이버상품 카테고리', "iamport-for-woocommerce") ?></label>
      <?=$select_node?>
    </div>
    <?php
    endif;
  }

  private static function save_product_category( $term_id, $mode ) {
    if ( isset( $_POST['term_meta'] ) ) {
      $iamport_naver_ctgr = isset( $_POST["term_meta"]["iamport_naver_ctgr"] ) ? sanitize_text_field( $_POST["term_meta"]["iamport_naver_ctgr"] ) : "";
      $categories = array_keys(self::$PRODUCT_CATEGORIES);

      if ( "NONE" !== $iamport_naver_ctgr && in_array($iamport_naver_ctgr, $categories) ) {
        $term_meta = array(
          "iamport_naver_ctgr" => $iamport_naver_ctgr
        );

        update_option( "taxonomy_{$term_id}", $term_meta );
      } else {
        delete_option( "taxonomy_{$term_id}" );
      }
    }
  }

  public function my_template($located, $template_name, $args) {
    if ( $template_name === "checkout/order-receipt.php" && isset($args["order"]) && $args["order"] instanceof WC_Order ) {
      $order = $args["order"];

      if ( $order->get_payment_method() === $this->get_gateway_id() ) { //네이버페이 결제형일 때만
        if ( in_array($order->get_status(), array("pending", "failed")) ) {
          return plugin_dir_path( __FILE__ ) . "/includes/templates/empty-order-receipt.php";
        }
      }
    }

    return $located;
  }

  public function iamport_order_detail( $order_id ) {
    ob_start();
    ?>
    <h2><?=__( '결제 상세', 'iamport-for-woocommerce' )?></h2>
    <table class="shop_table order_details">
      <tbody>
        <tr>
          <th><?=__( '결제수단', 'iamport-for-woocommerce' )?></th>
          <td><?=__( '네이버페이', 'iamport-for-woocommerce' )?></td>
        </tr>
      </tbody>
    </table>
    <?php
    ob_end_flush();
  }

  public function iamport_payment_info( $order_id ) {
    $response = parent::iamport_payment_info($order_id);

    $order = new WC_Order( $order_id );
    //naverProducts 생성

    $useManualPg = filter_var($this->settings['use_manual_pg'], FILTER_VALIDATE_BOOLEAN);
    $naverProducts = array();
    $product_items = $order->get_items(); //array of WC_Order_Item_Product
    foreach ($product_items as $item) {
      $cat = $this->get_naver_category($item);

      $naverProducts[] = array(
        "categoryType" => $cat["type"],
        "categoryId"   => $cat["id"],
        "uid"          => $this->get_product_uid($item),
        "name"         => $item->get_name(),
        "count"        => $item->get_quantity(),
      );
    }

    $response["pg"] = "naverpay";
    $response["naverProducts"] = $naverProducts;
    
    $response["unblock"] = true;

    if ( !wp_is_mobile() ) {
        $response["naverPopupMode"] = true;
    }

    $response['pay_method'] = 'naverpay';
    if (!$useManualPg) {
      $response['pg'] = 'naverpay';
    }
    if ($this->settings['naverUseCfm']){
      $response['naverUseCfm'] = $this->settings['naverUseCfm'];
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

  protected function get_order_name($order) { // "XXX 외 1건" 같이 외 1건이 붙으면 안됨
    $product_items = $order->get_items(); //array of WC_Order_Item_Product

    foreach ($product_items as $item) {
      return $item->get_name();
    }

    return "#" . $order->get_order_number() . "번 주문";
  }

  private function get_product_uid($item) {
    $product_id   = $item->get_product_id();
    $variation_id = $item->get_variation_id();

    if ( $variation_id )  return sprintf("%s-%s", $product_id, $variation_id);

    return strval( $product_id );
  }

  private function get_naver_category($product) {
    $product_id = $product->get_product_id();
    $terms = wp_get_post_terms( $product_id, 'product_cat', array('fields'=>'ids') );

    foreach ($terms as $term_id) {
      $term_meta = get_option( "taxonomy_{$term_id}" );
      $iamport_naver_ctgr = empty($term_meta["iamport_naver_ctgr"]) ? "" : $term_meta["iamport_naver_ctgr"];
      $categories = array_keys(self::$PRODUCT_CATEGORIES);

      if ( "NONE" !== $iamport_naver_ctgr && in_array($iamport_naver_ctgr, $categories) ) {
        $arr = explode("_", $iamport_naver_ctgr, 2); //처음만나는 _ 로만 잘라야 함

        return array(
          "type" => $arr[0],
          "id"   => $arr[1],
        );
      }
    }

    return array(
      "type" => "ETC",
      "id"   => "ETC",
    );
  }

}
