<?php
/**
 * Plugin Name: 우커머스용 아임포트 플러그인(국내 모든 PG를 한 번에)
 * Plugin URI: http://www.iamport.kr
 * Description: 우커머스용 한국PG 연동 플러그인 ( 신용카드 / 실시간계좌이체 / 가상계좌 / 휴대폰소액결제 - 에스크로포함 )
 * Version: 2.2.23
 * Author: SIOT
 * Author URI: http://www.siot.do
 *
 * Text Domain: iamport-for-woocommerce
 * Domain Path: /i18n/languages/
 *
 */

require_once('lib/IamportHelper.php');

if(!function_exists('iamport_woocommerce_not_installed')){
    function iamport_woocommerce_not_installed() {
        $class = 'notice notice-error';
        $message = '[우커머스용 아임포트 플러그인] 우커머스 플러그인이 설치되어있지 않거나 비활성화되어있습니다.';

        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }
}

if(!function_exists('iamport_woocommerce_not_compatible')){
    function iamport_woocommerce_not_compatible() {
        $class = 'notice notice-error';
        $message = '[우커머스용 아임포트 플러그인] 우커머스 3.0버전 이상과 호환이 됩니다. 현재 설치된 우커머스 플러그인과는 연동되지 않습니다.';

        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }
}

if(!function_exists('iamport_advanced_meta')){
    function iamport_advanced_meta()
    {

        woocommerce_wp_text_input(
            array(
                'id' => 'iamport_product_service_period_from',
                'label' => __( '서비스제공기간(시작)', 'iamport-for-woocommerce' ),
                'placeholder' => '예시) 20190101',
                'desc_tip' => '시작일자를 YYYYMMDD 형식에 맞춰 입력합니다.',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id' => 'iamport_product_service_period_to',
                'label' => __( '서비스제공기간(종료)', 'iamport-for-woocommerce' ),
                'placeholder' => '예시) 20191231',
                'desc_tip' => '종료일자를 YYYYMMDD 형식에 맞춰 입력합니다.',
            )
        );

        woocommerce_wp_select(
            array(
                'id' => 'iamport_product_service_period_interval',
                'label' => __( '서비스제공기간 내 반복주기)', 'iamport-for-woocommerce' ),
                'options' => array('none'=>'반복없음', 'year'=>'연단위', 'month'=>'월단위'),
            )
        );
    }
}

if(!function_exists('iamport_advanced_meta_save')){
    function iamport_advanced_meta_save($post_id)
    {
        $keys = array('iamport_product_service_period_from', 'iamport_product_service_period_to', 'iamport_product_service_period_interval');

        foreach ($keys as $k) {
            if (isset($_POST[$k])) {
                update_post_meta($post_id, $k, esc_attr($_POST[$k]));
            }
        }
    }
}

if(!function_exists('init_iamport_plugin')){
        function init_iamport_plugin() {
            if ( !class_exists( 'WooCommerce' ) ) {
                return add_action( 'admin_notices', 'iamport_woocommerce_not_installed' );
            }

            global $woocommerce;
            if ( version_compare( $woocommerce->version, "3.0" ) < 0 ) {
                add_action( 'admin_notices', 'iamport_woocommerce_not_compatible' );
            }

            //Really Simple SSL 플러그인 회피(네이버페이)
            if (!empty($_GET['wc-api'])) {
                $wcApi = sanitize_key(wp_unslash($_GET['wc-api']));

                if ($wcApi == 'naver-product-info' || $wcApi == 'iamport-naver-product-xml') {
                    define('rsssl_no_wp_redirect', 'Leave me alone');
                    define('rsssl_no_rest_api_redirect', 'Leave me alone');
                }
            }

            $label_refund = IamportHelper::display_label(IamportHelper::STATUS_REFUND);
            $label_exchange = IamportHelper::display_label(IamportHelper::STATUS_EXCHANGE);

            register_post_status( 'wc-refund-request', array(
                'label'						=> __( "{$label_refund}", 'iamport-for-woocommerce' ),
                'public'					=> true,
                'exclude_from_search'		=> false,
                'show_in_admin_all_list'	=> true,
                'show_in_admin_status_list'	=> true,
                'label_count'				=> _n_noop( "{$label_refund} <span class=\"count\">(%s)</span>", "{$label_refund} <span class=\"count\">(%s)</span>" )
            ) );

            register_post_status( 'wc-exchange-request', array(
                'label'						=> __( "{$label_exchange}", 'iamport-for-woocommerce' ),
                'public'					=> true,
                'exclude_from_search'		=> false,
                'show_in_admin_all_list'	=> true,
                'show_in_admin_status_list'	=> true,
                'label_count'				=> _n_noop( "{$label_exchange} <span class=\"count\">(%s)</span>", "{$label_exchange} <span class=\"count\">(%s)</span>" )
            ) );


            //상품 제공기간
            add_action( 'woocommerce_product_options_advanced', 'iamport_advanced_meta' );
            add_action( 'woocommerce_process_product_meta', 'iamport_advanced_meta_save' );

            //rest api endpoint
            add_action('rest_api_init', 'register_iamport_rest_routes');
        }
}

if(!function_exists('register_iamport_rest_routes')){
    function register_iamport_rest_routes()
    {
        require_once('IamportOrderRestController.php');
        require_once('NewIamportOrderRestController.php');

        $controller = new IamportOrderRestController();
        $controller->register_routes();
        $controller = new NewIamportOrderRestController();
        $controller->register_routes();
    }
}

if(!function_exists('enqueue_iamport_common_script')){
    function enqueue_iamport_common_script() {
        wp_enqueue_script( 'jquery-ui-dialog' );
        wp_enqueue_style( 'wp-jquery-ui-dialog' );
    }
}

if(!function_exists('add_cancel_actions_to_order_statuses')){
    function add_cancel_actions_to_order_statuses( $order_statuses ) {
        $new_order_statuses = array();

        // cancelled status다음에 추가
        foreach ( $order_statuses as $key => $status ) {

            $new_order_statuses[ $key ] = $status;

            if ( 'wc-cancelled' === $key ) {
                $label_refund = IamportHelper::display_label(IamportHelper::STATUS_REFUND);
                $label_exchange = IamportHelper::display_label(IamportHelper::STATUS_EXCHANGE);

                $new_order_statuses['wc-refund-request'] = __( "{$label_refund}", 'iamport-for-woocommerce' );
                $new_order_statuses['wc-exchange-request'] = __( "{$label_exchange}", 'iamport-for-woocommerce' );
            }
        }

        return $new_order_statuses;
    }
}

if(!function_exists('iamport_vbank_order_details')){
    function iamport_vbank_order_details($order) {
        $pay_method = get_post_meta($order->get_id(), '_iamport_paymethod', true);
        $vbank_name = get_post_meta($order->get_id(), '_iamport_vbank_name', true);
        $vbank_num = get_post_meta($order->get_id(), '_iamport_vbank_num', true);
        $vbank_date = get_post_meta($order->get_id(), '_iamport_vbank_date', true);
        $vbank_holder = get_post_meta($order->get_id(), '_iamport_vbank_holder', true);

        if ( $pay_method !== 'vbank' || empty($vbank_num) )	return;

        ob_start();?>
        <div class="order_data_column" style="width: 100%;clear: both">
            <h3><?php echo __( '가상계좌정보', 'iamport-for-woocommerce' ); ?></h3>
            <p class="form-field form-field-wide">
                <strong><?php echo __( '은행명', 'iamport-for-woocommerce' ); ?></strong>&nbsp;:&nbsp;<?php echo $vbank_name;?><br>
                <?php if (!empty($vbank_holder)) : ?>
                <strong><?php echo __( '예금주', 'iamport-for-woocommerce' ); ?></strong>&nbsp;:&nbsp;<?php echo $vbank_holder;?><br>
                <?php endif; ?>
                <strong><?php echo __( '계좌번호', 'iamport-for-woocommerce' ); ?></strong>&nbsp;:&nbsp;<?php echo $vbank_num;?><br>
                <strong><?php echo __( '입금기한', 'iamport-for-woocommerce' ); ?></strong>&nbsp;:&nbsp;<?php echo date('Y-m-d H:i:s', $vbank_date+( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ));?>
            </p>
        </div>
    <?php
        ob_end_flush();
    }
}

