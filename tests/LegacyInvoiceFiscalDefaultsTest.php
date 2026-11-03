<?php

/**
 * Regression test for issue #54: invoices created before the module was
 * enabled were rejected by AEAT with error 4102, "El XML no cumple el esquema.
 * Falta informar campo obligatorio: BaseImponibleOimporteNoSujeto".
 *
 * Those invoices never went through the BILL_CREATE trigger that fills the
 * fiscal extrafields. Dolibarr leaves them in one of two states:
 *   - no row in llx_facture_extrafields, so fetch_optionals() returns null for
 *     every field and the submission failed with "Tax Type not configured";
 *   - a row that already existed when the module added its columns, which
 *     received the column defaults (type F1, tax 01, regime 01) but not a
 *     qualification, which has none.
 *
 * In the second case the record carried CalificacionOperacion => null. The AEAT
 * schema declares CalificacionOperacion and OperacionExenta as a choice, and
 * PHP's SoapClient stops encoding a sequence at a choice it cannot satisfy, so
 * the DetalleDesglose went out with Impuesto and ClaveRegimen only, exactly what
 * the AEAT message says.
 *
 * Covers:
 *  - getVerifactuFiscalDefaults(): one precedence for the trigger and the submission.
 *  - getVerifactuInvoiceTypeForInvoice(): the type rules extracted from the trigger.
 *  - completeVerifactuFiscalFields(): fills only what is missing, and stores it.
 *  - Invoice::createTaxGroup()/validate(): the choice is honoured and checked locally.
 *  - The SoapClient truncation itself, when the soap extension is available.
 *
 * Run with: php tests/LegacyInvoiceFiscalDefaultsTest.php
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

foreach (array('LOG_ERR' => 3, 'LOG_WARNING' => 4, 'LOG_INFO' => 6, 'LOG_DEBUG' => 7) as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}
$logged = array();
if (!function_exists('dol_syslog')) {
    function dol_syslog($message, $level = 0)
    {
        global $logged;
        $logged[] = $message;
    }
}
if (!function_exists('isModEnabled')) {
    function isModEnabled($module)
    {
        return false;
    }
}

/**
 * Minimal Facture double: the constants and members the functions rely on.
 */
class Facture
{
    const TYPE_STANDARD = 0;
    const TYPE_REPLACEMENT = 1;
    const TYPE_CREDIT_NOTE = 2;
    const TYPE_DEPOSIT = 3;
    const TYPE_PROFORMA = 4;
    const TYPE_SITUATION = 5;

    public $id = 12;
    public $ref = 'FA2024-0007';
    public $type = self::TYPE_STANDARD;
    public $module_source = '';
    public $array_options = array();
    public $thirdparty = null;
    public $errors = array();
    public $persisted = 0;
    public $persistedOptions = null;

    public function fetch_optionals()
    {
        return 0;
    }

    public function fetch_thirdparty()
    {
        return 1;
    }

    public function insertExtraFields()
    {
        $this->persisted++;
        $this->persistedOptions = $this->array_options;
        return 1;
    }
}

class Societe
{
    public $id = 3;
    public $name = 'Cliente';
    public $array_options = array();

    public function fetch_optionals()
    {
        return 0;
    }
}

$conf = new stdClass();
$conf->global = new stdClass();
$conf->entity = 1;

require_once $moduleRoot . '/lib/newfenix/autoload.php';
require_once $moduleRoot . '/lib/functions/functions.configuration.php';

use OpenAEAT\Billing\Invoice;

/**
 * An invoice as Dolibarr returns it when it has no extrafields row at all.
 */
function invoiceWithoutRow()
{
    $facture = new Facture();
    $facture->thirdparty = new Societe();
    foreach (array('estado', 'factura_tipo', 'impuesto', 'clave_regimen', 'calificacion_operacion', 'operacion_exenta', 'incidencia', 'huella') as $suffix) {
        $facture->array_options['options_verifactu_' . $suffix] = null;
    }
    return $facture;
}

/**
 * An invoice whose extrafields row existed before the module added its columns:
 * the columns declared with a default got it, the qualification did not.
 */
