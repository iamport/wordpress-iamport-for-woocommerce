<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'IMP_Email_Customer_Vbank_Processing_Order' ) ) :

/**
 * 가상계좌 입금 확인처리가 되었을 때 구매자에게 이메일을 발송합니다.
 *
 * @class       IMP_Email_Customer_Vbank_Processing_Order
 * @version     1.3.4
 * @author      SIOT
 * @extends     WC_Email
 */
class IMP_Email_Customer_Vbank_Processing_Order extends WC_Email {

	/**
	 * Constructor
	 */
	function __construct() {

		$this->id               = 'customer_vbank_processing_order';
		$this->title            = '가상계좌 입금완료(구매자통지)';
		$this->description      = '가상계좌 입금이 이루어졌을 때 고객에게 자동으로 발송되는 이메일 알림입니다.';

		$this->heading          = '입금이 확인되었습니다.';
		$this->subject          = '고객님의 {order_date} {site_title} 주문에 대한 입금이 확인되었습니다.';

		$this->template_html    = 'emails/customer-processing-order.php'; //우커머스 내부의 이메일 그대로 사용
		$this->template_plain   = 'emails/plain/customer-processing-order.php'; //우커머스 내부의 이메일 그대로 사용

		// Triggers for this email
		add_action( 'woocommerce_order_status_awaiting-vbank_to_processing_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_awaiting-vbank_to_completed_notification', array( $this, 'trigger' ) );

		// Call parent constructor
		parent::__construct();
	}

	/**
	 * Trigger.
	 */
	function trigger( $order_id ) {

		if ( $order_id ) {
			$this->object       = wc_get_order( $order_id );
			$this->recipient    = $this->object->billing_email;

			$this->find['order-date']      = '{order_date}';
			$this->find['order-number']    = '{order_number}';

			$this->replace['order-date']   = date_i18n( wc_date_format(), strtotime( $this->object->order_date ) );
			$this->replace['order-number'] = $this->object->get_order_number();
		}

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * get_content_html function.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_html() {
		ob_start();
		wc_get_template( $this->template_html, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => false
		) );
		return ob_get_clean();
	}

	/**
	 * get_content_plain function.
	 *
	 * @access public
	 * @return string
	 */
	function get_content_plain() {
		ob_start();
		wc_get_template( $this->template_plain, array(
			'order'         => $this->object,
			'email_heading' => $this->get_heading(),
			'sent_to_admin' => false,
			'plain_text'    => true
		) );
		return ob_get_clean();
	}
}

endif;