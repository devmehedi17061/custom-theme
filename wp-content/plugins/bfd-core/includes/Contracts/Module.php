<?php
namespace BFD\Core\Contracts;

defined( 'ABSPATH' ) || exit;

interface Module {

	/** Unique slug, used for enabling/disabling via the `bfd_core_modules` filter. */
	public function slug(): string;

	/** Attach hooks. Called once on plugins_loaded. */
	public function register(): void;
}
