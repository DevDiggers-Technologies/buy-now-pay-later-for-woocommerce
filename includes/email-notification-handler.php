<?php
/**
 * PostPaid email notification.
 *
 * One WooCommerce email carries every PostPaid message (purchases, repayments, refunds,
 * application updates, reminders, overdue notices), so store owners edit one template and
 * can switch the whole set on or off from WooCommerce > Settings > Emails.
 *
 * @package WooCommerce PostPaid
 * @version 2.0.0
 */

namespace DDWCPostPaid\Includes;

defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'DDWCPP_Email_Notification_Handler' ) ) {
	/**
	 * Email Notification Handler class.
	 */
	class DDWCPP_Email_Notification_Handler extends \WC_Email {
		/**
		 * Lines of the current message.
		 *
		 * @var array
		 */
		public $email_message = [];

		/**
		 * Heading for the current message.
		 *
		 * @var string
		 */
		public $message_heading = '';

		/**
		 * Subject for the current message.
		 *
		 * @var string
		 */
		public $message_subject = '';

		/**
		 * Greeting name for the current message.
		 *
		 * @var string
		 */
		public $message_name = '';

		/**
		 * Constructor.
		 */
		public function __construct() {
			$this->id             = 'ddwcpp_notification';
			$this->customer_email = true;
			$this->title          = esc_html__( 'Pay Later Notification', 'buy-now-pay-later-for-woocommerce' );
			$this->description    = esc_html__( 'Sent for Pay Later purchases, repayments, refunds, application updates, payment reminders and overdue notices.', 'buy-now-pay-later-for-woocommerce' );
			$this->template_html  = 'emails/postpaid.php';
			$this->template_plain = 'emails/plain/postpaid.php';
			$this->template_base  = DDWCPP_PLUGIN_FILE . 'templates/';
			$this->placeholders   = [ '{site_title}' => $this->get_blogname() ];

			add_action( 'ddwcpp_mail_notification', [ $this, 'trigger' ] );

			parent::__construct();
		}

		/**
		 * Default subject.
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return esc_html__( '[{site_title}] Pay Later account update', 'buy-now-pay-later-for-woocommerce' );
		}

		/**
		 * Default heading.
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return esc_html__( 'Pay Later account update', 'buy-now-pay-later-for-woocommerce' );
		}

		/**
		 * Default additional content.
		 *
		 * @return string
		 */
		public function get_default_additional_content() {
			return ''; // ponytail: each message carries its own sign-off.
		}

		/**
		 * Heading: the message's own heading wins over the configured one.
		 *
		 * @return string
		 */
		public function get_heading() {
			return $this->message_heading ? $this->message_heading : parent::get_heading();
		}

		/**
		 * Subject: the event's own subject wins over the configured one.
		 *
		 * @return string
		 */
		public function get_subject() {
			return $this->message_subject ? $this->message_subject : parent::get_subject();
		}

		/**
		 * Trigger.
		 *
		 * @param array $data { email, name, subject, heading, message }; message is the finished HTML.
		 * @return void
		 */
		public function trigger( $data ) {
			if ( empty( $data['email'] ) || empty( $data['message'] ) ) {
				return;
			}

			$this->setup_locale();

			$this->recipient       = $data['email'];
			$this->email_message   = (array) $data['message'];
			$this->message_heading = ! empty( $data['heading'] ) ? $data['heading'] : '';
			$this->message_subject = ! empty( $data['subject'] ) ? $data['subject'] : '';
			$this->message_name    = isset( $data['name'] ) ? $data['name'] : '';

			$user         = get_user_by( 'email', $data['email'] );
			$this->object = $user ? $user : null;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * Template arguments.
		 *
		 * @param bool $plain_text Plain text.
		 * @return array
		 */
		protected function ddwcpp_get_template_args( $plain_text ) {
			return [
				'email_heading'      => $this->get_heading(),
				'email_message'      => $this->email_message,
				'customer_name'      => $this->message_name,
				'additional_content' => $this->get_additional_content(),
				'blogname'           => $this->get_blogname(),
				'sent_to_admin'      => false,
				'plain_text'         => $plain_text,
				'email'              => $this,
			];
		}

		/**
		 * Get content html.
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html( $this->template_html, $this->ddwcpp_get_template_args( false ), '', $this->template_base );
		}

		/**
		 * Get content plain.
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html( $this->template_plain, $this->ddwcpp_get_template_args( true ), '', $this->template_base );
		}
	}
}
