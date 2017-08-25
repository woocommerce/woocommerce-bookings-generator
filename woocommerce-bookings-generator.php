<?php
/**
 * Plugin Name: Bookings Generator
 * Version: 1.0.0
 * Plugin URI: https://github.com/woocommerce/bookings-generator
 * Description: This extension is a WooCommerce Bookings helper which will generate a mass number of bookings for you.
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Requires at least: 4.7.0
 * Tested up to: 4.8.1
 *
 * @package WordPress
 * @author  WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bookings_Generator' ) ) {
	/**
	 * Main class.
	 *
	 * @package Bookings_Generator
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	class Bookings_Generator {
		public $notice;
		public static $self;

		/**
		 * Initialize.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public static function init() {
			self::$self = new self();

			add_action( 'admin_menu', array( self::$self, 'add_submenu_page' ) );
			add_action( 'init', array( self::$self, 'catch_requests' ), 20 );
			add_action( 'bookings-generator-continue', array( self::$self, 'generate_bookings' ) );
			add_filter( 'woocommerce_bookings_email_actions', array( self::$self, 'clear_email_actions' ) );
		}

		/**
		 * Returns the current class object.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public static function get_instance() {
			return self::$self;
		}

		/**
		 * Adds submenu page to tools.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public function add_submenu_page() {
			add_submenu_page( 'tools.php', 'Bookings Generator', 'Bookings Generator', 'manage_options', 'bookings-generator', array( $this, 'tool_page' ) );
		}

		/**
		 * Renders the tool page.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public function tool_page() {
			if ( ! empty( $this->notice ) ) {
				echo $this->notice;
			}

			$action_url = add_query_arg( array( 'page' => 'bookings-generator' ), admin_url( 'tools.php' ) );
			?>
			<div class="wrap">
				<h1>Bookings Generator</h1>
				<hr />
				<div>
					<h3>Mass generate bookings</h3>
					<p>This will generate a mass number of bookings in your store for testing purposes. It takes a single booking and then replicates it according to the settings.</p>
					
					<form action="<?php echo $action_url; ?>" method="post" style="margin-bottom:20px;padding:5px;">
						<table>
							<tr>
								<td>
									<label for="booking_id">Booking ID</label>
								</td>
								<td>
									<input type="number" name="booking_id" min="1" />
								</td>
							</tr>
							<tr>
								<td>
									<label for="count">Count</label>
								</td>
								<td>
									<input type="number" name="count" min="1" max="10000" value="1000" />
									How many bookings do you want to generate?
								</td>
							</tr>
							<tr>
								<td>
									<label for="timeout">Timeout</label>
								</td>
								<td>
									<input type="number" name="timeout" min="15" max="1000" value="30" />
									How many seconds before it should timeout and pause?
								</td>
							</tr>
							<tr>
								<td>
									<label for="interval">Interval</label>
								</td>
								<td>
									<input type="number" name="interval" min="1" max="100" value="1" />
									<label for="unit" style="display:none;">Unit</label>
									<select name="unit">
										<option value="minutes">Minute(s)</option>
										<option value="hours">Hour(s)</option>
										<option value="days" selected="selected">Day(s)</option>
										<option value="months">Month(s)</option>
									</select>
									Interval of created bookings.
								</td>
							</tr>
							<tr>
								<td>
									&nbsp;
								</td>
								<td>
									<input type="submit" class="button" value="Generate Bookings" /> Once submitted, please check debug.log for status.
									<input type="hidden" name="action" value="generate_bookings" />
									<?php wp_nonce_field( 'generate_bookings' ); ?>
								</td>
							</tr>
						</table>
					</form>
				</div>
			</div>
			<?php
		}

		/**
		 * Catches form requests.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public function catch_requests() {
			if ( ! isset( $_GET['page'] ) || 'bookings-generator' !== $_GET['page'] ) {
				return;
			}

			if ( ! isset( $_POST['action'] ) || ! isset( $_POST['_wpnonce'] ) ) {
				return;
			}

			if ( 'generate_bookings' !== $_POST['action'] ) {
				return;
			}

			if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'generate_bookings' ) ) {
				wp_die( 'Cheatin&#8217; huh?' );
			}

			switch ( $_POST['action'] ) {
				case 'generate_bookings':
					$this->generate_bookings();
					break;
			}
		}

		/**
		 * Makes the donuts.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 */
		public function generate_bookings( $transient = false ) {

			if ( $transient ) {

				// Get the transient, set vars.
				$data       = get_transient( $transient );
				$booking_id = isset( $data['booking_id'] ) ? absint( $data['booking_id'] ) : '';
				$count      = isset( $data['count'] )      ? absint( $data['count'] )      : 1000;
				$timeout    = isset( $data['timeout'] )    ? absint( $data['timeout'] )    : 30;
				$interval   = isset( $data['interval'] )   ? absint( $data['interval'] )   : 1;
				$unit       = isset( $data['unit'] )       ? $data['unit']                 : 'day';
				$iteration  = isset( $data['iteration'] )  ? $data['iteration']            : false;

				// Delete the transient, we don't need it.
				delete_transient( $transient );

			} else {
				// Set vars from post data. 
				$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : '';
				$count      = isset( $_POST['count'] )      ? absint( $_POST['count'] )      : 1000;
				$timeout    = isset( $_POST['timeout'] )    ? absint( $_POST['timeout'] )    : 30;
				$interval   = isset( $_POST['interval'] )   ? absint( $_POST['interval'] )   : 1;
				$unit       = isset( $_POST['unit'] )       ? $_POST['unit']                 : 'day';
				$iteration  = 1;
			}

			try {
				
				if ( false === $iteration ) {
					throw new Exception( 'No iteration set, exiting so we don\'t loop forever!' );
				}

				if ( empty( $booking_id ) ) {
					throw new Exception( 'This booking does not exist!' );
				}

				// Get the previous booking from the ID
				$prev_booking = get_wc_booking( $booking_id );

				if ( ! is_a( $prev_booking, 'WC_Booking') ) {
					throw new Exception( 'The ID passed is not for a booking!' );
				}

				// Set the clone booking data.
				$product_id  = $prev_booking->get_product_id();
				$prev_status = $prev_booking->get_status();
				$new_booking_data['resource_id'] = $prev_booking->get_resource_id();

				// Did the previous booking have persons?
				$persons = $prev_booking->get_persons();
				if ( is_array( $persons ) && 0 < count( $persons ) ) {
					$new_booking_data['persons'] = $persons;
				}

				// Was the previous booking all day?
				if ( $prev_booking->is_all_day() ) {
					$new_booking_data['all_day'] = true;
				}

				// We want it all in the background, so if it's just set, stash it.
				if ( 1 === $iteration && false === $transient ) {
					$transient_name = 'booking_gen_'. time();
					$transient_args = array( 
						'booking_id' => $booking_id,
						'count'      => $count,
						'timeout'    => $timeout,
						'interval'   => $interval,
						'unit'       => $unit,
						'iteration'  => $iteration,
						);

					set_transient( $transient_name, $transient_args, 86400 * 7 );
					wp_schedule_single_event( time(), 'bookings-generator-continue', array( $transient_name ) );

					$this->print_notice( 'Schedule set, check debug.log for status.', 'success' );
					$this->log( 'Schedule set for generation of '. $count .' copies of booking '. $booking_id .'.' );
					return;
				}

				// Since we don't want this to timeout, we set our own timeout to pause.
				$pause = time() + $timeout;

				// Log what we're about to do.
				$this->log( 'Starting generation of '. $count .' copies of booking '. $booking_id .' at iteration '. $iteration .'.' );

				// Start our looping. 
				for ( $i = $iteration; $i <= $count; $i++ ) { 
					
					// Set the iteration number.
					$iteration = $i;

					// It's time to pause
					if ( time() > $pause ) {
						// Create a transient with our data, set a cron to start right back up.
						$transient_name = 'booking_gen_'. time();
						$transient_args = array( 
							'booking_id' => $booking_id,
							'count'      => $count,
							'timeout'    => $timeout,
							'interval'   => $interval,
							'unit'       => $unit,
							'iteration'  => $iteration,
							);

						set_transient( $transient_name, $transient_args, 86400 * 7 );
						wp_schedule_single_event( time(), 'bookings-generator-continue', array( $transient_name ) );

						$this->log( 'Timeout reached, transient and cron set for generation of '. $count .' copies of booking '. $booking_id .' at iteration '. $iteration .'.' );
						return;
					}

					// The start and end times for the new booking.
					$new_booking_data['start_date'] = strtotime( '+'. ( $interval * $iteration ) .' '. $unit, $prev_booking->get_start() );
					$new_booking_data['end_date']   = strtotime( '+'. ( $interval * $iteration ) .' '. $unit, $prev_booking->get_end() );

					// Make a new booking.
					$new_booking = create_wc_booking( $product_id, $new_booking_data, $prev_status, false );

					// The new booking isn't a booking, so log and exit.
					if ( ! is_a( $new_booking, 'WC_Booking' ) ) {
						$this->log( 'Error: Failed to create booking copy of '. $booking_id .' on iteration '. $iteration .' of '. $count .', exiting.' );
						return;
					}

					// No need for reminders //or to complete these bookings. @todo?
					wp_clear_scheduled_hook( 'wc-booking-reminder', array( $new_booking->get_id() ) );
					//wp_clear_scheduled_hook( 'wc-booking-complete', array( $new_booking->get_id() ) );
				}

				// Log what we've just done.
				$this->log( 'Generation of '. $count .' copies of booking '. $booking_id .' has completed.' );


			} catch ( Exception $e ) {
				// Pring and log error.
				$this->print_notice( $e->getMessage() );
				$this->log( 'Error: '. $e->getMessage() );

				return;
			}
		}

		/**
		 * Clears email actions so they are not sent while plugin is active. Prevents overload of emails.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 * @param   string $message
		 * @param   string $type
		 */
		public function clear_email_actions( $actions = array() ) {
			return array( 'bookings_generator_has_cleared_all_email_actions' );
		}

		/**
		 * Prints notices.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 * @param   string $message
		 * @param   string $type
		 */
		public function print_notice( $message = '', $type = 'warning' ) {
			$this->notice = '<div class="notice notice-' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . '</p></div>';
		}

		/**
		 * Logs logs.
		 *
		 * @since   1.0.0
		 * @version 1.0.0
		 * @param   string $log
		 */
		public function log( $log = '' ) {
			if ( WP_DEBUG ) {
				error_log( $log );
			}
		}
	}

	Bookings_Generator::init();
}