function invoiceWithColumnDefaults()
{
    $facture = invoiceWithoutRow();
    $facture->array_options['options_verifactu_factura_tipo'] = 'F1';
    $facture->array_options['options_verifactu_impuesto'] = '01';
    $facture->array_options['options_verifactu_clave_regimen'] = '01';
    $facture->array_options['options_verifactu_incidencia'] = 'N';
    return $facture;
}

echo "=== Legacy invoices: fiscal defaults (issue #54) ===\n\n";

// ---------------------------------------------------------------------------
// getVerifactuFiscalDefaults()
// ---------------------------------------------------------------------------
echo "getVerifactuFiscalDefaults()\n";

$defaults = getVerifactuFiscalDefaults(null);
assert_test($defaults['values'] === array('impuesto' => '01', 'clave_regimen' => '01', 'calificacion_operacion' => 'S1', 'operacion_exenta' => ''), 'module defaults are VAT, general regime, S1 and no exemption', $passed, $failed, $total);
assert_test(array_values(array_unique($defaults['sources'])) === array('module'), 'every default is reported as coming from the module', $passed, $failed, $total);

$conf->global->VERIFACTU_DEFAULT_OPERATION_QUALIFICATION = 'S2';
$defaults = getVerifactuFiscalDefaults(null);
assert_test($defaults['values']['calificacion_operacion'] === 'S2' && $defaults['sources']['calificacion_operacion'] === 'config', 'a VERIFACTU_DEFAULT_* constant overrides the module default', $passed, $failed, $total);

$customer = new Societe();
$customer->array_options['options_verifactu_calificacion_operacion'] = 'N1';
$customer->array_options['options_verifactu_operacion_exenta'] = 'E1';
$defaults = getVerifactuFiscalDefaults($customer);
assert_test($defaults['values']['calificacion_operacion'] === 'N1' && $defaults['sources']['calificacion_operacion'] === 'thirdparty', 'the customer configuration overrides the constant', $passed, $failed, $total);
assert_test($defaults['values']['operacion_exenta'] === 'E1', 'the customer exemption is taken', $passed, $failed, $total);
unset($conf->global->VERIFACTU_DEFAULT_OPERATION_QUALIFICATION);

$ipsiCustomer = new Societe();
$ipsiCustomer->array_options['options_verifactu_impuesto'] = '02';
$defaults = getVerifactuFiscalDefaults($ipsiCustomer);
assert_test($defaults['values']['clave_regimen'] === '', 'the module regime is not applied to IPSI, where the schema wants no regime', $passed, $failed, $total);

