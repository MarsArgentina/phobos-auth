<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class Login
{
	private Handler $handler;

	public function __construct(Handler $handler)
	{
		$this->handler = $handler;

		add_action('wp_enqueue_scripts', [$this, 'enqueueStyles']);
		add_action('um_after_form', [$this, 'socialButtons'], 100, 1);
	}

	public function enqueueStyles()
	{
		if ($this->isLoginPage()) {
			wp_enqueue_style("phobos-login", PHOBOS_AUTH_URL . 'main/styles/login.css', ["um_default_css"], PHOBOS_AUTH_VERSION);
		}
	}

	public function socialButtons($args)
	{
		if ($args['mode'] === 'login') {
?>
			<div class="phobos-social">
				<?php $this->handler->renderLoginButtons(); ?>
			</div>
<?php
		}
	}

	public function isLoginPage(): bool
	{
		$current_url = UM()->permalinks()->get_current_url(true);
		$login_url = um_get_core_page('login');

		return $current_url === $login_url;
	}
}