add_action( 'init', 'init_iamport_plugin' );
add_action( 'wp_enqueue_scripts', 'enqueue_iamport_common_script' );
add_action( 'plugins_loaded', 'woocommerce_gateway_iamport_init', 0 );
add_action( 'admin_enqueue_scripts', 'enqueue_iamport_admin_style' );
add_filter( 'wc_order_statuses', 'add_cancel_actions_to_order_statuses' );
add_action( 'woocommerce_admin_order_data_after_order_details', 'iamport_vbank_order_details' );


if(!function_exists('enqueue_iamport_admin_style')){
    function enqueue_iamport_admin_style() {
        wp_register_script( 'iamport_momentjs', plugins_url( '/assets/js/moment.min.js',plugin_basename(__FILE__) ) );
        wp_enqueue_script( 'iamport_momentjs' );

        wp_register_script( 'iamport_adminjs', plugins_url( '/assets/js/iamport.woocommerce.admin.js',plugin_basename(__FILE__) ) );
        wp_enqueue_script( 'iamport_adminjs' );

        wp_register_style( 'iamport_wp_admin_css', plugins_url( '/admin-style.css', plugin_basename(__FILE__)), array(), "20180914" );
        wp_enqueue_style( 'iamport_wp_admin_css' );
    }
}

if(!function_exists('find_gateway')){
    function find_gateway($pg_provider, $pay_method) {
        $gatewayId = null;

        switch($pg_provider) {
            case 'naverco' :
                $gatewayId = WC_Gateway_Iamport_NaverPay::GATEWAY_ID;
                break;

            case 'naverpay' :
                $gatewayId = WC_Gateway_Iamport_NaverPayExt::GATEWAY_ID;
                break;

            case 'smilepay' :
                $gatewayId = WC_Gateway_Iamport_Smilepay::GATEWAY_ID;
                break;

            case 'kakao' :
            case 'kakaopay' :
                $gatewayId = WC_Gateway_Iamport_Kakao::GATEWAY_ID;
                break;

            default :
                switch($pay_method) {
                    case 'card' :
                        $gatewayId = WC_Gateway_Iamport_Card::GATEWAY_ID;
                        break;

                    case 'trans' :
                        $gatewayId = WC_Gateway_Iamport_Trans::GATEWAY_ID;
                        break;

                    case 'vbank' :
                        $gatewayId = WC_Gateway_Iamport_Vbank::GATEWAY_ID;
                        break;

                    case 'phone' :
                        $gatewayId = WC_Gateway_Iamport_Phone::GATEWAY_ID;
                        break;

                    case 'kakao' : //order_review에서 결제가 올라올 때는 pay_method가 kakao로 그대로 올라옴(gateway이름 그대로)
                    case 'kakaopay' :
                        $gatewayId = WC_Gateway_Iamport_Kakao::GATEWAY_ID;
                        break;

                    case 'kpay' :
                        $gatewayId = WC_Gateway_Iamport_Kpay::GATEWAY_ID;
                        break;

                    case 'samsung' :
                        $gatewayId = WC_Gateway_Iamport_Samsung::GATEWAY_ID;
                        break;

                    case 'payco' :
                        $gatewayId = WC_Gateway_Iamport_Payco::GATEWAY_ID;
                        break;

                    case 'eximbay' :
                        $gatewayId = WC_Gateway_Iamport_Eximbay::GATEWAY_ID;
                        break;

                    default :
                        $gatewayId = WC_Gateway_Iamport_Card::GATEWAY_ID;
                        break;
                }
                break;
        }

        if ($gatewayId) {
            $availables = WC()->payment_gateways()->get_available_payment_gateways();

            if (isset($availables[$gatewayId])) {
                return $availables[$gatewayId];
            }
        }

        return null;
    }
}

if(!function_exists('iamport_order_detail_in_history')){
    function iamport_order_detail_in_history( $order ) {
        // $pay_method = get_post_meta($order->get_id(), '_iamport_paymethod', true);
        // $pg_provider = get_post_meta($order->get_id(), '_iamport_provider', true);

        // $gateway = find_gateway($pg_provider, $pay_method);
        $gateway = wc_get_payment_gateway_by_order($order);
        if ( IamportHelper::isIamportGateway($gateway) && method_exists($gateway, "iamport_order_detail") ) { //2.0.41 : iamport 관련 gateway일 때에만 반응해야 함
            $gateway->iamport_order_detail($order->get_id());
        }
    }
}

if(!function_exists('ajax_iamport_payment_info')){
    function ajax_iamport_payment_info() {
        header('Content-type: application/json');

        if ( !empty($_GET['gateway_name']) && !empty($_GET['order_key']) ) {
            $gateway_name = $_GET['gateway_name'];
            $pay_method = $_GET['pay_method'];
            $order_key = $_GET['order_key'];

            $order_id = wc_get_order_id_by_order_key($order_key);
            $order = wc_get_order($order_id);
            $order->set_payment_method($gateway_name); //[2019-07-25]사용자가 선택한 결제수단으로 Gateway 정보를 먼저 바꿔준다.
            $order->save();

            $gateway = wc_get_payment_gateway_by_order($order);

            //fallback : 2019-02-27 : 3rd party 플러그인에 의해 주문이 생성되는 경우 pay_method 가 없는 order가 존재하는 경우 대비
            if (!$gateway) {
                $pg_provider = get_post_meta($order_id, '_iamport_provider', true);

                $gateway = find_gateway($pg_provider, $pay_method);
            }

            if ( $gateway ) {
                try {
                    $iamport_info = $gateway->iamport_payment_info( $order_id );

                    echo json_encode(array(
                        'result' => 'success',
                        'order_id' => $order_id,
                                'order_key' => $order_key,
                                'iamport' => $iamport_info
                    ));
                } catch (Exception $e) {
                    echo json_encode(array(
                        'result' => 'fail',
                        'messages' => $e->getMessage(),
                    ));
                }

                wp_die();
            } else {
                echo json_encode(array(
                    'result' => 'fail',
                    'message' => __( '해당되는 woocommerce gateway를 찾을 수 없습니다.', 'iamport-for-woocommerce' )
                ));
            }
        }

        echo json_encode(array(
            'result' => 'fail'
        ));

        wp_die();
    }
}

if(!function_exists('iamport_email_actions')){
    function iamport_email_actions($actions) {
        $actions[] = 'woocommerce_order_status_awaiting-vbank_to_processing';
        $actions[] = 'woocommerce_order_status_awaiting-vbank_to_completed';

        $actions[] = 'woocommerce_order_status_failed_to_awaiting-vbank';
        $actions[] = 'woocommerce_order_status_on-hold_to_awaiting-vbank';
        $actions[] = 'woocommerce_order_status_pending_to_awaiting-vbank';

        return $actions;
    }
}

if(!function_exists('iamport_vbank_email_notification')){
    function iamport_vbank_email_notification($email_classes) {
        require_once(dirname(__FILE__).'/includes/emails/class-iamport-email-vbank-processing-order.php'); //구매자용
        require_once(dirname(__FILE__).'/includes/emails/class-iamport-email-vbank-confirm-order.php'); //관리자용
        require_once(dirname(__FILE__).'/includes/emails/class-iamport-email-vbank-awaiting-order.php'); //구매자용(가상계좌 발급)

        $email_classes['IMP_Customer_Vbank_Confirm_Email'] = new IMP_Email_Customer_Vbank_Processing_Order();
        $email_classes['IMP_Admin_Vbank_Confirm_Email'] = new IMP_Email_Admin_Vbank_Confirm_Order();
        $email_classes['IMP_Email_Customer_Vbank_Awaiting_Order'] = new IMP_Email_Customer_Vbank_Awaiting_Order();

        return $email_classes;
    }
}

