<?php

/**
 * Phobos Auth
 * php version 7
 *
 * @category WordPress Plugin
 *
 * @package Phobos_Auth
 * @author  The Mars Society Argentina <desarrollo@marssociety.com.ar>
 * @license MIT https://opensource.org/licenses/MIT
 * @link    https://tmsa.ar/phobos
 * @since   1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: Phobos Authentication
 * Plugin URI:  https://tmsa.ar/phobos
 * Description: Plugin para TMSA destinado a añadir conexiones con redes sociales para autenticar usuarios.
 * Version:     1.0.0
 * Author:      The Mars Society Argentina
 * Author URI:  https://tmsa.ar
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: phobos-auth
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

if (defined('PHOBOS_AUTH_VERSION')) {
	return;
}

define('PHOBOS_AUTH_PLUGIN_NAME', 'Phobos Auth');
define('PHOBOS_AUTH_VERSION', '1.0.0');
define('PHOBOS_AUTH_FILE', __FILE__);
define('PHOBOS_AUTH_PATH', plugin_dir_path(PHOBOS_AUTH_FILE));
define('PHOBOS_AUTH_URL', plugin_dir_url(PHOBOS_AUTH_FILE));
define('PHOBOS_AUTH_BASENAME', plugin_basename(PHOBOS_AUTH_FILE));

require_once PHOBOS_AUTH_PATH . '/vendor/autoload.php';

require_once PHOBOS_AUTH_PATH . '/main/errors.php';
require_once PHOBOS_AUTH_PATH . '/main/handler.php';
require_once PHOBOS_AUTH_PATH . '/main/login.php';
require_once PHOBOS_AUTH_PATH . '/main/account.php';
require_once PHOBOS_AUTH_PATH . '/main/admin.php';

/**
 * Main Phobos Auth Class
 *
 * The main class that initiates and runs the plugin.
 *
 * @since 1.0.0
 */
final class PhobosAuth
{

	const NAME = PHOBOS_AUTH_PLUGIN_NAME;
	/**
	 * Plugin Version
	 *
	 * @var string The plugin version.
	 */
	const VERSION = PHOBOS_AUTH_VERSION;

	/**
	 * Plugin Index File
	 *
	 * @var string The path to this plugin's index file.
	 */
	const FILE = PHOBOS_AUTH_FILE;

	/**
	 * Minimum PHP Version
	 *
	 * @var string Minimum PHP version required to run the plugin.
	 */
	const MINIMUM_PHP_VERSION = '7.0';

	/**
	 * Minimum UltimateMember Version
	 *
	 * @var string Minimum UltimateMember version required to run the plugin.
	 */
	const MINIMUM_UM_VERSION = '2.1.0';

	/**
	 * Minimum UltimateMember Version
	 *
	 * @var string Minimum UltimateMember version required to run the plugin.
	 */
	const MINIMUM_PHOBOS_VERSION = '1.0.0';