$ipsiCustomer->array_options['options_verifactu_clave_regimen'] = '01';
$defaults = getVerifactuFiscalDefaults($ipsiCustomer);
assert_test($defaults['values']['clave_regimen'] === '01', 'a regime the customer sets is kept, for the submission validation to report', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// getVerifactuInvoiceTypeForInvoice()
// ---------------------------------------------------------------------------
echo "\ngetVerifactuInvoiceTypeForInvoice()\n";

$facture = invoiceWithoutRow();
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === 'F1', 'a standard invoice is F1', $passed, $failed, $total);
$facture->type = Facture::TYPE_SITUATION;
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === 'F1', 'a situation invoice is F1', $passed, $failed, $total);
$facture->type = Facture::TYPE_DEPOSIT;
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === 'F1', 'a deposit invoice is F1', $passed, $failed, $total);
$facture->type = Facture::TYPE_CREDIT_NOTE;
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === 'R1', 'a credit note is R1, the type the submission transmits', $passed, $failed, $total);
$facture->type = Facture::TYPE_REPLACEMENT;
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === 'R1', 'a replacement invoice is R1', $passed, $failed, $total);
$facture->type = Facture::TYPE_PROFORMA;
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === '', 'a proforma has no VeriFactu type', $passed, $failed, $total);
$facture->type = Facture::TYPE_STANDARD;
$facture->module_source = 'takepos';
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === 'F2', 'a TakePOS invoice is simplified (F2)', $passed, $failed, $total);
$facture->type = Facture::TYPE_CREDIT_NOTE;
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === 'R5', 'a TakePOS credit note is R5', $passed, $failed, $total);
$facture = invoiceWithoutRow();
$facture->thirdparty->array_options['options_verifactu_factura_simplificada'] = 1;
assert_test(getVerifactuInvoiceTypeForInvoice($facture) === 'F2', 'a simplified-invoice customer gets F2', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// completeVerifactuFiscalFields()
// ---------------------------------------------------------------------------
echo "\ncompleteVerifactuFiscalFields()\n";

$facture = invoiceWithoutRow();
$filled = completeVerifactuFiscalFields($facture);
sort($filled);
assert_test($filled === array('calificacion_operacion', 'clave_regimen', 'factura_tipo', 'impuesto'), 'an invoice without extrafields row gets type, tax, regime and qualification', $passed, $failed, $total);
assert_test(
    $facture->array_options['options_verifactu_factura_tipo'] === 'F1'
    && $facture->array_options['options_verifactu_impuesto'] === '01'
    && $facture->array_options['options_verifactu_clave_regimen'] === '01'
    && $facture->array_options['options_verifactu_calificacion_operacion'] === 'S1',
    'with the values the BILL_CREATE trigger would have given it',
    $passed,
    $failed,
    $total
);
assert_test(empty($facture->array_options['options_verifactu_operacion_exenta']), 'and no exemption', $passed, $failed, $total);
assert_test($facture->persisted === 1, 'the completed fields are stored once', $passed, $failed, $total);
assert_test($facture->array_options['options_verifactu_huella'] === null, 'the other extrafields are left untouched', $passed, $failed, $total);

$facture = invoiceWithColumnDefaults();
$filled = completeVerifactuFiscalFields($facture);
assert_test($filled === array('calificacion_operacion'), 'the reported case, a row with column defaults, only lacks the qualification', $passed, $failed, $total);
assert_test($facture->array_options['options_verifactu_calificacion_operacion'] === 'S1', 'which is filled with S1', $passed, $failed, $total);

$facture = invoiceWithColumnDefaults();
$facture->array_options['options_verifactu_calificacion_operacion'] = 'S1';
$filled = completeVerifactuFiscalFields($facture);
assert_test($filled === array() && $facture->persisted === 0, 'a complete invoice is neither changed nor rewritten', $passed, $failed, $total);

$facture = invoiceWithColumnDefaults();
$facture->array_options['options_verifactu_impuesto'] = '0';
$facture->array_options['options_verifactu_calificacion_operacion'] = 'N2';
$filled = completeVerifactuFiscalFields($facture);
assert_test($filled === array('impuesto') && $facture->array_options['options_verifactu_calificacion_operacion'] === 'N2', 'a value already set is never replaced; "0" counts as missing', $passed, $failed, $total);

$facture = invoiceWithColumnDefaults();
$facture->array_options['options_verifactu_operacion_exenta'] = 'E1';
$filled = completeVerifactuFiscalFields($facture);
assert_test($filled === array() && empty($facture->array_options['options_verifactu_calificacion_operacion']), 'an exempt invoice does not get a qualification: the schema takes one or the other', $passed, $failed, $total);

$facture = invoiceWithColumnDefaults();
$facture->thirdparty->array_options['options_verifactu_operacion_exenta'] = 'E2';
$filled = completeVerifactuFiscalFields($facture);
assert_test($filled === array('operacion_exenta') && $facture->array_options['options_verifactu_operacion_exenta'] === 'E2' && empty($facture->array_options['options_verifactu_calificacion_operacion']), 'a customer exemption is applied instead of a qualification', $passed, $failed, $total);

$conf->global->VERIFACTU_DEFAULT_OPERATION_QUALIFICATION = 'S2';
$facture = invoiceWithColumnDefaults();
$filled = completeVerifactuFiscalFields($facture);
assert_test($facture->array_options['options_verifactu_calificacion_operacion'] === 'S2', 'the configured default qualification is honoured', $passed, $failed, $total);
unset($conf->global->VERIFACTU_DEFAULT_OPERATION_QUALIFICATION);

$facture = invoiceWithoutRow();
$facture->type = Facture::TYPE_CREDIT_NOTE;
completeVerifactuFiscalFields($facture);
assert_test($facture->array_options['options_verifactu_factura_tipo'] === 'R1', 'a legacy credit note is typed R1', $passed, $failed, $total);

$facture = invoiceWithoutRow();
completeVerifactuFiscalFields($facture, false);
assert_test($facture->persisted === 0 && $facture->array_options['options_verifactu_impuesto'] === '01', 'persistence can be skipped while the values are still completed in memory', $passed, $failed, $total);

$logged = array();
$facture = invoiceWithColumnDefaults();
completeVerifactuFiscalFields($facture);
assert_test(count($logged) === 1 && strpos($logged[0], 'calificacion_operacion') !== false, 'the completion is logged with the fields it filled', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// The submission and the trigger use the shared functions
// ---------------------------------------------------------------------------
echo "\nSubmission and trigger\n";

$submission = file_get_contents($moduleRoot . '/lib/functions/functions.submission.php');
$completePosition = strpos($submission, 'completeVerifactuFiscalFields($facture)');
$paramsPosition = strpos($submission, 'getVerifactuParams($facture)');
$typePosition = strpos($submission, '$invoice->setType(');
assert_test($completePosition !== false, 'handleInvoiceCreationOrSubsanation() completes the fiscal fields', $passed, $failed, $total);
assert_test($completePosition !== false && $paramsPosition !== false && $completePosition < $paramsPosition, 'before reading the parameters of the record', $passed, $failed, $total);
assert_test($completePosition !== false && $typePosition !== false && $completePosition < $typePosition, 'and before setting the invoice type', $passed, $failed, $total);
assert_test(strpos($submission, "options_verifactu_factura_tipo'] ?? VerifactuInvoice::TYPE_STANDARD") === false, 'the invoice type fallback also covers an empty string, not only null', $passed, $failed, $total);
assert_test(strpos($submission, "VERIFACTU_LEGACY_FISCAL_DEFAULTS_APPLIED") !== false, 'the user is told which fields were completed', $passed, $failed, $total);

$trigger = file_get_contents($moduleRoot . '/core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php');
assert_test(strpos($trigger, 'getVerifactuFiscalDefaults($object->thirdparty)') !== false, 'billCreate() resolves the fiscal values with getVerifactuFiscalDefaults()', $passed, $failed, $total);
assert_test(strpos($trigger, 'getVerifactuInvoiceTypeForInvoice($object)') !== false, 'billCreate() resolves the invoice type with getVerifactuInvoiceTypeForInvoice()', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// Invoice::createTaxGroup() and validate() honour the choice
// ---------------------------------------------------------------------------
echo "\nInvoice breakdown\n";

function breakdownInvoice()
{
    $invoice = new Invoice('FA-1', '05-01-2026', 'B12345678', 'Emisor');
    $invoice->setDescription('Test')->setAsFirstInChain()->addRecipient('A87654321', 'Cliente');
    return $invoice;
}

$invoice = breakdownInvoice();
$invoice->addDesglose('S1', 100, null, Invoice::TAX_VAT, '01', 21, 21);
$detail = $invoice->getData(false)['Desglose']['DetalleDesglose'][0];
assert_test(($detail['CalificacionOperacion'] ?? null) === 'S1' && !array_key_exists('OperacionExenta', $detail), 'a taxable line carries CalificacionOperacion and no OperacionExenta', $passed, $failed, $total);

$invoice = breakdownInvoice();
$invoice->addDesglose('S1', 100, 'E1', Invoice::TAX_VAT, '01');
$detail = $invoice->getData(false)['Desglose']['DetalleDesglose'][0];
assert_test(($detail['OperacionExenta'] ?? null) === 'E1' && !array_key_exists('CalificacionOperacion', $detail), 'an exempt line carries OperacionExenta only, even when a default qualification came along', $passed, $failed, $total);

$invoice = breakdownInvoice();
$invoice->addDesglose(null, 100, null, Invoice::TAX_VAT, '01', 21, 21);
$detailWithoutChoice = null;
$rejected = false;
try {
    $invoice->getData(false);
} catch (InvalidArgumentException $e) {
    $rejected = (strpos($e->getMessage(), 'CalificacionOperacion') !== false);
}
assert_test($rejected, 'a line with neither qualification nor exemption is rejected locally with a clear message, before AEAT', $passed, $failed, $total);

$reflection = new ReflectionProperty($invoice, 'taxEntries');
$reflection->setAccessible(true);
$detailWithoutChoice = $reflection->getValue($invoice)[0];
assert_test(!array_key_exists('CalificacionOperacion', $detailWithoutChoice), 'and the null qualification is not written into the record', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// What SoapClient does with the record (only when the soap extension is loaded)
// ---------------------------------------------------------------------------
echo "\nSoapClient encoding of the choice\n";

if (!extension_loaded('soap')) {
    echo "  SKIP: the soap extension is not loaded\n";
} else {
    $wsdl = tempnam(sys_get_temp_dir(), 'vfwsdl');
    file_put_contents($wsdl, <<<'WSDL'
<?xml version="1.0" encoding="UTF-8"?>
<definitions xmlns="http://schemas.xmlsoap.org/wsdl/" xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:tns="urn:test" xmlns:sf="urn:sf" targetNamespace="urn:test">
  <types>
    <xs:schema targetNamespace="urn:sf" elementFormDefault="qualified" xmlns:sf="urn:sf">
      <xs:complexType name="DetalleType">
        <xs:sequence>
          <xs:element name="Impuesto" type="xs:string" minOccurs="0"/>
          <xs:element name="ClaveRegimen" type="xs:string" minOccurs="0"/>
          <xs:choice>
            <xs:element name="CalificacionOperacion" type="xs:string"/>
            <xs:element name="OperacionExenta" type="xs:string"/>
          </xs:choice>
          <xs:element name="TipoImpositivo" type="xs:string" minOccurs="0"/>
          <xs:element name="BaseImponibleOimporteNoSujeto" type="xs:string"/>
          <xs:element name="CuotaRepercutida" type="xs:string" minOccurs="0"/>
        </xs:sequence>
      </xs:complexType>
      <xs:element name="Reg"><xs:complexType><xs:sequence><xs:element name="DetalleDesglose" type="sf:DetalleType"/></xs:sequence></xs:complexType></xs:element>
      <xs:element name="RegResp" type="xs:string"/>
    </xs:schema>
  </types>
  <message name="RegIn"><part name="body" element="sf:Reg"/></message>
  <message name="RegOut"><part name="body" element="sf:RegResp"/></message>
  <portType name="P"><operation name="Reg"><input message="tns:RegIn"/><output message="tns:RegOut"/></operation></portType>
  <binding name="B" type="tns:P">
    <soap:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>
    <operation name="Reg"><soap:operation soapAction="urn:Reg"/><input><soap:body use="literal"/></input><output><soap:body use="literal"/></output></operation>
  </binding>
  <service name="S"><port name="P" binding="tns:B"><soap:address location="http://127.0.0.1:9/"/></port></service>
</definitions>
WSDL
    );

    $encode = function (array $detail) use ($wsdl) {
        $client = new SoapClient($wsdl, array('trace' => 1, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE));
        try {
            $client->__soapCall('Reg', array(array('DetalleDesglose' => $detail)));
        } catch (SoapFault $fault) {
            // No server: only the encoded request matters.
        }
        return (string) $client->__getLastRequest();
    };

    $legacy = array('CalificacionOperacion' => null, 'BaseImponibleOimporteNoSujeto' => '100.00', 'Impuesto' => '01', 'ClaveRegimen' => '01', 'TipoImpositivo' => '21.00', 'CuotaRepercutida' => '21.00');
    $xml = $encode($legacy);
    assert_test(strpos($xml, 'BaseImponibleOimporteNoSujeto') === false && strpos($xml, 'ClaveRegimen') !== false, 'a null CalificacionOperacion makes SoapClient drop the rest of the detail, BaseImponibleOimporteNoSujeto included', $passed, $failed, $total);

    $invoice = breakdownInvoice();
    $invoice->addDesglose('S1', 100, null, Invoice::TAX_VAT, '01', 21, 21);
    $xml = $encode($invoice->getData(false)['Desglose']['DetalleDesglose'][0]);
    assert_test(strpos($xml, '<ns1:CalificacionOperacion>S1</ns1:CalificacionOperacion>') !== false && strpos($xml, '<ns1:BaseImponibleOimporteNoSujeto>100</ns1:BaseImponibleOimporteNoSujeto>') !== false, 'a completed record is encoded in full', $passed, $failed, $total);

    unlink($wsdl);
}

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
