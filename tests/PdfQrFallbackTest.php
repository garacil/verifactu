<?php

/**
 * Regression test for issue #55: the tax QR missing from the PDF of an invoice
 * generated with a Dolibarr template (Crabe), with
 * "TCPDF_PARSER ERROR: decodeFilterFlateDecode: invalid code" in the log.
 *
 * The afterPDFCreation hook adds the QR by importing the generated PDF with
 * TCPDI. That import has no way out when TCPDI cannot parse the file: an
 * encrypted PDF (PDF_SECURITY_ENCRYPTION) always fails, and so do some files
 * its parser inflates wrongly. The reporter normalized the PDF with Ghostscript
 * and TCPDI imported it. The hook now:
 *   - creates the TCPDI instance with pdf_getInstance() before checking for it,
 *     since that call is what loads TCPDI;
 *   - detects an encrypted PDF before handing it to the parser;
 *   - rewrites the file with Ghostscript when it is available, and retries once;
 *   - writes the result to a temporary file and moves it over the original;
 *   - tells the user when the PDF went out without the mandatory QR.
 * The bundled pdf_verifactu model draws the QR while building the document and
 * tells the hook so, which removes the import from that path altogether.
 *
 * Covers lib/functions/functions.pdf.php, the hook class and the model.
 *
 * Run with: php tests/PdfQrFallbackTest.php
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
if (!function_exists('dol_syslog')) {
    function dol_syslog($message, $level = 0)
    {
    }
}

$conf = new stdClass();
$conf->global = new stdClass();

require_once $moduleRoot . '/lib/functions/functions.pdf.php';

/**
 * Records the TCPDF calls the drawing function makes, in order.
 */
class RecordingPdf
{
    public $calls = array();
    private $family = 'helvetica';
    private $style = '';
    private $size = 10.0;
    private $x = 42.0;
    private $y = 99.0;

    public function getFontFamily()
    {
        return $this->family;
    }
    public function getFontStyle()
    {
        return $this->style;
    }
    public function getFontSizePt()
    {
        return $this->size;
    }
    public function GetX()
    {
        return $this->x;
    }
    public function GetY()
    {
        return $this->y;
    }
    public function SetFont($family, $style = '', $size = null)
    {
        $this->calls[] = array('SetFont', $family, $style, $size);
        if ($family !== '') {
            $this->family = $family;
        }
        $this->style = $style;
        if ($size !== null) {
            $this->size = $size;
        }
    }
    public function SetTextColor($r, $g, $b)
    {
        $this->calls[] = array('SetTextColor', $r, $g, $b);
    }
    public function SetXY($x, $y)
    {
        $this->calls[] = array('SetXY', $x, $y);
        $this->x = $x;
        $this->y = $y;
    }
    public function Cell($w, $h, $text, $border, $ln, $align)
    {
        $this->calls[] = array('Cell', $w, $h, $text, $align);
    }
    public function write2DBarcode($code, $type, $x, $y, $w, $h, $style, $align)
    {
        $this->calls[] = array('write2DBarcode', $code, $type, $x, $y, $w, $h);
    }
}

function callsNamed(array $calls, $name)
{
    return array_values(array_filter($calls, function ($call) use ($name) {
        return $call[0] === $name;
    }));
}

echo "=== Tax QR on the invoice PDF (issue #55) ===\n\n";

// ---------------------------------------------------------------------------
// getVerifactuQrPdfSettings()
// ---------------------------------------------------------------------------
echo "getVerifactuQrPdfSettings()\n";

$settings = getVerifactuQrPdfSettings();
assert_test($settings['size'] === 35.0 && $settings['position_x'] === 'center' && $settings['position_y'] === 15.0, 'without configuration: 35 mm, centred, 15 mm from the top', $passed, $failed, $total);
assert_test($settings['show_text'] === false && $settings['text_size'] === 8 && $settings['all_pages'] === false, 'texts off, 8 pt and first page only until configured', $passed, $failed, $total);

