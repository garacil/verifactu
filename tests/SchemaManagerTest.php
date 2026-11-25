<?php

/**
 * Tests for SchemaManager - local WSDL/XSD caching
 *
 * Tests the download, caching, and rewriting of WSDL/XSD schema files
 * without requiring actual network access.
 */

// Minimal bootstrap
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/newfenix/src/SchemaManager.php';

use OpenAEAT\Billing\SchemaManager;

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

// Create a temporary directory for tests
$testDir = sys_get_temp_dir() . '/verifactu_schema_test_' . uniqid();

echo "=== SchemaManager Unit Tests ===\n\n";

// ---------------------------------------------------------------
echo "Test 1: schemasExist() returns false when directory is empty\n";
$sm = new SchemaManager($testDir);
assert_test(!$sm->schemasExist(), 'schemasExist() is false for non-existent dir', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 2: getLocalWsdlPath() returns correct path\n";
$sm = new SchemaManager($testDir);
$expected = $testDir . '/SistemaFacturacion.wsdl';
assert_test($sm->getLocalWsdlPath() === $expected, 'WSDL path is correct: ' . $sm->getLocalWsdlPath(), $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 3: getSchemasDir() returns configured directory\n";
$sm = new SchemaManager($testDir);
assert_test($sm->getSchemasDir() === $testDir, 'getSchemasDir() returns correct dir', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 4: schemasExist() returns true when all files are present\n";
@mkdir($testDir, 0755, true);
$files = [
    'SistemaFacturacion.wsdl',
    'SuministroInformacion.xsd',
    'SuministroLR.xsd',
    'ConsultaLR.xsd',
    'RespuestaConsultaLR.xsd',
    'RespuestaSuministro.xsd',
    'xmldsig-core-schema.xsd',
];
foreach ($files as $f) {
    file_put_contents($testDir . '/' . $f, '<dummy/>');
}
$sm = new SchemaManager($testDir);
assert_test($sm->schemasExist(), 'schemasExist() is true when all files present', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 5: schemasExist() returns false when one file is missing\n";
unlink($testDir . '/xmldsig-core-schema.xsd');
$sm = new SchemaManager($testDir);
assert_test(!$sm->schemasExist(), 'schemasExist() is false when xmldsig missing', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 6: clearSchemas() removes all files\n";
// Restore the missing file first
file_put_contents($testDir . '/xmldsig-core-schema.xsd', '<dummy/>');
file_put_contents($testDir . '/schemas.meta.json', '{}');
$sm = new SchemaManager($testDir);
$sm->clearSchemas();
assert_test(!$sm->schemasExist(), 'schemasExist() is false after clearSchemas()', $passed, $failed, $total);
assert_test(!file_exists($testDir . '/schemas.meta.json'), 'meta file removed', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 7: rewriteSchemaLocations replaces W3C URL with local path\n";
// Simulate downloaded SuministroInformacion.xsd with remote schemaLocation
@mkdir($testDir, 0755, true);
$xsdContent = '<?xml version="1.0" encoding="UTF-8"?>
<schema xmlns="http://www.w3.org/2001/XMLSchema">
  <import namespace="http://www.w3.org/2000/09/xmldsig#" schemaLocation="http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd"/>
</schema>';
file_put_contents($testDir . '/SuministroInformacion.xsd', $xsdContent);

// Create all other required files
foreach ($files as $f) {
    if ($f !== 'SuministroInformacion.xsd') {
        file_put_contents($testDir . '/' . $f, '<dummy/>');
    }
}

// Use reflection to call rewriteSchemaLocations
$sm = new SchemaManager($testDir);
$reflection = new ReflectionMethod($sm, 'rewriteSchemaLocations');
$reflection->setAccessible(true);
$reflection->invoke($sm);

$rewritten = file_get_contents($testDir . '/SuministroInformacion.xsd');
assert_test(
    strpos($rewritten, 'schemaLocation="xmldsig-core-schema.xsd"') !== false,
    'W3C URL rewritten to local filename',
    $passed, $failed, $total
);
assert_test(
    strpos($rewritten, 'http://www.w3.org/TR/xmldsig-core/') === false,
    'No remote W3C URL remains',
    $passed, $failed, $total
);
echo "\n";

// ---------------------------------------------------------------
echo "Test 8: downloadSchemas() skips when schemas already exist and force=false\n";
$sm = new SchemaManager($testDir);
$result = $sm->downloadSchemas(false);
assert_test($result['success'] === true, 'Returns success', $passed, $failed, $total);
assert_test($result['count'] === 0, 'Downloaded 0 files (skipped)', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 9: getDownloadMetadata() returns null when no meta file\n";
@unlink($testDir . '/schemas.meta.json');
$sm = new SchemaManager($testDir);
assert_test($sm->getDownloadMetadata() === null, 'Returns null when no meta', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 10: getDownloadMetadata() returns data when meta file exists\n";
$meta = ['downloaded_at' => '2026-03-04 12:00:00', 'timestamp' => time(), 'files' => 7];
file_put_contents($testDir . '/schemas.meta.json', json_encode($meta));
$sm = new SchemaManager($testDir);
$result = $sm->getDownloadMetadata();
assert_test($result !== null, 'Returns non-null', $passed, $failed, $total);
assert_test($result['files'] === 7, 'Files count is 7', $passed, $failed, $total);
assert_test($result['downloaded_at'] === '2026-03-04 12:00:00', 'Date matches', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 11: Constructor trims trailing slash\n";
$sm = new SchemaManager('/tmp/test/');
assert_test($sm->getSchemasDir() === '/tmp/test', 'Trailing slash removed', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 12: Config integration - localWsdlPath getter/setter\n";
require_once __DIR__ . '/../lib/newfenix/src/Config.php';
$config = new \OpenAEAT\Billing\Config();
assert_test($config->getLocalWsdlPath() === null, 'Default is null', $passed, $failed, $total);
$config->setLocalWsdlPath('/tmp/test/SistemaFacturacion.wsdl');
assert_test($config->getLocalWsdlPath() === '/tmp/test/SistemaFacturacion.wsdl', 'Set/get works', $passed, $failed, $total);
$config->reset();
assert_test($config->getLocalWsdlPath() === null, 'Reset clears localWsdlPath', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 13: stripXmldsigDoctype() removes the DOCTYPE of the W3C xmldsig schema\n";
// Header of http://www.w3.org/TR/xmldsig-core/xmldsig-core-schema.xsd as served by W3C
$xmldsigDoctype = '<?xml version="1.0" encoding="utf-8"?>' . "\n"
    . '<!DOCTYPE schema' . "\n"
    . '  PUBLIC "-//W3C//DTD XMLSchema 200102//EN" "http://www.w3.org/2001/XMLSchema.dtd"' . "\n"
    . ' [' . "\n"
    . '   <!ATTLIST schema ' . "\n"
    . '     xmlns:ds CDATA #FIXED "http://www.w3.org/2000/09/xmldsig#">' . "\n"
    . "   <!ENTITY dsig 'http://www.w3.org/2000/09/xmldsig#'>\n"
    . "   <!ENTITY % p ''>\n"
    . "   <!ENTITY % s ''>\n"
    . '  ]>' . "\n";
$xmldsigBody = '<schema xmlns="http://www.w3.org/2001/XMLSchema"' . "\n"
    . '        xmlns:ds="http://www.w3.org/2000/09/xmldsig#"' . "\n"
    . '        targetNamespace="http://www.w3.org/2000/09/xmldsig#"' . "\n"
    . '        version="0.1" elementFormDefault="qualified">' . "\n"
    . '<element name="Signature" type="ds:SignatureType"/>' . "\n"
    . '<complexType name="SignatureType"><sequence><element name="SignatureValue" type="base64Binary"/></sequence></complexType>' . "\n"
    . '</schema>' . "\n";
$doctypeDir = $testDir . '/doctype';
@mkdir($doctypeDir, 0755, true);
$xmldsigPath = $doctypeDir . '/xmldsig-core-schema.xsd';
file_put_contents($xmldsigPath, $xmldsigDoctype . $xmldsigBody);
$sm = new SchemaManager($doctypeDir);
assert_test($sm->stripXmldsigDoctype(), 'stripXmldsigDoctype() reports success', $passed, $failed, $total);
$stripped = file_get_contents($xmldsigPath);
assert_test(strpos($stripped, '<!DOCTYPE') === false && strpos($stripped, '<!ENTITY') === false, 'DOCTYPE and its internal subset removed', $passed, $failed, $total);
assert_test($stripped === '<?xml version="1.0" encoding="utf-8"?>' . "\n\n" . $xmldsigBody, 'XML declaration and schema body kept unchanged', $passed, $failed, $total);
assert_test($sm->stripXmldsigDoctype() && file_get_contents($xmldsigPath) === $stripped, 'Second call leaves a clean file as it is', $passed, $failed, $total);
assert_test(!(new SchemaManager($doctypeDir . '/missing'))->stripXmldsigDoctype(), 'Missing file reports failure', $passed, $failed, $total);
echo "\n";

// ---------------------------------------------------------------
echo "Test 14: a WSDL importing the cleaned xmldsig schema loads in SoapClient\n";
if (!class_exists('SoapClient')) {
    echo "  SKIP: the soap extension is not loaded\n";
} else {
    file_put_contents($doctypeDir . '/test.wsdl', '<?xml version="1.0" encoding="utf-8"?>
<definitions xmlns="http://schemas.xmlsoap.org/wsdl/" xmlns:xs="http://www.w3.org/2001/XMLSchema"
    xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:tns="urn:verifactu-test"
    xmlns:ds="http://www.w3.org/2000/09/xmldsig#" targetNamespace="urn:verifactu-test">
  <types>
    <xs:schema targetNamespace="urn:verifactu-test" elementFormDefault="qualified">
      <xs:import namespace="http://www.w3.org/2000/09/xmldsig#" schemaLocation="xmldsig-core-schema.xsd"/>
      <xs:element name="Request"><xs:complexType><xs:sequence>
        <xs:element ref="ds:Signature" minOccurs="0"/>
      </xs:sequence></xs:complexType></xs:element>
    </xs:schema>
  </types>
  <message name="RequestMessage"><part name="body" element="tns:Request"/></message>
  <portType name="TestPort"><operation name="Send"><input message="tns:RequestMessage"/><output message="tns:RequestMessage"/></operation></portType>
  <binding name="TestBinding" type="tns:TestPort">
    <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
    <operation name="Send"><soap:operation soapAction=""/><input><soap:body use="literal"/></input><output><soap:body use="literal"/></output></operation>
  </binding>
  <service name="TestService"><port name="TestPort" binding="tns:TestBinding"><soap:address location="http://localhost/"/></port></service>
</definitions>');
    try {
        new SoapClient($doctypeDir . '/test.wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
        $loaded = true;
    } catch (Throwable $e) {
        $loaded = false;
        echo "  " . $e->getMessage() . "\n";
    }
    assert_test($loaded, 'SoapClient parses the WSDL with the cleaned schema', $passed, $failed, $total);
}
array_map('unlink', glob($doctypeDir . '/*'));
@rmdir($doctypeDir);
echo "\n";

// Cleanup
$sm = new SchemaManager($testDir);
$sm->clearSchemas();
@rmdir($testDir);

// ---------------------------------------------------------------
echo "=== RESULTS ===\n";
echo "Total: $total | Passed: $passed | Failed: $failed\n\n";

if ($failed > 0) {
    echo "SOME TESTS FAILED\n";
    exit(1);
} else {
    echo "ALL TESTS PASSED\n";
    exit(0);
}
