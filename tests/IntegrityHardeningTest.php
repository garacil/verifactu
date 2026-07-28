<?php

if (!defined('MAIN_DB_PREFIX')) {
	define('MAIN_DB_PREFIX', 'llx_');
}
if (!defined('LOG_ERR')) {
	define('LOG_ERR', 3);
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
	}
}

require_once __DIR__ . '/../lib/functions/functions.configuration.php';

class FakeIntegrityDatabase
{
	private $rows;
	private $position = 0;

	public function __construct(array $rows)
	{
		$this->rows = $rows;
	}

	public function query($sql)
	{
		$this->position = 0;
		return true;
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
		return '';
	}

	public function plimit($limit)
	{
		return ' LIMIT ' . (int) $limit;
	}
}

$conf = (object) array('entity' => 1);

if (normalizeVerifactuTaxIdentifier(' b-12345678 ') !== 'B12345678') {
	fwrite(STDERR, "Taxpayer identifiers must be normalized consistently\n");
	exit(1);
}

$db = new FakeIntegrityDatabase(array(array('found' => 1)));
if (!hasVerifactuFiscalRecords(1)) {
	fwrite(STDERR, "An existing fiscal fingerprint must lock the taxpayer identity\n");
	exit(1);
}

$db = new FakeIntegrityDatabase(array());
if (hasVerifactuFiscalRecords(1)) {
	fwrite(STDERR, "An entity without fiscal fingerprints must remain configurable\n");
	exit(1);
}

echo "Integrity hardening tests passed\n";
