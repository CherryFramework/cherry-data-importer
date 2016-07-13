<?php
/**
 * Logger class
 *
 * @package   Cherry_Data_Importer
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Cherry_Data_Importer_Logger' ) ) {

	/**
	 * Define Cherry_Data_Importer_Logger class
	 */
	class Cherry_Data_Importer_Logger {

		/**
		 * Add warning message into log.
		 *
		 * @param  string $message Log message.
		 * @return void
		 */
		public function warning( $message = null ) {
			$this->add_message( $message, 'warnings' );
		}

		/**
		 * Add info message into log.
		 *
		 * @param  string $message Log message.
		 * @return void
		 */
		public function info( $message = null ) {
			$this->add_message( $message, 'info' );
		}

		/**
		 * Add notice message into log.
		 *
		 * @param  string $message Log message.
		 * @return void
		 */
		public function notice( $message = null ) {
			$this->add_message( $message, 'notice' );
		}

		/**
		 * Add debug message into log.
		 *
		 * @param  string $message Log message.
		 * @return void
		 */
		public function debug( $message = null ) {
			$this->add_message( $message, 'debug' );
		}

		/**
		 * Add error message into log.
		 *
		 * @param  string $message Log message.
		 * @return void
		 */
		public function error( $message = null ) {
			$this->add_message( $message, 'error' );
		}

		/**
		 * Add passed message into passed log group.
		 *
		 * @param  string $message Log message.
		 * @param  string $type    Message type.
		 * @return void
		 */
		public function add_message( $message = null, $type = 'info' ) {

			$messages = cdi_cache()->get( $type, 'log' );

			if ( empty( $messages ) || ! is_array( $messages ) ) {
				$messages = array();
			}

			$messages[] = $message;
			cdi_cache()->update( $type, $messages, 'log' );
		}

	}

}
