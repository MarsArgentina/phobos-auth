<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

final class ErrorMapper
{
	private array $providers;

	public function __construct()
	{
		$this->providers = [
			'oauth' => [],
		];

		add_filter('um_custom_success_message_handler', [$this, 'successHandler'], 10, 2);
		add_filter('um_custom_error_message_handler',   [$this, 'errorHandler'  ], 10, 2);
	}

	public function errorHandler($error, string $code)
	{
		$message = $this->getMessageFromCode($code, true);
		if (!empty($message)) {
			return $message;
		}

		return $error;
	}

	public function successHandler($success, string $code)
	{
		$message = $this->getMessageFromCode($code, false);
		if (!empty($message)) {
			return $message;
		}

		return $success;
	}

	public function registerError(string $provider, string $error, string $message)
	{
		if (!isset($this->providers[$provider])) {
			$this->providers[$provider] = [];
		}

		if (!$this->has($provider, $error, true)) {
			$this->providers[$provider][$error] = $message;
			return true;
		} else {
			return false;
		}
	}

	public function getCode(string $provider, string $error)
	{
		return "ph-${provider}__${error}";
	}

	public function getMessageFromCode(string $code, ?bool $isError = true)
	{
		if (preg_match('/ph-(.+?)__(.+)/', $code, $match) === 1) {
			if ($isError)
				$default = __('An error occurred', 'phobos-auth') . " ($code)";
			else
				$default = __('Action performed successfully', 'phobos-auth'). " ($code)";

			return $this->getMessage($match[1], $match[2], $default);
		}

		return null;
	}

	public function has(string $provider, string $error, bool $shallow = false)
	{
		if (!isset($this->providers[$provider]) || empty($this->providers[$provider][$error])) {
			if ($shallow)
				return false;
			

			return isset($this->providers['oauth'][$error]);
		}
		return true;
	}

	public function getMessage(string $provider, string $error, string $fallback = "Unknown error")
	{
		if (!empty($this->providers[$provider]) && !empty($this->providers[$provider][$error])) {
			return $this->providers[$provider][$error];
		}

		if (!empty($this->providers['oauth'][$error])) {
			return $this->providers['oauth'][$error];
		}

		return $fallback;
	}
}
