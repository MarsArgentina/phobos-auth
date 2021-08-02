<?php

use \League\OAuth2\Client\Provider\AbstractProvider;

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

interface AuthProvider
{
	/**
	 * Greates a new AuthProvider
	 *
	 * @param ProviderSettings $settings The settings for this provider
	 * @param PhobosLogger $logger The logger object for this plugin
	 */
	public function __construct(ProviderSettings $settings, ErrorMapper $errors, Phobos\Logger $logger, Linker $linker);

	/**
	 * Get the URL for a given action handled by this provider.
	 *
	 * @param string $action
	 * @return string|null
	 */
	public function getURL(string $action, ?string $scheme = null);
	/**
	 * Get the name of this provider
	 *
	 * @return string
	 */
	public function getName(): string;
	/**
	 * Get the display name of this provider
	 *
	 * @return string
	 */
	public function getDisplayName(): string;

	/**
	 * Get the SVG icon that represents this provider.
	 *
	 * @return string
	 */
	public function renderIcon(): string;

	public function renderUserInfo(WP_User $user): string;

	public function errorHandler(string $error, ?string $description = null, ?string $uri = null): string;

	/**
	 * Get the options required to generate the 
	 *
	 * @param ProviderSettings $settings The settings for this provider
	 * @return array
	 */
	public function getAuthorizationOptions(ProviderSettings $settings): array;

	/**
	 * Gets a preconfigured provider
	 *
	 * @param ProviderSettings $settings The settings for this provider
	 * @param string $redirect The URL the auth server will redirect to
	 * @return AbstractProvider
	 */
	public function getProvider(ProviderSettings $settings, string $redirect): AbstractProvider;

	public function hasUser(WP_User $user): bool;
	public function getUser(ProviderSettings $provider, string $code): WP_User;
	public function addUser(ProviderSettings $provider, string $code, WP_User $user): void;
	public function removeUser(ProviderSettings $settings, WP_User $user): void;

	public function findAllExpiredTokens(): array;
	public function refreshExpiredToken(AbstractProvider $provider, WP_User $user): bool;
}
