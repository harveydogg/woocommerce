<?php
/**
 * Checkout Shortcode
 *
 * Used on the checkout page, the checkout shortcode displays the checkout process.
 *
 * @author 		WooThemes
 * @category 	Shortcodes
 * @package 	WooCommerce/Shortcodes/Checkout
 * @version     2.0.0
 */
class WC_Shortcode_Checkout {

	/**
	 * Get the shortcode content.
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function get( $atts ) {
		return WC_Shortcodes::shortcode_wrapper( array( __CLASS__, 'output' ), $atts );
	}

	/**
	 * Output the shortcode.
	 *
	 * @param array $atts
	 */
	public static function output( $atts ) {
		global $wp;

		// Check cart class is loaded or abort
		if ( is_null( WC()->cart ) ) {
			return;
		}

		// Backwards compat with old pay and thanks link arguments
		if ( isset( $_GET['order'] ) && isset( $_GET['key'] ) ) {
			wc_deprecated_argument( __CLASS__ . '->' . __FUNCTION__, '2.1', '"order" is no longer used to pass an order ID. Use the order-pay or order-received endpoint instead.' );

			// Get the order to work out what we are showing
			$order_id = absint( $_GET['order'] );
			$order    = wc_get_order( $order_id );

			if ( $order && $order->has_status( 'pending' ) ) {
				$wp->query_vars['order-pay'] = absint( $_GET['order'] );
			} else {
				$wp->query_vars['order-received'] = absint( $_GET['order'] );
			}
		}

		// Handle checkout actions
		if ( ! empty( $wp->query_vars['order-pay'] ) ) {

			self::order_pay( $wp->query_vars['order-pay'] );

		} elseif ( isset( $wp->query_vars['order-received'] ) ) {

			self::order_received( $wp->query_vars['order-received'] );

		} else {

			self::checkout();

		}
	}