$conf->global->VERIFACTU_QR_SIZE = '30';
$conf->global->VERIFACTU_QR_POSITION_X = 'custom';
$conf->global->VERIFACTU_QR_POSITION_X_CUSTOM = '70';
$conf->global->VERIFACTU_QR_POSITION_Y = '2';
$conf->global->VERIFACTU_QR_SHOW_TEXT = '1';
$conf->global->VERIFACTU_QR_TEXT_SIZE = '8';
$conf->global->VERIFACTU_QR_ALL_PAGES = '0';
$settings = getVerifactuQrPdfSettings();
assert_test($settings['size'] === 30.0 && $settings['position_x'] === 'custom' && $settings['custom_x'] === 70.0 && $settings['position_y'] === 2.0 && $settings['show_text'] === true, 'the module activation defaults are read back as configured', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// computeVerifactuQrPlacement()
// ---------------------------------------------------------------------------
echo "\ncomputeVerifactuQrPlacement()\n";

$placement = computeVerifactuQrPlacement($settings, 210, 297);
assert_test($placement['x'] === 70.0, 'custom position takes the custom X', $passed, $failed, $total);
assert_test($placement['y_text'] === 2.0 && $placement['y_qr'] === 7.0 && $placement['y_legend'] === 38.0, 'the "QR tributario:" text sits 4 mm above the code, the legend 1 mm below it', $passed, $failed, $total);
assert_test($placement['fits'] === true, 'and the block fits on an A4 page', $passed, $failed, $total);

$settings['position_x'] = 'center';
assert_test(computeVerifactuQrPlacement($settings, 210, 297)['x'] === 90.0, 'centred: (page width - size) / 2', $passed, $failed, $total);
$settings['position_x'] = 'left';
assert_test(computeVerifactuQrPlacement($settings, 210, 297)['x'] === 10.0, 'left: 10 mm margin', $passed, $failed, $total);
$settings['position_x'] = 'right';
assert_test(computeVerifactuQrPlacement($settings, 210, 297)['x'] === 170.0, 'right: 10 mm from the right edge', $passed, $failed, $total);
$settings['position_x'] = '12.5';
assert_test(computeVerifactuQrPlacement($settings, 210, 297)['x'] === 12.5, 'a numeric value is an exact X in mm', $passed, $failed, $total);
$settings['position_x'] = 'right';
assert_test(computeVerifactuQrPlacement($settings, 297, 210)['x'] === 257.0, 'a landscape page is measured by its own width', $passed, $failed, $total);

$settings['position_x'] = '190';
assert_test(computeVerifactuQrPlacement($settings, 210, 297)['fits'] === false, 'a block past the right edge, counting the 2 mm margin, does not fit', $passed, $failed, $total);
$settings['position_x'] = '1';
assert_test(computeVerifactuQrPlacement($settings, 210, 297)['fits'] === false, 'nor one glued to the left edge', $passed, $failed, $total);
$settings['position_x'] = 'center';
$settings['position_y'] = 280.0;
assert_test(computeVerifactuQrPlacement($settings, 210, 297)['fits'] === false, 'nor one past the bottom of the page', $passed, $failed, $total);
$settings['position_y'] = 2.0;

// ---------------------------------------------------------------------------
// drawVerifactuQrOnPdfPage()
// ---------------------------------------------------------------------------
echo "\ndrawVerifactuQrOnPdfPage()\n";

$pdf = new RecordingPdf();
$drawn = drawVerifactuQrOnPdfPage($pdf, 210, 297, 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR?nif=B1&numserie=F1&fecha=01-01-2026&importe=121.00', $settings);
assert_test($drawn === true, 'the block is drawn when it fits', $passed, $failed, $total);

$barcodes = callsNamed($pdf->calls, 'write2DBarcode');
assert_test(count($barcodes) === 1 && $barcodes[0][2] === 'QRCODE,M' && $barcodes[0][3] === 90.0 && $barcodes[0][4] === 7.0 && $barcodes[0][5] === 30.0, 'one QR code, error correction M, at the computed position and size', $passed, $failed, $total);

$cells = callsNamed($pdf->calls, 'Cell');
assert_test(count($cells) === 2 && $cells[0][3] === 'QR tributario:' && $cells[1][3] === 'VeriFactu', 'the texts "QR tributario:" and "VeriFactu" are written, centred over the code', $passed, $failed, $total);

$order = array_values(array_map(function ($call) {
    return $call[0] === 'Cell' ? $call[3] : $call[0];
}, array_filter($pdf->calls, function ($call) {
    return in_array($call[0], array('Cell', 'write2DBarcode'), true);
})));
assert_test($order === array('QR tributario:', 'write2DBarcode', 'VeriFactu'), 'text above, code, legend below, in that order', $passed, $failed, $total);

$fonts = callsNamed($pdf->calls, 'SetFont');
$lastFont = end($fonts);
$positions = callsNamed($pdf->calls, 'SetXY');
$lastPosition = end($positions);
assert_test($lastFont === array('SetFont', 'helvetica', '', 10.0), 'the font the model was using is restored afterwards', $passed, $failed, $total);
assert_test($lastPosition === array('SetXY', 42.0, 99.0), 'and so is the cursor, so the model keeps writing where it was', $passed, $failed, $total);

$settings['show_text'] = false;
$pdf = new RecordingPdf();
drawVerifactuQrOnPdfPage($pdf, 210, 297, 'https://example.test/qr', $settings);
assert_test(count(callsNamed($pdf->calls, 'Cell')) === 0 && count(callsNamed($pdf->calls, 'write2DBarcode')) === 1, 'with the texts disabled only the code is drawn', $passed, $failed, $total);
$settings['show_text'] = true;

$pdf = new RecordingPdf();
$settings['position_x'] = '200';
assert_test(drawVerifactuQrOnPdfPage($pdf, 210, 297, 'https://example.test/qr', $settings) === false && empty($pdf->calls), 'a block that does not fit is not drawn at all', $passed, $failed, $total);
$settings['position_x'] = 'center';

$pdf = new RecordingPdf();
assert_test(drawVerifactuQrOnPdfPage($pdf, 210, 297, '', $settings) === false && empty($pdf->calls), 'nothing is drawn without a URL', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// isVerifactuPdfEncrypted()
// ---------------------------------------------------------------------------
echo "\nisVerifactuPdfEncrypted()\n";

$workDir = sys_get_temp_dir() . '/verifactu-qr-test-' . uniqid();
mkdir($workDir, 0700);

$plainPdf = $workDir . '/plain.pdf';
file_put_contents($plainPdf, "%PDF-1.7\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\ntrailer\n<< /Root 1 0 R /Size 3 >>\n%%EOF\n");
$encryptedPdf = $workDir . '/encrypted.pdf';
file_put_contents($encryptedPdf, "%PDF-1.7\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n4 0 obj\n<< /Filter /Standard /V 1 /R 2 >>\nendobj\ntrailer\n<< /Root 1 0 R /Size 5 /Encrypt 4 0 R >>\n%%EOF\n");

assert_test(isVerifactuPdfEncrypted($plainPdf) === false, 'a PDF without /Encrypt in its trailer is not encrypted', $passed, $failed, $total);
assert_test(isVerifactuPdfEncrypted($encryptedPdf) === true, 'a PDF whose trailer references an /Encrypt dictionary is', $passed, $failed, $total);
assert_test(isVerifactuPdfEncrypted($workDir . '/missing.pdf') === false, 'a missing file is simply not encrypted', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// Ghostscript: detection, command and normalization
// ---------------------------------------------------------------------------
echo "\nGhostscript fallback\n";

$binDir = $workDir . '/bin';
mkdir($binDir, 0700);
$fakeGs = $binDir . '/gs';
// A stand-in that behaves like Ghostscript for what the module needs: it
// writes the file named by -sOutputFile and copies the input into it.
file_put_contents($fakeGs, "#!/bin/sh\nout=''\nin=''\nfor a in \"\$@\"; do case \"\$a\" in -sOutputFile=*) out=\"\${a#-sOutputFile=}\";; -*) ;; *) in=\"\$a\";; esac; done\n[ -n \"\$out\" ] && cp \"\$in\" \"\$out\"\necho normalized\nexit 0\n");
chmod($fakeGs, 0700);
$brokenGs = $binDir . '/gs-broken';
file_put_contents($brokenGs, "#!/bin/sh\necho 'GPL Ghostscript: Unrecoverable error' >&2\nexit 1\n");
chmod($brokenGs, 0700);

$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$canRunProcesses = function_exists('proc_open') && !$isWindows;

$originalPath = getenv('PATH');
putenv('PATH=' . $binDir);
$conf->global->VERIFACTU_PDF_GHOSTSCRIPT = '';
$found = getVerifactuGhostscriptBinary();
assert_test(!function_exists('proc_open') || $found === $fakeGs, 'with no setting the gs binary is looked up in the PATH', $passed, $failed, $total);

putenv('PATH=' . $workDir);
assert_test(getVerifactuGhostscriptBinary() === '', 'and nothing is returned when the PATH has none', $passed, $failed, $total);

$conf->global->VERIFACTU_PDF_GHOSTSCRIPT = $brokenGs;
assert_test(!function_exists('proc_open') || getVerifactuGhostscriptBinary() === $brokenGs, 'a configured path is used as it is', $passed, $failed, $total);
$conf->global->VERIFACTU_PDF_GHOSTSCRIPT = $workDir . '/does-not-exist';
assert_test(getVerifactuGhostscriptBinary() === '', 'a configured path that does not exist yields nothing', $passed, $failed, $total);
$conf->global->VERIFACTU_PDF_GHOSTSCRIPT = '0';
putenv('PATH=' . $binDir);
assert_test(getVerifactuGhostscriptBinary() === '', 'VERIFACTU_PDF_GHOSTSCRIPT = 0 disables the fallback even when gs is in the PATH', $passed, $failed, $total);
$conf->global->VERIFACTU_PDF_GHOSTSCRIPT = '';
putenv('PATH=' . $originalPath);

$command = buildVerifactuGhostscriptCommand('/usr/bin/gs', '/tmp/in.pdf', '/tmp/out.pdf');
assert_test($command[0] === '/usr/bin/gs' && end($command) === '/tmp/in.pdf', 'the command runs the binary on the source file', $passed, $failed, $total);
assert_test(in_array('-dSAFER', $command, true) && in_array('-sDEVICE=pdfwrite', $command, true) && in_array('-dCompatibilityLevel=1.4', $command, true) && in_array('-dCompressPages=false', $command, true) && in_array('-sOutputFile=/tmp/out.pdf', $command, true), 'with -dSAFER, pdfwrite, PDF 1.4 and uncompressed pages, the recipe that made TCPDI import the reported file', $passed, $failed, $total);
assert_test(in_array('-dNOPAUSE', $command, true) && in_array('-dBATCH', $command, true) && in_array('-q', $command, true), 'and without any interactive prompt', $passed, $failed, $total);

if (!$canRunProcesses) {
    echo "  SKIP: proc_open is not available here, the normalization itself is not run\n";
} else {
    $target = $workDir . '/normalized.pdf';
    $result = normalizeVerifactuPdfWithGhostscript($plainPdf, $target, $fakeGs);
    assert_test($result['success'] === true && is_file($target) && file_get_contents($target) === file_get_contents($plainPdf), 'normalization runs the binary and leaves the rewritten file in place', $passed, $failed, $total);

    $result = normalizeVerifactuPdfWithGhostscript($plainPdf, $workDir . '/never.pdf', $brokenGs);
    assert_test($result['success'] === false && strpos($result['output'], 'Unrecoverable error') !== false && !file_exists($workDir . '/never.pdf'), 'a failing binary is reported with its own message and leaves no file behind', $passed, $failed, $total);

    $result = normalizeVerifactuPdfWithGhostscript($plainPdf, $workDir . '/never2.pdf', '');
    assert_test($result['success'] === false, 'without a binary nothing is attempted', $passed, $failed, $total);

    $run = runVerifactuProcess(array('/bin/sh', '-c', 'echo out; echo err >&2; exit 3'));
    assert_test($run['exit_code'] === 3 && strpos($run['output'], 'out') !== false && strpos($run['output'], 'err') !== false && $run['timed_out'] === false, 'runVerifactuProcess() returns the exit code and both output streams', $passed, $failed, $total);

    $run = runVerifactuProcess(array('/bin/sh', '-c', 'sleep 5'), 1);
    assert_test($run['timed_out'] === true && $run['exit_code'] === -1, 'a process that overruns the limit is stopped and reported', $passed, $failed, $total);
}

// Clean up
foreach (glob($workDir . '/*') as $entry) {
    if (is_dir($entry)) {
        array_map('unlink', glob($entry . '/*'));
        rmdir($entry);
    } else {
        unlink($entry);
    }
}
rmdir($workDir);

// ---------------------------------------------------------------------------
// The hook class
// ---------------------------------------------------------------------------
echo "\nclass/actions_verifactu.class.php\n";

$hookSource = file_get_contents($moduleRoot . '/class/actions_verifactu.class.php');

$instancePosition = strpos($hookSource, '$pdf = pdf_getInstance();');
$checkPosition = strpos($hookSource, "method_exists(\$pdf, 'setSourceFile')");
assert_test(strpos($hookSource, "if (!class_exists('TCPDI'))") === false, 'TCPDI is no longer checked with class_exists() before anything has loaded it', $passed, $failed, $total);
assert_test($instancePosition !== false && $checkPosition !== false && $instancePosition < $checkPosition, 'pdf_getInstance() is called first, then the instance is checked for setSourceFile()', $passed, $failed, $total);
assert_test(strpos($hookSource, 'isVerifactuPdfEncrypted($file)') !== false, 'an encrypted PDF is detected before TCPDI is asked to parse it', $passed, $failed, $total);
assert_test(strpos($hookSource, 'normalizePdfForImport(') !== false && strpos($hookSource, 'getVerifactuGhostscriptBinary()') !== false, 'the Ghostscript normalization is the fallback of a failed import', $passed, $failed, $total);
assert_test(preg_match('/\$output = \$file \. \'\.verifactu-\' \. uniqid\(\) \. \'\.tmp\';\s*\$pdf->Output\(\$output, \'F\'\);/', $hookSource) === 1, 'the stamped PDF is written to a temporary file', $passed, $failed, $total);
assert_test(strpos($hookSource, '@rename($output, $file)') !== false, 'and moved over the original only when complete', $passed, $failed, $total);
assert_test(strpos($hookSource, "\$pdf->Output(\$file, 'F')") === false, 'the original is never overwritten in place', $passed, $failed, $total);
assert_test(strpos($hookSource, "VERIFACTU_PDF_QR_NOT_ADDED") !== false && strpos($hookSource, "setEventMessages(") !== false, 'the user is warned when the PDF goes out without the QR', $passed, $failed, $total);
foreach (array('TCPDI_MISSING', 'ENCRYPTED', 'IMPORT_FAILED', 'WRITE_FAILED') as $reason) {
    assert_test(strpos($hookSource, 'VERIFACTU_PDF_QR_REASON_' . $reason) !== false, 'with a specific reason when ' . strtolower(str_replace('_', ' ', $reason)), $passed, $failed, $total);
}
assert_test(strpos($hookSource, "\$parameters['verifactu_qr_embedded']") !== false, 'a model that drew the QR itself is left alone', $passed, $failed, $total);
assert_test(strpos($hookSource, 'drawVerifactuQrOnPdfPage(') !== false && strpos($hookSource, 'private function addQRToTCPDIPage') === false && strpos($hookSource, 'private function calculateQRPositionX') === false, 'the drawing itself is the shared function of functions.pdf.php', $passed, $failed, $total);
assert_test(preg_match('/afterPDFCreation\(.*?catch \(\\\\Throwable \$e\)/s', $hookSource) === 1, 'any failure inside the hook, not only Exception, is caught so the PDF generation finishes', $passed, $failed, $total);
assert_test(strpos($hookSource, '} finally {') !== false && strpos($hookSource, '@unlink($temporaryFile)') !== false, 'temporary files are removed whatever happened', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// The bundled model
// ---------------------------------------------------------------------------
echo "\ncore/modules/facture/doc/pdf_verifactu.modules.php\n";

$modelSource = file_get_contents($moduleRoot . '/core/modules/facture/doc/pdf_verifactu.modules.php');
assert_test(strpos($modelSource, 'drawVerifactuQrOnPdfPage($pdf, $this->page_largeur, $this->page_hauteur, $this->verifactuQrUrl, $qrSettings)') !== false, 'the model draws the QR itself while building the page', $passed, $failed, $total);
assert_test(strpos($modelSource, "'verifactu_qr_embedded' => \$this->verifactuQrEmbedded") !== false, 'and tells the hook so', $passed, $failed, $total);
assert_test(strpos($modelSource, 'isFactureSendToVerifactu($object)') !== false && strpos($modelSource, '$this->verifactuQrUrl = getQRUrl($object)') !== false, 'only an invoice registered at AEAT gets a URL to encode', $passed, $failed, $total);
assert_test(strpos($modelSource, "dol_include_once('/verifactu/lib/verifactu.lib.php');") !== false, 'the model loads the module library it relies on', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// Library wiring and translations
// ---------------------------------------------------------------------------
echo "\nWiring\n";

$libSource = file_get_contents($moduleRoot . '/lib/verifactu.lib.php');
assert_test(strpos($libSource, "functions.pdf.php") !== false, 'verifactu.lib.php loads functions.pdf.php', $passed, $failed, $total);

$setupSource = file_get_contents($moduleRoot . '/admin/setup.php');
assert_test(strpos($setupSource, "newItem('VERIFACTU_PDF_GHOSTSCRIPT')") !== false && strpos($setupSource, 'getVerifactuGhostscriptBinary()') !== false, 'the setup page offers the Ghostscript setting and shows whether a binary was found', $passed, $failed, $total);

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