if(!function_exists('iamport_valid_order_statuses_for_cancel')){
    function iamport_valid_order_statuses_for_cancel($statuses, $order=null) {
        //cancel_order가 실행될 때는 $order를 넘겨주지 않는
        require_once('lib/IamportHelper.php');

        $refundable_paid_statuses = array('processing');
        $custom = IamportHelper::paidCustomStatus(false);
        if ($custom) {
            $refundable_paid_statuses[] = $custom;
        }

        return array_merge( $statuses, $refundable_paid_statuses );
    }
}

if(!function_exists('iamport_refund_payment')){
    function iamport_refund_payment($order_id) {
        require_once(dirname(__FILE__).'/lib/iamport.php');

        $order = new WC_Order( $order_id );

        //[2.1.2] 아임포트 관련 Gateway 일 때에만 시도해야 함(예. BACS 등은 시도하면 안됨)
        $gateway = wc_get_payment_gateway_by_order($order);

        if ( IamportHelper::isIamportGateway($gateway) ) {
            $imp_uid = $order->get_transaction_id();
            $rest_key = get_post_meta($order_id, '_iamport_rest_key', true);
            $rest_secret = get_post_meta($order_id, '_iamport_rest_secret', true); //TODO : secret이 바뀌었을 수 있으므로 order_id 로 gateway설정값을 다시 읽어들여서 처리해야 함

            $iamport = new WooIamport($rest_key, $rest_secret);

            //전액취소
            $result = $iamport->cancel(array(
                'imp_uid'=>$imp_uid,
                'reason'=> __( '구매자 환불요청', 'iamport-for-woocommerce' )
            ));

            if ( $result->success ) {
                $payment_data = $result->data;
                $order->add_order_note( __( '구매자요청에 의해 전액 환불완료', 'iamport-for-woocommerce' ) );
                if ( $payment_data->amount == $payment_data->cancel_amount ) {
                    $old_status = $order->get_status();
                    $order->update_status('refunded'); //iamport_refund_payment가 old_status -> cancelled로 바뀌는 중이라 update_state('refunded')를 호출하는 것이 향후에 문제가 될 수 있음

                    //fire hook
                    do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);
                }
            } else {
                $order->add_order_note($result->error['message']);
            }
        }
    }
}

if(!function_exists('iamport_auto_complete')){
    function iamport_auto_complete($order_id) {
        global $woocommerce;
        require_once('lib/IamportHelper.php');

        //custom 상태는 processing처럼 고객 환불이 가능하다는 점에서 completed와 다르다.
        //때문에, completed 설정과 custom 설정을 동시에 했다면 completed설정으로 따라줘야 한다.

        $auto_complete_enabled = get_option( 'woocommerce_iamport_auto_complete' ) !== 'yes' ? false : true;
        $custom = IamportHelper::paidCustomStatus(false);

        if ( !$auto_complete_enabled && empty($custom) )	return;

        $order = new WC_Order($order_id);

        $old_status = $order->get_status();

        if ($auto_complete_enabled) {
            $order->update_status('completed');
            $order->add_order_note( '처리중 주문이 완료됨으로 자동 변경되었습니다.' );
        } else {
            $order->update_status($custom);
            $order->add_order_note( '처리중 주문이 '. $custom .'으로 자동 변경되었습니다.' );
        }

        //fire hook
        do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);
    }
}

if(!function_exists('iamport_address_replacements')){
    function iamport_address_replacements($replaces, $args)
    {
        $replaces["{first_name}"]     = "";
        $replaces["{last_name}"]      = "";
        $replaces["{name}"]           = "";
        $replaces["{postcode}"]       = "";
        $replaces["{postcode_upper}"] = "";
        $replaces["{company}"]        = "";

        return $replaces;
    }
}

if(!function_exists('iamport_woocommerce_general_settings')){
    function iamport_woocommerce_general_settings($settings) {
        $settings[] = array( 'title' => __( '아임포트 옵션', 'iamport-for-woocommerce' ), 'type' => 'title', 'desc' => '', 'id' => 'iamport_general_options' );

        $settings[] = array(
            'title'   => __( '자동 완료됨 처리', 'iamport-for-woocommerce' ),
            'desc'    => __( '처리중 상태를 거치지 않고 완료됨으로 자동 변경하시겠습니까?<br>(우커머스에서 "처리중"상태는 결제가 완료되었음을, "완료됨"상태는 상품발송이 완료되었음을 의미합니다. 발송될 상품없이 결제가 되면 곧 서비스가 개시되어야 하는 경우 사용하시면 편리합니다.', 'iamport-for-woocommerce' ),
            'id'      => 'woocommerce_iamport_auto_complete',
            'default' => 'no',
            'type'    => 'checkbox'
        );

        $settings[] = array( 'type' => 'sectionend', 'id' => 'iamport_general_options');

        return $settings;
    }
}

if(!function_exists('iamport_order_endpoint_data')){
    function iamport_order_endpoint_data() {
        global $woocommerce;

        if ( isset($_GET['iamport-cancel-action']) && isset($_GET['order-id']) && isset($_GET['order-key']) ) {
            $iamport_cancel_action = $_GET['iamport-cancel-action'];
            $order_id = $_GET['order-id'];
            $order_key = $_GET['order-key'];
            $page = isset($_GET['order-page']) ? $_GET['order-page'] : '';

            $order = new WC_Order( $order_id );
            if ( !in_array( $order->get_status(), array('completed') ) )	return;

            $orders_url = wc_get_endpoint_url( 'orders', $page, wc_get_page_permalink( 'myaccount' ) );
            if ( $iamport_cancel_action === 'refund-ask' ) {
                $redirect_url = add_query_arg(array(
                    'iamport-cancel-action'=>'refund',
                    'order-page'=>$page,
                    'order-id'=>$order_id,
                    'order-key'=>$order_key
                ), $orders_url);

                ob_start();?>
                <div class="iamport-refund-box" id="iamport-refund-box" style="display:none;clear:both">
                    <p><?=sprintf( __( '#%s 주문을 반품요청하시겠습니까?', 'iamport-for-woocommerce'), $order_id )?></p>
                    <p>
                        <label for="iamport-refund-reason"><?=__( '반품요청사유', 'iamport-for-woocommerce' )?> : </label>
                        <textarea id="iamport-refund-reason"></textarea>
                        <p id="invalid-reason" style="display:none"><?=__("사유를 입력해주세요", "iamport-for-woocommerce")?></p>
                    </p>
                </div>

                <script type="text/javascript">
                    jQuery(function($) {
                        $('#iamport-refund-box').dialog({
                            title: "<?=__('판매자에게 반품요청하시겠습니까?', 'iamport-for-woocommerce')?>",
                            resizable: false,
                            height: "auto",
                            width: 400,
                            modal: true,
                            close : function() {
                                history.back();
                            },
                            buttons: {
                                "<?=__('반품요청', 'iamport-for-woocommerce')?>": function() {
                                    var input = $(this).find('#iamport-refund-reason'),
                                        orders_url = '<?=$redirect_url?>';

                                    var reason = input.val();
                                    if ( reason.length == 0 ) {
                                        $(this).find('#invalid-reason').show();
                                        return false;
                                    }

                                    location.href = orders_url + '&reason=' + encodeURIComponent(reason)
                                    $( this ).dialog( "close" );
                                },
                                "<?=__('그냥두기', 'iamport-for-woocommerce')?>": function() {
                                    $( this ).dialog( "close" );
                                }
                            }
                        });
                    });
                </script>
            <?php
                ob_end_flush();
            } else if ( $iamport_cancel_action === 'exchange-ask' ) {
                $redirect_url = add_query_arg(array(
                    'iamport-cancel-action'=>'exchange',
                    'order-page'=>$page,
                    'order-id'=>$order_id,
                    'order-key'=>$order_key
                ), $orders_url);

                ob_start();?>
                <div class="iamport-exchange-box" id="iamport-exchange-box" style="display:none;clear:both">
                    <p><?=sprintf( __( '#%s 주문을 교환요청하시겠습니까?', 'iamport-for-woocommerce'), $order_id )?></p>
                    <p>
                        <label for="iamport-exchange-reason"><?=__( '교환요청사유', 'iamport-for-woocommerce' )?> : </label>
                        <textarea id="iamport-exchange-reason"></textarea>
                        <p id="invalid-reason" style="display:none"><?=__("사유를 입력해주세요", "iamport-for-woocommerce")?></p>
                    </p>
                </div>

                <script type="text/javascript">
                    jQuery(function($) {
                        $('#iamport-exchange-box').dialog({
                            title: "<?=__('판매자에게 교환요청하시겠습니까?', 'iamport-for-woocommerce')?>",
                            resizable: false,
                            height: "auto",
                            width: 400,
                            modal: true,
                            close : function() {
                                history.back();
                            },
                            buttons: {
                                "<?=__('교환요청', 'iamport-for-woocommerce')?>": function() {
                                    var input = $(this).find('#iamport-exchange-reason'),
                                        orders_url = '<?=$redirect_url?>';

                                    var reason = input.val();
                                    if ( reason.length == 0 ) {
                                        $(this).find('#invalid-reason').show();
                                        return false;
                                    }

                                    location.href = orders_url + '&reason=' + encodeURIComponent(reason)
                                    $( this ).dialog( "close" );
                                },
                                "<?=__('그냥두기', 'iamport-for-woocommerce')?>": function() {
                                    $( this ).dialog( "close" );
                                }
                            }
                        });
                    });
                </script>
            <?php
                ob_end_flush();
            }
        }
    }
}

