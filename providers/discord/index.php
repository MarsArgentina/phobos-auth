<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

use \Wohali\OAuth2\Client\Provider\{Discord, DiscordResourceOwner};
use \League\OAuth2\Client\Token\AccessTokenInterface as AccessToken;
use \League\OAuth2\Client\Provider\AbstractProvider as Provider;

class DiscordProvider implements AuthProvider
{
	public Phobos\Logger $logger;
	private ErrorMapper $errors;
	private Linker $linker;

	const NAME = "discord";
	const DISPLAY_NAME = "Discord";

	public function __construct(ProviderSettings $settings, ErrorMapper $errors, Phobos\Logger $logger, Linker $linker)
	{
		$this->linker = $linker;
		$this->logger = $logger;
		$this->registerSettings($settings);
		$this->registerErrors($errors);
	}

	private function registerErrors(ErrorMapper $errors): void
	{
		$this->errors = $errors;

		$errors->registerError(self::NAME, 'user_taken',        __("This Discord user is already linked to an account.",                 "phobos-auth"));
		$errors->registerError(self::NAME, 'no_resource_owner', __("Couldn't retrieve information from Discord. Try again later.",       "phobos-auth"));
		$errors->registerError(self::NAME, 'saving_data_fail',  __("There was an error when saving data to WordPress. Try again later.", "phobos-auth"));
		$errors->registerError(self::NAME, 'user_not_found',    __("No user has been linked to this Discord account.",                   "phobos-auth"));
		$errors->registerError(self::NAME, 'revoke_token_fail', __("Couldn't disconnect from Discord. Try again later.",                 "phobos-auth"));
		$errors->registerError(self::NAME, 'not_linked',        __("This user is not connected to Discord.",                             "phobos-auth"));
		$errors->registerError(self::NAME, 'invalid_state',     __("Authentication failed or took too much time. Try again.",            "phobos-auth"));
		$errors->registerError(self::NAME, 'success_add',       __("Successfully connected to Discord.",                                 "phobos-auth"));
		$errors->registerError(self::NAME, 'success_remove',    __("Successfully disconnected from Discord.",                            "phobos-auth"));

		$errors->registerError(self::NAME, "invalid_request",           __("Discord login is not configured properly.", "phobos-auth"));
		$errors->registerError(self::NAME, "unauthorized_client",       __("Discord login is not configured properly.", "phobos-auth"));
		$errors->registerError(self::NAME, "unsupported_response_type", __("Discord login is not configured properly.", "phobos-auth"));
		$errors->registerError(self::NAME, "invalid_scope",             __("Discord login is not configured properly.", "phobos-auth"));

		$errors->registerError(self::NAME, "server_error",            __("Discord login is temporarily unavailable. Try again later.", "phobos-auth"));
		$errors->registerError(self::NAME, "temporarily_unavailable", __("Discord login is temporarily unavailable. Try again later.", "phobos-auth"));

		$errors->registerError(self::NAME, "access_denied", __("You have denied access to your Discord account.", "phobos-auth"));
	}

	private function registerSettings(ProviderSettings $settings): void
	{
		$settings->addSetting("client_id", [
			"type" => "string",
			"description" => __("The ID given by Discord for this client application.", "phobos-auth"),
			"initialValue" => null,
			"isRequired" => true,
			"validate" => [$this, 'validateClientID'],
		]);

		$settings->addSetting("client_secret", [
			"type" => "string",
			"description" => __("A secret only known by Discord and your application.", "phobos-auth"),
			"initialValue" => null,
			"isRequired" => true,
			"validate" => [$this, 'validateClientSecret'],
		]);
	}

	public function validateClientID($value)
	{
		return empty($value) || (is_string($value) && preg_match('/^[0-9]{18}$/', $value));
	}

	public function validateClientSecret($value)
	{
		return empty($value) || (is_string($value) && preg_match('/^[a-zA-Z0-9_-]{32}$/', $value));
	}

	/**
	 * Gets a given metadata key
	 *
	 * @param string $name
	 * @return string
	 */
	private function getKey(string $name): string
	{
		return "phobos_auth_" . self::NAME . "_" . $name;
	}

