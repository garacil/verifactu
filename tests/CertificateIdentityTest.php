<?php

/**
 * Regression test for the certificate and taxpayer identity hardening
 * reported in PR #41.
 *
 * Certificates are generated during the run, so the X.509 validity window is
 * checked against real containers instead of a simulation.
 */

if (!defined('MAIN_DB_PREFIX')) {
	define('MAIN_DB_PREFIX', 'llx_');
}
if (!defined('LOG_ERR')) {
	define('LOG_ERR', 3);
}
if (!defined('LOG_DEBUG')) {
	define('LOG_DEBUG', 7);
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
	}
}
if (!function_exists('dol_now')) {
	function dol_now()
	{
		return time();
	}
}

class FakeTranslate
{
	public function trans($key)
	{
		return $key;
	}
	public function load($domain)
	{
	}
}

class FakeChainDatabase
{
	private $rows;
	private $position = 0;
	public $failQuery = false;

	public function __construct(array $rows, $failQuery = false)
	{
		$this->rows = $rows;
		$this->failQuery = $failQuery;
	}

	public function query($sql)
	{
		$this->position = 0;
		return !$this->failQuery;
	}

	public function fetch_object($result)
	{
		if (!isset($this->rows[$this->position])) {
			return false;
		}
		return (object) $this->rows[$this->position++];
	}

	public function free($result)
	{
	}

	public function lasterror()
	{
		return 'simulated failure';
	}

	public function plimit($limit)
	{
		return ' LIMIT ' . (int) $limit;
	}
}

$langs = new FakeTranslate();
$conf = (object) array('entity' => 1);
$conf->global = new stdClass();
$db = new FakeChainDatabase(array());

require_once __DIR__ . '/../lib/functions/functions.configuration.php';
require_once __DIR__ . '/../lib/functions/functions.certificates.php';

$total = 0;
$passed = 0;
function check($condition, $label)
{
	global $total, $passed;
	$total++;
	if ($condition) {
		$passed++;
		echo "  PASS: " . $label . "\n";
		return;
	}
	echo "  FAIL: " . $label . "\n";
}

/**
 * Builds a self-signed PEM bundle with an explicit validity window.
 */
function buildCertificate($dir, $name, $startDate, $endDate)
{
	$workDir = $dir . '/' . $name;
	mkdir($workDir, 0700, true);
	$config = "[ca]\ndefault_ca = CA_default\n[CA_default]\ndir = .\ndatabase = ./index.txt\n"
		. "serial = ./serial\nnew_certs_dir = .\ndefault_md = sha256\npolicy = policy_any\n"
		. "email_in_dn = no\nrand_serial = no\nunique_subject = no\n[policy_any]\ncommonName = supplied\n"
		. "[req]\ndistinguished_name = dn\nprompt = no\n[dn]\nCN = VeriFactu Test\n";
	file_put_contents($workDir . '/ca.cnf', $config);
	file_put_contents($workDir . '/index.txt', '');
	file_put_contents($workDir . '/serial', "01\n");

	$commands = array(
		'openssl req -new -newkey rsa:2048 -nodes -keyout key.pem -out csr.pem -config ca.cnf',
		'openssl ca -batch -selfsign -config ca.cnf -keyfile key.pem -in csr.pem -out cert.pem'
			. ' -startdate ' . $startDate . ' -enddate ' . $endDate . ' -notext',
	);
	foreach ($commands as $command) {
		exec('cd ' . escapeshellarg($workDir) . ' && ' . $command . ' 2>/dev/null', $output, $code);
		if ($code !== 0) {
			return null;
		}
	}

	$bundle = $workDir . '/bundle.pem';
	file_put_contents($bundle, file_get_contents($workDir . '/cert.pem') . "\n" . file_get_contents($workDir . '/key.pem'));
	return $bundle;
}

echo "PR #41: X.509 validity window is enforced\n";

$tempDir = sys_get_temp_dir() . '/verifactu_cert_identity_' . getmypid();
mkdir($tempDir, 0700, true);

$expired = buildCertificate($tempDir, 'expired', '20200101000000Z', '20200102000000Z');
$future = buildCertificate($tempDir, 'future', '20400101000000Z', '20400102000000Z');
$current = buildCertificate($tempDir, 'current', gmdate('Ymd000000\Z', strtotime('-1 day')), gmdate('Ymd000000\Z', strtotime('+2 days')));

if ($expired === null || $future === null || $current === null) {
	echo "  SKIP: the openssl binary could not generate the test certificates\n";
} else {
	check(validateCertificateAndKey($expired) === false, 'an expired certificate is rejected');
	check(
		isset($GLOBALS['verifactu_cert_error']) && $GLOBALS['verifactu_cert_error'] === 'VERIFACTU_CERT_ERROR_EXPIRED',
		'the expiry is reported with its own message, not as a key mismatch'
	);
	check(validateCertificateAndKey($future) === false, 'a certificate whose validity has not begun is rejected');
	check(validateCertificateAndKey($current) === true, 'a certificate inside its validity window is accepted');
}

echo "\nPR #41: the taxpayer identity is locked once the chain has records\n";

check(normalizeVerifactuTaxIdentifier(' b-12345678 ') === 'B12345678', 'identifiers are normalized before comparing');
check(normalizeVerifactuTaxIdentifier('B12345678') === normalizeVerifactuTaxIdentifier('b 12.345.678'), 'separators and case never make two identities differ');

$db = new FakeChainDatabase(array(array('rowid' => 1)));
check(hasVerifactuFiscalRecords(1) === true, 'an existing fingerprint locks the taxpayer identity');

$db = new FakeChainDatabase(array());
check(hasVerifactuFiscalRecords(1) === false, 'an entity without fingerprints stays configurable');

$db = new FakeChainDatabase(array(), true);
check(hasVerifactuFiscalRecords(1) === true, 'the identity stays locked when the chain cannot be inspected');

$setup = file_get_contents(__DIR__ . '/../admin/setup.php');
check(
	strpos($setup, 'hasVerifactuFiscalRecords((int) $conf->entity)') !== false
		&& strpos($setup, 'VERIFACTU_TAX_IDENTITY_LOCKED') !== false,
	'the setup form refuses a NIF change before storing anything'
);

$certificates = file_get_contents(__DIR__ . '/../lib/functions/functions.certificates.php');
check(substr_count($certificates, '@chmod($bundleFile, 0600)') === 2, 'both PEM bundles are written with owner-only permissions');

exec('rm -rf ' . escapeshellarg($tempDir));

echo "\nTotal: " . $total . " | Passed: " . $passed . " | Failed: " . ($total - $passed) . "\n";
exit($total === $passed ? 0 : 1);
