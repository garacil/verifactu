<?php

/**
 * Regression tests for the writes made through the independent connection (issue #57)
 *
 * Covers:
 *  - saveVerifactuSuccessData() and saveVerifactuErrorData() stored the AEAT
 *    response with CommonObject::insertExtraFields() on an independent
 *    connection. Since Dolibarr 23 that method also runs
 *    "UPDATE llx_facture SET fk_user_modif = ..." on the parent table, a row the
 *    validation transaction of the main connection still holds. On
 *    MySQL/MariaDB every validation waited innodb_lock_wait_timeout (50 s by
 *    default) before going on. On MySQL/MariaDB the response is now written
 *    straight into llx_facture_extrafields, converted as insertExtraFields()
 *    does, and the independent session gives up quickly when the row is held
 *    anyway. Other databases (PostgreSQL) keep the insertExtraFields() path.
 *
 * Run with: php tests/IndependentConnectionLockTest.php
 */

error_reporting(E_ALL);

$passed = 0;
$failed = 0;
$total = 0;

function assert_test(bool $condition, string $message, &$passed, &$failed, &$total): void
{
    $total++;
    if ($condition) {
        $passed++;
        echo "  PASS: $message\n";
    } else {
        $failed++;
        echo "  FAIL: $message\n";
    }
}

$moduleRoot = dirname(__DIR__);

// functions.response.php only needs these to be loadable outside Dolibarr.
if (!defined('LOG_ERR')) {
    define('LOG_ERR', 3);
}
if (!defined('LOG_WARNING')) {
    define('LOG_WARNING', 4);
}
if (!defined('LOG_DEBUG')) {
    define('LOG_DEBUG', 7);
}
if (!defined('MAIN_DB_PREFIX')) {
    define('MAIN_DB_PREFIX', 'llx_');
}
if (!function_exists('dol_syslog')) {
    function dol_syslog($message, $level = LOG_DEBUG)
    {
    }
}
if (!class_exists('Facture')) {
    /**
     * Records what the former insertExtraFields() path does with the invoice.
     */
    class Facture
    {
        public static $instances = array();
        public static $fetchResult = 1;
        public static $insertResult = 1;
        public $db;
        public $id;
        public $ref = 'FA-TEST';
        public $array_options = array();
        public $errors = array();
        public $insertCalls = 0;

        public function __construct($db = null)
        {
            $this->db = $db;
            self::$instances[] = $this;
        }

        public function fetch($id)
        {
            $this->id = $id;
            return self::$fetchResult;
        }

        public function insertExtraFields()
        {
            $this->insertCalls++;
            return self::$insertResult;
        }
    }
}

if (!class_exists('ExtraFields')) {
    /**
     * Returns the invoice extrafields definition set in $definition.
     */
    class ExtraFields
    {
        public static $definition = array();
        public $attributes = array();

        public function __construct($db)
        {
        }

        public function fetch_name_optionals_label($elementtype)
        {
            $this->attributes[$elementtype] = self::$definition;
            return array();
        }
    }
}

/**
 * Records the queries it receives and answers the two SELECTs of the writer.
 */
class FakeIndependentDb
{
    public $type;
    public $queries = array();
    public $invoiceVisible = true;
    public $rowExists = true;
    public $failWrites = false;

    public function __construct($type = 'mysqli')
    {
        $this->type = $type;
    }

    public function query($sql)
    {
        $this->queries[] = $sql;
        if (stripos($sql, 'SELECT rowid FROM llx_facture_extrafields') === 0) {
            return array('rows' => $this->rowExists ? array((object) array('rowid' => 7)) : array());
        }
        if (stripos($sql, 'SELECT rowid FROM llx_facture ') === 0) {
            return array('rows' => $this->invoiceVisible ? array((object) array('rowid' => 41)) : array());
        }
        if ($this->failWrites && preg_match('/^(UPDATE|INSERT)/i', $sql)) {
            return false;
        }
        return true;
    }

    public function fetch_object(&$resql)
    {
        return is_array($resql) ? array_shift($resql['rows']) : null;
    }

    public function free($resql)
    {
    }

    public function escape($value)
    {
        return str_replace("'", "''", (string) $value);
    }

    public function idate($timestamp)
    {
        return gmdate('Y-m-d H:i:s', (int) $timestamp);
    }

    public function lasterror()
    {
        return 'fake error';
    }
}

require_once $moduleRoot . '/lib/functions/functions.response.php';

echo "=== Independent Connection Lock Regression Tests (issue #57) ===\n\n";

