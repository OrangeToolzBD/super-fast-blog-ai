<?php
/**
 * Hook/filter registration manager.
 *
 * Collects add_action / add_filter registrations from every module and
 * executes them in a single pass. This keeps hook registration auditable
 * and testable — all hooks are visible in one place before run() fires.
 *
 * Usage inside a module's init():
 *   $loader->add_action( 'init', $this, 'my_method' );
 *   $loader->add_filter( 'the_content', $this, 'my_filter', 20, 1 );
 *
 * @package SuperFastBlogAI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFBA_Loader {

	/**
	 * Registered action hooks.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private array $actions = [];

	/**
	 * Registered filter hooks.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private array $filters = [];

	// -------------------------------------------------------------------------
	// Registration.
	// -------------------------------------------------------------------------

	/**
	 * Queue an action hook.
	 *
	 * @param string $hook          The WordPress action hook name.
	 * @param object $component     The object that owns the callback.
	 * @param string $callback      The method name on $component.
	 * @param int    $priority      Hook priority (default 10).
	 * @param int    $accepted_args Number of arguments (default 1).
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions[] = $this->build( $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Queue a filter hook.
	 *
	 * @param string $hook          The WordPress filter hook name.
	 * @param object $component     The object that owns the callback.
	 * @param string $callback      The method name on $component.
	 * @param int    $priority      Hook priority (default 10).
	 * @param int    $accepted_args Number of arguments (default 1).
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->filters[] = $this->build( $hook, $component, $callback, $priority, $accepted_args );
	}

	// -------------------------------------------------------------------------
	// Execution.
	// -------------------------------------------------------------------------

	/**
	 * Register all collected hooks with WordPress.
	 *
	 * Called once by SFBA_Core::run().
	 */
	public function run(): void {
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				[ $hook['component'], $hook['callback'] ],
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}

	// -------------------------------------------------------------------------
	// Helpers.
	// -------------------------------------------------------------------------

	/**
	 * Build a hook descriptor array.
	 */
	private function build(
		string $hook,
		object $component,
		string $callback,
		int $priority,
		int $accepted_args
	): array {
		return [
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];
	}

	/**
	 * Returns all registered action descriptors (useful for testing).
	 *
	 * @return array
	 */
	public function get_actions(): array {
		return $this->actions;
	}

	/**
	 * Returns all registered filter descriptors (useful for testing).
	 *
	 * @return array
	 */
	public function get_filters(): array {
		return $this->filters;
	}
}
