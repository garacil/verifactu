<?php
/* Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
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

	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	$opensslBin = $isWindows ? "\"$winOpensslPath\"" : 'openssl';

	$certOut = $outputPath . '_cert.pem';
	$keyOut = $outputPath . '_key.pem';

	// Extract certificate without key
	$cmdCert = "$opensslBin pkcs12 -in \"$certificateFile\" -clcerts -nokeys -out \"$certOut\" -password pass:$certificatePassword";
	exec($cmdCert, $outputCert, $codeCert);

	if ($codeCert !== 0 || !file_exists($certOut)) {
		die("Error extracting certificate.");
	}

	// Extract private key (with or without passphrase)
	$passOut = $privateKeyPassword ?? $certificatePassword;
	$cmdKey = "$opensslBin pkcs12 -in \"$certificateFile\" -nocerts " .
		($encryptKey ? '' : '-nodes') .
		" -out \"$keyOut\" -password pass:$certificatePassword" .
		($encryptKey ? " -passout pass:$passOut" : '');
	exec($cmdKey, $outputKey, $codeKey);

	if ($codeKey !== 0 || !file_exists($keyOut)) {
		die("Error extracting private key.");
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

	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	$opensslBin = $isWindows ? "\"$winOpensslPath\"" : 'openssl';

	// Verify binary exists
	if ($isWindows && !file_exists($winOpensslPath)) {
		return [
			'success' => false,
			'message' => 'OpenSSL binary not found at: ' . $winOpensslPath
		];
	}

	// Create temporary file for private key
	$tempKey = tempnam(sys_get_temp_dir(), 'verifactu_key_') . '.pem';

	try {
		// Command to extract private key without encryption
		$cmd = "$opensslBin pkcs12 -in \"$certificateFile\" -nocerts -nodes -out \"$tempKey\" -password pass:$certificatePassword 2>&1";

		// Execute command
		exec($cmd, $output, $returnCode);

		if ($returnCode !== 0) {
			if (file_exists($tempKey)) {
				unlink($tempKey);
			}

			return [
				'success' => false,
				'message' => 'OpenSSL command failed: ' . implode("\n", $output)
			];
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

	$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	$opensslBin = $isWindows ? "\"$winOpensslPath\"" : 'openssl';

	if ($isWindows && !file_exists($winOpensslPath)) {
		return [
			'success' => false,
			'message' => 'OpenSSL binary not found at: ' . $winOpensslPath
		];
	}

	$tempCert = tempnam(sys_get_temp_dir(), 'verifactu_cert_') . '.pem';

	try {
		$cmd = "$opensslBin pkcs12 -in \"$certificateFile\" -clcerts -nokeys -out \"$tempCert\" -password pass:$certificatePassword 2>&1";

		exec($cmd, $output, $returnCode);

		if ($returnCode !== 0) {
			if (file_exists($tempCert)) {
				unlink($tempCert);
			}

			return [
				'success' => false,
				'message' => 'OpenSSL command failed: ' . implode("\n", $output)
			];
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

	// Verify exec function is enabled
	if (!function_exists('exec')) {
		$GLOBALS['verifactu_cert_error'] = "The exec function is not enabled on your server. Please contact your administrator.";
		return false;
	}

	$certificateFile = basename($certPath);

	// Check if certificate extension is .pem
	if (substr($certificateFile, -4) != '.pem' && $certPassphrase != '') {

		// If not .pem, convert to .pem
		$privateKey = ($dolibarr_main_instance_unique_id ? $dolibarr_main_instance_unique_id : $certPassphrase);
		$outputPath = $conf->verifactu->multidir_output[$conf->entity] . "/certificates/";
		$localCert = prepareLocalCertificate(
			$certPath,
			$outputPath,
			$certPassphrase,
			true,               // Encrypt private key
			$privateKey
		);

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
