<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'IMP_Email_Admin_Vbank_Confirm_Order' ) ) :

/**
 * 가상계좌 입금 확인처리가 되었을 때 관리자에게 이메일을 발송합니다.
 *
 * An email sent to the admin when a new order is received/paid for.
 *
 * @class       IMP_Email_Admin_Vbank_Confirm_Order
 * @version     1.3.4
 * @author      SIOT
 * @extends     WC_Email
 */
class IMP_Email_Admin_Vbank_Confirm_Order extends WC_Email {

	/**
	 * Constructor
	 */
	function __construct() {

		$this->id               = 'vbank_confirm_order';
		$this->title            = '가상계좌 입금완료(관리자통지)';
		$this->description      = '가상계좌 입금확인이 이루어졌을 때 관리자에게 발송되는 이메일입니다.';

		$this->heading          = __( 'New customer order', 'woocommerce' );
		$this->subject          = __( '[{site_title}] New customer order ({order_number}) - {order_date}', 'woocommerce' );

		$this->template_html    = 'emails/admin-new-order.php';
		$this->template_plain   = 'emails/plain/admin-new-order.php';

		// Triggers for this email
		add_action( 'woocommerce_order_status_awaiting-vbank_to_processing_notification', array( $this, 'trigger' ) );
		add_action( 'woocommerce_order_status_awaiting-vbank_to_completed_notification', array( $this, 'trigger' ) );

		// Call parent constructor
		parent::__construct();

		// Other settings
		$this->recipient = $this->get_option( 'recipient' );

		if ( ! $this->recipient )
			$this->recipient = get_option( 'admin_email' );
	}

	/**
	 * Trigger.
	 */
	function trigger( $order_id ) {

		if ( $order_id ) {
			$this->object       = wc_get_order( $order_id );

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
			'sent_to_admin' => true,
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
			'sent_to_admin' => true,
			'plain_text'    => true
		) );
		return ob_get_clean();
	}

	/**
	 * Initialise settings form fields
	 */
	function init_form_fields() {
		// 2.3.4부터 제공되는 get_email_type_options() 에 대한 의존성 제거. woocommerce version에 무관한 코드
		parent::init_form_fields();

		$new_form_fields = array();
		foreach ($this->form_fields as $key => $value) {
			$new_form_fields[$key] = $value;

			if ( $key === 'enabled' ) { //enabled뒤에 recipient집어넣음
				$new_form_fields['recipient'] = array(
					'title'         => __( 'Recipient(s)', 'woocommerce' ),
					'type'          => 'text',
					'description'   => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.', 'woocommerce' ), esc_attr( get_option('admin_email') ) ),
					'placeholder'   => '',
					'default'       => ''
				);
			}
		}

		$this->form_fields = $new_form_fields;
	}

}

endif;