	private function _arrayToken(AccessToken $accessToken)
	{
		return [
			"access"  => $accessToken->getToken(),
			"refresh" => $accessToken->getRefreshToken()
		];
	}

	/**
	 * Saves the Access Token and account ID as User Metadata. 
	 *
	 * @param WP_User $user
	 * @param AccessToken $accessToken
	 * @param string $id
	 * @return void
	 */
	private function _saveUser(WP_User $user, AccessToken $accessToken, string $id)
	{
		$token = json_encode($this->_arrayToken($accessToken));

		$expires = $accessToken->getExpires();

		if (!update_user_meta($user->ID, $this->getKey('id'), $id)) {
			throw new Exception("saving_data_fail");
		}

		if (!update_user_meta($user->ID, $this->getKey('expires'), $expires)) {
			throw new Exception("saving_data_fail");
		}

		if (!update_user_meta($user->ID, $this->getKey('token'), $token)) {
			throw new Exception('saving_data_fail');
		}
	}

	/**
	 * Revokes the Refresh Token or the Access Token.
	 *
	 * @param Discord $provider
	 * @param array $token An array containing the access and refresh tokens. ['access' => 'token', 'refresh' => 'token']
	 * @param string $type The type of token either 'access' or 'refresh'
	 * @return boolean true if the token was successfully revoked, false otherwise.
	 */
	private function _revokeToken(ProviderSettings $settings, array $token, string $type): bool
	{
		$values = $settings->getAllValues();

		$response = wp_remote_post(
			'https://discord.com/api/oauth2/token/revoke',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token['access'],
					'Content-Type' => 'application/x-www-form-urlencoded'
				],
				'body' => http_build_query([
					'token' => $token[$type],
					'token_type_hint' => $type . '_token',
					'client_id' => $values['client_id'],
					'client_secret' => $values['client_secret'],
				], '', '&')
			]
		);

		if (is_wp_error($response)) {
			/** @var WP_Error $response */
			$this->logger->error($response->get_error_message());
			return false;
		}

		if ($response['response']['code'] === 200) {
			return true;
		}

		$this->logger->error($response['body'], $response['response']['code']);
		return false;
	}

	private function _revokeBothTokens(ProviderSettings $settings, array $token)
	{
		if (!$this->_revokeToken($settings, $token, "refresh")) {
			$this->logger->error('Discord refresh token token couldn\'t be revoked');
			throw new Exception("revoke_token_fail");
		}
		if (!$this->_revokeToken($settings, $token, "access")) {
			$this->logger->error('Discord access token token couldn\'t be revoked');
			throw new Exception("revoke_token_fail");
		}
	}

	private function _getID(Discord $provider, \League\OAuth2\Client\Token\AccessToken $token)
	{
		try {
			/** @var DiscordResourceOwner  */
			$owner = $provider->getResourceOwner($token);
			$id = $owner->getId();
		} catch (Exception $e) {
			throw new Exception("no_resource_owner");
		}

		if (!$id) {
			throw new Exception("no_resource_owner");
		}

		return $id;
	}

	private function _getAccessToken(Discord $provider, string $code)
	{
		try {
			$token = $provider->getAccessToken('authorization_code', ['code' => $code]);
			$refreshed = false;

			if ($token->hasExpired()) {
				$token = $provider->getAccessToken('refresh_token', [
					'refresh_token' => $token->getRefreshToken()
				]);

				$refreshed = true;
			}
		} catch (Exception $e) {
			throw new Exception("no_access_token");
		}

		return ['token' => $token, 'refreshed' => $refreshed];
	}

	public function errorHandler(string $error, ?string $description = null, ?string $uri = null): string
	{
		$message = (!empty($description) ? $description . " :: " : "") . $this->errors->getMessage(self::NAME, $error, "Unknown Discord authentication error");

		$this->logger->error(!empty($url) ? "${message} <${uri}>" : $message, $error);

		return esc_attr($this->errors->getCode(self::NAME, $error));
	}

	/**
	 * @param ProviderSettings $settings
	 * @param string $redirect
	 * @return Discord
	 */
	public function getProvider(ProviderSettings $settings, string $redirect): Discord
	{
		$values = $settings->getAllValues();

		return new Discord(
			[
				'clientId' => $values['client_id'],
				'clientSecret' => $values['client_secret'],
				'redirectUri' => $redirect
			]
		);
	}

	public function getURL(string $action, ?string $scheme = null)
	{
		return $this->linker->getURL(self::NAME, $action, $scheme);
	}

	public function getName(): string
	{
		return self::NAME;
	}

	public function getDisplayName(): string
	{
		return self::DISPLAY_NAME;
	}

	public function getAuthorizationOptions(ProviderSettings $settings): array
	{
		return [
			'scope' => ['identify', 'email', 'guilds.join'],
			'prompt' => 'none'
		];
	}

	public function renderIcon(): string
	{
		return "<svg class='phobos-discord-logo'viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'>
			<path d='m19.875 4.778c-1.448-.664-3.001-1.154-4.625-1.434-.03-.005-.059.008-.074.035-.2.355-.421.819-.576 1.183-1.746-.261-3.484-.261-5.194 0-.155-.372-.384-.828-.585-1.183-.015-.026-.045-.04-.074-.035-1.623.279-3.176.769-4.625 1.434-.013.005-.023.014-.03.026-2.945 4.4-3.752 8.693-3.357 12.932.002 .021.013 .041.03 .053 1.943 1.427 3.826 2.294 5.673 2.868.03 .009.061-.002.08-.026.437-.597.827-1.226 1.161-1.888.02-.039.001-.085-.039-.1-.618-.234-1.206-.52-1.772-.845-.045-.026-.048-.09-.007-.121.119-.089.238-.182.352-.276.021-.017.049-.021.073-.01 3.718 1.698 7.744 1.698 11.418 0 .024-.012.053-.008.074 .009.114 .094.233 .188.353 .277.041 .031.038 .095-.006.121-.566.331-1.154.61-1.773.844-.04.015-.058.062-.038.101 .341.661 .731 1.29 1.16 1.887.018 .025.05 .036.08 .027 1.856-.574 3.739-1.441 5.682-2.868.017-.013.028-.032.03-.052.474-4.901-.793-9.158-3.359-12.932-.006-.013-.017-.022-.03-.027zm-11.641 10.377c-1.119 0-2.042-1.028-2.042-2.29 0-1.262.905-2.29 2.042-2.29 1.146 0 2.06 1.037 2.042 2.29 0 1.262-.905 2.29-2.042 2.29zm7.549 0c-1.119 0-2.042-1.028-2.042-2.29 0-1.262.904-2.29 2.042-2.29 1.146 0 2.06 1.037 2.042 2.29 0 1.262-.896 2.29-2.042 2.29z' fill='currentColor'/>
		</svg>";
	}

	/**
	 * @param Discord $provider
	 * @param string $code
	 * @return WP_User
	 */
	public function getUser(ProviderSettings $settings, string $code): WP_User
	{

		$provider = $this->getProvider($settings, $this->getURL('login'));
		/** 
		 * @var AccessToken $token
		 * @var bool $refreshed 
		 */
		extract($this->_getAccessToken($provider,	$code));

		$id = $this->_getID($provider, $token);

		$user = $this->findUser($id);

		if ($user == null) {
			$this->_revokeBothTokens($settings, $this->_arrayToken($token));
			throw new Exception('user_not_found');
		}

		if ($refreshed) {
			$this->_saveUser($user, $token, $id);
		}

		return $user;
	}

	public function hasUser(WP_User $user): bool
	{
		$meta = get_user_meta($user->ID, $this->getKey('token'), true);

		return !empty($meta);
	}

	public function getUserInfo(string $token, ?string $avatarFormat = 'webp', $avatarSize = null)
	{
		$response = wp_remote_get('https://discord.com/api/oauth2/@me', [
			'headers' => [
				'Authorization' => 'Bearer ' . $token
			]
		]);

		if (empty($response['body']))
			return null;

		try {
			$decoded = json_decode($response['body'], true, 100, JSON_THROW_ON_ERROR);
		} catch (Exception $e) {
			return null;
		}

		if (empty($decoded['user'])) return null;

		$user = $decoded['user'];

		if (empty($user['username']) || empty($user['discriminator'])) return null;

		$id = $user['id'];
		$hash = $user['avatar'];

		if (empty($user['id']) || empty($user['avatar'])) {
			$user['avatarUrl'] = '';
		}

		$user['avatarUrl'] = "https://cdn.discordapp.com/avatars/$id/$hash.$avatarFormat" . (!empty($avatarSize) ? "?size=$avatarSize" : "");

		return $user;
	}

	public function renderUserInfo(WP_User $user): string
	{
		if ($this->hasUser($user)) {
			$meta = get_user_meta($user->ID, $this->getKey('token'), true);
			$token = json_decode($meta, true);

			$info = $this->getUserInfo($token['access']);


			if (empty($info)) {
				return '<span>' . __('Account Connected', 'phobos-auth') . '</span>';
			} else {
				return sprintf(
					'<img src="%1$s" alt="%2$s"/><span><b>%3$s</b>#%4$s</span>',
					$info['avatarUrl'],
					sprintf(__('%1$s\'s avatar', 'phobos-auth'), $info['username']),
					$info['username'],
					$info['discriminator']
				);
			}
		} else {
			return '';
		}
	}

	/**
	 * Finds a Discord user by ID in the WordPress user database
	 *
	 * @param string $id
	 * @return WP_User|null
	 */
	private function findUser(string $id)
	{
		/** @var WP_User[] */
		$users = get_users([
			'meta_key' => $this->getKey('id'),
			'meta_value' => $id,
			'meta_compare' => '=',
			'number' => 1,
			'count_total' => false,
			'fields' => 'all_with_meta'
		]);

		if (empty($users) || reset($users) == null) {
			return null;
		}

		return reset($users);
	}

	/**
	 * @param Discord $provider
	 * @param string $code
	 * @param WP_User $user
	 * @return void
	 */
	public function addUser(ProviderSettings $settings, string $code, WP_User $user): void
	{
		$provider = $this->getProvider($settings, $this->getURL('add'));
		/** @var AccessToken $token */
		extract($this->_getAccessToken($provider, $code));

		$id = $this->_getID($provider, $token);

		if ($this->findUser($id) != null) {
			throw new Exception('user_taken');
		}

		$this->_saveUser($user, $token, $id);
	}


	/**
	 * @param Discord $provider
	 * @param WP_User $user
	 * @return void
	 */
	public function removeUser(ProviderSettings $settings, WP_User $user): void
	{
		$meta = get_user_meta($user->ID, $this->getKey('token'), true);

		if (empty($meta)) {
			throw new Exception("not_linked");
		}

		$token = json_decode($meta, true);

		$this->_revokeBothTokens($settings, $token);

		delete_user_meta($user->ID, $this->getKey('id'));
		delete_user_meta($user->ID, $this->getKey('expires'));
		delete_user_meta($user->ID, $this->getKey('token'));
	}

	public function findAllExpiredTokens(): array
	{
		/** @var WP_User[] */
		return get_users([
			'meta_key' => $this->getKey('expires'),
			'meta_value' => time(),
			'meta_compare' => '<=',
			'count_total' => false,
			'fields' => 'all_with_meta'
		]);
	}

	/**
	 * Refreshes the token for a given user.
	 *
	 * @param Discord $provider
	 * @param WP_User $user
	 * @return void
	 */
	public function refreshExpiredToken(Provider $provider, WP_User $user): bool
	{
		$expires = get_user_meta($user->ID, $this->getKey('expires'), true);
		$meta    = get_user_meta($user->ID, $this->getKey('token'),   true);
		$id      = get_user_meta($user->ID, $this->getKey('id'),      true);

		if (empty($expires) || empty($meta) || empty($id)) {
			throw new Exception('not_linked');
		}

		if ($expires > time()) {
			return false;
		}

		$token = json_decode($meta, true);

		try {
			$new = $provider->getAccessToken('refresh_token', [
				'refresh_token' => $token["refresh"]
			]);
		} catch (Exception $e) {
			throw new Exception('no_access_token');
		}

		$this->_saveUser($user, $new, $id);

		return true;
	}

	/**
	 * Undocumented function
	 *
	 * @param AuthProvider[] $providers
	 * @return AuthProvider[]
	 */
	static function load($providers)
	{
		$providers[] = self::class;

		return $providers;
	}
}

add_filter('phobos_load_providers', ['DiscordProvider', 'load'], 1);
