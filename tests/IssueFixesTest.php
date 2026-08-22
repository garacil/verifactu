<?php

/**
 * Regression tests for reported issues
 *
 * Covers:
 *  - Issue #31: fatal error in beforePDFCreation() when the hook is fired by a
 *               payment report model (pdf_paiement / pdf_paiement_fourn).
 *  - Issue #32: TypeError in QRGenerator::renderQrCode() under PHP 8 because the
 *               chillerlan/php-qrcode output constants are strings, not ints.
 *  - Issue #30 (2): files listed for the integrity hash must actually exist.
 *  - Issue #30 (1/6): the system identity declared in the responsible declaration
 *               must match what is transmitted to AEAT and respect the XSD limits.
 *
 * Run with: php tests/IssueFixesTest.php
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

echo "=== Reported Issues Regression Tests ===\n\n";

// ---------------------------------------------------------------------------
// Issue #32 - QR rendering must not throw a TypeError on PHP 8
// ---------------------------------------------------------------------------
echo "Issue #32: QRGenerator output type under strict_types\n";

require_once $moduleRoot . '/lib/newfenix/vendor/autoload.php';
require_once $moduleRoot . '/lib/newfenix/src/QRGenerator.php';

use OpenAEAT\Billing\QRGenerator;

$qrUrl = QRGenerator::generateVerifiableUrl('B12345678', 'FA2026-0001', '22-08-2026', 121.00, true);
assert_test(strpos($qrUrl, 'nif=B12345678') !== false, 'generateVerifiableUrl() builds the AEAT URL', $passed, $failed, $total);

$reflection = new ReflectionMethod(QRGenerator::class, 'renderQrCode');
$outputTypeParam = $reflection->getParameters()[2];
assert_test(
    (string) $outputTypeParam->getType() === 'string',
    'renderQrCode() declares $outputType as string (matches QRCode::OUTPUT_* constants)',
    $passed,
    $failed,
    $total
);

if (extension_loaded('gd')) {
    $png = null;
    $error = '';
    try {
        $png = (new QRGenerator())->renderPng($qrUrl, 300);
    } catch (\Throwable $e) {
        $error = get_class($e) . ': ' . $e->getMessage();
    }
    assert_test($error === '', 'renderPng() does not throw' . ($error ? " ($error)" : ''), $passed, $failed, $total);
    assert_test(is_string($png) && substr($png, 0, 4) === "\x89PNG", 'renderPng() returns PNG binary data', $passed, $failed, $total);

    $base64 = QRGenerator::generateBase64QR($qrUrl, 300, 0);
    assert_test(strpos($base64, 'data:image/png;base64,') === 0, 'generateBase64QR() returns a PNG data URI', $passed, $failed, $total);

    $svg = (new QRGenerator())->renderSvg($qrUrl, 300);
    assert_test(strpos($svg, '<svg') !== false, 'renderSvg() returns SVG markup', $passed, $failed, $total);
} else {
    echo "  SKIP: GD extension not available, image rendering not exercised\n";
}

echo "\n";

// ---------------------------------------------------------------------------
// Issue #31 - beforePDFCreation() must survive non-CommonObject arguments
// ---------------------------------------------------------------------------
echo "Issue #31: beforePDFCreation() guard for payment report models\n";

if (!function_exists('dol_include_once')) {
    /**
     * Minimal stand-in so the hook class can be loaded without a Dolibarr runtime.
     *
     * @param string $relativePath Path relative to the custom directory
     * @return void
     */
    function dol_include_once($relativePath)
    {
        // Nothing to load in the test harness.
    }
}

/**
 * Stand-in for Dolibarr's Translate, only what the hook touches.
 */
class VerifactuTestTranslate
{
    /** @var array<string,string> Overridden translations */
    public $tab_translate = array();

    /**
     * @param string $key Language key
     * @return void
     */
    public function load($key)
    {
    }

    /**
     * @param string $key Language key
     * @return string
     */
    public function trans($key)
    {
        return $key;
    }
}