	/**
	 * Show the pay page.
	 *
	 * @param int $order_id
	 */
	private static function order_pay( $order_id ) {

		do_action( 'before_woocommerce_pay' );

		wc_print_notices();

		$order_id = absint( $order_id );

		// Handle payment
		if ( isset( $_GET['pay_for_order'], $_GET['key'] ) && $order_id ) {

			// Pay for existing order
			$order_key = $_GET['key'];
			$order     = wc_get_order( $order_id );

			// Order or payment link is invalid.
			if ( ! $order || $order->get_id() !== $order_id || $order->get_order_key() !== $order_key ) {
				wc_add_notice( __( 'Sorry, this order is invalid and cannot be paid for.', 'woocommerce' ), 'error' );

			// Logged out customer does not have permission to pay for this order.
			} elseif ( ! current_user_can( 'pay_for_order', $order_id ) && ! is_user_logged_in() ) {
				echo '<div class="woocommerce-info">' . __( 'Please login to your account below to continue to the payment form.', 'woocommerce' ) . '</div>';
				woocommerce_login_form( array(
					'redirect' => $order->get_checkout_payment_url(),
				) );
				return;

			// Logged in customer trying to pay for someone else's order.
			} elseif ( ! current_user_can( 'pay_for_order', $order_id ) ) {
				wc_add_notice( __( 'This order cannot be paid for. Please contact us if you need assistance.', 'woocommerce' ), 'error' );

			// Order does not need to be paid.
			} elseif ( ! $order->needs_payment() ) {
				wc_add_notice( sprintf( __( 'This order&rsquo;s status is &ldquo;%s&rdquo;&mdash;it cannot be paid for. Please contact us if you need assistance.', 'woocommerce' ), wc_get_order_status_name( $order->get_status() ) ), 'error' );

			// Show payment form.
			} else {

				WC()->customer->set_props( array(
					'billing_country'  => $order->get_billing_country() ? $order->get_billing_country()   : null,
					'billing_state'    => $order->get_billing_state() ? $order->get_billing_state()       : null,
					'billing_postcode' => $order->get_billing_postcode() ? $order->get_billing_postcode() : null,
				) );
				WC()->customer->save();

				$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

				if ( sizeof( $available_gateways ) ) {
					current( $available_gateways )->set_current();
				}

				wc_get_template( 'checkout/form-pay.php', array(
					'order'              => $order,
					'available_gateways' => $available_gateways,
					'order_button_text'  => apply_filters( 'woocommerce_pay_order_button_text', __( 'Pay for order', 'woocommerce' ) ),
				) );
			}
		} elseif ( $order_id ) {

			// Pay for order after checkout step
			$order_key            = isset( $_GET['key'] ) ? wc_clean( $_GET['key'] ) : '';
			$order                = wc_get_order( $order_id );

			if ( $order && $order->get_id() === $order_id && $order->get_order_key() === $order_key ) {

				if ( $order->needs_payment() ) {

					?>
					<ul class="order_details">
						<li class="order">
							<?php _e( 'Order number:', 'woocommerce' ); ?>
							<strong><?php echo $order->get_order_number(); ?></strong>
						</li>
						<li class="date">
							<?php _e( 'Date:', 'woocommerce' ); ?>
							<strong><?php echo wc_format_datetime( $order->get_date_created() ); ?></strong>
						</li>
						<li class="total">
							<?php _e( 'Total:', 'woocommerce' ); ?>
							<strong><?php echo $order->get_formatted_order_total(); ?></strong>
						</li>
						<?php if ( $order->get_payment_method_title() ) : ?>
						<li class="method">
							<?php _e( 'Payment method:', 'woocommerce' ); ?>
							<strong><?php
								echo wp_kses_post( $order->get_payment_method_title() );
							?></strong>
						</li>
						<?php endif; ?>
					</ul>

					<?php do_action( 'woocommerce_receipt_' . $order->get_payment_method(), $order_id ); ?>

					<div class="clear"></div>
					<?php

				} else {
					wc_add_notice( sprintf( __( 'This order&rsquo;s status is &ldquo;%s&rdquo;&mdash;it cannot be paid for. Please contact us if you need assistance.', 'woocommerce' ), wc_get_order_status_name( $order->get_status() ) ), 'error' );
				}
			} else {
				wc_add_notice( __( 'Sorry, this order is invalid and cannot be paid for.', 'woocommerce' ), 'error' );
			}
		} else {
			wc_add_notice( __( 'Invalid order.', 'woocommerce' ), 'error' );
		}

		wc_print_notices();

		do_action( 'after_woocommerce_pay' );
	}

	/**
	 * Show the thanks page.
	 *
	 * @param int $order_id
	 */
	private static function order_received( $order_id = 0 ) {

		wc_print_notices();

		$order = false;

		// Get the order
		$order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $order_id ) );
		$order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( $_GET['key'] ) );

		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || $order->get_order_key() !== $order_key ) {
				$order = false;
			}
		}

		// Empty awaiting payment session
		unset( WC()->session->order_awaiting_payment );

		// Empty current cart
		wc_empty_cart();

		wc_get_template( 'checkout/thankyou.php', array( 'order' => $order ) );
	}

	/**
	 * Show the checkout.
	 */
	private static function checkout() {

		// Show non-cart errors
		wc_print_notices();

		// Check cart has contents
		if ( WC()->cart->is_empty() ) {
			return;
		}

		// Check cart contents for errors
		do_action( 'woocommerce_check_cart_items' );

		// Calc totals
		WC()->cart->calculate_totals();

		// Get checkout object
		$checkout = WC()->checkout();

		if ( empty( $_POST ) && wc_notice_count( 'error' ) > 0 ) {

			wc_get_template( 'checkout/cart-errors.php', array( 'checkout' => $checkout ) );

		} else {

			$non_js_checkout = ! empty( $_POST['woocommerce_checkout_update_totals'] ) ? true : false;

			if ( wc_notice_count( 'error' ) == 0 && $non_js_checkout ) {
				wc_add_notice( __( 'The order totals have been updated. Please confirm your order by pressing the "Place order" button at the bottom of the page.', 'woocommerce' ) );
			}

			wc_get_template( 'checkout/form-checkout.php', array( 'checkout' => $checkout ) );

		}
	}
}
