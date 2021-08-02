<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

require_once PHOBOS_AUTH_PATH . '/main/manager.php';
require_once PHOBOS_AUTH_PATH . '/main/linker.php';

class Handler
{

	const ACTIONS = ["add", "login", "remove"];

	/** @var AuthManager[] $managers */
	private array $managers;
	private Phobos\Logger $logger;
	private ErrorMapper $errors;
	private Linker $linker;

	public function __construct(Phobos\Logger $logger, ErrorMapper $errors)
	{
		$this->managers = [];

		$this->logger = $logger;
		$this->errors = $errors;
		$this->linker = new Linker($this);

		require_once PHOBOS_AUTH_PATH . 'providers/index.php';

		/** @var AuthProvider[] $providers */
		$providers = [];
		$providers = apply_filters('phobos_load_providers', $providers);

		foreach ($providers as $provider) {
			/** @var AuthProvider $provider */
			$this->addProvider($provider);
		}

		$this->linker->addRewriteRule();

		add_action('parse_query', [$this, 'parseQuery'], 10, 1);
	}

	private function addProvider($provider)
	{
		$manager = new AuthManager($this->logger, $this->errors, $this->linker, $provider);

		$this->managers[$manager->getName()] = $manager;
	}

	public function registerSettings(bool $rest = true, bool $mock = false)
	{
		foreach ($this->managers as $name => $manager) {
			/** @var AuthManager $manager */
			$manager->registerSettings($rest, $mock);
		}
	}

	public function isProviderEnabled(string $provider): bool
	{
		$manager = $this->managers[$provider];
		if (empty($manager) || !$manager->isEnabled()) return false;

		return true;
	}

	/**
	 * Returns a list of registered providers
	 *
	 * @return string[]
	 */
	public function providers(): array
	{
		return array_keys($this->managers);
	}

	public function enabledProviders(): array
	{
		$result = [];
		foreach ($this->managers as $name => $manager) {
			if ($manager->isEnabled()) {
				$result[] = $name;
			}
		}

		return $result;
	}

	/**
	 * Returns a list of known actions
	 *
	 * @return string[]
	 */
	public function actions(): array
	{
		return self::ACTIONS;
	}

	public function renderLoginButtons()
	{
		foreach ($this->managers as $name => $manager) {
			/** @var AuthManager $manager */
			if ($manager->isEnabled()) {
				$button = $manager->renderLoginButton();

				print($button);
			}
		}
	}

	public function renderConnectButton(string $provider, WP_User $user)
	{
		if (!$this->isProviderEnabled($provider)) {
			throw new Exception('Provider is not enabled: ' . $provider);
		}

		if ($this->hasConnectedAccount($provider, $user)) {
			throw new Exception('This account is already connected to this provider: ' . $provider);
		}

		$manager = $this->managers[$provider];

		$manager->renderConnectButton();
	}

	public function renderConnections(WP_User $user)
	{
		foreach ($this->managers as $name => $manager) {
			/** @var AuthManager $manager */
			if ($manager->isEnabled()) {
				$section = $manager->renderConnection($user);

				print($section);
			}
		}
	}

	public function parseQuery()
	{
		/** @var wp $wp WordPress Main Class */
		global $wp;

		if (empty($wp->query_vars)) return;

		if (array_key_exists('oauth_provider', $wp->query_vars) && array_key_exists('oauth_action', $wp->query_vars)) {
			$provider = $wp->query_vars['oauth_provider'];
			$action   = $wp->query_vars['oauth_action'];

			if (in_array($provider, $this->providers()) && in_array($action, $this->actions())) {
				$this->oauth();
				exit();
			}
		}
	}

	private function oauth()
	{
		if (!session_id()) {
			session_start();
		}

		$this->registerSettings(false);

		$provider = get_query_var('oauth_provider');
		if (empty($provider) || !array_key_exists($provider, $this->managers)) {
			$this->logger->warning('Trying to access OAuth with an unrecognized provider.', 'unrecognized_provider');

			http_response_code(403);
			exit('Forbidden');
		}

		/** @var AuthManager $manager */
		$manager = $this->managers[$provider];

		if (!$manager->isEnabled()) {
			$this->logger->warning('Trying to access OAuth with a disabled provider.', 'disabled_provider');

			http_response_code(403);
			exit('Forbidden');
		}

		$action = get_query_var('oauth_action');
		if (!in_array($action, self::ACTIONS)) {
			$this->logger->warning('Trying to access OAuth with an unrecognized action.', 'unrecognized_action');

			http_response_code(403);
			exit('Forbidden');
		}

		/** @var wp $wp */
		global $wp;

		$error = get_query_var('oauth_error');

		if (!empty($error)) {
			$manager->handleError(
				$this->getState($action),
				$error,
				get_query_var('error_description'),
				get_query_var('error_uri')
			);
		}


		if ($action === 'remove') {
			$manager->remove($this->getState($action));
		} else {
			$code = get_query_var('code');
			$validation = get_query_var('state');

			if (!empty($code)) {
				$state = $this->recoverState($action);

				if (empty($validation) || !$manager->validate($validation)) {
					$manager->handleError($state, 'invalid_state');
				}

				switch ($action) {
					case 'add':
						$manager->add($state, $code);
						break;
					case 'login':
						$manager->login($state, $code);
						break;
					default:
						http_response_code(403);
						exit('Forbidden');
				}
			} else {
				$state = $this->persistState($action);

				$manager->redirect($state['url']);
			}
		}
	}

	private function getState($action)
	{
		/** @var wp $wp */
		global $wp;

		return [
			'url'      => home_url($wp->request),
			'action'   => $action,
			'referer'  => $_SERVER["HTTP_REFERER"],
			'redirect' => $_REQUEST['redirect_to'],
			'remember' => isset($_REQUEST['rememberme']) && $_REQUEST['rememberme'] == 1
		];
	}

	private function persistState($action)
	{
		$state = $this->getState($action);

		$_SESSION['phobos_auth_referer'] = $state['referer'];
		$_SESSION['phobos_auth_redirect'] = $state['redirect'];
		$_SESSION['phobos_auth_remember'] = $state['remember'];

		return $state;
	}

	private function recoverState($action)
	{
		/** @var wp $wp */
		global $wp;

		$state = [
			'url'      => home_url($wp->request),
			'action'   => $action,
			'referer'  => $_SESSION['phobos_auth_referer'],
			'redirect' => $_SESSION['phobos_auth_redirect'],
			'remember' => $_SESSION['phobos_auth_remember'] === true,
		];

		return $state;
	}

	public function hasConnectedAccount(string $provider, WP_User $user): bool
	{
		if ($this->isProviderEnabled($provider)) {
			$manager = $this->managers[$provider];

			return $manager->hasUser($user);
		}

		return false;
	}
}
