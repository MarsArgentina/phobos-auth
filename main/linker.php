<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class Linker
{
	private $handler;
	public function __construct(Handler $handler)
	{
		$this->handler = $handler;
		$this->addQueryVars();
	}

	public function getURL(string $provider, string $action, string $scheme = null)
	{
		if (!in_array($action, $this->handler->actions())) return null;

		if (!$this->handler->isProviderEnabled($provider)) return null;

		return home_url("/oauth/$provider/$action", $scheme);
	}

	private function rewriteRule(): string
	{
		$providers = implode('|', $this->handler->providers());
		$actions   = implode('|', $this->handler->actions());

		return "oauth/($providers)/($actions)/?";
	}

	public function addRewriteRule()
	{
		$rule = $this->rewriteRule();

		add_rewrite_rule(
			$rule,
			'index.php?oauth_provider=$matches[1]&oauth_action=$matches[2]',
			"top"
		);

		$this->renameErrorQueryVar($rule);
	}

	private function addQueryVars()
	{
		/** @var wp $wp WordPress Main Class */
		global $wp;

		$wp->add_query_var('oauth_provider');
		$wp->add_query_var('oauth_action');

		$wp->add_query_var('state');             //OAuth State
		$wp->add_query_var('code');              //OAuth Access Code
		$wp->add_query_var('oauth_error');             //OAuth Errors
		$wp->add_query_var('error_description'); //OAuth Human readable, error description
		$wp->add_query_var('error_uri');         //OAuth human-readable web page with information about the error
	}

	private function renameErrorQueryVar($rule)
	{
		$reg = "/" . str_replace('/', '\\/', $rule) . "/";

		if (preg_match($reg, $_SERVER['REQUEST_URI'])) {
			if (isset($_GET['error'])) {
				$_GET['oauth_error'] = $_GET['error'];
			}
		}
	}
}
