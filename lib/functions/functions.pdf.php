<?php
/* Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
 * Copyright (C) 2025-2026 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * Based on original code from verifactu module by Alberto SuperAdmin (easysoft.es)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    verifactu/lib/functions/functions.pdf.php
 * \ingroup verifactu
 * \brief   Placement of the tax QR code on invoice PDFs
 *
 * Two paths put the QR on a PDF and both use these functions:
 *  - the bundled model pdf_verifactu draws it while the document is built;
 *  - the afterPDFCreation hook re-opens the PDF produced by any other model
 *    with TCPDI and draws it on the imported pages.
 *
 * The second path depends on TCPDI being able to parse the generated file.
 * It cannot read an encrypted PDF (Dolibarr option PDF_SECURITY_ENCRYPTION)
 * and some files make its parser fail with "decodeFilterFlateDecode: invalid
 * code" (issue #55). Ghostscript, when the server has it, rewrites such a file
 * into one TCPDI can import; it is used only as a fallback and never required.
 */

/**
 * Reads the QR layout settings from the module configuration.
 *
 * Size and position follow Art. 20 and 21 of Orden HAC/1177/2024: a side of
 * 30 to 40 mm, near the top of the page, with the text "QR tributario:"
 * above it and the legend "VeriFactu" below.
 *
 * @return array{size: float, position_x: string, custom_x: float, position_y: float, show_text: bool, text_size: int, all_pages: bool}
 */
function getVerifactuQrPdfSettings()
{
	global $conf;

	$positionY = 15.0;
	if (isset($conf->global->VERIFACTU_QR_POSITION_Y) && is_numeric($conf->global->VERIFACTU_QR_POSITION_Y) && $conf->global->VERIFACTU_QR_POSITION_Y >= 0) {
		$positionY = (float) $conf->global->VERIFACTU_QR_POSITION_Y;
	}

	return array(
		'size' => !empty($conf->global->VERIFACTU_QR_SIZE) ? (float) $conf->global->VERIFACTU_QR_SIZE : 35.0,
		'position_x' => isset($conf->global->VERIFACTU_QR_POSITION_X) ? (string) $conf->global->VERIFACTU_QR_POSITION_X : 'center',
		'custom_x' => !empty($conf->global->VERIFACTU_QR_POSITION_X_CUSTOM) ? (float) $conf->global->VERIFACTU_QR_POSITION_X_CUSTOM : 50.0,
		'position_y' => $positionY,
		'show_text' => !empty($conf->global->VERIFACTU_QR_SHOW_TEXT),
		'text_size' => !empty($conf->global->VERIFACTU_QR_TEXT_SIZE) ? (int) $conf->global->VERIFACTU_QR_TEXT_SIZE : 8,
		'all_pages' => !empty($conf->global->VERIFACTU_QR_ALL_PAGES),
	);
}

/**
 * Works out where the QR block goes on a page of the given size.
 *
 * The block is the "QR tributario:" text (4 mm), 1 mm of spacing, the QR
 * itself and the legend below it. The specification asks for at least 2 mm of
 * clear margin around the code, so a block that would not fit with that
 * margin is reported as not fitting instead of being drawn off the page.
 *
 * @param  array $settings   Settings from getVerifactuQrPdfSettings()
 * @param  float $pageWidth  Page width in mm
 * @param  float $pageHeight Page height in mm
 * @return array{x: float, y_text: float, y_qr: float, y_legend: float, size: float, fits: bool}
 */
