<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 26.05.18
 * Time: 09:44
 */

/*
 * ToDo
 *
 * add parallelization
 *
 * the fast-option makes a copy of the tables to update and update the copy and after that rename and delete the old tables
 * it first makes the changes on all copies and if no errors rename them at the end and delete the originals
 * in case of error delete the copies and keep the originals
 */

try {
	// setup
	error_reporting(E_ALL);
	ini_set('max_input_time', '1024');
	set_time_limit(60 * 60 * 3);
	date_default_timezone_set('Europe/Berlin');
	ini_set('memory_limit', '-1');
	ignore_user_abort(true);

	// init arguments
	$arguments         = [];
	$parameter         = [];
	$validateParameter = json_decode(
		file_get_contents(__DIR__ . '/parameter.json'),
		true
	);

	// validate arguments
	foreach ($argv as $arg) {
		// help
		if (preg_match(
			'/(-h|--help)/i',
			$arg
		)) {
			$helpText = 'Usage: "php dbv.php {argument-name=argument-value}". '
				. "\nThe following Arguments are supprted (case-insensitive):\n";
			foreach ($validateParameter as $sKey => $validParameter) {
				$helpText .= 'Name: ' . $sKey . "\n";
				$helpText .= 'Values: ' . $validParameter['values'] . "\n";
				$helpText .= 'Mandatory: ' . $validParameter['mandatory'] . "\n";
				$helpText .= 'Description: ' . $validParameter['description'] . "\n\n";
			}
			exit($helpText);
		}

		preg_match(
			'/(.+)=(.+)/mi',
			$arg,
			$parameter
		);
		if (3 != count($parameter)) {
			continue;
		}
		if (!isset($validateParameter[$parameter[1]])) {
			continue;
		}
		if (1 !== preg_match(
				'/' . $validateParameter[$parameter[1]]['values'] . '/mi',
				$parameter[2]
			)) {
			throw new Exception(
				'Parameter "'
				. $parameter[1]
				. '" with value: "'
				. $parameter[2]
				. '" does not match the requirements: "'
				. $validateParameter[$parameter[1]]['values']
				. '".'
			);
		}
		$arguments[strtolower($parameter[1])] = $parameter[2];
	}

// validate that all mandatory parameter are set
	foreach ($validateParameter as $parameterName => $validParameter) {
		if ('true' != $validParameter['mandatory']) {
			continue;
		}
		if (!isset($arguments[$parameterName])) {
			throw new Exception('Mandatory Parameter "' . $parameterName . '" is missing.');
		}
	}

// deploy
	require_once __DIR__ . '/Deploy.php';
	$deploy = new \dbv\Deploy(
		$arguments['config']
	);

	if (!$deploy->deploy(
		$arguments['version'] ?? $deploy->getHighestPossibleVersion(),
		$arguments['mode'] ?? \dbv\Deploy::MODE_INTEGRITY

	)) {
		throw new Exception('Deployment failed!');
	}
} catch (Exception $exception) {
	echo($exception . "\n");
	echo "------------------------------\n";
	exit(42);
}
exit(0);