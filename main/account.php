<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class Account
{
	private Handler $handler;
	private string $name;
	private string $displayName;

	public function __construct(Handler $handler)
	{
		$this->handler = $handler;

		$this->name = __('connections', 'phobos-auth');
		$this->displayName = __('Connections', 'phobos-auth');

		//add_action('um_after_account_general', [$this, 'addConnections'], 10, 1);

		$providers = $this->handler->enabledProviders();
		if (is_array($providers) && count($providers) > 0) {
			add_filter('um_account_page_default_tabs_hook', [$this, 'registerConnectionsTab'], 100);
			add_action('um_account_tab__' . $this->name, [$this, 'hookConnectionsTab']);
			add_filter('um_account_content_hook_' . $this->name, [$this, 'renderConnectionsTab']);
			add_filter('um_get_core_page_filter', [$this, 'addCorePage'], 100, 3);

			add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
		}
	}

	public function addCorePage($url, $slug, $updated)
	{
		if ($slug === 'phobos-auth') {
			$url = um_get_core_page('account') . $this->name . '/';;
			if ($updated) {
				$url = add_query_arg('updated', esc_attr($updated), $url);
			}
		}

		return $url;
	}

	public function enqueueStyles()
	{
		if ($this->isAccountPage()) {
			wp_enqueue_style("phobos-account", PHOBOS_AUTH_URL . 'main/styles/account.css', ["um_default_css"], PHOBOS_AUTH_VERSION);
		}
	}

	public function registerConnectionsTab($tabs)
	{

		$tabs[110][$this->name]['icon'] = 'um-faicon-plug';
		$tabs[110][$this->name]['title'] = $this->displayName;
		$tabs[110][$this->name]['custom'] = true;
		$tabs[110][$this->name]['show_button'] = false;

		return $tabs;
	}

	public function hookConnectionsTab($info)
	{
		global $ultimatemember;
		extract($info);

		$output = $ultimatemember->account->get_tab_output($this->name);

		if ($output) {
			echo $output;
		}
	}

	public function renderConnectionsTab($output)
	{
		ob_start();
		$this->addConnections(false);
		return $output . ob_get_clean();
	}


	public function addConnections(?bool $renderLabel = true)
	{
		if (!is_user_logged_in()) {
			return;
		}

		$user = wp_get_current_user();

?>

		<div class="um-field">
			<?php if ($renderLabel) { ?>
				<div class="um-field-label">
					<label><?php print($this->displayName) ?></label>
				</div>
			<?php } ?>

			<div class="phobos-social">
				<?php $this->handler->renderConnections($user); ?>
			</div>
		</div>

<?php
	}

	public function isAccountPage()
	{
		$current_url = UM()->permalinks()->get_current_url(true);
		$account_url = um_get_core_page('account');

		return strpos($current_url, $account_url) === 0;
	}
}
