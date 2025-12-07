<?php
if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');				// Do not load object $user
if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');				// Do not load object $menu
if (! defined('NOLOGIN'))            define('NOLOGIN', '1');				// Do not load object $user
// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Configure response headers
top_httphead('text/json');

// Include libraries
dol_include_once('/verifactu/lib/verifactu.lib.php');
dol_include_once('/verifactu/core/modules/modVerifactu.class.php');
$verifactuModule = new ModVerifactu($db);
try {
	// Get the module's current directory
	$moduleDirectory = __DIR__;

	// Calculate integrity hash
	$integrityHash = calculateVerifactuIntegrityChecksums($moduleDirectory);

	if ($integrityHash === false) {
		// Error calculating hash

		http_response_code(500);

		echo json_encode([
			'status' => 'error',
			'message' => 'Could not calculate integrity hash',
			'hash' => null
		]);
	} else {
		// License is no longer required
		$licenseKey = null;

		// Check if private key is configured
		$hasPrivateKey = !empty($conf->global->VERIFACTU_CERTIFICATE_PRIVATE_KEY);

		// Check if invoices have already been sent to VeriFactu
		$sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "facture f";
		$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
		$sql .= " WHERE 1=1 ";
		$sql .= " AND fe.verifactu_csv_factura IS NOT NULL AND fe.verifactu_csv_factura != ''";
		$sql .= " AND fe.verifactu_estado IS NOT NULL AND fe.verifactu_estado != ''";
		$sql .= " LIMIT 1";

		$resql = $db->query($sql);
		$invoicesSent = 0;
		if ($resql) {
			$obj = $db->fetch_object($resql);
			$invoicesSent = intval($obj->count);
			$db->free($resql);
		}

		// Count invoices with VeriFactu errors
		$sqlErrors = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "facture f";
		$sqlErrors .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
		$sqlErrors .= " WHERE fe.verifactu_error IS NOT NULL AND fe.verifactu_error != ''";
		$sqlErrors .= " AND f.fk_statut > 0";
		$sqlErrors .= " LIMIT 1";

		$resqlErrors = $db->query($sqlErrors);
		$invoicesWithErrors = 0;
		if ($resqlErrors) {
			$objErrors = $db->fetch_object($resqlErrors);
			$invoicesWithErrors = intval($objErrors->count);
			$db->free($resqlErrors);
		}

		// Check critical configuration
		$hasNIF = !empty($conf->global->VERIFACTU_HOLDER_NIF);
		$hasCompanyName = !empty($conf->global->VERIFACTU_HOLDER_COMPANY_NAME);

		// Hash calculated successfully
		http_response_code(200);

		echo json_encode([
			'status' => 'success',
			'message' => 'Integrity hash calculated successfully',
			'hash' => $integrityHash,
			'module' => 'verifactu',
			'version' => $verifactuModule->version,
			'timestamp' => time(),
			'domain' => getVerifactuDomain(),
			'directory' => basename($moduleDirectory),
			'license' => $licenseKey,
			'has_private_key' => $hasPrivateKey,
			'invoices_sent' => $invoicesSent,
			'invoices_with_errors' => $invoicesWithErrors,
			'local_certificate' => true,
			'configuration' => [
				'has_nif' => $hasNIF,
				'has_company_name' => $hasCompanyName,
				'environment' => getEnvironment()
			]
		]);
	}
} catch (Exception $e) {
	// Error during execution

	http_response_code(500);

	echo json_encode([
		'status' => 'error',
		'message' => 'Error en el cálculo de integridad: ' . $e->getMessage(),
		'hash' => null,
		'trace' => $e->getTraceAsString()
	]);
}
