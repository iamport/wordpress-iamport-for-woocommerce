<?php
class WC_Gateway_Iamport_Paymentwall extends Base_Gateway_Iamport {

  const GATEWAY_ID = 'iamport_paymentwall';

  public function __construct() {
    parent::__construct();

    //settings
    $this->method_title = __( '아임포트(Paymentwall)', 'iamport-for-woocommerce' );
    $this->method_description = __( '현재 Paymentwall은 신용카드를 이용한 결제만 정상적으로 동작합니다.', 'iamport-for-woocommerce' );
    $this->has_fields = true;
    $this->supports = array( 'products', 'refunds' );

    $this->title = $this->settings['title'];
    $this->description = $this->settings['description'];
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
        'label' => __( '아임포트(Paymentwall) 결제 사용. (Paymentwall 사용하시려면, <a href="https://admin.iamport.kr/settings" target="_blank">아임포트 관리자페이지의 PG설정화면</a>에서 "추가PG사"로 Paymentwall을 추가 후 사용해주세요.)', 'iamport-for-woocommerce' ),
        'default' => 'yes'
      ),
      'title' => array(
        'title' => __( 'Title', 'woocommerce' ),
        'type' => 'text',
        'description' => __( '구매자에게 표시될 구매수단명', 'iamport-for-woocommerce' ),
        'default' => __( 'Paymentwall 결제', 'iamport-for-woocommerce' ),
        'desc_tip'      => true,
      ),
      'description' => array(
        'title' => __( 'Customer Message', 'woocommerce' ),
        'type' => 'textarea',
        'description' => __( '구매자에게 결제수단에 대한 상세설명을 합니다.', 'iamport-for-woocommerce' ),
        'default' => __( '주문확정 버튼을 클릭하시면 Paymentwall 결제창이 나타나 결제를 진행하실 수 있습니다.', 'iamport-for-woocommerce' )
      )
    ), $this->form_fields);
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
          <td><?=__( 'Paymentwall', 'iamport-for-woocommerce' )?></td>
        </tr>
      </tbody>
    </table>
    <?php 
    ob_end_flush();
  }

  public function iamport_payment_info( $order_id ) {
    $response = parent::iamport_payment_info($order_id);
    $response['pg'] = 'paymentwall';
    $response['currency'] = get_woocommerce_currency();

    return $response;
  }

}