<?php
/* Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
 * Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
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
 * \file    verifactu/lib/functions/functions.certificates.php
 * \ingroup verifactu
 * \brief   Functions for digital certificate handling
 */

/**
 * Returns the OpenSSL binary to invoke on this platform.
 *
 * @param string $winOpensslPath Path to OpenSSL binary on Windows
 * @return string Binary path (unquoted; quoting is handled by the caller)
 */
function getOpensslBinary(string $winOpensslPath = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'): string
{
	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

	return $isWindows ? $winOpensslPath : 'openssl';
}

/**
 * Tells whether an OpenSSL failure was caused by a legacy PKCS#12 cipher
 * rather than by a wrong password.
 *
 * OpenSSL 3 moved RC2-40-CBC and PBE-SHA1-3DES (the algorithms the FNMT has
 * historically used to protect .p12 containers for individuals) into the
 * "legacy" provider, which is not active by default on Debian 12 or in the
 * official dolibarr/dolibarr image. Reading such a container then fails with
 * "digital envelope routines::unsupported" (error:0308010C), which is a very
 * different problem from an incorrect password and deserves a different message.
 *
 * @param string $errorText OpenSSL error output (CLI output or openssl_error_string())
 * @return bool True when the failure points at an unsupported legacy algorithm
 */
function isLegacyCipherOpensslError(string $errorText): bool
{
	if ($errorText === '') {
		return false;
	}

	$needles = array(
		'0308010c',                     // EVP_R_UNSUPPORTED_ALGORITHM
		'digital envelope routines',
		'unsupported algorithm',
		'rc2-cbc',
		'rc2-40-cbc',
		'pbe-sha1-3des',
		'algorithm (rc2',
	);

	$haystack = strtolower($errorText);
	foreach ($needles as $needle) {
		if (strpos($haystack, $needle) !== false) {
			return true;
		}
	}

	return false;
}

/**
 * Tells whether an OpenSSL failure really was a wrong password.
 *
 * @param string $errorText OpenSSL error output (CLI output or openssl_error_string())
 * @return bool True when the failure points at an invalid password
 */
function isWrongPasswordOpensslError(string $errorText): bool
{
	if ($errorText === '') {
		return false;
	}

	$needles = array(
		'mac verify error',
		'mac verify failure',
		'invalid password',
		'wrong final block length',
		'bad decrypt',
	);

	$haystack = strtolower($errorText);
	foreach ($needles as $needle) {
		if (strpos($haystack, $needle) !== false) {
			return true;
		}
	}

	return false;
}

/**
 * Tells whether the OpenSSL binary understands the -legacy switch (OpenSSL 3+).
 *
 * @param string $winOpensslPath Path to OpenSSL binary on Windows
 * @return bool True when -legacy can be used
 */
function opensslCliSupportsLegacyFlag(string $winOpensslPath = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'): bool
{
	static $supported = null;

	if ($supported !== null) {
		return $supported;
	}

	$result = runOpensslCommand(array(getOpensslBinary($winOpensslPath), 'version'));
	// "OpenSSL 3.x" and newer ship the legacy provider behind the -legacy switch.
	$supported = ($result['exit_code'] === 0 && preg_match('/OpenSSL\s+([3-9]|\d{2,})\./', $result['output']) === 1);

	return $supported;
}

/**
 * Runs an OpenSSL command without exposing the certificate password.
 *
 * Uses proc_open so that arguments are passed as an array (no shell quoting
 * issues, no command injection through the password) and so that the password
 * travels in the child process environment instead of the command line, where
 * it would be visible to any local user through the process list.
 *
 * @param array  $arguments Command and arguments, first element is the binary
 * @param string $password  Password exported as VERIFACTU_P12_PASS (optional)
 * @return array{exit_code:int, output:string} Exit code and combined output
 */
function runOpensslCommand(array $arguments, string $password = ''): array
{
	if (!function_exists('proc_open')) {
		return array('exit_code' => -1, 'output' => 'proc_open is disabled on this server');
	}

	$descriptors = array(
		0 => array('pipe', 'r'),
		1 => array('pipe', 'w'),
		2 => array('pipe', 'w'),
	);

	// Inherit the current environment and add the password variable.
	// getenv() is the base because under the usual web SAPI variables_order
	// ("GPCS", no "E") $_ENV comes back empty, and dropping PATH would leave
	// proc_open unable to find the openssl binary.
	$environment = array();
	if (function_exists('getenv')) {
		$inherited = getenv();
		if (is_array($inherited)) {
			$environment = array_filter($inherited, 'is_string');
		}
	}
	foreach ($_ENV as $key => $value) {
		if (is_string($value)) {
			$environment[$key] = $value;
		}
	}
	if (empty($environment['PATH']) && !empty($_SERVER['PATH']) && is_string($_SERVER['PATH'])) {
		$environment['PATH'] = $_SERVER['PATH'];
	}
	$environment['VERIFACTU_P12_PASS'] = $password;

	// On Windows proc_open needs a command string; elsewhere the array form
	// bypasses the shell entirely.
	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	if ($isWindows) {
		$command = implode(' ', array_map('escapeshellarg', $arguments));
	} else {
		$command = $arguments;
	}

	$process = @proc_open($command, $descriptors, $pipes, null, $environment);
	if (!is_resource($process)) {
		return array('exit_code' => -1, 'output' => 'Could not start the OpenSSL process');
	}

	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[2]);

	$exitCode = proc_close($process);

	return array(
		'exit_code' => $exitCode,
		'output' => trim($stdout . "\n" . $stderr),
	);
}

/**
 * Runs "openssl pkcs12" retrying with -legacy when the container uses a
 * legacy cipher that OpenSSL 3 disables by default.
 *
 * @param array  $extraArguments  pkcs12 arguments after the subcommand (e.g. -clcerts -nokeys)
 * @param string $certificateFile Path to the .pfx or .p12 file
 * @param string $outputFile      Destination file for the extracted PEM
 * @param string $password        Certificate password
 * @param string $winOpensslPath  Path to OpenSSL binary on Windows
 * @return array{success:bool, output:string, used_legacy:bool, legacy_cipher:bool, wrong_password:bool}
 */
function runOpensslPkcs12(
	array $extraArguments,
	string $certificateFile,
	string $outputFile,
	string $password,
	string $winOpensslPath = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'
): array {

	$binary = getOpensslBinary($winOpensslPath);

	$buildCommand = function (bool $withLegacy) use ($binary, $extraArguments, $certificateFile, $outputFile) {
		$command = array($binary, 'pkcs12');
		if ($withLegacy) {
			$command[] = '-legacy';
		}
		$command[] = '-in';
		$command[] = $certificateFile;
		foreach ($extraArguments as $argument) {
			$command[] = $argument;
		}
		$command[] = '-out';
		$command[] = $outputFile;
		$command[] = '-password';
		$command[] = 'env:VERIFACTU_P12_PASS';

		return $command;
	};

	// Attempt 1: standard invocation.
	$result = runOpensslCommand($buildCommand(false), $password);
	if ($result['exit_code'] === 0) {
		return array(
			'success' => true,
			'output' => $result['output'],
			'used_legacy' => false,
			'legacy_cipher' => false,
			'wrong_password' => false,
		);
	}

	$firstOutput = $result['output'];

	// Attempt 2: retry through the legacy provider when the failure looks like a
	// legacy cipher and the binary supports the switch. This is what makes FNMT
	// .p12 containers readable again under OpenSSL 3.
	if (isLegacyCipherOpensslError($firstOutput) && opensslCliSupportsLegacyFlag($winOpensslPath)) {
		$legacyResult = runOpensslCommand($buildCommand(true), $password);
		if ($legacyResult['exit_code'] === 0) {
			return array(
				'success' => true,
				'output' => $legacyResult['output'],
				'used_legacy' => true,
				'legacy_cipher' => true,
				'wrong_password' => false,
			);
		}

		return array(
			'success' => false,
			'output' => $legacyResult['output'] !== '' ? $legacyResult['output'] : $firstOutput,
			'used_legacy' => true,
			'legacy_cipher' => true,
			'wrong_password' => isWrongPasswordOpensslError($legacyResult['output']),
		);
	}

	return array(
		'success' => false,
		'output' => $firstOutput,
		'used_legacy' => false,
		'legacy_cipher' => isLegacyCipherOpensslError($firstOutput),
		'wrong_password' => isWrongPasswordOpensslError($firstOutput),
	);
}

/**
 * Tells whether a PHP function is really callable on this server.
 *
 * function_exists() already returns false for anything listed in
 * disable_functions on current PHP versions, but the ini value is checked too:
 * some SAPI and hardening setups (common on shared hosting) leave the function
 * defined while refusing to run it.
 *
 * @param string $name Function name
 * @return bool True when the function can be called
 */
function isPhpFunctionAvailable(string $name): bool
{
	if (!function_exists($name)) {
		return false;
	}

	$disabled = ini_get('disable_functions');
	if (!is_string($disabled) || $disabled === '') {
		return true;
	}

	$disabledList = array_map('trim', explode(',', strtolower($disabled)));

	return !in_array(strtolower($name), $disabledList, true);
}

/**
 * Extracts a PKCS#12 container using PHP's own OpenSSL bindings.
 *
 * This needs no external process, so it is the only path that works on shared
 * hosting where exec() and proc_open() are both in disable_functions - a very
 * common setup among the small Spanish businesses VeriFactu targets.
 *
 * It cannot open a container encrypted with a legacy cipher unless the server's
 * OpenSSL has the legacy provider active, because there is no PHP equivalent of
 * the "-legacy" switch. That case is reported through the legacy_cipher flag so
 * the caller can try the external binary instead.
 *
 * @param string $certificateFile Path to the .pfx or .p12 file
 * @param string $certificatePassword Certificate password
 * @return array{success:bool, public_cert_pem:string, private_key_pem:string, extra_certs:array, message:string, legacy_cipher:bool, wrong_password:bool}
 */
function extractPkcs12WithPhp(string $certificateFile, string $certificatePassword): array
{
	$failure = array(
		'success' => false,
		'public_cert_pem' => '',
		'private_key_pem' => '',
		'extra_certs' => array(),
		'message' => '',
		'legacy_cipher' => false,
		'wrong_password' => false,
	);

	if (!function_exists('openssl_pkcs12_read')) {
		$failure['message'] = 'The OpenSSL extension is not available in PHP';
		return $failure;
	}

	if (!is_readable($certificateFile)) {
		$failure['message'] = 'Certificate file could not be read: ' . $certificateFile;
		return $failure;
	}

	$contents = file_get_contents($certificateFile);
	if ($contents === false || $contents === '') {
		$failure['message'] = 'Certificate file is empty: ' . $certificateFile;
		return $failure;
	}

	// Drain any error left behind by earlier calls so the diagnosis below
	// describes this attempt and not a previous one.
	while (openssl_error_string()) {
		continue;
	}

	$bundle = array();
	if (!openssl_pkcs12_read($contents, $bundle, $certificatePassword)) {
		$errors = array();
		while ($error = openssl_error_string()) {
			$errors[] = $error;
		}
		$errorText = implode('; ', $errors);

		$failure['message'] = $errorText !== '' ? $errorText : 'openssl_pkcs12_read() failed without reporting a reason';
		$failure['legacy_cipher'] = isLegacyCipherOpensslError($errorText);
		$failure['wrong_password'] = isWrongPasswordOpensslError($errorText);

		return $failure;
	}

	$publicCert = isset($bundle['cert']) ? (string) $bundle['cert'] : '';
	$privateKey = '';

	if (isset($bundle['pkey']) && !empty($bundle['pkey'])) {
		// openssl_pkcs12_read() hands back the key already in PEM form on every
		// supported PHP version, but normalise it through the key functions so a
		// resource or handle is exported consistently.
		if (is_string($bundle['pkey'])) {
			$privateKey = $bundle['pkey'];
		} else {
			$exported = '';
			if (openssl_pkey_export($bundle['pkey'], $exported)) {
				$privateKey = $exported;
			}
		}
	}

	if ($publicCert === '') {
		$failure['message'] = 'The PKCS#12 container has no public certificate';
		return $failure;
	}

	return array(
		'success' => true,
		'public_cert_pem' => $publicCert,
		'private_key_pem' => $privateKey,
		'extra_certs' => isset($bundle['extracerts']) && is_array($bundle['extracerts']) ? $bundle['extracerts'] : array(),
		'message' => 'Extracted with PHP openssl_pkcs12_read()',
		'legacy_cipher' => false,
		'wrong_password' => false,
	);
}

/**
 * Builds a human-readable explanation for a failed PKCS#12 extraction.
 *
 * Distinguishes the three cases that used to collapse into the misleading
 * "check the password" message: an unsupported legacy cipher, a genuinely wrong
 * password, and anything else.
 *
 * @param array $run Result returned by runOpensslPkcs12()
 * @return string Explanatory message
 */
function describeCertificateExtractionFailure(array $run): string
{
	if (!empty($run['legacy_cipher'])) {
		return 'The certificate uses a legacy encryption algorithm (RC2-40-CBC / PBE-SHA1-3DES) '
			. 'that OpenSSL 3 disables by default. Enable the OpenSSL legacy provider on the server, '
			. 'or re-export the certificate with a modern algorithm. OpenSSL output: ' . $run['output'];
	}

	if (!empty($run['wrong_password'])) {
		return 'The certificate password is not correct. OpenSSL output: ' . $run['output'];
	}

	return 'Could not extract the certificate. OpenSSL output: ' . $run['output'];
}

/**
 * Converts a PFX/P12 certificate to a combined PEM file with certificate and private key,
 * optionally encrypting the key with a passphrase.
 *
 * @param string $certificateFile Path to the .pfx or .p12 file
 * @param string $outputPath Output directory path
 * @param string $certificatePassword Password for the PFX/P12 file
 * @param bool $encryptKey If true, the private key will be encrypted
 * @param string|null $privateKeyPassword Passphrase to encrypt the key (if null, uses $certificatePassword)
 * @param string $winOpensslPath Path to OpenSSL binary on Windows
 * @return string Path to the generated .pem file
 */
function prepareLocalCertificate(
	string $certificateFile,
	string $outputPath,
	string $certificatePassword,
	bool $encryptKey = false,
	?string $privateKeyPassword = null,
	string $winOpensslPath = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'
): string {

	$certName = pathinfo($certificateFile, PATHINFO_FILENAME);
	$outputPath = $outputPath . DIRECTORY_SEPARATOR . $certName;
	$bundleFile = $outputPath . ($encryptKey ? '_bundle_protegido.pem' : '_bundle.pem');

	if (file_exists($bundleFile)) {
		return $bundleFile;
	}

	$passOut = $privateKeyPassword ?? $certificatePassword;

	// PHP-native conversion first: works with no external process, which is the
	// only option on shared hosting where exec() and proc_open() are disabled.
	$native = extractPkcs12WithPhp($certificateFile, $certificatePassword);
	if ($native['success'] && $native['private_key_pem'] !== '') {
		$privateKeyPem = $native['private_key_pem'];

		if ($encryptKey) {
			// Re-export the key protected with the requested passphrase.
			$keyResource = openssl_pkey_get_private($privateKeyPem);
			$protectedKey = '';
			if ($keyResource !== false && openssl_pkey_export($keyResource, $protectedKey, $passOut)) {
				$privateKeyPem = $protectedKey;
			} else {
				// Without the passphrase the bundle would silently be weaker
				// than asked for, so fall through to the external binary.
				$privateKeyPem = '';
			}
		}

		if ($privateKeyPem !== '') {
			file_put_contents($bundleFile, trim($native['public_cert_pem']) . "\n" . trim($privateKeyPem) . "\n");
			return $bundleFile;
		}
	}

	if (!isPhpFunctionAvailable('proc_open')) {
		throw new RuntimeException(describeCertificateExtractionFailure(array(
			'output' => $native['message'] . ' (proc_open is disabled on this server, so the external OpenSSL binary cannot be used either)',
			'legacy_cipher' => $native['legacy_cipher'],
			'wrong_password' => $native['wrong_password'],
		)));
	}

	$certOut = $outputPath . '_cert.pem';
	$keyOut = $outputPath . '_key.pem';

	// Extract certificate without key.
	// runOpensslPkcs12() retries with -legacy so that FNMT containers protected
	// with RC2-40-CBC / PBE-SHA1-3DES keep working under OpenSSL 3.
	$certRun = runOpensslPkcs12(
		array('-clcerts', '-nokeys'),
		$certificateFile,
		$certOut,
		$certificatePassword,
		$winOpensslPath
	);

	if (!$certRun['success'] || !file_exists($certOut)) {
		throw new RuntimeException(describeCertificateExtractionFailure($certRun));
	}

	// Extract private key (with or without passphrase)
	$keyArguments = array('-nocerts');
	if (!$encryptKey) {
		$keyArguments[] = '-nodes';
	}
	if ($encryptKey) {
		// -passout accepts the same env: syntax as -password; a second variable
		// keeps the passphrase off the command line as well.
		putenv('VERIFACTU_P12_PASSOUT=' . $passOut);
		$_ENV['VERIFACTU_P12_PASSOUT'] = $passOut;
		$keyArguments[] = '-passout';
		$keyArguments[] = 'env:VERIFACTU_P12_PASSOUT';
	}

	$keyRun = runOpensslPkcs12(
		$keyArguments,
		$certificateFile,
		$keyOut,
		$certificatePassword,
		$winOpensslPath
	);

	if ($encryptKey) {
		putenv('VERIFACTU_P12_PASSOUT');
		unset($_ENV['VERIFACTU_P12_PASSOUT']);
	}

	if (!$keyRun['success'] || !file_exists($keyOut)) {
		if (file_exists($certOut)) {
			unlink($certOut);
		}
		throw new RuntimeException(describeCertificateExtractionFailure($keyRun));
	}

	// Combine into a single .pem
	file_put_contents($bundleFile, file_get_contents($certOut) . "\n" . file_get_contents($keyOut));

	// Delete temporary files
	unlink($certOut);
	unlink($keyOut);

	return $bundleFile;
}

// Backward compatibility alias
function prepararCertificadoLocal(
	string $fichero_certificado,
	string $ruta_salida,
	string $clave_certificado,
	bool $cifrar_clave = false,
	?string $clave_privada = null,
	string $win_openssl_path = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'
): string {
	return prepareLocalCertificate($fichero_certificado, $ruta_salida, $clave_certificado, $cifrar_clave, $clave_privada, $win_openssl_path);
}

/**
 * Deletes existing PEM files related to a P12/PFX certificate.
 * This ensures that when a new certificate is uploaded, old PEM files are removed
 * and the system will regenerate them from the new certificate.
 *
 * @param string $certificatesDir Directory where certificates are stored
 * @param string $certBaseName Base name of the certificate (without extension)
 * @return int Number of files deleted
 */
function deleteExistingPemFiles(string $certificatesDir, string $certBaseName): int
{
	$deletedCount = 0;

	// Patterns for PEM files that might be generated from a P12/PFX
	$pemPatterns = [
		$certBaseName . '_bundle.pem',
		$certBaseName . '_bundle_protegido.pem',
		$certBaseName . '_cert.pem',
		$certBaseName . '_key.pem',
	];

	foreach ($pemPatterns as $pemFilename) {
		$pemPath = $certificatesDir . DIRECTORY_SEPARATOR . $pemFilename;
		if (file_exists($pemPath)) {
			if (@unlink($pemPath)) {
				dol_syslog("VERIFACTU: Deleted old PEM file: $pemPath", LOG_INFO);
				$deletedCount++;
			} else {
				dol_syslog("VERIFACTU: Failed to delete PEM file: $pemPath", LOG_WARNING);
			}
		}
	}

	return $deletedCount;
}

/**
 * Extracts the private key from a PFX/P12 certificate using external OpenSSL
 * Useful when PHP OpenSSL functions fail due to configuration issues
 *
 * @param string $certificateFile Path to the .pfx or .p12 file
 * @param string $certificatePassword Certificate password
 * @param string $winOpensslPath Path to OpenSSL binary on Windows
 * @return array Result with success, private_key_pem and message
 */
function extractPrivateKeyWithOpenSSL(
	string $certificateFile,
	string $certificatePassword,
	string $winOpensslPath = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'
): array {

	// Try PHP's own OpenSSL bindings first: no external process, so this is the
	// only path available on shared hosting where exec() and proc_open() are
	// both disabled, and it is cheaper than spawning a binary everywhere else.
	$native = extractPkcs12WithPhp($certificateFile, $certificatePassword);
	if ($native['success'] && $native['private_key_pem'] !== '') {
		return [
			'success' => true,
			'private_key_pem' => $native['private_key_pem'],
			'message' => 'Private key extracted successfully using PHP OpenSSL',
		];
	}

	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

	// Verify binary exists
	if ($isWindows && !file_exists($winOpensslPath)) {
		return [
			'success' => false,
			'message' => 'OpenSSL binary not found at: ' . $winOpensslPath
		];
	}

	// Fall back to the external binary, which is the only way to open a legacy
	// container under OpenSSL 3. If it cannot be launched, report the native
	// failure instead: it explains the real reason.
	if (!isPhpFunctionAvailable('proc_open')) {
		return [
			'success' => false,
			'message' => 'proc_open is disabled on this server and PHP could not read the certificate: ' . $native['message'],
			'legacy_cipher' => $native['legacy_cipher'],
			'wrong_password' => $native['wrong_password'],
		];
	}

	// Create temporary file for private key
	$tempKey = tempnam(sys_get_temp_dir(), 'verifactu_key_') . '.pem';

	try {
		// Extract the private key without encryption, retrying through the
		// legacy provider for FNMT containers that OpenSSL 3 refuses by default.
		$run = runOpensslPkcs12(
			array('-nocerts', '-nodes'),
			$certificateFile,
			$tempKey,
			$certificatePassword,
			$winOpensslPath
		);

		if (!$run['success']) {
			if (file_exists($tempKey)) {
				unlink($tempKey);
			}

			return [
				'success' => false,
				'message' => 'OpenSSL command failed: ' . $run['output'],
				'legacy_cipher' => $run['legacy_cipher'],
				'wrong_password' => $run['wrong_password'],
			];
		}

		if ($run['used_legacy'] && function_exists('dol_syslog')) {
			dol_syslog('VERIFACTU: private key extracted using the OpenSSL legacy provider (-legacy)', LOG_INFO);
		}

		if (!file_exists($tempKey)) {
			return [
				'success' => false,
				'message' => 'Temporary key file was not created'
			];
		}

		$privateKeyContent = file_get_contents($tempKey);
		unlink($tempKey);

		if (empty($privateKeyContent)) {
			return [
				'success' => false,
				'message' => 'Empty private key content'
			];
		}

		if (strpos($privateKeyContent, '-----BEGIN PRIVATE KEY-----') === false &&
			strpos($privateKeyContent, '-----BEGIN RSA PRIVATE KEY-----') === false) {
			return [
				'success' => false,
				'message' => 'Invalid PEM format in extracted private key'
			];
		}

		return [
			'success' => true,
			'private_key_pem' => $privateKeyContent,
			'message' => 'Private key extracted successfully using external OpenSSL'
		];

	} catch (Exception $e) {
		if (file_exists($tempKey)) {
			unlink($tempKey);
		}

		return [
			'success' => false,
			'message' => 'Exception: ' . $e->getMessage()
		];
	}
}

// Backward compatibility alias
function extraerClavePrivadaConOpenSSL(
	string $fichero_certificado,
	string $clave_certificado,
	string $win_openssl_path = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'
): array {
	return extractPrivateKeyWithOpenSSL($fichero_certificado, $clave_certificado, $win_openssl_path);
}

/**
 * Extracts the public certificate from a PFX/P12 file using external OpenSSL
 * Useful when PHP OpenSSL functions fail due to configuration issues
 *
 * @param string $certificateFile Path to the .pfx or .p12 file
 * @param string $certificatePassword Certificate password
 * @param string $winOpensslPath Path to OpenSSL binary on Windows
 * @return array Result with success, public_cert_pem and message
 */
function extractPublicCertificateWithOpenSSL(
	string $certificateFile,
	string $certificatePassword,
	string $winOpensslPath = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'
): array {

	// Try PHP's own OpenSSL bindings first (see extractPrivateKeyWithOpenSSL).
	$native = extractPkcs12WithPhp($certificateFile, $certificatePassword);
	if ($native['success']) {
		return [
			'success' => true,
			'public_cert_pem' => $native['public_cert_pem'],
			'message' => 'Public certificate extracted successfully using PHP OpenSSL',
		];
	}

	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

	if ($isWindows && !file_exists($winOpensslPath)) {
		return [
			'success' => false,
			'message' => 'OpenSSL binary not found at: ' . $winOpensslPath
		];
	}

	// Fall back to the external binary for legacy containers; when it cannot be
	// launched, the native failure is the informative one.
	if (!isPhpFunctionAvailable('proc_open')) {
		return [
			'success' => false,
			'message' => 'proc_open is disabled on this server and PHP could not read the certificate: ' . $native['message'],
			'legacy_cipher' => $native['legacy_cipher'],
			'wrong_password' => $native['wrong_password'],
		];
	}

	$tempCert = tempnam(sys_get_temp_dir(), 'verifactu_cert_') . '.pem';

	try {
		// Extract the public certificate, retrying through the legacy provider
		// for FNMT containers that OpenSSL 3 refuses by default.
		$run = runOpensslPkcs12(
			array('-clcerts', '-nokeys'),
			$certificateFile,
			$tempCert,
			$certificatePassword,
			$winOpensslPath
		);

		if (!$run['success']) {
			if (file_exists($tempCert)) {
				unlink($tempCert);
			}

			return [
				'success' => false,
				'message' => 'OpenSSL command failed: ' . $run['output'],
				'legacy_cipher' => $run['legacy_cipher'],
				'wrong_password' => $run['wrong_password'],
			];
		}

		if ($run['used_legacy'] && function_exists('dol_syslog')) {
			dol_syslog('VERIFACTU: public certificate extracted using the OpenSSL legacy provider (-legacy)', LOG_INFO);
		}

		if (!file_exists($tempCert)) {
			return [
				'success' => false,
				'message' => 'Temporary certificate file was not created'
			];
		}

		$publicCertContent = file_get_contents($tempCert);
		unlink($tempCert);

		if (empty($publicCertContent)) {
			return [
				'success' => false,
				'message' => 'Empty certificate content'
			];
		}

		if (strpos($publicCertContent, '-----BEGIN CERTIFICATE-----') === false) {
			return [
				'success' => false,
				'message' => 'Invalid PEM format in extracted certificate'
			];
		}

		return [
			'success' => true,
			'public_cert_pem' => $publicCertContent,
			'message' => 'Public certificate extracted successfully using external OpenSSL'
		];

	} catch (Exception $e) {
		if (file_exists($tempCert)) {
			unlink($tempCert);
		}

		return [
			'success' => false,
			'message' => 'Exception: ' . $e->getMessage()
		];
	}
}

// Backward compatibility alias
function extraerCertificadoPublicoConOpenSSL(
	string $fichero_certificado,
	string $clave_certificado,
	string $win_openssl_path = 'C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe'
): array {
	return extractPublicCertificateWithOpenSSL($fichero_certificado, $clave_certificado, $win_openssl_path);
}

/**
 * Generates detailed certificate information for display to the user
 * Similar to information seen in the Windows certificate viewer
 *
 * @param array $certInfo Certificate information from openssl_x509_parse()
 * @param string $publicPem Public certificate in PEM format
 * @return string HTML with detailed certificate information
 */
function generateCertificateDisplayInfo(array $certInfo, string $publicPem): string {

	// Get basic information
	$subject = $certInfo['subject'] ?? [];
	$issuer = $certInfo['issuer'] ?? [];

	// Holder data
	$name = $subject['CN'] ?? 'Not available';
	$organization = $subject['O'] ?? 'Not available';
	$document = $subject['serialNumber'] ?? 'Not available';

	// Issuer data
	$issuerName = $issuer['CN'] ?? 'Not available';
	$issuerOrg = $issuer['O'] ?? 'Not available';
	$country = $issuer['C'] ?? 'ES';

	// Validity dates
	$validFrom = isset($certInfo['validFrom_time_t']) ? date('d/m/Y H:i:s', $certInfo['validFrom_time_t']) : 'Not available';
	$validTo = isset($certInfo['validTo_time_t']) ? date('d/m/Y H:i:s', $certInfo['validTo_time_t']) : 'Not available';

	// Check if valid
	$now = time();
	$isValid = ($certInfo['validFrom_time_t'] <= $now && $certInfo['validTo_time_t'] >= $now);
	$validityStatus = $isValid ? 'Valid' : 'Invalid';
	$validityColor = $isValid ? '#28a745' : '#dc3545';

	// Technical details
	$serialNumber = isset($certInfo['serialNumberHex']) ? strtoupper($certInfo['serialNumberHex']) :
					(isset($certInfo['serialNumber']) ? $certInfo['serialNumber'] : 'Not available');

	// Signature algorithm
	$signatureAlgo = $certInfo['signatureTypeLN'] ?? $certInfo['signatureTypeSN'] ?? 'Not available';

	// Get public key information
	$publicKeyDetails = openssl_pkey_get_details(openssl_pkey_get_public($publicPem));
	$keyType = 'Not available';
	$keyBits = 'Not available';

	if ($publicKeyDetails) {
		$keyBits = $publicKeyDetails['bits'] ?? 'Not available';
		if (isset($publicKeyDetails['type'])) {
			switch ($publicKeyDetails['type']) {
				case OPENSSL_KEYTYPE_RSA:
					$keyType = 'RSA (' . $keyBits . ' bits)';
					break;
				case OPENSSL_KEYTYPE_DSA:
					$keyType = 'DSA';
					break;
				case OPENSSL_KEYTYPE_EC:
					$keyType = 'EC';
					break;
				default:
					$keyType = 'Unknown';
			}
		}
	}

	// Calculate fingerprints
	$certResource = openssl_x509_read($publicPem);
	$sha1Fingerprint = 'Not available';
	$sha256Fingerprint = 'Not available';

	if ($certResource) {
		openssl_x509_export($certResource, $certDer, false);
		$sha1Fingerprint = strtoupper(implode(':', str_split(sha1($certDer), 2)));
		$sha256Fingerprint = strtoupper(implode(':', str_split(hash('sha256', $certDer), 2)));
	}

	// Allowed uses (simplified)
	$keyUsage = [];
	if (isset($certInfo['extensions']['keyUsage'])) {
		$usage = $certInfo['extensions']['keyUsage'];
		if (strpos($usage, 'Digital Signature') !== false) $keyUsage[] = 'Digital Signature';
		if (strpos($usage, 'Non Repudiation') !== false) $keyUsage[] = 'Non Repudiation';
		if (strpos($usage, 'Key Encipherment') !== false) $keyUsage[] = 'Key Encipherment';
	}

	// Generate HTML using standard Dolibarr style
	$validityBadge = $isValid ? '<span class="badge badge-status4">'.$validityStatus.'</span>' : '<span class="badge badge-status8">'.$validityStatus.'</span>';

	$html = '<div class="div-table-responsive-no-min">';
	$html .= '<table class="noborder centpercent">';

	// Header with status
	$html .= '<tr class="liste_titre">';
	$html .= '<td colspan="2">'.$GLOBALS['langs']->trans("CertificateInfo").' '.$validityBadge.'</td>';
	$html .= '</tr>';

	// Certificate holder
	$html .= '<tr class="oddeven"><td class="titlefield">'.$GLOBALS['langs']->trans("Name").'</td><td>'.htmlspecialchars($name).'</td></tr>';
	$html .= '<tr class="oddeven"><td>'.$GLOBALS['langs']->trans("Document").'</td><td>'.htmlspecialchars($document).'</td></tr>';
	$html .= '<tr class="oddeven"><td>'.$GLOBALS['langs']->trans("Organization").'</td><td>'.htmlspecialchars($organization).'</td></tr>';

	// Issuer
	$html .= '<tr class="liste_titre"><td colspan="2">'.$GLOBALS['langs']->trans("CertificateAuthority").'</td></tr>';
	$html .= '<tr class="oddeven"><td class="titlefield">'.$GLOBALS['langs']->trans("Issuer").'</td><td>'.htmlspecialchars($issuerName).'</td></tr>';
	$html .= '<tr class="oddeven"><td>'.$GLOBALS['langs']->trans("Organization").'</td><td>'.htmlspecialchars($issuerOrg).'</td></tr>';
	$html .= '<tr class="oddeven"><td>'.$GLOBALS['langs']->trans("Country").'</td><td>'.htmlspecialchars($country).'</td></tr>';

	// Validity
	$html .= '<tr class="liste_titre"><td colspan="2">'.$GLOBALS['langs']->trans("ValidityPeriod").'</td></tr>';
	$html .= '<tr class="oddeven"><td class="titlefield">'.$GLOBALS['langs']->trans("ValidFrom").'</td><td>'.$validFrom.'</td></tr>';
	$html .= '<tr class="oddeven"><td>'.$GLOBALS['langs']->trans("ValidTo").'</td><td>'.$validTo.'</td></tr>';

	// Technical details
	$html .= '<tr class="liste_titre"><td colspan="2">'.$GLOBALS['langs']->trans("TechnicalDetails").'</td></tr>';
	$html .= '<tr class="oddeven"><td class="titlefield">'.$GLOBALS['langs']->trans("SerialNumber").'</td><td><span class="opacitymedium" style="font-family: monospace;">'.htmlspecialchars($serialNumber).'</span></td></tr>';
	$html .= '<tr class="oddeven"><td>'.$GLOBALS['langs']->trans("SignatureAlgorithm").'</td><td>'.htmlspecialchars($signatureAlgo).'</td></tr>';
	$html .= '<tr class="oddeven"><td>'.$GLOBALS['langs']->trans("PublicKey").'</td><td>'.htmlspecialchars($keyType).'</td></tr>';

	// Fingerprints
	$html .= '<tr class="liste_titre"><td colspan="2">'.$GLOBALS['langs']->trans("Fingerprints").'</td></tr>';
	$html .= '<tr class="oddeven"><td class="titlefield">SHA1</td><td><span class="opacitymedium" style="font-family: monospace; font-size: 11px;">'.$sha1Fingerprint.'</span></td></tr>';
	$html .= '<tr class="oddeven"><td>SHA256</td><td><span class="opacitymedium" style="font-family: monospace; font-size: 11px; word-break: break-all;">'.$sha256Fingerprint.'</span></td></tr>';

	// Allowed uses
	if (!empty($keyUsage)) {
		$html .= '<tr class="liste_titre"><td colspan="2">'.$GLOBALS['langs']->trans("AllowedUsages").'</td></tr>';
		$html .= '<tr class="oddeven"><td colspan="2">'.implode(', ', array_map('htmlspecialchars', $keyUsage)).'</td></tr>';
	}

	$html .= '</table>';
	$html .= '</div>';

	return $html;
}

/**
 * Gets the certificate options configured for VeriFactu
 *
 * @return array|false Certificate options or false if not configured
 */
function getCertificateOptions()
{
	global $conf, $db;

	// Prepare certificate (convert to PEM if necessary)
	$certInfo = prepareCertificateForVerifactu();

	if (!$certInfo) {
		// Error is already stored in $GLOBALS['verifactu_cert_error']
		return false;
	}

	// Use prepared certificate information
	$certPath = $certInfo['path'];
	$certContent = isset($certInfo['content']) ? $certInfo['content'] : null;
	$inMemoryOnly = isset($certInfo['in_memory_only']) && $certInfo['in_memory_only'];

	// Use passphrase returned by prepareCertificate (may have changed during PFX->PEM conversion)
	// If not in certInfo, use the one from configuration as fallback
	$certPassphrase = isset($certInfo['passphrase']) ? $certInfo['passphrase'] : ($conf->global->VERIFACTU_CERTIFICATE_KEY ?? '');

	// If certificate is memory-only (local connector), create temporary file for this request
	if ($inMemoryOnly && !empty($certContent)) {
		$tempFile = tmpfile();
		if ($tempFile === false) {
			$GLOBALS['verifactu_cert_error'] = 'Error creating temporary file for in-memory certificate';
			return false;
		}

		fwrite($tempFile, $certContent);
		$tempFilePath = stream_get_meta_data($tempFile)['uri'];
		$certPath = $tempFilePath;

		// Store tmpfile handle reference so it doesn't close prematurely
		$GLOBALS['verifactu_tmpfile_handle'] = $tempFile;

		dol_syslog("VERIFACTU: Using system temporary file for connector certificate (will auto-delete)", LOG_DEBUG);
	}

	// Certificate configuration
	$certOptions = [
		'local_cert' => $certPath,
		'trace' => true,
		'exceptions' => true,
		'cache_wsdl' => WSDL_CACHE_NONE,
		'stream_context' => stream_context_create([
			'ssl' => [
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true
			]
		])
	];

	if (!empty($certPassphrase)) {
		$certOptions['passphrase'] = $certPassphrase;
		dol_syslog("VERIFACTU: Certificate with passphrase configured", LOG_DEBUG);
	}

	// Verify that private key can open the certificate
	if (!validateCertificateAndKey($certPath, $certPassphrase)) {
		dol_syslog("VERIFACTU: Error in certificate and private key validation", LOG_ERR);
		$GLOBALS['verifactu_cert_error'] = 'Private key does not match certificate or passphrase is incorrect';
		return false;
	}

	dol_syslog("VERIFACTU: Certificate options prepared successfully", LOG_DEBUG);
	return $certOptions;
}

/**
 * Prepares and converts a certificate to PEM format if necessary
 *
 * @return array|false Array with certificate information or false on error
 */
function prepareCertificateForVerifactu()
{
	return prepareCertificateLocalForVerifactu();
}

/**
 * Prepares local certificate for VeriFactu (original behavior)
 *
 * @return array|false Array with certificate information or false on error
 */
function prepareCertificateLocalForVerifactu()
{
	global $conf, $db, $dolibarr_main_instance_unique_id;

	$certificateFile = $conf->global->VERIFACTU_CERTIFICATE;
	$certificatesDir = $conf->verifactu->multidir_output[$conf->entity] . "/certificates";
	$certPath = $certificatesDir . '/' . $certificateFile;
	$certPassphrase = $conf->global->VERIFACTU_CERTIFICATE_KEY;

	dol_syslog("VERIFACTU: Preparing local certificate for use", LOG_DEBUG);

	if (empty($certificateFile)) {
		dol_syslog("VERIFACTU: No certificate configured", LOG_ERR);
		$GLOBALS['verifactu_cert_error'] = 'No certificate configured in module settings';
		return false;
	}

	// Verify file exists
	if (!dol_is_file($certPath)) {
		$GLOBALS['verifactu_cert_error'] = "Certificate file not found: $certPath";
		return false;
	}

	// No hard requirement on proc_open any more: the conversion falls back to
	// PHP's own OpenSSL bindings, which is what makes the module usable on shared
	// hosting where exec() and proc_open() are both in disable_functions.
	if (!function_exists('openssl_pkcs12_read') && !isPhpFunctionAvailable('proc_open')) {
		$GLOBALS['verifactu_cert_error'] = "Neither the PHP OpenSSL extension nor proc_open is available on your server. Please contact your administrator.";
		return false;
	}

	$certificateFile = basename($certPath);

	// Check if certificate extension is .pem
	if (substr($certificateFile, -4) != '.pem' && $certPassphrase != '') {

		// If not .pem, convert to .pem
		$privateKey = ($dolibarr_main_instance_unique_id ? $dolibarr_main_instance_unique_id : $certPassphrase);
		$outputPath = $conf->verifactu->multidir_output[$conf->entity] . "/certificates/";
		try {
			$localCert = prepareLocalCertificate(
				$certPath,
				$outputPath,
				$certPassphrase,
				true,               // Encrypt private key
				$privateKey
			);
		} catch (Exception $e) {
			// prepareLocalCertificate() used to die() here, taking the whole
			// request down. Report the reason instead so the caller can show it.
			dol_syslog("VERIFACTU: Certificate conversion failed: " . $e->getMessage(), LOG_ERR);
			$GLOBALS['verifactu_cert_error'] = $e->getMessage();
			return false;
		}

		// Get only the .pem filename
		$basename = basename($localCert);

		// Update configuration
		dolibarr_set_const($db, 'VERIFACTU_CERTIFICATE', $basename, 'chaine', 0, '', $conf->entity);
		dolibarr_set_const($db, 'VERIFACTU_CERTIFICATE_KEY', $privateKey, 'chaine', 0, '', $conf->entity);

		// Build new certificate path
		$newCertPath = $conf->verifactu->multidir_output[$conf->entity] . "/certificates/" . $basename;

		dol_syslog("VERIFACTU: Certificate converted to PEM: $newCertPath", LOG_DEBUG);

		return [
			'path' => $newCertPath,
			'passphrase' => $privateKey,
			'converted' => true,
			'type' => 'local'
		];
	} else {
		// Certificate is already in PEM format or doesn't require conversion
		return [
			'path' => $certPath,
			'passphrase' => $certPassphrase,
			'converted' => false,
			'type' => 'local'
		];
	}
}

/**
 * Validates that the private key can open the certificate
 *
 * @param string $certPath Path to certificate
 * @param string $certPassphrase Certificate passphrase (optional)
 * @return bool True if validation successful, false otherwise
 */
function validateCertificateAndKey($certPath, $certPassphrase = '')
{
	global $langs, $conf;

	dol_syslog("VERIFACTU: Validating certificate and private key", LOG_DEBUG);

	// Verify file exists
	if (!file_exists($certPath)) {
		dol_syslog("VERIFACTU: Certificate file not found: $certPath", LOG_ERR);
		$GLOBALS['verifactu_cert_error'] = "Certificate file not found: $certPath";
		return false;
	}

	// Read certificate content
	$certContent = file_get_contents($certPath);
	if ($certContent === false) {
		dol_syslog("VERIFACTU: Error reading certificate file", LOG_ERR);
		$GLOBALS['verifactu_cert_error'] = "Error reading certificate file";
		return false;
	}

	// Extract X.509 certificate
	$cert = openssl_x509_read($certContent);
	if ($cert === false) {
		dol_syslog("VERIFACTU: Error reading X.509 certificate", LOG_ERR);
		$GLOBALS['verifactu_cert_error'] = "Error reading X.509 certificate - invalid format";
		return false;
	}

	// Extract private key
	$privateKey = openssl_pkey_get_private($certContent, $certPassphrase);
	if ($privateKey === false) {
		dol_syslog("VERIFACTU: Error reading private key. Incorrect passphrase or key not found", LOG_ERR);
		$GLOBALS['verifactu_cert_error'] = "Error reading private key. Incorrect passphrase or key not found";
		return false;
	}

	// Verify private key matches certificate
	$isValid = openssl_x509_check_private_key($cert, $privateKey);

	// Free resources
	openssl_x509_free($cert);
	openssl_pkey_free($privateKey);

	if (!$isValid) {
		dol_syslog("VERIFACTU: Private key does not match certificate", LOG_ERR);
		$GLOBALS['verifactu_cert_error'] = "Private key does not match certificate";
		return false;
	}

	dol_syslog("VERIFACTU: Certificate and private key validated successfully", LOG_DEBUG);
	return true;
}

/**
 * Processes private key format to ensure it has correct tags
 *
 * @param string $privateKeyContent Private key content (with or without tags)
 * @return string Private key with correct format
 */
function processPrivateKeyFormat($privateKeyContent)
{
	// Clean whitespace at start and end
	$privateKeyContent = trim($privateKeyContent);

	// If empty, return as-is
	if (empty($privateKeyContent)) {
		return $privateKeyContent;
	}

	// Detect if already has private key tags
	$hasBeginTag = strpos($privateKeyContent, '-----BEGIN') !== false;
	$hasEndTag = strpos($privateKeyContent, '-----END') !== false;

	// If already has correct tags, return as-is
	if ($hasBeginTag && $hasEndTag) {
		dol_syslog("VERIFACTU: Private key already has correct format with tags", LOG_DEBUG);
		return $privateKeyContent;
	}

	// If no tags, add standard encrypted private key tags
	if (!$hasBeginTag && !$hasEndTag) {
		dol_syslog("VERIFACTU: Adding encrypted private key tags to content", LOG_INFO);

		$formattedKey = "-----BEGIN ENCRYPTED PRIVATE KEY-----\n";
		$formattedKey .= $privateKeyContent . "\n";
		$formattedKey .= "-----END ENCRYPTED PRIVATE KEY-----";

		return $formattedKey;
	}

	// If partial or incorrect tags, try to correct
	if ($hasBeginTag && !$hasEndTag) {
		dol_syslog("VERIFACTU: Private key has BEGIN tag but not END - adding END", LOG_INFO);
		$privateKeyContent .= "\n-----END ENCRYPTED PRIVATE KEY-----";
	} elseif (!$hasBeginTag && $hasEndTag) {
		dol_syslog("VERIFACTU: Private key has END tag but not BEGIN - adding BEGIN", LOG_INFO);
		$privateKeyContent = "-----BEGIN ENCRYPTED PRIVATE KEY-----\n" . $privateKeyContent;
	}

	return $privateKeyContent;
}
