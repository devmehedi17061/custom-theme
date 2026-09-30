<?php
namespace BFD\Core;

defined( 'ABSPATH' ) || exit;

/**
 * PSR-4 style autoloader: BFD\Core\Fields\Fields_Module => includes/Fields/Fields_Module.php
 */
final class Autoloader {

	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $prefix, $base_dir ): void {
				if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
					return;
				}
				$relative = substr( $class_name, strlen( $prefix ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);
	}
}
