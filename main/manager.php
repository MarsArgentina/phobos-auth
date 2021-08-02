<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

require_once PHOBOS_AUTH_PATH . '/main/settings.php';

class AuthManager
{
	/** The name of the nonce used for OAuth state validation */
	const VALIDATION = 'phobos_auth_validation';

	public ProviderSettings $settings;
	public ErrorMapper $errors;

	private AuthProvider $provider;

	/**
	 * Builds a new AuthProvider
	 */
	public function __construct(Phobos\Logger $logger, ErrorMapper $errors, Linker $linker, $provider)
	{
		$this->logger = $logger;
		$this->errors = $errors;

		$this->settings = new ProviderSettings();
		$this->provider = new $provider($this->settings, $errors, $logger, $linker);

		$this->settings->init($this->provider);
	}

	public function getName(): string
	{
		return $this->provider->getName();
	}

	/**
	 * Register the REST API and options for this provider.
	 * Returns the ProviderSettings that were registered.
	 *
	 * @return ProviderSettings
	 */
	public function registerSettings(bool $rest = true, bool $mock = false)
	{
		$this->settings->register($rest, $mock);

		return $this->settings;
	}

	public function renderLoginButton(): string
	{
		$button = sprintf(
			'<button class="um-button phobos-social-btn %3$s" type="submit" formmethod="POST" formaction="%2$s">%4$s<span>%1$s</span></button>',
			sprintf(__('Login with %1$s'), $this->provider->getDisplayName()),
			$this->provider->getURL('login'),
			'phobos-' . $this->provider->getName() . '-btn',
			$this->provider->renderIcon()
		);

		return $button;
	}

	private function _renderConnectionButton(): string
	{
		$button = sprintf(
			'<div class="%4$s">
				<a class="big-connect" href="%1$s">
					%2$s
					<span>%3$s</span>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="plus">
						<path
							d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"
							fill="currentColor"
						></path>
					</svg>
				</a>
			</div>',
			$this->provider->getURL('add'),
			$this->provider->renderIcon(),
			sprintf(__('Connect your %1$s account', "phobos-auth"), $this->provider->getDisplayName()),
			'phobos-' . $this->provider->getName()
		);

		return $button;
	}

	private function _renderConnectionInfo(WP_User $user): string
	{
		$info = sprintf(
			'<div class="%6$s">
				<div class="info-header">
					%1$s
					<span>%2$s</span>
				</div>
				<div class="info">
					%4$s
					<a class="disconnect" href="%3$s">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
							<path
								d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12 19 6.41z"
								fill="currentColor"
							></path>
						</svg>
						<span>%5$s</span>
					</a>
				</div>
			</div>',
			$this->provider->renderIcon(),
			$this->provider->getDisplayName(),
			$this->provider->getURL('remove'),
			$this->provider->renderUserInfo($user),
			__('Disconnect', 'phobos-auth'),
			'phobos-' . $this->provider->getName(),
		);

		return $info;
	}

	public function renderConnectButton(): string
	{
		$url = $this->provider->getURL('add');
		$icon = $this->provider->renderIcon();
		$class = 'phobos-' . $this->provider->getName() . '-btn';
		$name = $this->provider->getDisplayName();

		// TODO: Fill this up!
		return '';
	}

	public function renderConnection(WP_User $user): string
	{
		if ($this->provider->hasUser($user)) {
			return $this->_renderConnectionInfo($user);
		} else {
			return $this->_renderConnectionButton();
		}
	}


	public function redirect(string $url)
	{
		$authProvider = $this->provider->getProvider($this->settings, $url);

		$authUrl = $authProvider->getAuthorizationUrl(array_merge(
			$this->provider->getAuthorizationOptions($this->settings),
			[
				'state' => wp_create_nonce(self::VALIDATION)
			]
		));

		$this->logger->info($authUrl);

		exit(wp_redirect($authUrl, 303));
	}

	public function validate($validation)
	{
		return wp_verify_nonce($validation, self::VALIDATION);
	}

