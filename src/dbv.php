<?php
/**
 * Created by PhpStorm.
 * User: danielhemmerich
 * Date: 26.05.18
 * Time: 09:44
 */

/*
 * no other database-changes should exist in the /database/v0 folder
 * you should start with your changes in the /database/v1 folder
 *
 * ToDo
 * add pre- and post-sql statements to execute before and after deployment
 */


// setup
error_reporting(E_ALL);

// init arguments
$arguments         = [];
$parameter         = [];
$validateParameter = json_decode(
	file_get_contents(__DIR__ . '/parameter.json'),
	true
);

// validate arguments
foreach ($argv as $arg) {
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

return $deploy->deploy(
	$arguments['version'] ?? $deploy->getHighestPossibleVersion(),
	$arguments['mode'] ?? \dbv\Deploy::MODE_INTEGRITY

);