/**
 * Stand-in for pdf_paiement_fourn: a PDF report model, not a CommonObject.
 * It deliberately has no fetch_optionals() method - that is the crash from #31.
 */
class VerifactuTestPdfPaiementFourn
{
    /** @var string Model type */
    public $type = 'pdf';
}

/**
 * Stand-in for a customer invoice carrying VeriFactu extrafields.
 */
class VerifactuTestFacture
{
    /** @var array<string,mixed> Extrafield values */
    public $array_options = array();

    /** @var int Value returned by fetch_optionals() */
    public $fetchOptionalsResult = 1;

    /**
     * @return int Result of the extrafield load
     */
    public function fetch_optionals()
    {
        return $this->fetchOptionalsResult;
    }
}

if (!class_exists('Sietekas\Verifactu\VerifactuInvoice')) {
    eval('namespace Sietekas\Verifactu; class VerifactuInvoice { const TYPE_SIMPLIFIED = "F2"; }');
}

require_once $moduleRoot . '/class/actions_verifactu.class.php';

$hook = new ActionsVerifactu(null);
$action = '';

// 1. The exact crash from the report: pdf_paiement_fourn passed as $object.
$GLOBALS['langs'] = new VerifactuTestTranslate();
$reportModel = new VerifactuTestPdfPaiementFourn();
$crash = '';
$result = null;
try {
    $result = $hook->beforePDFCreation(array(), $reportModel, $action);
} catch (\Throwable $e) {
    $crash = get_class($e) . ': ' . $e->getMessage();
}
assert_test($crash === '', 'payment report model does not raise an error' . ($crash ? " ($crash)" : ''), $passed, $failed, $total);
assert_test($result === 0, 'payment report model returns 0 (hook continues normally)', $passed, $failed, $total);

// 2. A plain array / null must not crash either.
$crash = '';
try {
    $nothing = null;
    $hook->beforePDFCreation(array(), $nothing, $action);
} catch (\Throwable $e) {
    $crash = get_class($e) . ': ' . $e->getMessage();
}
assert_test($crash === '', 'null object does not raise an error' . ($crash ? " ($crash)" : ''), $passed, $failed, $total);

// 3. Simplified invoice still gets its PDF title overridden.
$GLOBALS['langs'] = new VerifactuTestTranslate();
$simplified = new VerifactuTestFacture();
$simplified->array_options['options_verifactu_factura_tipo'] = 'F2';
$hook->beforePDFCreation(array(), $simplified, $action);
assert_test(
    isset($GLOBALS['langs']->tab_translate['PdfInvoiceTitle']),
    'simplified invoice still overrides the PDF title',
    $passed,
    $failed,
    $total
);

// 4. A standard invoice leaves the title untouched.
$GLOBALS['langs'] = new VerifactuTestTranslate();
$standard = new VerifactuTestFacture();
$standard->array_options['options_verifactu_factura_tipo'] = 'F1';
$hook->beforePDFCreation(array(), $standard, $action);
assert_test(
    !isset($GLOBALS['langs']->tab_translate['PdfInvoiceTitle']),
    'standard invoice leaves the PDF title untouched',
    $passed,
    $failed,
    $total
);

// 5. An invoice with no VeriFactu extrafield at all must not warn.
$GLOBALS['langs'] = new VerifactuTestTranslate();
$bare = new VerifactuTestFacture();
$warning = '';
set_error_handler(function ($no, $str) use (&$warning) {
    $warning = $str;
    return true;
});
$hook->beforePDFCreation(array(), $bare, $action);
restore_error_handler();
assert_test($warning === '', 'invoice without the extrafield raises no PHP warning' . ($warning ? " ($warning)" : ''), $passed, $failed, $total);

echo "\n";

// ---------------------------------------------------------------------------
// Issue #30 (point 2) - every file in the integrity hash must exist
// ---------------------------------------------------------------------------
echo "Issue #30 (2): integrity hash file list\n";

$GLOBALS['conf'] = (object) array('global' => (object) array(), 'entity' => 1);
require_once $moduleRoot . '/conf/declaracion_responsable.conf.php';