	public function handleError(array $state, string $code, ?string $description = null, ?string $uri = null)
	{
		$err = $this->provider->errorHandler($code, $description, $uri);

		if ($code === 'no_access_token') {
			$this->redirect($this->provider->getURL($state['action']));
		}

		switch ($state['action']) {
			case 'login':
				return $this->errorOnLogin($state, $err, $code);
			case 'add':
			case 'remove':
				return $this->errorOnAccountChange($state, $err, $code);
			default:
				http_response_code(400);
				exit($this->errors->getMessageFromCode($err));
		}
	}

	private function errorOnAccountChange(array $state, string $err, string $code)
	{
		if ($code === 'add_no_user' || $code === 'remove_no_user') {
			exit(wp_redirect(
				um_get_core_page('login')
			));
		}

		exit(wp_redirect(
			add_query_arg(
				[
					'err' => $err,
					'redirect_to' => $state['redirect']
				],
				um_get_core_page('phobos-auth')
			)
		));
	}

	private function errorOnLogin(array $state, string $err, string $code)
	{
		if ($code === 'already_logged_in') {
			$this->redirectAfterLogin($state);
		}

		exit(wp_redirect(
			add_query_arg(
				[
					'err' => $err,
					'redirect_to' => $state['redirect']
				],
				um_get_core_page('login')
			)
		));
	}

	public function add(array $state, string $code)
	{
		if (!is_user_logged_in()) {
			$this->handleError($state, 'add_no_user');
		}

		$user = wp_get_current_user();
		um_fetch_user($user->ID);

		try {
			$this->provider->addUser($this->settings, $code, $user);

			$this->redirectAfterAccountChange($state);
		} catch (Exception $e) {
			$this->handleError($state, $e->getMessage());
		}
	}

	public function login(array $state, string $code)
	{
		if (is_user_logged_in()) {
			$this->handleError($state, 'already_logged_in');
		}

		try {
			$user = $this->provider->getUser($this->settings, $code);

			um_fetch_user($user->ID);
			UM()->user()->auto_login(um_user('ID'), $state['remember'] ? 1 : 0);

			do_action('um_on_login_before_redirect', um_user('ID'));

			$this->redirectAfterLogin($state);
		} catch (Exception $e) {
			$this->handleError($state, $e->getMessage());
		}
	}

	public function remove(array $state)
	{
		if (!is_user_logged_in()) {
			$this->handleError($state, 'remove_no_user');
		}

		$user = wp_get_current_user();

		try {
			$this->provider->removeUser($this->settings, $user);

			$this->redirectAfterAccountChange($state);
		} catch (Exception $e) {
			$this->handleError($state, $e->getMessage());
		}
	}

	private function redirectAfterAccountChange(array $state)
	{
		// Priority redirect
		if (!empty($state['redirect'])) {
			exit(wp_safe_redirect($state['redirect']));
		}

		exit(wp_redirect(
			um_get_core_page(
				'phobos-auth',
				$this->errors->getCode(
					$this->getName(),
					"success_" . $state['action']
				)
			)
		));
	}

	private function redirectAfterLogin(array $state)
	{
		// Priority redirect
		if (!empty($state['redirect'])) {
			exit(wp_safe_redirect($state['redirect']));
		}

		// Role redirect
		$after_login = um_user('after_login');
		if (empty($after_login)) {
			exit(wp_redirect(um_user_profile_url()));
		}

		switch ($after_login) {
			case 'redirect_admin':
				exit(wp_redirect(admin_url()));
				break;

			case 'redirect_url':
				$redirect_url = apply_filters(
					'um_login_redirect_url',
					um_user('login_redirect_url'),
					um_user('ID')
				);

				exit(wp_redirect($redirect_url));
				break;

			case 'refresh':
				if (!empty($state['referer'])) {
					exit(wp_redirect($state['referer']));
					break;
				}

			case 'redirect_profile':
			default:
				exit(wp_redirect(um_user_profile_url()));
				break;
		}
	}

	public function hasUser(WP_User $user): bool
	{
		return $this->provider->hasUser($user);
	}

	/**
	 * True if this Auth provider is ready to be used.
	 */
	public function isEnabled(): bool
	{
		if (!$this->settings->hasAllRequiredValues()) {
			return false;
		}

		if (!$this->settings->getValue("enabled", true)) {
			return false;
		}

		return true;
	}
}