function computeVerifactuQrPlacement(array $settings, $pageWidth, $pageHeight)
{
	$size = (float) $settings['size'];
	$textHeight = 4.0;
	$spacing = 1.0;
	$margin = 2.0;
	$leftMargin = 10.0;
	$rightMargin = 10.0;

	$positionX = $settings['position_x'];
	if (is_numeric($positionX)) {
		$x = (float) $positionX;
	} else {
		switch (strtolower((string) $positionX)) {
			case 'left':
				$x = $leftMargin;
				break;
			case 'right':
				$x = $pageWidth - $rightMargin - $size;
				break;
			case 'custom':
				$x = (float) $settings['custom_x'];
				break;
			case 'center':
			default:
				$x = ($pageWidth - $size) / 2;
				break;
		}
	}

	$yText = (float) $settings['position_y'];
	$yQr = $yText + $textHeight + $spacing;
	$yLegend = $yQr + $size + 1.0;

	$fits = ($x - $margin >= 0) && ($x + $size + $margin <= $pageWidth)
		&& ($yQr - $margin >= 0) && ($yQr + $size + $margin <= $pageHeight);

	return array(
		'x' => $x,
		'y_text' => $yText,
		'y_qr' => $yQr,
		'y_legend' => $yLegend,
		'size' => $size,
		'fits' => $fits,
	);
}

/**
 * Draws the tax QR block on the current page of a TCPDF document.
 *
 * Works on any TCPDF or TCPDI instance, so the bundled model and the hook
 * share it. The font, text colour and cursor the caller had are restored
 * afterwards, since the model keeps writing the page after the call.
 *
 * The texts are the ones Art. 20.1.b and 21 of Orden HAC/1177/2024 prescribe
 * for a VERI*FACTU system: "QR tributario:" above the code and "VeriFactu"
 * below it.
 *
 * @param  TCPDF  $pdf        Document positioned on the page to draw on
 * @param  float  $pageWidth  Page width in mm
 * @param  float  $pageHeight Page height in mm
 * @param  string $qrUrl      AEAT verification URL encoded in the QR
 * @param  array  $settings   Settings from getVerifactuQrPdfSettings()
 * @return bool               True when drawn, false when the block does not fit on the page
 */
function drawVerifactuQrOnPdfPage($pdf, $pageWidth, $pageHeight, $qrUrl, array $settings)
{
	if (empty($qrUrl)) {
		return false;
	}

	$placement = computeVerifactuQrPlacement($settings, $pageWidth, $pageHeight);
	if (!$placement['fits']) {
		dol_syslog("VERIFACTU: QR position out of page bounds with the required 2 mm margins (x=" . $placement['x'] . ", y=" . $placement['y_qr'] . ", size=" . $placement['size'] . ", page " . $pageWidth . "x" . $pageHeight . ")", LOG_WARNING);
		return false;
	}

	$x = $placement['x'];
	$size = $placement['size'];
	$textSize = max(1, (int) $settings['text_size']);

	// Remember what the caller was writing with.
	$fontFamily = method_exists($pdf, 'getFontFamily') ? $pdf->getFontFamily() : '';
	$fontStyle = method_exists($pdf, 'getFontStyle') ? $pdf->getFontStyle() : '';
	$fontSize = method_exists($pdf, 'getFontSizePt') ? $pdf->getFontSizePt() : 0;
	$cursorX = method_exists($pdf, 'GetX') ? $pdf->GetX() : null;
	$cursorY = method_exists($pdf, 'GetY') ? $pdf->GetY() : null;

	if (!empty($settings['show_text'])) {
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', 'B', $textSize);
		$pdf->SetXY($x, $placement['y_text']);
		$pdf->Cell($size, 4, 'QR tributario:', 0, 0, 'C');
	}

	// ISO/IEC 18004 with error correction level M, as the specification asks
	$style = array(
		'border' => 0,
		'vpadding' => 0,
		'hpadding' => 0,
		'fgcolor' => array(0, 0, 0),
		'bgcolor' => array(255, 255, 255),
		'module_width' => 1,
		'module_height' => 1,
	);
	$pdf->write2DBarcode($qrUrl, 'QRCODE,M', $x, $placement['y_qr'], $size, $size, $style, 'N');

	if (!empty($settings['show_text'])) {
		$pdf->SetFont('', 'B', max(1, $textSize - 1));
		$pdf->SetXY($x, $placement['y_legend']);
		$pdf->Cell($size, 3, 'VeriFactu', 0, 0, 'C');
	}

	// Leave the document as it was
	if ($fontSize > 0) {
		$pdf->SetFont($fontFamily, $fontStyle, $fontSize);
	}
	if ($cursorX !== null && $cursorY !== null) {
		$pdf->SetXY($cursorX, $cursorY);
	}

	return true;
}