	/**
	 * Instance
	 *
	 * @since 1.0.0
	 *
	 * @access private
	 * @static
	 *
	 * @var PhobosAuth The single instance of the class.
	 */
	private static $instance = null;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 * @static
	 *
	 * @return PhobosAuth An instance of the class.
	 */
	public static function instance()
	{

		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private Phobos\Logger $logger;
	private ErrorMapper $errors;
	private Handler $handler;
	private Login $login;
	private Admin $admin;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'onPluginsLoaded']);
	}

	/**
	 * Load Textdomain
	 *
	 * Load plugin localization files.
	 *
	 * Fired by `init` action hook.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function i18n()
	{
		load_plugin_textdomain('phobos-auth');
	}

	/**
	 * On Plugins Loaded
	 *
	 * Checks if Elementor has loaded, and performs some compatibility checks.
	 * If All checks pass, inits the plugin.
	 *
	 * Fired by `plugins_loaded` action hook.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function onPluginsLoaded()
	{
		if ($this->isCompatible()) {
			$this->logger = new Phobos\Logger(PHOBOS_AUTH_PATH);

			register_activation_hook(PHOBOS_AUTH_FILE, [$this, 'activate']);
			register_deactivation_hook(PHOBOS_AUTH_FILE, [$this, 'deactivate']);

			add_action('init', [$this, 'init']);
		}
	}

	/**
	 * Compatibility Checks
	 *
	 * Checks if the installed version of Elementor meets the plugin's minimum requirement.
	 * Checks if the installed PHP version meets the plugin's minimum requirement.
	 *
	 * @since 1.0.0
	 *
	 * @access public
	 */
	public function isCompatible()
	{

		// Check for required PHP version
		if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '<')) {
			add_action('admin_notices', [$this, 'adminNoticePHPVersion']);
			return false;
		}

		if (!defined('PHOBOS_VERSION') || version_compare(Phobos\Phobos::VERSION, self::MINIMUM_PHOBOS_VERSION, '<') || !Phobos\isPluginActive(Phobos\Phobos::FILE)) {
			add_action('admin_notices', [$this, 'adminNoticePhobos']);
			return false;
		}

		// Check for UltimateMember
		if (!defined('um_path') || !file_exists(um_path  . 'includes/class-dependencies.php')) {
			add_action('admin_notices', [$this, 'adminNoticeUltimateMember']);
			return false;
		} elseif (!function_exists('UM') || !UM()->dependencies()->ultimatemember_active_check()) {
			add_action('admin_notices', [$this, 'adminNoticeUltimateMember']);
			return false;
		} elseif (version_compare(ultimatemember_version, self::MINIMUM_UM_VERSION, '<')) {
			add_action('admin_notices', [$this, 'adminNoticeUltimateMember']);
			return false;
		}

		return true;
	}

	/**
	 * Initialize the plugin
	 *
	 * Load the plugin only after other required plugins are loaded.
	 * Load the files required to run the plugin.
	 *
	 * Fired by `plugins_loaded` action hook.
	 */
	public function init()
	{
		$this->errors = new ErrorMapper();
		$this->handler = new Handler($this->logger, $this->errors);

		$this->login   = new Login($this->handler, $this->errors);
		$this->account = new Account($this->handler, $this->errors);
		$this->admin   = new Admin($this->handler);

		$this->i18n();
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required PHP version.
	 */
	public function adminNoticePHPVersion()
	{

		if (isset($_GET['activate'])) unset($_GET['activate']);

		$message = sprintf(
			/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__('"%1$s" requires "%2$s" version %3$s or greater.', 'phobos-auth'),
			'<strong>' . esc_html__(self::NAME, 'phobos-auth') . '</strong>',
			'<strong>' . esc_html__('PHP', 'phobos-auth') . '</strong>',
			self::MINIMUM_PHP_VERSION
		);

		printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
	}

	/**
	 * Admin notice
	 *
	 * Warning when the site doesn't have a minimum required UltimateMember version.
	 */
	public function adminNoticeUltimateMember()
	{

		if (isset($_GET['activate'])) unset($_GET['activate']);

		$message = sprintf(
			/* translators: 1: Plugin name 2: UltimateMember 3: Required UM version */
			esc_html__('"%1$s" requires "%2$s" version %3$s or greater to be installed and activated.', 'phobos-auth'),
			'<strong>' . esc_html__(self::NAME, 'phobos-auth') . '</strong>',
			'<a href="https://wordpress.org/plugins/ultimate-member"><strong>' . esc_html__('UltimateMember', 'phobos-auth') . '</strong></a>',
			self::MINIMUM_UM_VERSION
		);

		printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
	}

	public function adminNoticePhobos()
	{

		if (isset($_GET['activate'])) unset($_GET['activate']);

		$message = sprintf(
			/* translators: 1: Plugin name 2: Phobos 3: Required Phobos Version*/
			esc_html__('"%1$s" requires %2$s plugin, version %3$s or greater, to be installed and activated to work properly.', 'phobos-auth'),
			'<strong>' . esc_html__(self::NAME, 'phobos-auth') . '</strong>',
			'<a href="https://tmsa.ar/phobos"><strong>' . esc_html__('Phobos', 'phobos-auth') . '</strong></a>',
			self::MINIMUM_PHOBOS_VERSION
		);

		printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
	}

	/**
	 * Run when the plugin is activated.
	 */
	public function activate()
	{
		flush_rewrite_rules();
	}

	/**
	 * Run when the plugin is deactivated.
	 */
	public function deactivate()
	{
		// Nothing here yet!
	}
}

$GLOBALS['phobos_auth'] = PhobosAuth::instance();