$verifiedFiles = $declaracionResponsable['integridad']['ficheros_verificados'];
$missing = array();
foreach ($verifiedFiles as $relative) {
    if (!file_exists($moduleRoot . '/' . $relative)) {
        $missing[] = $relative;
    }
}
assert_test(empty($missing), 'every verified file exists' . ($missing ? ' (missing: ' . implode(', ', $missing) . ')' : ''), $passed, $failed, $total);
assert_test(
    in_array('core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php', $verifiedFiles, true),
    'the main trigger is included in the integrity hash',
    $passed,
    $failed,
    $total
);

$hashInfo = calcularHashModuloVerifactu($moduleRoot);
assert_test(strlen($hashInfo) === 64, 'calcularHashModuloVerifactu() returns a SHA-256 digest', $passed, $failed, $total);
assert_test(
    empty($declaracionResponsable['integridad']['ficheros_no_encontrados']),
    'no verified file is silently skipped',
    $passed,
    $failed,
    $total
);

echo "\n";

// ---------------------------------------------------------------------------
// Issue #30 (points 1 and 6) - declared identity == transmitted identity
// ---------------------------------------------------------------------------
echo "Issue #30 (1/6): system identity declared vs transmitted\n";

$GLOBALS['dolibarr_main_instance_unique_id'] = 'testinstance';
require_once $moduleRoot . '/lib/functions/functions.configuration.php';

$systemConfig = getSystemConfig();

assert_test(
    $systemConfig['IdSistemaInformatico'] === $declaracionResponsable['sistema']['id_sistema_informatico'],
    'IdSistemaInformatico transmitted matches the one declared',
    $passed,
    $failed,
    $total
);
assert_test(
    $systemConfig['NombreSistemaInformatico'] === $declaracionResponsable['sistema']['nombre_sistema_informatico'],
    'NombreSistemaInformatico transmitted matches the one declared',
    $passed,
    $failed,
    $total
);
assert_test(
    $systemConfig['Version'] === $declaracionResponsable['sistema']['version'],
    'Version transmitted matches the one declared',
    $passed,
    $failed,
    $total
);

