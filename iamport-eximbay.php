<?php
class WC_Gateway_Iamport_Eximbay extends Base_Gateway_Iamport {

  const GATEWAY_ID = 'iamport_eximbay';

  public function __construct() {
    parent::__construct();

    //settings
    $this->method_title = __( '아임포트(Eximbay)', 'iamport-for-woocommerce' );
    $this->method_description = __( '=> 아임포트 서비스를 이용해 결제모듈을 연동할 수 있습니다.<br>=> [아임포트] X PG사 제휴할인혜택을 받아보세요! <a href="http://www.iamport.kr/pg#promotion" target="_blank">PG 신규계약 프로모션 안내</a><br>=> 아임포트의 최신 공지사항도 놓치지 마세요! <a href="http://www.iamport.kr/notice" target="_blank">공지사항보기</a>', 'iamport-for-woocommerce' );
    $this->has_fields = true;
    $this->supports = array( 'products', 'refunds', 'default_credit_card_form' );

    $this->title = $this->settings['title'];
    $this->description = $this->settings['description'];

    //actions
    // add_action( 'woocommerce_thankyou_'.$this->id, array( $this, 'iamport_order_detail' ) ); woocommerce_order_details_after_order_table 로 대체. 중복으로 나오고 있음
    add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'iamport_credit_card_form_fields' ), 10, 2);
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
        'label' => __( '아임포트(Eximbay) 결제 사용. (Eximbay를 사용하시려면, <a href="https://admin.iamport.kr/settings" target="_blank">아임포트 관리자페이지의 PG설정화면</a>에서 "추가PG사"로 Eximbay를 추가 후 사용해주세요.)', 'iamport-for-woocommerce' ),
        'default' => 'yes'
      ),
      'title' => array(
        'title' => __( 'Title', 'woocommerce' ),
        'type' => 'text',
        'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
        'default' => __( 'Eximbay 결제', 'iamport-for-woocommerce' ),
        'desc_tip'      => true,
      ),
      'description' => array(
        'title' => __( 'Customer Message', 'woocommerce' ),
        'type' => 'textarea',
        'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
        'default' => __( '주문확정 버튼을 클릭하시면 Eximbay 결제창이 나타나 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
      )
    ), $this->form_fields);
  }

  public function iamport_credit_card_form_fields($default_fields, $id)
  {
    if ($id !== $this->id) return $default_fields;

      $pay_select = '<p class="form-row">
                      <select id="' . esc_attr( $id ) . '-pay-method" class="input-text wc-credit-card-form-pay-method" autocomplete="off" name="' . ( $this->id . '-pay-method' ) . '">
                          <option value="card">CreditCard</option>
                          <option value="unionpay">UnionPay</option>
                          <option value="alipay">Alipay</option>
                          <option value="wechat">WechatPay</option>
                          <option value="econtext">EContext(' . __( '일본편의점결제', 'iamport-for-woocommerce' ) . ')</option>
                      </select> </p>';

    $iamport_fields = array(
      'pay-method-field' => $pay_select
    );

    return $iamport_fields;
  }

  public function iamport_order_detail( $order_id ) {
    global $woocommerce;

    $order = new WC_Order( $order_id );

    $paymethod = get_post_meta($order_id, '_iamport_paymethod', true);
    $receipt_url = get_post_meta($order_id, '_iamport_receipt_url', true);
    // $vbank_name = get_post_meta($order_id, '_iamport_vbank_name', true);
    // $vbank_num = get_post_meta($order_id, '_iamport_vbank_num', true);
    // $vbank_date = get_post_meta($order_id, '_iamport_vbank_date', true);
    $tid = $order->get_transaction_id();

        ob_start();
    ?>
    <h2><?=__( '결제 상세', 'iamport-for-woocommerce' )?></h2>
    <table class="shop_table order_details">
      <tbody>
        <tr>
          <th><?=__( '결제수단', 'iamport-for-woocommerce' )?></th>
          <td><?=__( 'Eximbay', 'iamport-for-woocommerce' )?></td>
        </tr>
    <?php
      if ($paymethod == 'vbank') {
    ?>
        <tr>
          <th><?=__( 'QR link', 'iamport-for-woocommerce' )?></th>
          <td><a href="<?=$receipt_url?>" target="_blank"><?=$receipt_url?></a</td>
        </tr>
    <?php
      }
    ?>
      </tbody>
    </table>
    <?php 
    ob_end_flush();
  }

  public function iamport_payment_info( $order_id ) {
    $response = parent::iamport_payment_info($order_id);
    $response['pg'] = 'eximbay';
    $response['currency'] = get_woocommerce_currency();

    return $response;
  }

}