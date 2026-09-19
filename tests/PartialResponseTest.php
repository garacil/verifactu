<?php

/**
 * Regression test for the partial AEAT response handling reported in PR #36.
 *
 * RespuestaSuministro.xsd allows a ParcialmenteCorrecto submission to carry
 * rejected lines, so the global state alone cannot mark an invoice as sent.
 */

require_once __DIR__ . '/../lib/functions/functions.response.php';

function responseWithStatus($globalStatus, $lineStatuses = array())
{
	$response = (object) array('EstadoEnvio' => $globalStatus);
	if (!empty($lineStatuses)) {
		$lines = array();
		foreach ($lineStatuses as $status) {
			$lines[] = (object) array('EstadoRegistro' => $status);
		}
		// A single line arrives as an object, several as an array.
		$response->RespuestaLinea = count($lines) === 1 ? $lines[0] : $lines;
	}
	return $response;
}

$cases = array(
	array('Correcto', array(), true),
	array('Incorrecto', array('Incorrecto'), false),
	array('ParcialmenteCorrecto', array('Correcto'), true),
	array('ParcialmenteCorrecto', array('AceptadoConErrores'), true),
	array('ParcialmenteCorrecto', array('Incorrecto'), false),
	array('ParcialmenteCorrecto', array('Correcto', 'Incorrecto'), false),
	array('ParcialmenteCorrecto', array(), false),
);

foreach ($cases as $index => $case) {
	$result = isAEATResponseAccepted(responseWithStatus($case[0], $case[1]));
	if ($result !== $case[2]) {
		fwrite(STDERR, 'Failed case ' . ($index + 1) . ': ' . $case[0] . PHP_EOL);
		exit(1);
	}
}

if (isAEATResponseAccepted(null) || isAEATResponseAccepted((object) array())) {
	fwrite(STDERR, "A missing response must never be accepted\n");
	exit(1);
}

echo "All partial response tests passed\n";