// AEAT SuministroInformacion.xsd limits (SistemaInformatico block).
assert_test(
    strlen($systemConfig['IdSistemaInformatico']) >= 1 && strlen($systemConfig['IdSistemaInformatico']) <= 2,
    'IdSistemaInformatico fits TextMax2Type (AEAT XSD)',
    $passed,
    $failed,
    $total
);
assert_test(
    strlen($systemConfig['NombreSistemaInformatico']) <= 30,
    'NombreSistemaInformatico fits TextMax30Type (AEAT XSD)',
    $passed,
    $failed,
    $total
);
assert_test(strlen($systemConfig['Version']) <= 50, 'Version fits TextMax50Type (AEAT XSD)', $passed, $failed, $total);
assert_test(strlen($systemConfig['NumeroInstalacion']) <= 100, 'NumeroInstalacion fits TextMax100Type (AEAT XSD)', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// Issue #33 - FNMT .p12 containers with legacy ciphers under OpenSSL 3
// ---------------------------------------------------------------------------
echo "Issue #33: legacy PKCS#12 containers under OpenSSL 3\n";

require_once $moduleRoot . '/lib/functions/functions.certificates.php';

// Error classification: the two failures must never be confused again.
$legacyError = '4087B060787F0000:error:0308010C:digital envelope routines:'
    . 'inner_evp_generic_fetch:unsupported:../crypto/evp/evp_fetch.c:386:'
    . 'Global default library context, Algorithm (RC2-CBC : 4), Properties ()';
$passwordError = 'Mac verify error: invalid password?';

assert_test(isLegacyCipherOpensslError($legacyError), 'legacy cipher failure is recognised', $passed, $failed, $total);
assert_test(!isWrongPasswordOpensslError($legacyError), 'legacy cipher failure is not reported as a wrong password', $passed, $failed, $total);
assert_test(isWrongPasswordOpensslError($passwordError), 'wrong password failure is recognised', $passed, $failed, $total);
assert_test(!isLegacyCipherOpensslError($passwordError), 'wrong password failure is not reported as a legacy cipher', $passed, $failed, $total);
assert_test(!isLegacyCipherOpensslError(''), 'empty output is not classified as a legacy cipher', $passed, $failed, $total);
assert_test(!isWrongPasswordOpensslError(''), 'empty output is not classified as a wrong password', $passed, $failed, $total);

// The user-facing explanation must name the real cause.
$legacyMessage = describeCertificateExtractionFailure(array(
    'output' => $legacyError,
    'legacy_cipher' => true,
    'wrong_password' => false,
));
assert_test(stripos($legacyMessage, 'legacy') !== false, 'legacy failure message mentions the legacy algorithm', $passed, $failed, $total);
assert_test(stripos($legacyMessage, 'password') === false || stripos($legacyMessage, 'not correct') === false, 'legacy failure message does not blame the password', $passed, $failed, $total);

$passwordMessage = describeCertificateExtractionFailure(array(
    'output' => $passwordError,
    'legacy_cipher' => false,
    'wrong_password' => true,
));
assert_test(stripos($passwordMessage, 'password') !== false, 'wrong password message mentions the password', $passed, $failed, $total);

// End-to-end against real containers, when the OpenSSL binary is usable.
$opensslUsable = function_exists('proc_open');
$fixtureDir = sys_get_temp_dir() . '/verifactu_p12_' . getmypid();

if ($opensslUsable) {
    @mkdir($fixtureDir, 0700, true);
    $quiet = ' 2>/dev/null';
    $subject = '/C=ES/O=FNMT-RCM/CN=TEST USER - 12345678Z';
    shell_exec('openssl req -x509 -newkey rsa:2048 -keyout ' . escapeshellarg($fixtureDir . '/key.pem')
        . ' -out ' . escapeshellarg($fixtureDir . '/cert.pem')
        . ' -days 2 -nodes -subj ' . escapeshellarg($subject) . $quiet);

    $haveSource = file_exists($fixtureDir . '/key.pem') && file_exists($fixtureDir . '/cert.pem');

    if ($haveSource) {
        // A container encrypted the way older FNMT exports are.
        shell_exec('openssl pkcs12 -export -legacy -certpbe RC2-40-CBC -keypbe PBE-SHA1-3DES'
            . ' -in ' . escapeshellarg($fixtureDir . '/cert.pem')
            . ' -inkey ' . escapeshellarg($fixtureDir . '/key.pem')
            . ' -out ' . escapeshellarg($fixtureDir . '/legacy.p12')
            . ' -password pass:secreto123' . $quiet);

        // A container encrypted with current defaults, as a control.
        shell_exec('openssl pkcs12 -export'
            . ' -in ' . escapeshellarg($fixtureDir . '/cert.pem')
            . ' -inkey ' . escapeshellarg($fixtureDir . '/key.pem')
            . ' -out ' . escapeshellarg($fixtureDir . '/modern.p12')
            . ' -password pass:secreto123' . $quiet);
    }

    if ($haveSource && file_exists($fixtureDir . '/legacy.p12') && file_exists($fixtureDir . '/modern.p12')) {
        $legacyCert = extractPublicCertificateWithOpenSSL($fixtureDir . '/legacy.p12', 'secreto123');
        assert_test(
            !empty($legacyCert['success']),
            'legacy .p12 with the right password yields the public certificate'
                . (empty($legacyCert['success']) ? ' (' . $legacyCert['message'] . ')' : ''),
            $passed,
            $failed,
            $total
        );

        $legacyKey = extractPrivateKeyWithOpenSSL($fixtureDir . '/legacy.p12', 'secreto123');
        assert_test(!empty($legacyKey['success']), 'legacy .p12 with the right password yields the private key', $passed, $failed, $total);

        $modernCert = extractPublicCertificateWithOpenSSL($fixtureDir . '/modern.p12', 'secreto123');
        assert_test(!empty($modernCert['success']), 'modern .p12 still works (no regression)', $passed, $failed, $total);

        $badPassword = extractPublicCertificateWithOpenSSL($fixtureDir . '/modern.p12', 'incorrecta');
        assert_test(empty($badPassword['success']), 'a wrong password still fails', $passed, $failed, $total);
        assert_test(!empty($badPassword['wrong_password']), 'a wrong password is reported as such', $passed, $failed, $total);

        // The password reaches OpenSSL through the environment, so shell
        // metacharacters must be inert rather than executed.
        $canary = $fixtureDir . '/canary';
        $injection = extractPublicCertificateWithOpenSSL($fixtureDir . '/modern.p12', 'a"; touch ' . $canary . '; #');
        assert_test(empty($injection['success']), 'a password full of shell metacharacters simply fails', $passed, $failed, $total);
        assert_test(!file_exists($canary), 'the password cannot inject shell commands', $passed, $failed, $total);
    } else {
        echo "  SKIP: could not build .p12 fixtures with the local openssl binary\n";
    }

    // Clean up fixtures.
    foreach (glob($fixtureDir . '/*') as $leftover) {
        @unlink($leftover);
    }
    @rmdir($fixtureDir);
} else {
    echo "  SKIP: proc_open is disabled, external OpenSSL not exercised\n";
}

echo "\n";

// ---------------------------------------------------------------------------
// Issue #30 (point 3) - proforma invoices must stay out of VeriFactu
// ---------------------------------------------------------------------------
echo "Issue #30 (3): proforma invoices excluded from VeriFactu\n";

require_once $moduleRoot . '/lib/functions/functions.compatibility.php';

/**
 * Stand-in for a Dolibarr invoice carrying the real type constants.
 *
 * The values are the ones Dolibarr actually uses (verified against 22.0.5,
 * htdocs/compta/facture/class/facture.class.php and
 * htdocs/core/class/commoninvoice.class.php). Getting these wrong is not
 * academic: TYPE_CREDIT_NOTE is 2 and TYPE_PROFORMA is 4, so mistaking one for
 * the other would silently drop every credit note from VeriFactu.
 */
class VerifactuTestTypedInvoice
{
    const TYPE_STANDARD = 0;
    const TYPE_REPLACEMENT = 1;
    const TYPE_CREDIT_NOTE = 2;
    const TYPE_DEPOSIT = 3;
    const TYPE_PROFORMA = 4;
    const TYPE_SITUATION = 5;

    /** @var int Dolibarr invoice type */
    public $type;

    /**
     * @param int $type Dolibarr invoice type
     */
    public function __construct($type)
    {
        $this->type = $type;
    }
}

assert_test(!isVerifactuApplicableInvoice(new VerifactuTestTypedInvoice(4)), 'proforma (type 4) is excluded', $passed, $failed, $total);
assert_test(isVerifactuApplicableInvoice(new VerifactuTestTypedInvoice(0)), 'standard invoice (type 0) is included', $passed, $failed, $total);
assert_test(isVerifactuApplicableInvoice(new VerifactuTestTypedInvoice(1)), 'replacement invoice (type 1) is included', $passed, $failed, $total);
assert_test(isVerifactuApplicableInvoice(new VerifactuTestTypedInvoice(2)), 'credit note (type 2) is included - NOT mistaken for a proforma', $passed, $failed, $total);
assert_test(isVerifactuApplicableInvoice(new VerifactuTestTypedInvoice(3)), 'deposit invoice (type 3) is included', $passed, $failed, $total);
assert_test(isVerifactuApplicableInvoice(new VerifactuTestTypedInvoice(5)), 'situation invoice (type 5) is included', $passed, $failed, $total);
assert_test(!isVerifactuApplicableInvoice(null), 'a non-object is excluded', $passed, $failed, $total);

// An object with no type at all must not be dropped silently.
assert_test(isVerifactuApplicableInvoice(new stdClass()), 'an object without a type is kept in scope', $passed, $failed, $total);

// The constants must match the ones a real Dolibarr install exposes. If Dolibarr
// is available locally, assert against its own source rather than our copy.
$dolibarrFacture = getenv('DOLIBARR_HTDOCS') . '/compta/facture/class/facture.class.php';
if (getenv('DOLIBARR_HTDOCS') && is_readable($dolibarrFacture)) {
    $factureSource = file_get_contents($dolibarrFacture);
    preg_match('/const TYPE_PROFORMA\s*=\s*(\d+)/', $factureSource, $m);
    $realProforma = isset($m[1]) ? (int) $m[1] : null;
    preg_match('/const TYPE_CREDIT_NOTE\s*=\s*(\d+)/', $factureSource, $m);
    $realCreditNote = isset($m[1]) ? (int) $m[1] : null;

    assert_test($realProforma === 4, 'real Dolibarr TYPE_PROFORMA is 4', $passed, $failed, $total);
    assert_test($realCreditNote === 2, 'real Dolibarr TYPE_CREDIT_NOTE is 2', $passed, $failed, $total);
    assert_test(
        !isVerifactuApplicableInvoice(new VerifactuTestTypedInvoice($realProforma)),
        'real Dolibarr proforma type is excluded',
        $passed,
        $failed,
        $total
    );
    assert_test(
        isVerifactuApplicableInvoice(new VerifactuTestTypedInvoice($realCreditNote)),
        'real Dolibarr credit note type is included',
        $passed,
        $failed,
        $total
    );
} else {
    echo "  SKIP: set DOLIBARR_HTDOCS to cross-check the type constants against a real install\n";
}

$triggerSource = file_get_contents($moduleRoot . '/core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php');
assert_test(
    strpos($triggerSource, 'isVerifactuApplicableInvoice($object)') !== false,
    'billValidate() guards proformas before any VeriFactu processing',
    $passed,
    $failed,
    $total
);

$submissionSource = file_get_contents($moduleRoot . '/lib/functions/functions.submission.php');
assert_test(
    strpos($submissionSource, 'isVerifactuApplicableInvoice($facture)') !== false,
    'execVERIFACTUCall() guards proformas for every entry point',
    $passed,
    $failed,
    $total
);

echo "\n";

// ---------------------------------------------------------------------------
// Issue #30 (point 5) - stored rectification type must match what is sent
// ---------------------------------------------------------------------------
echo "Issue #30 (5): stored rectification type matches the transmitted one\n";

require_once $moduleRoot . '/lib/newfenix/src/Invoice.php';

// billCreate() stores the type shown in the VeriFactu tab; functions.submission.php
// decides what is actually transmitted. They must agree for non-TakePOS invoices.
$storedCreditNoteType = null;
if (preg_match('/case \$object::TYPE_CREDIT_NOTE:.*?VerifactuInvoice::(TYPE_\w+)/s', $triggerSource, $matches)) {
    $storedCreditNoteType = $matches[1];
}
$storedReplacementType = null;
if (preg_match('/case \$object::TYPE_REPLACEMENT:.*?VerifactuInvoice::(TYPE_\w+)/s', $triggerSource, $matches)) {
    $storedReplacementType = $matches[1];
}

assert_test($storedCreditNoteType === 'TYPE_CREDIT_NOTE_LEGAL', 'credit note is stored as R1, the type actually sent', $passed, $failed, $total);
assert_test($storedReplacementType === 'TYPE_CREDIT_NOTE_LEGAL', 'replacement invoice is stored as R1, the type actually sent', $passed, $failed, $total);
assert_test(
    OpenAEAT\Billing\Invoice::TYPE_CREDIT_NOTE_LEGAL === 'R1',
    'TYPE_CREDIT_NOTE_LEGAL is the R1 code',
    $passed,
    $failed,
    $total
);

$storedProformaType = null;
if (preg_match('/case \$object::TYPE_PROFORMA:.*?options_verifactu_factura_tipo\'\] = ([^;]+);/s', $triggerSource, $matches)) {
    $storedProformaType = trim($matches[1]);
}
assert_test($storedProformaType === "''", 'proforma gets no VeriFactu invoice type', $passed, $failed, $total);

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
