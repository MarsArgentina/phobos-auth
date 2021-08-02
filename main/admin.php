<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class Admin
{
	private Handler $handler;
	private string $slug;

	public function __construct(Handler $handler)
	{
		$this->slug = 'phobos-auth-settings';
		$this->handler = $handler;

		add_action('rest_api_init', [$this, 'registerRestApi']);
		add_action('phobos_submenu', [$this, 'addPage']);
		add_action('admin_enqueue_scripts', [$this, 'registerAssets']);
		add_filter('plugin_action_links_' . PHOBOS_AUTH_BASENAME, [$this, 'pluginActionLinks']);
	}


	public function pluginActionLinks($actions)
	{
		$actions[] = '<a href="' . menu_page_url('phobos-auth', false) . '">' . __('Settings', 'phobos-auth') . '</a>';
		return $actions;
	}

	public function registerRestApi()
	{
		$this->handler->registerSettings();
	}

	public function addPage()
	{
		$page_hook_suffix = add_submenu_page(
			'phobos',
			__('Authentication', 'phobos-auth'),
			__('Authentication', 'phobos-auth'),
			'manage_options',
			'phobos-auth',
			[$this, 'renderAdmin'],
			2
		);

		add_action("admin_print_scripts-{$page_hook_suffix}", [$this, 'enqueueAssets']);
	}

	public function registerAssets()
	{
		wp_register_script($this->slug, PHOBOS_AUTH_URL . '/build/admin.js',  ['wp-api', 'wp-i18n', 'wp-components', 'wp-element', 'wp-api-fetch']);
		wp_register_style($this->slug,  PHOBOS_AUTH_URL . '/build/admin.css', ['wp-components']);
		// wp_localize_script($this->slug, 'REST', [
		// 	'url' => esc_url_raw(rest_url()),
		// 	'none' => wp_create_nonce('wp-rest')
		// ]);
		wp_localize_script($this->slug, 'PhobosAuth', [
			'actions' => $this->handler->actions(),
			'url' => esc_url_raw(home_url('oauth'))
		]);
	}

	public function enqueueAssets()
	{
		if (!wp_script_is($this->slug, 'registered')) {
			$this->registerAssets();
		}
		wp_enqueue_script($this->slug);
		wp_enqueue_style($this->slug);
	}

	public function renderAdmin()
	{
		echo '<div id="phobos-auth-admin"></div>';
	}
}
