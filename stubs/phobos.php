<?php

namespace Phobos {
	class Phobos {
		const VERSION = 'x.x.x';
		const FILE = __FILE__;
	}

	class Logger {
		/**
		 * Create a new logger
		 *
		 * @param string $path
		 * @param string $filename [optional]
		 */
		public function __construct(string $path, string $filename = 'debug.log'){}

		/**
		 * Logs an error to the log file
		 *
		 * @param string $message
		 * @param string|null $code
		 * @return boolean
		 */
		public function error(string $message, string $code = null) {}

		/**
		 * Logs a warning to the log file
		 *
		 * @param string $message
		 * @param string|null $code
		 * @return boolean
		 */
		public function warning(string $message, string $code = null) {}

		/**
		 * Logs an information message to the log file
		 *
		 * @param string $message
		 * @param string|null $code
		 * @return boolean
		 */
		public function info(string $message, string $code = null) {}
	}

	/**
	 * Dump the contents of a variable to a string
	 * 
	 * @param mixed $var The variable to dump
	 * @return string The resulting string
	 */
	function dump($var) {}

	/**
	 * Get the absolute path, resolves ../ and ./, removes duplicated separators etc.
	 *
	 * @param string $path
	 * @return string
	 */
	function getAbsolutePath(string $path){}

	/**
	 * Returns true if the plugin is active
	 * 
	 * @param string $file Absolute path to the plugin index file
	 * @return bool True if the plugin is active, false otherwise
	 */
	function isPluginActive (string $file) {}

	/**
	 * Get the relative path from the first argument to the second argument
	 *
	 * @param string $root
	 * @param string $to
	 * @return string
	 */
	function getRelativePath(string $root, string $to) {}

	/**
	 * Removes all unwanted keys from the array.
	 *
	 * @param array $array The array you want to modify
	 * @param array $keep A list of keys you want to keep.
	 * @return void
	 */
	function filterKeys($array, $keep){}


	/**
	 * Check that the fields requested are present in the array.
	 *
	 * @param array $array The array you want to check
	 * @param array $fields The fields that need to be present in the array.
	 * @return void
	 */
	function noMissingFields($array, $fields) {}

	$GLOBAL['phobos'] = new Phobos();
}