/**
 * Tells whether a PDF file is encrypted.
 *
 * Dolibarr encrypts every generated PDF when PDF_SECURITY_ENCRYPTION is on.
 * Such a file opens in any reader (the user password is usually empty) but
 * TCPDI cannot import it: its parser inflates the still-encrypted content
 * streams and fails with "decodeFilterFlateDecode: invalid code".
 *
 * Dolibarr's own pdf_getEncryption() is used when it is loaded; otherwise the
 * trailer is inspected directly.
 *
 * @param  string $file Path of the PDF
 * @return bool         True when the file declares an /Encrypt dictionary
 */
function isVerifactuPdfEncrypted($file)
{
	if (!is_readable($file)) {
		return false;
	}

	if (function_exists('pdf_getEncryption')) {
		try {
			return (bool) pdf_getEncryption($file);
		} catch (\Throwable $e) {
			dol_syslog("VERIFACTU: pdf_getEncryption() failed on " . $file . ": " . $e->getMessage(), LOG_WARNING);
		}
	}

	// The trailer sits at the end of the file; 64 KB is plenty for it even
	// when the document carries an update section.
	$size = filesize($file);
	$tail = file_get_contents($file, false, null, max(0, $size - 65536));

	return ($tail !== false && preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/', $tail) === 1);
}

/**
 * Finds the Ghostscript binary the fallback may use.
 *
 * VERIFACTU_PDF_GHOSTSCRIPT governs it: empty, the binary is looked up in the
 * PATH (gs on Unix, gswin64c/gswin32c on Windows); '0' or 'off' disables the
 * fallback; any other value is taken as the path of the binary. Running it
 * needs proc_open, which shared hosting often disables.
 *
 * @return string Path or command of the binary, empty when none can be used
 */
function getVerifactuGhostscriptBinary()
{
	global $conf;

	$setting = trim((string) ($conf->global->VERIFACTU_PDF_GHOSTSCRIPT ?? ''));
	if (in_array(strtolower($setting), array('0', 'off', 'no', 'disabled'), true)) {
		return '';
	}

	$procOpenAvailable = function_exists('isPhpFunctionAvailable') ? isPhpFunctionAvailable('proc_open') : function_exists('proc_open');
	if (!$procOpenAvailable) {
		return '';
	}

	if ($setting !== '') {
		return (is_file($setting) && is_executable($setting)) ? $setting : '';
	}

	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	$candidates = $isWindows ? array('gswin64c.exe', 'gswin32c.exe', 'gs.exe') : array('gs');

	$path = getenv('PATH');
	if (!is_string($path) || $path === '') {
		$path = isset($_SERVER['PATH']) && is_string($_SERVER['PATH']) ? $_SERVER['PATH'] : '';
	}
	if ($path === '') {
		$path = $isWindows ? '' : '/usr/bin:/usr/local/bin:/bin';
	}

	foreach (explode(PATH_SEPARATOR, $path) as $dir) {
		$dir = rtrim($dir, '/\\');
		if ($dir === '') {
			continue;
		}
		foreach ($candidates as $candidate) {
			$binary = $dir . DIRECTORY_SEPARATOR . $candidate;
			if (is_file($binary) && is_executable($binary)) {
				return $binary;
			}
		}
	}

	return '';
}

/**
 * Builds the Ghostscript command that rewrites a PDF for TCPDI.
 *
 * The output is PDF 1.4 with uncompressed page streams and fonts, which is
 * the form TCPDI parses without trouble, and -dSAFER keeps Ghostscript from
 * touching anything but the two files it is given. Ghostscript also drops the
 * encryption of a file whose user password is empty.
 *
 * @param  string $binary Ghostscript binary
 * @param  string $source PDF to read
 * @param  string $target PDF to write
 * @return string[]       Command and arguments
 */
function buildVerifactuGhostscriptCommand($binary, $source, $target)
{
	return array(
		$binary,
		'-q',
		'-dNOPAUSE',
		'-dBATCH',
		'-dSAFER',
		'-sDEVICE=pdfwrite',
		'-dCompatibilityLevel=1.4',
		'-dCompressPages=false',
		'-dCompressFonts=false',
		'-sOutputFile=' . $target,
		$source,
	);
}

/**
 * Runs an external command without a shell and with a time limit.
 *
 * Arguments travel as an array, so no quoting is involved and nothing in a
 * file name can inject another command. A process still running when the
 * limit expires is terminated.
 *
 * @param  string[] $arguments      Command and arguments, binary first
 * @param  int      $timeoutSeconds Seconds to wait before giving up
 * @return array{exit_code: int, output: string, timed_out: bool}
 */
function runVerifactuProcess(array $arguments, $timeoutSeconds = 60)
{
	if (!function_exists('proc_open')) {
		return array('exit_code' => -1, 'output' => 'proc_open is disabled on this server', 'timed_out' => false);
	}

	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);

	// Same reasoning as runOpensslCommand(): the web SAPI may hand an empty
	// $_ENV, and without PATH the child could not even be located.
	$environment = array();
	if (function_exists('getenv')) {
		$inherited = getenv();
		if (is_array($inherited)) {
			$environment = array_filter($inherited, 'is_string');
		}
	}
	if (empty($environment['PATH']) && !empty($_SERVER['PATH']) && is_string($_SERVER['PATH'])) {
		$environment['PATH'] = $_SERVER['PATH'];
	}

	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	$command = $isWindows ? implode(' ', array_map('escapeshellarg', $arguments)) : $arguments;

	$process = @proc_open($command, $descriptors, $pipes, null, $environment);
	if (!is_resource($process)) {
		return array('exit_code' => -1, 'output' => 'Could not start ' . $arguments[0], 'timed_out' => false);
	}

	fclose($pipes[0]);
	stream_set_blocking($pipes[1], false);
	stream_set_blocking($pipes[2], false);

	$output = '';
	$timedOut = false;
	$deadline = microtime(true) + max(1, (int) $timeoutSeconds);
	do {
		$output .= (string) stream_get_contents($pipes[1]);
		$output .= (string) stream_get_contents($pipes[2]);
		$status = proc_get_status($process);
		if (!$status['running']) {
			break;
		}
		if (microtime(true) > $deadline) {
			$timedOut = true;
			proc_terminate($process, 9);
			break;
		}
		usleep(50000);
	} while (true);

	$output .= (string) stream_get_contents($pipes[1]);
	$output .= (string) stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	$exitCode = proc_close($process);
	if (!$timedOut && isset($status) && !$status['running'] && isset($status['exitcode']) && $status['exitcode'] !== -1) {
		// proc_close() returns -1 once proc_get_status() has reaped the child
		$exitCode = $status['exitcode'];
	}

	return array('exit_code' => $timedOut ? -1 : (int) $exitCode, 'output' => trim($output), 'timed_out' => $timedOut);
}

/**
 * Rewrites a PDF with Ghostscript so TCPDI can import it.
 *
 * @param  string $source PDF to read
 * @param  string $target PDF to write
 * @param  string $binary Ghostscript binary, from getVerifactuGhostscriptBinary()
 * @return array{success: bool, output: string}
 */
function normalizeVerifactuPdfWithGhostscript($source, $target, $binary)
{
	if ($binary === '' || !is_readable($source)) {
		return array('success' => false, 'output' => 'Ghostscript is not available or the source file cannot be read');
	}

	$run = runVerifactuProcess(buildVerifactuGhostscriptCommand($binary, $source, $target), 120);

	$success = ($run['exit_code'] === 0 && is_file($target) && filesize($target) > 0);
	if (!$success) {
		@unlink($target);
		$detail = $run['timed_out'] ? 'Ghostscript did not finish in time' : ('Ghostscript exit code ' . $run['exit_code']);
		return array('success' => false, 'output' => trim($detail . ' ' . $run['output']));
	}

	return array('success' => true, 'output' => $run['output']);
}
