<?php

if (!defined('MAIN_DB_PREFIX')) {
	define('MAIN_DB_PREFIX', 'llx_');
}

if (!function_exists('isModEnabled')) {
	function isModEnabled($module)
	{
		return $module === 'multicompany';
	}
}

require_once __DIR__ . '/../lib/functions/functions.configuration.php';

class FakeVerifactuEntitiesDatabase
{
	private $entityCount;
	public $lastQuery = '';

	public function __construct($entityCount)
	{
		$this->entityCount = $entityCount;
	}

	public function query($sql)
	{
		$this->lastQuery = $sql;
		return true;
	}

	public function fetch_object($result)
	{
		return (object) ['nb' => $this->entityCount];
	}

	public function free($result)
	{
	}
}

$conf = new stdClass();
$conf->entity = 1;
$conf->global = new stdClass();
$dolibarr_main_instance_unique_id = 'test-instance';
$db = new FakeVerifactuEntitiesDatabase(2);

$config = getSystemConfig();
if ($config['TipoUsoPosibleMultiOT'] !== 'S') {
	fwrite(STDERR, "Two active VeriFactu entities must set TipoUsoPosibleMultiOT to S\n");
	exit(1);
}

if ($config['IndicadorMultiplesOT'] !== 'S') {
	fwrite(STDERR, "Two active VeriFactu entities must set IndicadorMultiplesOT to S\n");
	exit(1);
}

if (strpos($db->lastQuery, 'e.active = 1') === false
	|| strpos($db->lastQuery, "c.name = 'MAIN_MODULE_VERIFACTU'") === false
	|| strpos($db->lastQuery, "c.value = '1'") === false) {
	fwrite(STDERR, "Only active entities with VeriFactu enabled must be counted\n");
	exit(1);
}

if (stripos($db->lastQuery, 'country') !== false) {
	fwrite(STDERR, "Entity nationality must not exclude an active VeriFactu entity\n");
	exit(1);
}

$db = new FakeVerifactuEntitiesDatabase(1);
$config = getSystemConfig();
if ($config['TipoUsoPosibleMultiOT'] !== 'N') {
	fwrite(STDERR, "One active VeriFactu entity must set TipoUsoPosibleMultiOT to N\n");
	exit(1);
}

if ($config['IndicadorMultiplesOT'] !== 'N') {
	fwrite(STDERR, "One active VeriFactu entity must set IndicadorMultiplesOT to N\n");
	exit(1);
}

echo "Multiple active VeriFactu entity tests passed\n";