if(!function_exists('iamport_cancel_request_actions')){
    function iamport_cancel_request_actions($actions, $order) {
        global $wp;

        if ( in_array( $order->get_status(), array('completed') ) ) {
            $exchange_capable = iamport_exchange_capable($order);
            $refund_capable = iamport_refund_capable($order);

            $page = $wp->query_vars['orders'];

            if ($refund_capable) {
                $actions['iamport_refund_request'] = array(
                    'name' => __( '반품요청', 'iamport-for-woocommerce' ),
                    'url' => add_query_arg( array(
                        'iamport-cancel-action'=>'refund-ask',
                        'order-page'=>$page,
                        'order-id'=>$order->get_id(),
                        'order-key'=>$order->get_order_key()
                    ), wc_get_endpoint_url( 'orders', $page, wc_get_page_permalink( 'myaccount' ) ) )
                );
            }

            if ($exchange_capable) {
                $actions['iamport_exchange_request'] = array(
                    'name' => __( '교환요청', 'iamport-for-woocommerce' ),
                    'url' => add_query_arg( array(
                        'iamport-cancel-action'=>'exchange-ask',
                        'order-page'=>$page,
                        'order-id'=>$order->get_id(),
                        'order-key'=>$order->get_order_key()
                    ), wc_get_endpoint_url( 'orders', $page, wc_get_page_permalink( 'myaccount' ) ) )
                );
            }
        }

        return $actions;
    }
}

if(!function_exists('iamport_cancel_handle')){
    function iamport_cancel_handle() {
        global $woocommerce;

        if ( isset($_GET['iamport-cancel-action']) && isset($_GET['order-key']) && isset($_GET['order-id']) ) {
            $order_id = $_GET['order-id'];
            $page = isset($_GET['order-page']) ? $_GET['order-page'] : '';
            $reason = isset($_GET['reason']) ? $_GET['reason'] : '구매자 요청';

            $order = new WC_Order( $order_id );

            if ( $_GET['iamport-cancel-action'] == 'refund' ) {
                $refund_capable = iamport_refund_capable($order);
                if ( !$refund_capable )	return;

                $old_status = $order->get_status();

                $order->update_status('refund-request');
                $order->add_order_note( sprintf(__( '반품요청 사유 : %s', 'iamport-for-woocommerce' ), $reason) );

                //fire hook
                do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);

                wp_redirect( wc_get_endpoint_url( 'orders', $page, wc_get_page_permalink( 'myaccount' ) ) );
            } else if ( $_GET['iamport-cancel-action'] == 'exchange' ) {
                $exchange_capable = iamport_exchange_capable($order);
                if ( !$exchange_capable )	return;

                $old_status = $order->get_status();

                $order->update_status('exchange-request');
                $order->add_order_note( sprintf(__( '교환요청 사유 : %s', 'iamport-for-woocommerce' ), $reason) );

                //fire hook
                do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);

                wp_redirect( wc_get_endpoint_url( 'orders', $page, wc_get_page_permalink( 'myaccount' ) ) );
            }
        }
    }
}

if(!function_exists('iamport_exchange_capable')){
    function iamport_exchange_capable($order) {
        if ( get_option( 'woocommerce_iamport_exchange_capable' ) === 'no' )	return false;

        $limit = get_option( "woocommerce_iamport_exchange_limit", null );
        if ( is_numeric($limit) && $limit > 0 ) {
            $completedAt = $order->get_date_completed(); //exchange_capable 은 completed 상태의 주문에 대하여 제공되는 기능이므로, completed 시점 기준으로 날짜 계산
            if ( !empty($completedAt) ) {
                $diff = time() - $completedAt->getTimestamp();

                if ( $diff > $limit * 24 * 60 * 60 )	return false;
            }
        }

        return true;
    }
}

if(!function_exists('iamport_refund_capable')){
    function iamport_refund_capable($order) {
        if ( get_option('woocommerce_iamport_refund_capable', get_option('woocommerce_iamport_exchange_capable'))  === 'no' )	return false;

        $limit = get_option("woocommerce_iamport_refund_limit", get_option( "woocommerce_iamport_exchange_limit", null ));
        if ( is_numeric($limit) && $limit > 0 ) {
            $completedAt = $order->get_date_completed(); //exchange_capable 은 completed 상태의 주문에 대하여 제공되는 기능이므로, completed 시점 기준으로 날짜 계산
            if ( !empty($completedAt) ) {
                $diff = time() - $completedAt->getTimestamp();

                if ( $diff > $limit * 24 * 60 * 60 )	return false;
            }
        }

        return true;
    }
}

if(!function_exists('iamport_order_is_paid_statuses')){
    function iamport_order_is_paid_statuses($statuses) {
        require_once('lib/IamportHelper.php');

        $custom = IamportHelper::paidCustomStatus(false);
        if ($custom) {
            $statuses[] = $custom;
        }

        return $statuses;
    }
}

if(!function_exists('iamport_add_order_phone_column_header')){
    function iamport_add_order_phone_column_header($columns)
    {
        $new_columns = array();

        foreach ($columns as $column_name => $column_info) {

            $new_columns[ $column_name ] = $column_info;

            if ( 'shipping_address' === $column_name ) {
                $new_columns['billing_phone'] = __( '전화번호', 'iamport-for-woocommerce' );
            }
        }

        return $new_columns;
    }
}

if(!function_exists('iamport_add_order_phone_column_content')){
    function iamport_add_order_phone_column_content($column)
    {
        global $post;

        if ( 'billing_phone' === $column ) {
            $order    = wc_get_order( $post->ID );
            echo $order->get_billing_phone();
        }
    }
}

