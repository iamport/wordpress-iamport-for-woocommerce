<?php
class WC_Tools_Iamport_Status_Button {

	public function __construct() {
		add_filter( 'woocommerce_debug_tools', array( $this,'debug_button' ) );
	}

	public function debug_button( $tools ) {
		// $tools[] = array(
		// 	'iamport_status_button' => array(
		// 		'name'		=> __( '아임포트 서버와 통신체크', 'iamport-for-woocommerce' ),
		// 		'button'	=> __( '통신 시작', 'iamport-for-woocommerce' ),
		// 		'desc'		=> __( '고객의 결제가 완료되었을 때, 아임포트 서버로부터 결제정보를 정상적으로 받아올 수 있는지 서버 환경에 대한 점검을 진행합니다.', 'iamport-for-woocommerce' ),
		// 		'callback'	=> array( $this, 'debug_button_action' ),
		// 	),
		// );

		$tools['iamport_status_button'] = array(
			'name'		=> __( '아임포트 서버와 통신체크', 'iamport-for-woocommerce' ),
			'button'	=> __( '통신 시작', 'iamport-for-woocommerce' ),
			'desc'		=> __( '고객의 결제가 완료되었을 때, 아임포트 서버로부터 결제정보를 정상적으로 받아올 수 있는지 서버 환경에 대한 점검을 진행합니다.', 'iamport-for-woocommerce' ),
			'callback'	=> array( $this, 'debug_button_action' ),
		);

		return $tools;
	}

	public function debug_button_action() {

		if ( !function_exists('curl_version') )	{
			echo '통신에 필요한 PHP Curl 모듈이 설치되어있지 않습니다.';
			return false;
		} else {
			$curl_info = curl_version();
			echo "<pre>[DEBUG] PHP Curl 버전 확인\n";
			foreach ($curl_info as $key => $val) {
				echo "* ", $key, " : ", print_r($val, true), "\n";
			}
			echo "</pre>";
		}

		//데모계정으로 Token 정상적으로 받아오는지 체크
		$post_data = array(
			'imp_key' => 'imp_apikey',
			'imp_secret' => 'ekKoeW8RyKuT0zgaZsUtXXTLQ4AhPFW3ZGseDA6bkA5lamv9OqDMnxyeB9wqOsuO9W3Mx9YSJ4dTqJ3f'
		);
		$post_data_str = json_encode($post_data);
		$default_header = array('Content-Type: application/json', 'Content-Length: ' . strlen($post_data_str));
		$verbose = fopen('php://temp', 'w+');

		$ch = curl_init();
		if ( defined('WP_PROXY_HOST') ) {
			curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
			curl_setopt($ch, CURLOPT_PROXY, WP_PROXY_HOST);
			curl_setopt($ch, CURLOPT_PROXYPORT, WP_PROXY_PORT);
		}
		curl_setopt($ch, CURLOPT_URL, 'https://api.iamport.kr/users/getToken');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $default_header);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_str);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_STDERR, $verbose);

		//execute post
		$body = curl_exec($ch);
		$error_code = curl_errno($ch);

		rewind($verbose);
		$verboseLog = stream_get_contents($verbose);
		echo "<pre>[DEBUG] SSL Connection 로그 출력", htmlspecialchars($verboseLog), "</pre>\n";

		if ( $error_code !== 0 ) {
			echo "[ERROR] Curl통신 과정에 문제가 있습니다";
			return false;
		} else {
			echo "<br>========= 통신 과정에 문제 없음 =========<br>";
		}
	}

}