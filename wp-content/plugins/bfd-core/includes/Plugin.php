<?php
namespace BFD\Core;

use BFD\Core\Contracts\Module;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** Bump when post types / rewrite rules change so rules are flushed once. */
	private const REWRITE_VERSION = '2';

	private static ?Plugin $instance = null;

	/** @var Module[] */
	private array $modules = array();

	private bool $booted = false;

	public static function instance(): Plugin {
		return self::$instance ??= new self();
	}

	/**
	 * Module classes, in load order. Filter to add or remove modules.
	 *
	 * @return class-string<Module>[]
	 */
	private function module_classes(): array {
		$classes = array(
			Security\Security_Module::class,
			PostTypes\PostTypes_Module::class,
			Fields\Fields_Module::class,
			Sections\Sections_Module::class,
			Forms\Forms_Module::class,
			Seo\Seo_Module::class,
			Admin\Admin_Module::class,
		);
		return (array) apply_filters( 'bfd_core_modules', $classes );
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		load_plugin_textdomain( 'bfd-core', false, dirname( plugin_basename( BFD_CORE_FILE ) ) . '/languages' );

		foreach ( $this->module_classes() as $class ) {
			if ( ! class_exists( $class ) || ! is_subclass_of( $class, Module::class ) ) {
				continue;
			}
			$module = new $class();
			$module->register();
			$this->modules[ $module->slug() ] = $module;
		}

		add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );

		do_action( 'bfd_core_loaded', $this );
	}

	public function module( string $slug ): ?Module {
		return $this->modules[ $slug ] ?? null;
	}

	public static function activate(): void {
		// CPTs register on `init`, which hasn't run yet; flush on the next request.
		delete_option( 'bfd_core_rewrite_version' );
	}

	public function maybe_flush_rewrite(): void {
		if ( self::REWRITE_VERSION !== get_option( 'bfd_core_rewrite_version' ) ) {
			update_option( 'bfd_core_rewrite_version', self::REWRITE_VERSION, false );
			flush_rewrite_rules();
		}
	}

	public static function deactivate(): void {
		\BFD\Core\Security\Activity_Log::unschedule();
		flush_rewrite_rules();
	}
}
