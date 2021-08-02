<?php

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

class SettingData
{
	/**
	 * The name for this setting
	 * @var string
	 */
	public $name;

	/**
	 * The type of variable used for this setting.
	 * @var string
	 */
	public $type;

	/**
	 * The initial value to be used for this setting.
	 * @var mixed
	 */
	public $initialValue;

	/**
	 * The description for this setting.
	 * @var string
	 */
	public $description = '';

	public $sanitize;
	public $validate;

	/**
	 * Whether this setting is required.
	 * @var boolean
	 */
	public $isRequired = false;

	private static $METHOD_KEYS   = ["validate", "sanitize"];
	private static $REQUIRED_KEYS = ["type", "initialValue"];
	private static $VALID_KEYS    = ["type", "initialValue", "isRequired", "description", "validate", "sanitize"];
	private static $KEY_TYPES     = ["type" => 'string', "isRequired" => 'boolean', "description" => 'string'];

	/**
	 * Constructor for SettingData.
	 *
	 * @param string $name The name for this setting.
	 * @param array $data The information about this setting:
	 * 		[type, description, initialValue, isRequired, validate, sanitize].
	 */
	public function __construct($name, $data)
	{
		$this->name = $name;

		$this->sanitize = function ($value) {
			return $value;
		};
		$this->validate = function () {
			return true;
		};

		Phobos\filterKeys($data, self::$VALID_KEYS);
		Phobos\noMissingFields($data, self::$REQUIRED_KEYS);

		foreach (self::$KEY_TYPES as $key => $type) {
			if (array_key_exists($key, $data)) {
				if (gettype($data[$key]) !== $type) {
					throw new Exception("Mismatched type for property '" . $key . "'. Expected " . $type . ", got " . gettype($data[$key]));
				}

				$this->$key = $data[$key];
			}
		}

		foreach (self::$METHOD_KEYS as $key) {
			if (!empty($data[$key])) {
				if (!is_callable($data[$key])) {
					throw new Exception("Property '" . $key . "' is not callable.");
				}
				$this->$key = $data[$key];
			}
		}

		$this->initialValue = $data['initialValue'];
	}
}

class ProviderSettings implements JsonSerializable
{
	/** @var SettingData[] */
	protected $settings = [];

	/** @var AuthProvider */
	protected $provider;

	protected $option;

	/** @var array<mixed> */
	protected $initialValues = null;

	/**
	 * Build a new ProviderSettings.
	 *
	 * @param string $rest
	 * @param AuthProvider $provider The provider this settings belong to.
	 */
	public function init(AuthProvider $provider)
	{
		$this->provider = $provider;

		$this->option = 'phobos_auth_' . $this->provider->getName() . '_settings';

		$this->addSetting('enabled', [
			'type'         => 'boolean',
			'isRequired'   => true,
			'description'  => __('Enable this provider', 'phobos-auth'),
			'initialValue' => false,
			'sanitize' => 'rest_sanitize_boolean'
		]);
	}

	/**
	 * Register this ProviderSettings to WordPress
	 * .
	 * @return void
	 */
	public function register(bool $rest = true, bool $mock = false)
	{
		if ($this->isRegistered()) {
			throw new Exception("These settings have already been registered.");
		}

		// Options may have been registered previously.

		// $settings = get_option($option);
		// if ($settings && !empty($settings)) {
		// 	throw new Exception("An option, with key '" . $option . "', has already been set.");
		// }
		$this->setInitialValues();

		if ($rest) {
			$this->registerREST();
		}
	}

	/**
	 * Check if this ProviderSettings has already been registered to WordPress.
	 *
	 * @return boolean
	 */
	public function isRegistered()
	{
		return is_array($this->initialValues);
	}

	/**
	 * Set the options to the initial (default) values if not already set.
	 *
	 * @return void
	 */
	private function setInitialValues()
	{
		$settings = get_option($this->option, []);
		$this->initialValues = [];

		foreach ($this->settings as $name => $setting) {
			if (!array_key_exists($name, $settings)) {
				$settings[$name] = $setting->initialValue;
			}

			$this->initialValues[$name] = $setting->initialValue;
		}

		update_option($this->option, $settings);
	}

	/**
	 * Register the REST route for this settings.
	 *
	 * @return void
	 */
	private function registerREST()
	{
		$arguments = [];

		foreach ($this->settings as $name => $setting) {
			$arguments[$name] = [
				'type'              => $setting->type,
				'validate_callback' => $setting->validate,
				'sanitize_callback' => $setting->sanitize,
				'required'          => false,
			];
		}


		register_rest_route(
			'phobos/auth',
			'/settings/' . $this->provider->getName(),
			[
				[
					'methods'  => WP_REST_Server::EDITABLE,
					'callback' => [$this, 'updateREST'],
					'args'     => $arguments,
					'permission_callback' => [$this, 'permissions']
				],

				[
					'methods'  => WP_REST_Server::READABLE,
					'callback' => [$this, 'getREST'],
					'args'     => [],
					'permission_callback' => [$this, 'permissions']
				],

				'schema' => [$this, 'jsonSerialize'],
			]
		);
	}