// Invoice extrafields definition, as in ExtraFields::$attributes['facture']
$attributes = array(
    'type' => array(
        'verifactu_inicio_separador1' => 'separate',
        'verifactu_estado' => 'varchar',
        'verifactu_error' => 'text',
        'verifactu_csv_factura' => 'text',
        'verifactu_fecha_factura' => 'date',
        'verifactu_ultimafecha_modificacion' => 'datetime',
        'verifactu_entorno' => 'select',
    ),
);

// ---------------------------------------------------------------------------
// getVerifactuExtraFieldSqlValue()
// ---------------------------------------------------------------------------
echo "getVerifactuExtraFieldSqlValue(): same conversion as insertExtraFields()\n";

$db = new FakeIndependentDb();
assert_test(getVerifactuExtraFieldSqlValue($db, 'text', null) === 'null', 'null is stored as NULL', $passed, $failed, $total);
assert_test(getVerifactuExtraFieldSqlValue($db, 'text', '') === 'null', 'an empty string is stored as NULL (the error field is cleared this way)', $passed, $failed, $total);
assert_test(getVerifactuExtraFieldSqlValue($db, 'varchar', 'Enviada') === "'Enviada'", 'a string is quoted', $passed, $failed, $total);
assert_test(getVerifactuExtraFieldSqlValue($db, 'text', "d'Ors") === "'d''Ors'", 'a string is escaped with the handler', $passed, $failed, $total);
assert_test(getVerifactuExtraFieldSqlValue($db, 'select', '0') === "'0'", "'0' is kept, not turned into NULL", $passed, $failed, $total);
assert_test(getVerifactuExtraFieldSqlValue($db, 'date', 1794528000) === "'2026-11-13 00:00:00'", 'a date timestamp goes through idate()', $passed, $failed, $total);
assert_test(getVerifactuExtraFieldSqlValue($db, 'datetime', '1794593903') === "'2026-11-13 18:18:23'", 'a numeric string datetime goes through idate()', $passed, $failed, $total);
assert_test(getVerifactuExtraFieldSqlValue($db, 'date', '2026-11-13 00:00:00 UTC') === "'2026-11-13 00:00:00'", 'a date string is parsed with strtotime() first', $passed, $failed, $total);
assert_test(getVerifactuExtraFieldSqlValue($db, 'date', 'not a date') === 'null', 'an unparsable date is stored as NULL', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// writeVerifactuInvoiceExtraFields()
// ---------------------------------------------------------------------------
echo "\nwriteVerifactuInvoiceExtraFields(): extrafields row already there\n";

$db = new FakeIndependentDb();
$ok = writeVerifactuInvoiceExtraFields($db, 41, array(
    'verifactu_estado' => 'Enviada',
    'verifactu_csv_factura' => 'A-TESTCSV',
    'verifactu_error' => '',
    'verifactu_fecha_factura' => 1794528000,
), $attributes);
$writes = array_values(array_filter($db->queries, function ($sql) {
    return preg_match('/^(UPDATE|INSERT|DELETE)/i', $sql);
}));
assert_test($ok === true, 'returns true', $passed, $failed, $total);
assert_test(count($writes) === 1, 'a single write statement', $passed, $failed, $total);
assert_test(
    isset($writes[0]) && $writes[0] === "UPDATE llx_facture_extrafields SET verifactu_estado = 'Enviada', verifactu_csv_factura = 'A-TESTCSV', verifactu_error = null, verifactu_fecha_factura = '2026-11-13 00:00:00' WHERE fk_object = 41",
    'it updates only the given columns of the extrafields row',
    $passed,
    $failed,
    $total
);
$touchesParent = false;
foreach ($db->queries as $sql) {
    if (preg_match('/^(UPDATE|INSERT INTO|DELETE FROM)\s+llx_facture\b(?!_extrafields)/i', $sql) || stripos($sql, 'fk_user_modif') !== false) {
        $touchesParent = true;
    }
}
assert_test(!$touchesParent, 'nothing is written to llx_facture (the row the validation holds)', $passed, $failed, $total);

echo "\nwriteVerifactuInvoiceExtraFields(): no extrafields row yet\n";

$db = new FakeIndependentDb();
$db->rowExists = false;
$ok = writeVerifactuInvoiceExtraFields($db, 41, array('verifactu_estado' => 'Enviada', 'verifactu_entorno' => 'production'), $attributes);
$last = end($db->queries);
assert_test($ok === true, 'returns true', $passed, $failed, $total);
assert_test(
    $last === "INSERT INTO llx_facture_extrafields (fk_object, verifactu_estado, verifactu_entorno) VALUES (41, 'Enviada', 'production')",
    'it inserts the row for the invoice',
    $passed,
    $failed,
    $total
);

// Same as insertExtraFields(): NOT NULL columns of fields that are mandatory
// in other entities get a default value when the row is created
$db = new FakeIndependentDb();
$db->rowExists = false;
$withMandatory = $attributes;
$withMandatory['mandatoryfieldsofotherentities'] = array('otro_texto' => 'varchar', 'otro_importe' => 'price', 'verifactu_estado' => 'varchar');
$ok = writeVerifactuInvoiceExtraFields($db, 41, array('verifactu_estado' => 'Enviada'), $withMandatory);
$last = end($db->queries);
assert_test(
    $ok === true && $last === "INSERT INTO llx_facture_extrafields (fk_object, verifactu_estado, otro_texto, otro_importe) VALUES (41, 'Enviada', '', 0)",
    'fields mandatory in other entities get the default insertExtraFields() gives them',
    $passed,
    $failed,
    $total
);

$db = new FakeIndependentDb();
$ok = writeVerifactuInvoiceExtraFields($db, 41, array('verifactu_estado' => 'Enviada'), $withMandatory);
$last = end($db->queries);
assert_test(
    $ok === true && $last === "UPDATE llx_facture_extrafields SET verifactu_estado = 'Enviada' WHERE fk_object = 41",
    'an existing row keeps the values of the other fields',
    $passed,
    $failed,
    $total
);

echo "\nwriteVerifactuInvoiceExtraFields(): guards\n";

$db = new FakeIndependentDb();
$db->invoiceVisible = false;
$ok = writeVerifactuInvoiceExtraFields($db, 41, array('verifactu_estado' => 'Enviada'), $attributes);
$writes = array_filter($db->queries, function ($sql) {
    return preg_match('/^(UPDATE|INSERT|DELETE)/i', $sql);
});
assert_test($ok === false && count($writes) === 0, 'an invoice this connection cannot see is not written, so the caller falls back', $passed, $failed, $total);

$db = new FakeIndependentDb();
$ok = writeVerifactuInvoiceExtraFields($db, 41, array(
    'verifactu_inicio_separador1' => 'x',
    'not_a_verifactu_field' => 'x',
    'verifactu_estado; DROP TABLE llx_facture' => 'x',
    'verifactu_estado' => 'Enviada',
), $attributes);
$last = end($db->queries);
assert_test(
    $ok === true && $last === "UPDATE llx_facture_extrafields SET verifactu_estado = 'Enviada' WHERE fk_object = 41",
    'separators, unknown attributes and unexpected names are ignored',
    $passed,
    $failed,
    $total
);

$db = new FakeIndependentDb();
$db->failWrites = true;
assert_test(
    writeVerifactuInvoiceExtraFields($db, 41, array('verifactu_estado' => 'Enviada'), $attributes) === false,
    'a failed write (lock wait timeout, for instance) returns false so the caller falls back',
    $passed,
    $failed,
    $total
);

assert_test(writeVerifactuInvoiceExtraFields(new FakeIndependentDb(), 0, array('verifactu_estado' => 'x'), $attributes) === false, 'an invalid invoice id returns false', $passed, $failed, $total);

$db = new FakeIndependentDb();
assert_test(
    writeVerifactuInvoiceExtraFields($db, 41, array('not_a_verifactu_field' => 'x'), $attributes) === false && $db->queries === array(),
    'values for unknown fields only return false (nothing stored), so the caller falls back',
    $passed,
    $failed,
    $total
);
$db = new FakeIndependentDb();
assert_test(writeVerifactuInvoiceExtraFields($db, 41, array(), $attributes) === true && $db->queries === array(), 'no values: nothing to do, true', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// limitVerifactuIndependentLockWait()
// ---------------------------------------------------------------------------
echo "\nlimitVerifactuIndependentLockWait()\n";

$conf = new stdClass();
$conf->global = new stdClass();

$db = new FakeIndependentDb('mysqli');
limitVerifactuIndependentLockWait($db);
assert_test($db->queries === array('SET SESSION innodb_lock_wait_timeout = 1'), 'MySQL/MariaDB: the independent session waits 1 s at most by default', $passed, $failed, $total);

$conf->global->VERIFACTU_INDEPENDENT_LOCK_WAIT_TIMEOUT = 5;
$db = new FakeIndependentDb('mysqli');
limitVerifactuIndependentLockWait($db);
assert_test($db->queries === array('SET SESSION innodb_lock_wait_timeout = 5'), 'the wait can be changed with VERIFACTU_INDEPENDENT_LOCK_WAIT_TIMEOUT', $passed, $failed, $total);

$conf->global->VERIFACTU_INDEPENDENT_LOCK_WAIT_TIMEOUT = 0;
$db = new FakeIndependentDb('mysqli');
limitVerifactuIndependentLockWait($db);
assert_test($db->queries === array('SET SESSION innodb_lock_wait_timeout = 1'), 'never below 1 s (the minimum MySQL accepts)', $passed, $failed, $total);

$db = new FakeIndependentDb('pgsql');
limitVerifactuIndependentLockWait($db);
assert_test($db->queries === array(), 'PostgreSQL: the session is left alone (it may be the main connection)', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// storeVerifactuIndependentData()
// ---------------------------------------------------------------------------
echo "\nstoreVerifactuIndependentData(): MySQL/MariaDB\n";

$conf->global->VERIFACTU_INDEPENDENT_LOCK_WAIT_TIMEOUT = 1;
ExtraFields::$definition = $attributes; // read by the writer from the definitions
$invoice = new Facture();
$invoice->id = 41;
Facture::$instances = array();
$db = new FakeIndependentDb('mysqli');
$ok = storeVerifactuIndependentData($db, $invoice, array('verifactu_estado' => 'Enviada'));
assert_test($ok === true, 'returns true', $passed, $failed, $total);
assert_test(isset($db->queries[0]) && $db->queries[0] === 'SET SESSION innodb_lock_wait_timeout = 1', 'the lock wait is limited before anything else', $passed, $failed, $total);
assert_test(end($db->queries) === "UPDATE llx_facture_extrafields SET verifactu_estado = 'Enviada' WHERE fk_object = 41", 'the extrafields row is written directly', $passed, $failed, $total);
assert_test(count(Facture::$instances) === 0, 'no invoice object on the independent connection, so no insertExtraFields()', $passed, $failed, $total);

echo "\nstoreVerifactuIndependentData(): PostgreSQL keeps the insertExtraFields() path\n";

Facture::$instances = array();
$db = new FakeIndependentDb('pgsql');
$ok = storeVerifactuIndependentData($db, $invoice, array('verifactu_estado' => 'Enviada', 'verifactu_error' => ''));
$independent = Facture::$instances[0] ?? null;
assert_test($ok === true && $db->queries === array(), 'returns true without SQL of its own', $passed, $failed, $total);
assert_test(
    $independent !== null && $independent->db === $db && $independent->id === 41 && $independent->insertCalls === 1,
    'the invoice is fetched on the independent handler and insertExtraFields() is called once',
    $passed,
    $failed,
    $total
);
assert_test(
    $independent !== null && $independent->array_options === array('options_verifactu_estado' => 'Enviada', 'options_verifactu_error' => ''),
    'the values go to array_options with the options_ prefix, as before',
    $passed,
    $failed,
    $total
);

Facture::$instances = array();
Facture::$insertResult = -1;
assert_test(storeVerifactuIndependentData(new FakeIndependentDb('pgsql'), $invoice, array('verifactu_estado' => 'x')) === false, 'a failed insertExtraFields() returns false', $passed, $failed, $total);
Facture::$insertResult = 1;

Facture::$instances = array();
Facture::$fetchResult = 0;
$ok = storeVerifactuIndependentData(new FakeIndependentDb('pgsql'), $invoice, array('verifactu_estado' => 'x'));
assert_test($ok === false && Facture::$instances[0]->insertCalls === 0, 'an invoice the handler cannot load returns false without writing', $passed, $failed, $total);
Facture::$fetchResult = 1;

// ---------------------------------------------------------------------------
// The save functions go through storeVerifactuIndependentData()
// ---------------------------------------------------------------------------
echo "\nsaveVerifactuSuccessData() and saveVerifactuErrorData()\n";

$source = file_get_contents($moduleRoot . '/lib/functions/functions.response.php');
foreach (array('saveVerifactuSuccessData', 'saveVerifactuErrorData') as $function) {
    $start = strpos($source, 'function ' . $function . '(');
    $end = strpos($source, "\nfunction ", $start + 10);
    $body = substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
    assert_test($start !== false && strpos($body, 'storeVerifactuIndependentData($independentDb, $facture, $values)') !== false, "$function() saves through storeVerifactuIndependentData()", $passed, $failed, $total);
    assert_test(strpos($body, '->insertExtraFields(') === false && strpos($body, '->updateExtraField(') === false, "$function() does not call insertExtraFields()/updateExtraField() itself", $passed, $failed, $total);
}

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