if(!function_exists('hook_common_actions')){
    function hook_common_actions() {
        //default
        add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_gateway_iamport_gateway' );

        add_action( 'woocommerce_order_details_after_order_table', 'iamport_order_detail_in_history' );
        add_action( 'woocommerce_email_after_order_table', 'iamport_order_detail_in_history' );

        //email sends
        add_filter('woocommerce_email_actions', 'iamport_email_actions' );
        add_filter('woocommerce_email_classes', 'iamport_vbank_email_notification');

        //ajax. iamport payment for order_review
        add_action('wp_ajax_iamport_payment_info', 'ajax_iamport_payment_info');
        add_action('wp_ajax_nopriv_iamport_payment_info', 'ajax_iamport_payment_info');

        //cancel in my-page
        add_filter( 'woocommerce_valid_order_statuses_for_cancel', 'iamport_valid_order_statuses_for_cancel', 10, 2 );
        //구매자가 직접 취소할 때 환불처리(processing상태일 때만)
        add_action( 'woocommerce_order_status_processing_to_cancelled', 'iamport_refund_payment', 10, 1 );
        //[2020-09-25] custom 주문상태에서 환불되는 경우
        require_once('lib/IamportHelper.php');
        $custom = IamportHelper::paidCustomStatus(false);
        if ($custom) {
            add_action( 'woocommerce_order_status_' . $custom . '_to_cancelled', 'iamport_refund_payment', 10, 1 );
        }

        add_action( 'wp_footer', 'iamport_order_endpoint_data' );
        add_action( 'template_redirect', 'iamport_cancel_handle' );
        add_filter( 'woocommerce_my_account_my_orders_actions', 'iamport_cancel_request_actions', 10, 2 );

        //auto complete추가
        // add_filter( 'woocommerce_general_settings', 'iamport_woocommerce_general_settings', 10, 1 ); 별도 탭으로 변경
        add_action( 'woocommerce_order_status_pending_to_processing', 'iamport_auto_complete', 10, 1 );
        add_action( 'woocommerce_order_status_on-hold_to_processing', 'iamport_auto_complete', 10, 1 );
        add_action( 'woocommerce_order_status_failed_to_processing', 'iamport_auto_complete', 10, 1 );
        add_action( 'woocommerce_order_status_awaiting-vbank_to_processing', 'iamport_auto_complete', 10, 1 );

        //아임포트 Tab설정 추가
        $settingInst = new IamportSettingTab();
        add_filter( 'woocommerce_settings_tabs_array', array($settingInst, 'label'), 50, 1 );

        //buyer_addr 에 postcode 등이 넘어오지 않도록 replace filter
        // add_filter( "woocommerce_formatted_address_replacements", "iamport_address_replacements", 10, 2 );

        //네이버페이(결제형) 상품 카테고리
        add_action( "product_cat_add_form_fields",  array("WC_Gateway_Iamport_NaverPayExt", "render_add_product_category"), 50 );
        add_action( "product_cat_edit_form_fields", array("WC_Gateway_Iamport_NaverPayExt", "render_edit_product_category"), 50 );

        add_action( "edited_product_cat", array("WC_Gateway_Iamport_NaverPayExt", "save_edit_product_category") );
        add_action( "create_product_cat", array("WC_Gateway_Iamport_NaverPayExt", "save_add_product_category") );

        add_filter( 'woocommerce_order_is_paid_statuses', 'iamport_order_is_paid_statuses' );

        //주문내역 리스트에 전화번호 추가
        add_filter( 'manage_edit-shop_order_columns', 'iamport_add_order_phone_column_header', 20 );
        add_action( 'manage_shop_order_posts_custom_column', 'iamport_add_order_phone_column_content' );
    }
}