	/**
	 * Check if the current user has the required permissions to access the API Endpoint.
	 *
	 * @return boolean
	 */
	public function permissions()
	{
		return current_user_can('manage_options');
	}

	/**
	 * Adds a setting to this ProviderSettings.
	 *
	 * @param string $name The name for this setting
	 * @param array $data The information for this setting
	 * 		[type, description, initialValue, isRequired, validate, sanitize].
	 * @return void
	 */
	public function addSetting($name, $data)
	{
		if ($this->isRegistered()) {
			throw new Exception("These settings have already been registered. New settings can't be added to it.");
		} elseif ($this->hasSetting($name)) {
			throw new Exception("Couldn't add a setting with name: '" . $name . "'. A setting has already been added with this name.");
		}

		$this->settings[$name] = new SettingData($name, $data);
	}

	/**
	 * Check if a setting has been registered with the specified name.
	 *
	 * @param string $name The name of the setting you want to check.
	 * @return boolean
	 */
	public function hasSetting($name)
	{
		return array_key_exists($name, $this->settings);
	}

	/**
	 * Gets the SettingData for a given setting.
	 *
	 * @param string $name The name of the setting.
	 * @return SettingData
	 */
	public function getSetting($name)
	{
		if (!$this->hasSetting($name)) {
			throw new Exception("Setting '" . $name . "' has not been added.");
		}

		return $this->settings[$name];
	}

	/**
	 * Serializes this ProviderSettings into a JSON Schema.
	 *
	 * @return array
	 */
	public function jsonSerialize()
	{
		$properties = [];
		// $required = [];

		foreach ($this->settings as $name => $data) {
			$properties[$name] = [
				'type' => $data->type,
				'description' => $data->description,
			];

			// if ($data->isRequired) {
			// 	array_push($required, $name);
			// }
		}

		return [
			"\$schema"    => "http://json-schema.org/draft-04/schema#",
			"title"       => $this->provider->getName() . '-settings',
			"description" => "Settings for " . $this->provider->getDisplayName() . " auth provider.",
			"type"        => "object",
			"properties"  => $properties,
			// "required"    => $required // They are required internally but not for the REST Request
		];
	}

	/**
	 * Return a list of all the SettingData registered for this ProviderSettings.
	 *
	 * @return SettingData[]
	 */
	public function getSettings()
	{
		return array_values($this->settings);
	}

	/**
	 * An array of values you want to update [$name => $value].
	 * Note that no validation or sanitization is performed.
	 *
	 * @param array $values
	 * @return void
	 */
	public function updateValues($values)
	{
		if (!$this->isRegistered()) {
			throw new Exception("Can't update values, this provider hasn't been registered.");
		}

		$options = get_option($this->option, $this->initialValues);

		foreach ($this->settings as $setting) {
			if (array_key_exists($setting->name, $values) && isset($values[$setting->name])) {
				$options[$setting->name] = $values[$setting->name];
			}
		}

		update_option($this->option, $options);
	}

	/**
	 * Get an individual value by its name.
	 *
	 * @param string $name The name for the setting you want to retrieve.
	 * @return void
	 */
	public function getValue($name, bool $bypass = false)
	{
		if (!$this->isRegistered() && !$bypass) {
			throw new Exception("Can't get the value, this provider hasn't been registered.");
		}

		$options = get_option($this->option, $this->initialValues);

		return $options[$name];
	}

	/**
	 * Get all the values as an array.
	 *
	 * @return array
	 */
	public function getAllValues(bool $bypass = false)
	{
		if (!$this->isRegistered() && !$bypass) {
			throw new Exception("Can't get the values, this provider hasn't been registered.");
		}

		return get_option($this->option, $this->initialValues);
	}

	/**
	 * Returns true if all the required values are set (not null)
	 *
	 * @return boolean
	 */
	public function hasAllRequiredValues()
	{
		$options = get_option($this->option, $this->initialValues);

		foreach ($this->settings as $name => $data) {
			if ($data->isRequired && !isset($options[$name])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Update the values through a REST request (POST, PUT or UPDATE).
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function updateREST(WP_REST_Request $request)
	{
		if (!$this->isRegistered()) {
			return rest_ensure_response(new WP_Error('provider_not_registered', __("This provider hasn't been registered yet.", "phobos-auth"), ["status" => 404]));
		}

		$values = [];

		foreach ($this->settings as $setting) {
			$values[$setting->name] = $request->get_param($setting->name);
		}

		$this->provider->logger->info(Phobos\dump($values));

		$this->updateValues($values);
		return $this->getREST();
	}



	/**
	 * Get all the values as a REST request (GET).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function getREST()
	{
		if (!$this->isRegistered()) {
			return rest_ensure_response(new WP_Error('provider_not_registered', __("This provider hasn't been registered yet.", "phobos-auth"), ["status" => 404]));
		}

		return rest_ensure_response(get_option($this->option, $this->initialValues));
	}
}