if(!function_exists('woocommerce_gateway_iamport_init')){
    function woocommerce_gateway_iamport_init() {

        if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

        /**
         * Common Gateway class
         */
        abstract class Base_Gateway_Iamport extends WC_Payment_Gateway {

            public function __construct() {
                $this->id = $this->get_gateway_id(); //id가 먼저 세팅되어야 init_setting가 제대로 동작

                $this->init_form_fields();
                $this->init_settings();

                $this->imp_user_code = $this->settings['imp_user_code'];
                $this->imp_rest_key = $this->settings['imp_rest_key'];
                $this->imp_rest_secret = $this->settings['imp_rest_secret'];

                //woocommerce action
                add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_payment_response' ) );
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_iamport_script') );
                add_filter( 'woocommerce_generate_order_key', array($this, 'generate_order_key') );
            }

            abstract protected function get_gateway_id();
            abstract public function iamport_order_detail( $order_id );

            public function init_form_fields() {
                //iamport기본 플러그인에 해당 정보가 세팅되어있는지 먼저 확인
                $default_user_code = get_option('iamport_user_code');
                $default_api_key = get_option('iamport_rest_key');
                $default_api_secret = get_option('iamport_rest_secret');

                $this->form_fields = array(
                    'imp_user_code' => array(
                        'title' => __( '[아임포트] 가맹점 식별코드', 'iamport-for-woocommerce' ),
                        'type' => 'text',
                        'description' => __( 'https://admin.iamport.kr에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.', 'iamport-for-woocommerce' ),
                        'label' => __( '[아임포트] 가맹점 식별코드', 'iamport-for-woocommerce' ),
                        'default' => $default_user_code
                    ),
                    'imp_rest_key' => array(
                        'title' => __( '[아임포트] REST API 키', 'iamport-for-woocommerce' ),
                        'type' => 'text',
                        'description' => __( 'https://admin.iamport.kr에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.', 'iamport-for-woocommerce' ),
                        'label' => __( '[아임포트] REST API 키', 'iamport-for-woocommerce' ),
                        'default' => $default_api_key
                    ),
                    'imp_rest_secret' => array(
                        'title' => __( '[아임포트] REST API Secret', 'iamport-for-woocommerce' ),
                        'type' => 'text',
                        'description' => __( 'https://admin.iamport.kr에서 회원가입 후, "시스템설정" > "내정보"에서 확인하실 수 있습니다.', 'iamport-for-woocommerce' ),
                        'label' => __( '[아임포트] REST API Secret', 'iamport-for-woocommerce' ),
                        'default' => $default_api_secret
                    )
                );
            }

            public function generate_order_key($order_key) {
                //22자 글자제한이 있어서 prefix 를 줄임
                return 'p'.rand(0, 99999).substr( preg_replace( "/[^A-Za-z0-9_]/", '', uniqid('', true)), 10 ); //more entropy
            }

            protected function getKcpProducts($order_id) {
                $order = new WC_Order( $order_id );

                $cart_items = $order->get_items();
                $kcpProducts = array();

                foreach ($cart_items as $item_id=>$item) { //WC_Order_Item 에서 지원되는  메소드만 사용하고 있음
                    $kcpProducts[] = array(
                        "orderNumber" => $item->get_order_id() . "-" . $item->get_id(),
                        "name" => $item->get_name(),
                        "quantity" => wc_get_order_item_meta($item_id, '_qty', true),
                        "amount" => wc_get_order_item_meta($item_id, '_line_total', true) + wc_get_order_item_meta($item_id, '_line_tax', true),
                    );
                }

                return $kcpProducts;
            }

            public function is_paid_confirmed($order, $payment_data) {
                return $order->get_total() == $payment_data->amount;
            }

            // common for check payment
            // #1. woocommerce 결제 프로세스시 전달되는 데이터
            /**
            * 	[pay_for_order] => true
            * 	[key] => wc_order_5747ba9d89c1c
            * 	[order_id] => 628
            *  	[wc-api] => WC_Gateway_Iamport_Card
            *	[imp_uid] => imp_414622838033
            */

            // #2. Notification URL에 의해 전달되는 데이터
            /**
            *	[imp_uid] => imp_414622838033
            * 	[merchant_uid] => wc_orderx_65723e22924514023
            */
            public function check_payment_response() {
                global $woocommerce, $wpdb;

                $http_method = $_SERVER['REQUEST_METHOD'];
                $http_param = array(
                    'imp_uid' => $this->http_param('imp_uid', $http_method),
                    'merchant_uid' => $this->http_param('merchant_uid', $http_method),
                    'order_id' => $this->http_param('order_id', $http_method)
                );

                $called_from_iamport = empty($http_param['order_id']); //wp_redirect 안하기 위해서 boolean 기록

                if ( !empty($http_param['imp_uid']) ) {
                    //결제승인 결과조회
                    require_once(dirname(__FILE__).'/lib/iamport.php');

                    $imp_uid = $http_param['imp_uid'];

                    //Gateway마다 다른 key/secret을 가질 수 있으므로 현재 Gateway를 확인하고처리
                    $auth = $this->getRestInfo($http_param['merchant_uid'], $called_from_iamport);

                    $iamport = new WooIamport($auth['imp_rest_key'], $auth['imp_rest_secret']);
                    $result = $iamport->findByImpUID($imp_uid);
                    $loggers = array();

                    if ( $result->success ) {
                        $loggers[] = "A:success";
                        $payment_data = $result->data;

                        //보안상 REST API로부터 받아온 merchant_uid에서 order_id를 찾아내야한다.(GET파라메터의 order_id를 100%신뢰하지 않도록)
                        $order_id = wc_get_order_id_by_order_key( $payment_data->merchant_uid );
                        $gateway = wc_get_payment_gateway_by_order($order_id);

                        $this->_iamport_post_meta($order_id, '_iamport_rest_key', $auth['imp_rest_key']);
                        $this->_iamport_post_meta($order_id, '_iamport_rest_secret', $auth['imp_rest_secret']);
                        $this->_iamport_post_meta($order_id, '_iamport_provider', $payment_data->pg_provider);
                        $this->_iamport_post_meta($order_id, '_iamport_paymethod', $payment_data->pay_method);
                        $this->_iamport_post_meta($order_id, '_iamport_pg_tid', $payment_data->pg_tid);
                        $this->_iamport_post_meta($order_id, '_iamport_receipt_url', $payment_data->receipt_url);

                        if ( $payment_data->status === 'paid' ) {
                            $loggers[] = "B:paid";

                            try {
                                $wpdb->query("BEGIN");
                                //lock the row
                                $synced_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$order_id} FOR UPDATE");

                                $order = new WC_Order( $order_id ); //lock잡은 후 호출(2017-01-16 : 의미없음. [1.6.8] synced_row의 값을 활용해서 status체크해야 함)

                                if ( $gateway->is_paid_confirmed($order, $payment_data) ) {
                                    $loggers[] = "C:confirm";

                                    if ( !$this->has_status($synced_row->post_status, wc_get_is_paid_statuses()) ) {
                                        $loggers[] = "D:completed";

                                        $order->set_payment_method( $gateway );

                                        //fire hook
                                        do_action('iamport_pre_order_completed', $order, $payment_data);

                                        $order->payment_complete( $payment_data->imp_uid ); //imp_uid

                                        $wpdb->query("COMMIT");

                                        //fire hook
                                        do_action('iamport_post_order_completed', $order, $payment_data);
                                        do_action('iamport_order_status_changed', $synced_row->post_status, $order->get_status(), $order);

                                        $called_from_iamport ? exit('Payment Saved') : wp_redirect( $this->get_return_url($order) );
                                    } else {
                                        $loggers[] = "D:status(".$synced_row->post_status.")";

                                        $wpdb->query("ROLLBACK");
                                        //이미 이뤄진 주문 : 2016-09-01 / redirect가 중복으로 발생되는 경우들이 발견
                                        $called_from_iamport ? exit('Already Payment Saved') : wp_redirect( $this->get_return_url($order) );
                                    }

                                    return;
                                } else {
                                    $loggers[] = "C:invalid";

                                    $order->add_order_note( __( '요청하신 결제금액이 다릅니다.', 'iamport-for-woocommerce' ) );
                                    wc_add_notice( __( '요청하신 결제금액이 다릅니다.', 'iamport-for-woocommerce' ), 'error');

                                    $wpdb->query("COMMIT");
                                }
                            } catch(Exception $e) {
                                $loggers[] = "C:".$e->getMessage();

                                $wpdb->query("ROLLBACK");
                            }
                        } else if ( $payment_data->status == 'ready' ) {
                            $loggers[] = "B:ready";

                            try {
                                $wpdb->query("BEGIN");
                                //lock the row
                                $synced_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$order_id} FOR UPDATE");

                                $order = new WC_Order( $order_id ); //lock잡은 후 호출(2017-01-16 : 의미없음. [1.6.8] synced_row의 값을 활용해서 status체크해야 함)

                                if ( $payment_data->pay_method == 'vbank' ) {
                                    $loggers[] = "C:vbank";

                                    $vbank_name = $payment_data->vbank_name;
                                    $vbank_num = $payment_data->vbank_num;
                                    $vbank_date = $payment_data->vbank_date;
                                    $vbank_holder = trim($payment_data->vbank_holder);

                                    //가상계좌 입금할 계좌정보 기록
                                    $this->_iamport_post_meta($order_id, '_iamport_vbank_name', $vbank_name);
                                    $this->_iamport_post_meta($order_id, '_iamport_vbank_num', $vbank_num);
                                    $this->_iamport_post_meta($order_id, '_iamport_vbank_date', $vbank_date);
                                    $this->_iamport_post_meta($order_id, '_iamport_vbank_holder', $vbank_holder);

                                    //가상계좌 입금대기 중
                                    if ( !$this->has_status($synced_row->post_status, array('awaiting-vbank')) ) {
                                        $loggers[] = "D:awaiting";

                                        $order->update_status('awaiting-vbank', __( '가상계좌 입금대기 중', 'iamport-for-woocommerce' ));
                                        $order->set_payment_method( $gateway );
                                        $order->save();

                                        $wpdb->query("COMMIT");

                                        do_action('iamport_order_status_changed', $synced_row->post_status, $order->get_status(), $order);
                                    } else {
                                        $loggers[] = "D:status(".$synced_row->post_status.")";

                                        $wpdb->query("ROLLBACK");
                                    }

                                    $called_from_iamport ? exit('Awaiting Vbank') : wp_redirect( $this->get_return_url($order) );
                                    return;
                                } else {
                                    $loggers[] = "C:invalid";

                                    $order->add_order_note( __( '실제 결제가 이루어지지 않았습니다.', 'iamport-for-woocommerce' ) );
                                    wc_add_notice( __('실제 결제가 이루어지지 않았습니다.', 'iamport-for-woocommerce' ), 'error');

                                    $wpdb->query("COMMIT");
                                }
                            } catch(Exception $e) {
                                $loggers[] = "C:".$e->getMessage();

                                $wpdb->query("ROLLBACK");
                            }
                        } else if ( $payment_data->status == 'failed' ) {
                            $loggers[] = "B:failed";

                            $order = new WC_Order( $order_id );

                            $failMessage = $payment_data->fail_reason;
                            if ( empty($failMessage) )	$failMessage = __( '결제요청 승인에 실패하였습니다.', 'iamport-for-woocommerce' );

                            $order->add_order_note( $failMessage );
                            wc_add_notice( $failMessage, 'error' );
                        } else if ( $payment_data->status == 'cancelled' ) {
                            //아임포트 관리자 페이지에서 취소하여 Notification이 발송된 경우도 대응
                            $loggers[] = "B:cancelled";

                            try {
                                $wpdb->query("BEGIN");
                                //lock the row
                                $synced_row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}posts WHERE ID = {$order_id} FOR UPDATE");

                                $order = new WC_Order( $order_id ); //lock잡은 후 호출(2017-01-16 : 의미없음. [1.6.8] synced_row의 값을 활용해서 status체크해야 함)

                                if ( !$this->has_status($synced_row->post_status, array('cancelled', 'refunded')) ) {
                                    $amountLeft = $payment_data->amount > $payment_data->cancel_amount; //취소할 잔액이 남음

                                    if ( $amountLeft ) { //한 번 더 환불이 가능함. 다음 번 환불이 가능하도록 status는 바꾸지 않음
                                        $len = count($payment_data->cancel_history); // always > 0
                                        $increment = $len - count($order->get_refunds());

                                        for ($i=0; $i < $increment; $i++) {
                                            $cancelItem = $payment_data->cancel_history[$len-$increment+$i];

                                            // 취소내역을 만들어줌 (부분취소도 대응가능)
                                            $refund = wc_create_refund( array(
                                                'amount'     => $cancelItem->amount,
                                                'reason'     => $cancelItem->reason,
                                                'order_id'   => $order_id
                                            ) );

                                            if ( is_wp_error( $refund ) ) {
                                                $order->add_order_note( $refund->get_error_message() );
                                            } else {
                                                $order->add_order_note( sprintf(__( '부분환불(%s원) 내역을 환불정보에 반영하였습니다.', 'iamport-for-woocommerce' ), number_format($cancelItem->amount)) );
                                            }
                                        }
                                    } else {
                                        $order->update_status( 'refunded' ); //imp_uid
                                        $order->add_order_note( __( '전액환불되어 우커머스 주문 상태를 "환불됨"으로 수정합니다.', 'iamport-for-woocommerce' ));

                                        //fire hook
                                        do_action('iamport_order_status_changed', $synced_row->post_status, $order->get_status(), $order);
                                    }

                                    $wpdb->query("COMMIT");

                                    do_action('iamport_order_status_changed', $synced_row->post_status, $order->get_status(), $order);
                                } else {
                                    $wpdb->query("ROLLBACK");
                                }

                                $called_from_iamport ? exit('Refund Information Saved') : wp_redirect( $this->get_return_url($order) );
                                return;
                            } catch(Exception $e) {
                                $loggers[] = "C:".$e->getMessage();

                                $wpdb->query("ROLLBACK");
                            }
                        }
                    } else { // not result->success
                        $loggers[] = "A:fail - " . $result->error['message'];

                        if ( !empty($http_param['order_id']) ) {
                            $order = new WC_Order( $http_param['order_id'] );

                            // [v2.0.51] 운영 중인 워드프레스 서버의 문제로 HTTP통신을 요청하지 못한 상황일 수도 있는데, 굳이 failed 상태로 변경할 필요는 없을 듯
                            // $old_status = $order->get_status();
                            // $order->update_status('failed');
                            $order->add_order_note( sprintf(__( '결제승인정보를 받아오지 못했습니다. 관리자에게 문의해주세요. %s', 'iamport-for-woocommerce' ), $result->error['message']) );

                            //fire hook
                            // do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);
                        }
                        wc_add_notice($result->error['message'], 'error');
                    }

                    if ( !empty($order) ) {
                        $default_redirect_url = $order->get_checkout_payment_url( true );
                    } else {
                        $default_redirect_url = '/';
                    }

                    $called_from_iamport ? exit( json_encode(array("version"=>"IamportForWoocommerce 2.2.17", "log"=>$loggers)) ) : wp_redirect( $default_redirect_url );
                } else {
                    //just test(아임포트가 지원하는대로 호출되지 않음)
                    exit( json_encode(array("version"=>"IamportForWoocommerce 2.2.17")) );
                }
            }

            //common for payment
            public function process_payment( $order_id ) {
                global $woocommerce;
                $order = new WC_Order( $order_id );

                if ( $order->has_status(wc_get_is_paid_statuses()) ) {
                    $redirect_url = $this->get_return_url($order);
    				//$redirect_url = $order->get_checkout_order_received_url();
                } else {
                    $redirect_url = $order->get_checkout_payment_url( false );
                }

                try {
                    $iamport_info = $this->iamport_payment_info( $order_id );

                    return array(
                        'result' => 'success',
                        'redirect'	=> $redirect_url,
                        'order_id' => $order_id,
                        'order_key' => $order->get_order_key(),
                        'iamport' => $iamport_info
                    );
                } catch (Exception $e) {
                    wc_add_notice( $e->getMessage(), 'error' );

                    return array(
                        'result' => 'fail',
                        'messages' => $e->getMessage(),
                    );
                }
            }

            //common for refund
            public function process_refund($order_id, $amount = null, $reason = '') {
                require_once(dirname(__FILE__).'/lib/iamport.php');

                global $woocommerce;
                $order = new WC_Order( $order_id );

                $imp_uid = $order->get_transaction_id();
                $iamport = new WooIamport($this->imp_rest_key, $this->imp_rest_secret);

                // 만약 데이터 동기화에 실패하는 상황이 되어 imp_uid가 없더라도 order_key가 있으면 취소를 시도해볼 수 있다.
                if ( empty($imp_uid) ) {
                    $cancel_data = array(
                        'merchant_uid'=>$order->get_order_key(),
                        'reason'=>$reason,
                        'amount'=>$amount
                    );
                } else {
                    $cancel_data = array(
                        'imp_uid'=>$imp_uid,
                        'reason'=>$reason,
                        'amount'=>$amount
                    );
                }

                $refundTaxFree = intval($_POST['iamport_refund_taxfree']);
                if ($refundTaxFree > 0) {
                    $cancel_data['tax_free'] = $refundTaxFree;
                }

                $result = $iamport->cancel($cancel_data);

                if ( $result->success ) {
                    $payment_data = $result->data;
                    $order->add_order_note( sprintf(__( '%s 원 환불완료', 'iamport-for-woocommerce'), number_format($amount)) );
                    if ( $payment_data->amount == $payment_data->cancel_amount ) {
                        $old_status = $order->get_status();
                        $order->update_status('refunded');

                        //fire hook
                        do_action('iamport_order_status_changed', $old_status, $order->get_status(), $order);
                    }
                    return true;
                } else {
                    $order->add_order_note($result->error['message']);
                    return false;
                }

                return false;
            }

            public function enqueue_iamport_script() {
                wp_register_script( 'woocommerce_iamport_script', 'https://cdn.iamport.kr/js/iamport.payment-1.1.7.js', array('jquery'), '20190812' );
                wp_register_script( 'iamport_jquery_url', plugins_url( '/assets/js/url.min.js',plugin_basename(__FILE__) ), array(), '20190918');
                wp_register_script( 'iamport_script_for_woocommerce', plugins_url( '/assets/js/iamport.woocommerce.js',plugin_basename(__FILE__) ), array('jquery', 'iamport_jquery_url'), '20200925');
                wp_register_script( 'samsung_runnable', 'https://d3sfvyfh4b9elq.cloudfront.net/pmt/web/device.json' );
                wp_enqueue_script('woocommerce_iamport_script');
                wp_enqueue_script('iamport_jquery_url');
                wp_enqueue_script('iamport_script_for_woocommerce');
                wp_enqueue_script( 'samsung_runnable' );
            }

            public function iamport_payment_info( $order_id ) {
                global $woocommerce;

                $order = new WC_Order( $order_id );
                $order_name = $this->get_order_name($order);
                $redirect_url = add_query_arg( array('order_id'=>$order_id, 'wc-api'=>get_class( $this )), $order->get_checkout_payment_url());
                $notice_url = IamportHelper::get_notice_url();
                $maxCardQuota = IamportHelper::getMaxCardQuota();

                $stripLineFeedInAddress = get_option('woocommerce_iamport_strip_line_feed_in_address', 'yes') != 'no'; //strip 이 기본
                $buyerAddr = $order->get_formatted_shipping_address();
                if ($stripLineFeedInAddress) {
                    $buyerAddr = strip_tags($buyerAddr); //br 태그 모두 제거
                } else {
                    $buyerAddr = str_replace('<br/>', "\n", $buyerAddr);
                }

                $response = array(
                    'user_code' => $this->imp_user_code,
                    'name' => $order_name,
                    'merchant_uid' => $order->get_order_key(),
                    'amount' => $order->get_total(), //amount
                    'buyer_name' => trim($order->get_billing_last_name() . $order->get_billing_first_name()), //name
                    'buyer_email' => $order->get_billing_email(), //email
                    'buyer_tel' => $order->get_billing_phone() ? $order->get_billing_phone():'010-1234-5678', //tel. KG이니시스 오류 방지. 다른 플러그인을 통해 전화번호 required해제한 경우가 있음
                    'buyer_addr' => $buyerAddr, //address
                    'buyer_postcode' => $order->get_shipping_postcode(),
                    // 'vbank_due' => date('Ymd', strtotime("+1 day")),
                    'm_redirect_url' => $redirect_url,
                    'currency' => $order->get_currency(),
                );

                if ( empty($response["buyer_name"]) )	$response["buyer_name"] = $this->get_default_user_name();
                if ( $notice_url )	$response['notice_url'] = $notice_url;

                if ( wc_tax_enabled() ) {
                    $tax_free_amount = IamportHelper::get_tax_free_amount($order);
                    $response["tax_free"] = intval($tax_free_amount);
                    // $vat = $order->get_total_tax();
                    // $response['vat'] = intval($vat);
                }

                $language = $this->paymentLanguage();
                if ( $language ) {
                    $response['language'] = $language;
                }

                //[2019-08-08] 최대 할부개월수 제한
                if ($maxCardQuota > 0) {
                    $response['card_quota'] = range(1, $maxCardQuota);
                }

                //서비스 제공기간
                foreach ($order->get_items() as $it) {
                    $product = $it->get_product();

                    if ($product instanceof WC_Product) { //$product 가 boolean 인 경우가 있어 타입 체크
                        $product_id = $product->get_id();
                        $parent_id  = $product->get_parent_id();
                        if ( !empty($parent_id) )  $product_id = $parent_id;

                        $service_period = $this->getServicePeriod($product_id);
                        if (!empty($service_period)) { //유효한 period 정보가 하나라도 있으면, 먼저 걸리는 것을 적용(한 개만 적용 가능하므로)
                            $response['period'] = $service_period;
                        }
                    }
                }

                return $response;
            }

            protected function get_default_user_name() {
                $current_user = wp_get_current_user();

                if ( $current_user->ID > 0 ) {
                    $name = $current_user->user_lastname . $current_user->user_firstname;
                    if ( !empty($name) )	return $name;

                    $name = $current_user->display_name;
                    if ( !empty($name) )	return $name;

                    $name = $current_user->user_login;
                    if ( !empty($name) )	return $name;
                }

                return "구매자";
            }

            protected function get_order_name($order) {
                $order_name = "#" . $order->get_order_number() . "번 주문";

                $cart_items = $order->get_items();
                $cnt = count($cart_items);

                if (!empty($cart_items)) {
                    $index = 0;
                    foreach ($cart_items as $item) {
                        if ( $index == 0 ) {
                            $order_name = $item->get_name();
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

            protected function _iamport_post_meta($order_id, $meta_key, $meta_value) {
                if ( !add_post_meta($order_id, $meta_key, $meta_value, true) ) {
                    update_post_meta($order_id, $meta_key, $meta_value);
                }

                do_action('iamport_order_meta_saved', $order_id, $meta_key, $meta_value);
            }

            protected function has_status($current_status, $status) {
                $formed_status = $this->format_status($current_status);
                return apply_filters( 'woocommerce_order_has_status', ( is_array( $status ) && in_array( $formed_status, $status ) ) || $formed_status === $status ? true : false, null, $status );
            }

            protected function format_status($raw_status) {
                return apply_filters( 'woocommerce_order_get_status', 'wc-' === substr( $raw_status, 0, 3 ) ? substr( $raw_status, 3 ) : $raw_status, null );
            }

            protected function http_param($name, $default_method) {
                if ( $default_method == 'GET' ) {
                    if ( isset($_GET[ $name ]) )	return $_GET[ $name ];
                } else if ( $default_method == 'POST' ) {
                    //bugfix-2016-08-03 : 아임포트 Notification URL에서 application/x-form-www-urlencoded 와 application/json 중 Content-Type을 선택적으로 지정하여 노티할 수 있음
                    if ( $_SERVER["CONTENT_TYPE"] === 'application/json' ) {
                        $data = json_decode( file_get_contents('php://input'), true );

                        if ( isset($data[ $name ]) )	return $data[ $name ];
                    } else {
                        if ( isset($_POST[ $name ]) )	return $_POST[ $name ];
                        if ( isset($_GET[ $name ]) )	return $_GET[ $name ];
                    }
                }

                return null;
            }

            protected function paymentLanguage() {
                $locale = get_locale();
                if ( $locale !== 'ko_KR' )	return 'en';

                return null;
            }

            protected function getRestInfo($merchant_uid, $called_from_iamport=true) {
                if ( $called_from_iamport ) {
                    $order_id = wc_get_order_id_by_order_key( $merchant_uid );
                    $gateway = wc_get_payment_gateway_by_order($order_id);

                    if ( $gateway ) {
                        return array(
                            'imp_rest_key' => $gateway->imp_rest_key,
                            'imp_rest_secret' => $gateway->imp_rest_secret,
                        );
                    }

                    // $pay_method = get_post_meta($order_id, '_iamport_paymethod', true);
                    // $pg_provider = get_post_meta($order_id, '_iamport_provider', true);

                    // $gateway = find_gateway($pg_provider, $pay_method);
                    // if ( $gateway ) {
                    // 	return array(
                    // 		'imp_rest_key' => $gateway->imp_rest_key,
                    // 		'imp_rest_secret' => $gateway->imp_rest_secret,
                    // 	);
                    // }
                }

                return array(
                    'imp_rest_key' => $this->imp_rest_key,
                    'imp_rest_secret' => $this->imp_rest_secret,
                );
            }

            protected function isMobile() {
                $userAgent = $_SERVER['HTTP_USER_AGENT'];

                $mobiles = array(
                    'Android', 'AvantGo', 'BlackBerry', 'DoCoMo', 'Fennec', 'iPod', 'iPhone', 'iPad',
                    'J2ME', 'MIDP', 'NetFront', 'Nokia', 'Opera Mini', 'Opera Mobi', 'PalmOS', 'PalmSource',
                    'portalmmm', 'Plucker', 'ReqwirelessWeb', 'SonyEricsson', 'Symbian', 'UP\\.Browser',
                    'webOS', 'Windows CE', 'Windows Phone OS', 'Xiino'
                );

                $pattern = '/' . implode('|', $mobiles) . '/i';
                return (bool)preg_match($pattern, $userAgent);
            }

            protected function getServicePeriod($productId)
            {
                $keys = array('iamport_product_service_period_from', 'iamport_product_service_period_to', 'iamport_product_service_period_interval');
                $period = array();

                foreach ($keys as $k) {
                    $val = get_post_meta($productId, $k, true);

                    if (!empty($val)) {
                        $period[ str_replace('iamport_product_service_period_', '', $k) ] = $val;
                    }
                }

                return $period;
            }

        }

        require_once('iamport-naverpay.php');
        require_once('iamport-naverpay-ext.php');
        require_once('iamport-card.php');
        require_once('iamport-trans.php');
        require_once('iamport-vbank.php');
        require_once('iamport-phone.php');
        require_once('iamport-kakao.php');
        require_once('iamport-kpay.php');
        require_once('iamport-samsung.php');
        require_once('iamport-payco.php');
        require_once('iamport-foreign.php');
        require_once('iamport-eximbay.php');
        require_once('includes/IamportStatusButton.php');
        require_once('includes/IamportSettingTab.php');
        require_once('iamport-subscription.php');// KEY-IN결제에 사용될 수 있음
        require_once('iamport-subscription-ex.php');// PG사 결제창을 통한 빌링키 발급 방식의 정기결제
        require_once('iamport-smilepay.php');
        require_once('iamport-alipay.php');
        require_once('iamport-paypal.php');

        require_once('lib/IamportHelper.php');

        new WC_Tools_Iamport_Status_Button();

        /**
         * Localisation
         */
        load_plugin_textdomain('iamport-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages');

        hook_common_actions();
    }
}

/**
* Add the Gateway to WooCommerce
**/
if(!function_exists('woocommerce_add_gateway_iamport_gateway')){
    function woocommerce_add_gateway_iamport_gateway($methods) {
        $iamport_gateways = array(
            'WC_Gateway_Iamport_NaverPay',
            'WC_Gateway_Iamport_Card',
            'WC_Gateway_Iamport_Samsung',
            'WC_Gateway_Iamport_Trans',
            'WC_Gateway_Iamport_Vbank',
            'WC_Gateway_Iamport_Phone',
            'WC_Gateway_Iamport_Kakao',
            'WC_Gateway_Iamport_Smilepay',
            'WC_Gateway_Iamport_Payco',
            'WC_Gateway_Iamport_Kpay',
            'WC_Gateway_Iamport_Subscription',// KEY-IN결제에 사용될 수 있음
            'WC_Gateway_Iamport_Subscription_Ex',// PG사 결제창을 통한 빌링키 발급 방식의 정기결제
            'WC_Gateway_Iamport_Paypal',
            'WC_Gateway_Iamport_Foreign',
            'WC_Gateway_Iamport_Alipay',
            'WC_Gateway_Iamport_Eximbay',
            'WC_Gateway_Iamport_NaverPayExt',
        );

        $methods = array_merge($methods, $iamport_gateways);
        return $methods;
    }
}
