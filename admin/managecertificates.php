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
 * \file    verifactu/admin/managecertificates.php
 * \ingroup verifactu
 * \brief   Certificate management - Public key extraction and server submission
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user, $conf;

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT . "/core/lib/files.lib.php";
dol_include_once('/core/lib/geturl.lib.php');
dol_include_once('/verifactu/lib/verifactu.lib.php');
dol_include_once('/verifactu/lib/functions/funciones.certificados.php');

// Translations
$langs->loadLangs(array("admin", "verifactu@verifactu"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$error = 0;
$errorMsg = '';
$successMsg = '';

/*
 * Actions
 */

if ($action == 'upload_certificate') {
	$cert_password = GETPOST('cert_password', 'alphanohtml');

	if (isset($_FILES['certificate_file'])) {
		$uploadedFile = $_FILES['certificate_file'];

		if ($uploadedFile['error'] == UPLOAD_ERR_OK) {
			$tmpFile = $uploadedFile['tmp_name'];
			$originalName = $uploadedFile['name'];

			// Check extension
			$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
			if (!in_array($ext, ['pfx', 'p12'])) {
				$errorMsg = 'Error: Solo se permiten archivos .pfx o .p12';
			} else {
				// Create certificates directory if it doesn't exist
				$certDir = $conf->verifactu->multidir_output[$conf->entity] . "/certificates";
				if (!dol_is_dir($certDir)) {
					dol_mkdir($certDir);
				}

				// Save PFX file to certificates directory
				$destPath = $certDir . '/' . $originalName;
				if (move_uploaded_file($tmpFile, $destPath)) {
					// Process certificate to validate it is correct
					$result = extractAndSendPublicKey($destPath, $cert_password);
					if ($result['success']) {
						// Automatically configure certificate and password
						dolibarr_set_const($db, 'VERIFACTU_CERTIFICATE', $originalName, 'chaine', 0, '', $conf->entity);
						dolibarr_set_const($db, 'VERIFACTU_CERTIFICATE_KEY', $cert_password, 'chaine', 0, '', $conf->entity);
						$successMsg = $result['message'] . '<br><br>✅ <strong>Certificado configurado automáticamente:</strong> ' . htmlspecialchars($originalName);
					} else {
						// If validation fails, delete the file
						@unlink($destPath);
						$errorMsg = $result['message'];
					}
				} else {
					$errorMsg = 'Error: No se pudo guardar el archivo del certificado';
				}
			}
		} else {
			$errorMsg = 'Error al subir el archivo: ' . $uploadedFile['error'];
		}
	} else {
		$errorMsg = 'No se ha seleccionado ningún archivo';
	}
}

/**
 * Extracts the public key from a certificate and sends it to the server
 * Also automatically extracts and saves the private key in module configuration
 */
function extractAndSendPublicKey($certPath, $password)
{
	global $conf, $db;

	try {
		// Read certificate content
		$certContent = file_get_contents($certPath);
		if ($certContent === false) {
			return ['success' => false, 'message' => 'Error: No se pudo leer el archivo del certificado'];
		}

		// Variables for public certificate and private key
		$publicCert = '';
		$privateKey = null;
		$extractionMethod = '';

		// METHOD 1: Try PHP native functions first
		$certs = [];
		dol_syslog("VERIFACTU DEBUG: Iniciando extracción PHP nativa - openssl_pkcs12_read...", LOG_DEBUG);
		dol_syslog("VERIFACTU DEBUG: Tamaño archivo certificado: " . strlen($certContent) . " bytes", LOG_DEBUG);

		$pkcs12_result = openssl_pkcs12_read($certContent, $certs, $password);
		dol_syslog("VERIFACTU DEBUG: Resultado openssl_pkcs12_read: " . ($pkcs12_result ? 'ÉXITO' : 'FALLO'), LOG_DEBUG);

		if ($pkcs12_result) {
			dol_syslog("VERIFACTU DEBUG: Claves encontradas en PKCS12: " . implode(', ', array_keys($certs)), LOG_DEBUG);

			// Verify we have public certificate
			if (isset($certs['cert'])) {
				$publicCert = $certs['cert'];
				$extractionMethod .= 'Certificado público: PHP nativo; ';
				dol_syslog("VERIFACTU DEBUG: ✅ Certificado público extraído con PHP nativo", LOG_DEBUG);
			} else {
				dol_syslog("VERIFACTU DEBUG: ⚠️ No se encontró 'cert' en PKCS12", LOG_WARNING);
			}

			// Verify we have private key
			if (isset($certs['pkey'])) {
				$privateKey = $certs['pkey'];
				$extractionMethod .= 'Clave privada: PHP nativo';
				dol_syslog("VERIFACTU DEBUG: ✅ Clave privada extraída con PHP nativo - Tipo: " . gettype($privateKey), LOG_DEBUG);
			} else {
				dol_syslog("VERIFACTU DEBUG: ⚠️ No se encontró 'pkey' en PKCS12", LOG_WARNING);
			}
		} else {
			// Capture specific OpenSSL errors
			$openssl_errors = [];
			while ($error = openssl_error_string()) {
				$openssl_errors[] = $error;
			}
			dol_syslog("VERIFACTU DEBUG: ❌ openssl_pkcs12_read falló - Errores OpenSSL: " . implode('; ', $openssl_errors), LOG_ERR);
		}

		// METHOD 2: If PHP native extraction of public certificate failed, use external OpenSSL
		if (empty($publicCert)) {
			dol_syslog("VERIFACTU DEBUG: Extracción PHP nativa de certificado público falló, intentando con OpenSSL externo...", LOG_DEBUG);

			$certResult = extraerCertificadoPublicoConOpenSSL($certPath, $password);
			if ($certResult['success']) {
				$publicCert = $certResult['public_cert_pem'];
				$extractionMethod = str_replace('Certificado público: PHP nativo; ', '', $extractionMethod);
				$extractionMethod = 'Certificado público: OpenSSL externo; ' . $extractionMethod;
				dol_syslog("VERIFACTU DEBUG: ✅ Certificado público extraído con OpenSSL externo", LOG_DEBUG);
			} else {
				dol_syslog("VERIFACTU DEBUG: ❌ También falló OpenSSL externo para certificado público: " . $certResult['message'], LOG_ERR);
			}
		}

		// Verify we have at least the public certificate
		if (empty($publicCert)) {
			return ['success' => false, 'message' => 'Error: No se pudo extraer el certificado público del archivo. Verifique la contraseña.'];
		}

		// Verify it is a valid certificate
		$certResource = openssl_x509_read($publicCert);
		if ($certResource === false) {
			return ['success' => false, 'message' => 'Error: El certificado extraído no es válido'];
		}

		// Get certificate information
		$certInfo = openssl_x509_parse($certResource);
		$certSubject = $certInfo['subject']['CN'] ?? 'Desconocido';
		$certIssuer = $certInfo['issuer']['CN'] ?? 'Desconocido';
		$certValidFrom = date('Y-m-d H:i:s', $certInfo['validFrom_time_t']);
		$certValidTo = date('Y-m-d H:i:s', $certInfo['validTo_time_t']);

		// Clean the PEM certificate (public certificate only)
		$publicPem = trim($publicCert);

		// 🏆 DETAILED CERTIFICATE INFORMATION
		$certDisplayInfo = generateCertificateDisplayInfo($certInfo, $publicPem);

		// 🔧 Extract private key in PEM format for auto-configuration
		$privateKeyPem = '';
		$privateKeyExported = false;
		$privateKeyMethod = '';

		// Debug: Check initial OpenSSL state
		dol_syslog("VERIFACTU DEBUG: Iniciando extracción de clave privada...", LOG_DEBUG);

		// Clear any previous OpenSSL errors
		while (openssl_error_string()) {
			// Clear previous errors
		}

		// METHOD 1: Try PHP native functions first
		// 🔧 Configure OpenSSL for Windows (avoid config file errors)
		$opensslConfig = [];
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			// Basic configuration for Windows
			$opensslConfig = [
				"config" => null, // Don't use config file
				"private_key_bits" => 2048,
				"private_key_type" => OPENSSL_KEYTYPE_RSA,
			];
			dol_syslog("VERIFACTU DEBUG: Configuración OpenSSL para Windows aplicada", LOG_DEBUG);
		}

		// Debug: Verify that we have a valid key resource
		$keyResource = null;
		dol_syslog("VERIFACTU DEBUG: Analizando tipo de privateKey: " . gettype($privateKey), LOG_DEBUG);
		dol_syslog("VERIFACTU DEBUG: 📊 Valor de privateKey: " . var_export($privateKey, true), LOG_DEBUG);
		dol_syslog("VERIFACTU DEBUG: 📏 Longitud/tamaño de privateKey: " . (is_string($privateKey) ? strlen($privateKey) : 'N/A'), LOG_DEBUG);

		if (is_resource($privateKey)) {
			$keyResource = $privateKey;
			dol_syslog("VERIFACTU DEBUG: ✅ Recurso de clave privada es válido (resource)", LOG_DEBUG);
		} elseif (is_object($privateKey)) {
			$className = get_class($privateKey);
			dol_syslog("VERIFACTU DEBUG: Objeto detectado - Clase: " . $className, LOG_DEBUG);
			if ($className === 'OpenSSLAsymmetricKey') {
				$keyResource = $privateKey;
				dol_syslog("VERIFACTU DEBUG: ✅ Recurso de clave privada es válido (OpenSSLAsymmetricKey)", LOG_DEBUG);
			} else {
				dol_syslog("VERIFACTU DEBUG: ⚠️ Objeto de clase desconocida: " . $className, LOG_WARNING);
			}
		} elseif (is_string($privateKey)) {
			dol_syslog("VERIFACTU DEBUG: ⚠️ privateKey es string con longitud: " . strlen($privateKey), LOG_WARNING);
			dol_syslog("VERIFACTU DEBUG: 🔍 Vista previa string (primeros 100 chars): " . substr($privateKey, 0, 100) . "...", LOG_DEBUG);

			// If string, try to convert to resource
			if (!empty($privateKey)) {
				// Intento 1: Usar directamente el string como recurso
				dol_syslog("VERIFACTU DEBUG: 🔄 Intento 1 - Convertir string a recurso sin contraseña...", LOG_DEBUG);
				$tempResource = openssl_pkey_get_private($privateKey);
				if ($tempResource !== false) {
					$keyResource = $tempResource;
					dol_syslog("VERIFACTU DEBUG: ✅ String convertido a recurso exitosamente", LOG_DEBUG);
				} else {
					// Capturar errores OpenSSL del intento 1
					$errors1 = [];
					while ($error = openssl_error_string()) {
						$errors1[] = $error;
					}
					dol_syslog("VERIFACTU DEBUG: ❌ Intento 1 falló - Errores: " . implode('; ', $errors1), LOG_DEBUG);

					// Intento 2: Usar el string con la contraseña
					dol_syslog("VERIFACTU DEBUG: 🔄 Intento 2 - Convertir string a recurso CON contraseña...", LOG_DEBUG);
					$tempResource = openssl_pkey_get_private($privateKey, $password);
					if ($tempResource !== false) {
						$keyResource = $tempResource;
						dol_syslog("VERIFACTU DEBUG: ✅ String convertido a recurso con contraseña exitosamente", LOG_DEBUG);
					} else {
						// Capturar errores OpenSSL del intento 2
						$errors2 = [];
						while ($error = openssl_error_string()) {
							$errors2[] = $error;
						}
						dol_syslog("VERIFACTU DEBUG: ❌ Intento 2 falló - Errores: " . implode('; ', $errors2), LOG_DEBUG);

						dol_syslog("VERIFACTU DEBUG: ❌ No se pudo convertir string a recurso", LOG_WARNING);
						// If valid PEM string, try using it directly for export
						if (strpos($privateKey, '-----BEGIN') !== false) {
							dol_syslog("VERIFACTU DEBUG: 🔄 String parece ser PEM válido, intentando uso directo", LOG_DEBUG);
							$privateKeyPem = $privateKey;
							$privateKeyExported = true;
							$privateKeyMethod = 'PHP nativo (string PEM directo)';
						} else {
							dol_syslog("VERIFACTU DEBUG: ❌ String no parece ser PEM válido", LOG_WARNING);
						}
					}
				}
			} else {
				dol_syslog("VERIFACTU DEBUG: ❌ String de clave privada está vacío", LOG_ERR);
			}
		} else {
			dol_syslog("VERIFACTU DEBUG: ⚠️ El recurso de clave privada NO es válido - Tipo: " . gettype($privateKey) . " - Valor: " . var_export($privateKey, true), LOG_WARNING);
		}

		// Debug: Verify private key type
		$keyDetails = null;
		if ($keyResource) {
			$keyDetails = openssl_pkey_get_details($keyResource);
			if ($keyDetails !== false) {
				$keyInfo = "Tipo: " . ($keyDetails['type'] ?? 'desconocido') . ", Bits: " . ($keyDetails['bits'] ?? 'desconocido');
				dol_syslog("VERIFACTU DEBUG: Detalles de clave privada - " . $keyInfo, LOG_DEBUG);

				// Additional debug for RSA keys
				if (isset($keyDetails['rsa'])) {
					dol_syslog("VERIFACTU DEBUG: Clave RSA detectada - modulus length: " . strlen($keyDetails['rsa']['n'] ?? ''), LOG_DEBUG);
				}
			} else {
				dol_syslog("VERIFACTU DEBUG: No se pudieron obtener detalles de la clave privada", LOG_WARNING);
			}
		}

		// Try to export private key only if we have a valid resource AND haven't exported yet
		if ($keyResource && !$privateKeyExported) {
			// Attempt 1: Export without password
			dol_syslog("VERIFACTU DEBUG: Intento 1 - Exportando sin contraseña...", LOG_DEBUG);
			if (openssl_pkey_export($keyResource, $privateKeyPem, null, $opensslConfig)) {
				dol_syslog("VERIFACTU DEBUG: ✅ Clave privada exportada SIN contraseña (tamaño: " . strlen($privateKeyPem) . " bytes)", LOG_DEBUG);
				$privateKeyExported = true;
				$privateKeyMethod = 'PHP nativo (sin contraseña)';
			} else {
				// Capture errors from first attempt
				$error1 = '';
				while ($msg = openssl_error_string()) {
					$error1 .= $msg . "; ";
				}
				dol_syslog("VERIFACTU DEBUG: ❌ Intento 1 falló: " . $error1, LOG_DEBUG);

				// Attempt 2: Export with password
				dol_syslog("VERIFACTU DEBUG: Intento 2 - Exportando con contraseña...", LOG_DEBUG);
				if (openssl_pkey_export($keyResource, $privateKeyPem, $password, $opensslConfig)) {
					dol_syslog("VERIFACTU DEBUG: ✅ Clave privada exportada CON contraseña (tamaño: " . strlen($privateKeyPem) . " bytes)", LOG_DEBUG);
					$privateKeyExported = true;
					$privateKeyMethod = 'PHP nativo (con contraseña)';
				} else {
					// Capture errors from second attempt
					$error2 = '';
					while ($msg = openssl_error_string()) {
						$error2 .= $msg . "; ";
					}
					dol_syslog("VERIFACTU DEBUG: ❌ Intento 2 también falló: " . $error2, LOG_ERR);
					dol_syslog("VERIFACTU DEBUG: Continuando con método alternativo (OpenSSL externo)...", LOG_INFO);
				}
			}
		} elseif ($privateKeyExported) {
			dol_syslog("VERIFACTU DEBUG: ✅ Clave privada ya exportada como string PEM, omitiendo exportación adicional", LOG_DEBUG);
		}

		// METHOD 2: If PHP native method failed, use external OpenSSL
		if (!$privateKeyExported) {
			dol_syslog("VERIFACTU DEBUG: Intentando extracción con OpenSSL externo...", LOG_DEBUG);
			$opensslResult = extraerClavePrivadaConOpenSSL($certPath, $password);

			if ($opensslResult['success']) {
				$privateKeyPem = $opensslResult['private_key_pem'];
				$privateKeyExported = true;
				$privateKeyMethod = 'OpenSSL externo';
				dol_syslog("VERIFACTU DEBUG: ✅ Clave privada extraída con OpenSSL externo", LOG_DEBUG);
			} else {
				dol_syslog("VERIFACTU DEBUG: ❌ También falló OpenSSL externo: " . $opensslResult['message'], LOG_ERR);
			}
		}

		// Save private key if extracted successfully
		if ($privateKeyExported && !empty($privateKeyPem)) {
			// Verify PEM is valid
			if (
				strpos($privateKeyPem, '-----BEGIN PRIVATE KEY-----') !== false ||
				strpos($privateKeyPem, '-----BEGIN RSA PRIVATE KEY-----') !== false ||
				strpos($privateKeyPem, '-----BEGIN ENCRYPTED PRIVATE KEY-----') !== false
			) {
				dol_syslog("VERIFACTU DEBUG: ✅ Formato PEM de clave privada verificado", LOG_DEBUG);
			} else {
				dol_syslog("VERIFACTU DEBUG: ⚠️ El PEM exportado no tiene el formato esperado", LOG_WARNING);
			}

			// ✅ Save private key automatically in configuration
			$result = dolibarr_set_const($db, 'VERIFACTU_CERTIFICATE_PRIVATE_KEY', $privateKeyPem, 'chaine', 0, '', $conf->entity);

			if ($result > 0) {
				dol_syslog("VERIFACTU: ✅ Clave privada guardada automáticamente desde certificado PFX/P12 (método: " . $privateKeyMethod . ")", LOG_INFO);
			} else {
				dol_syslog("VERIFACTU: ❌ Error al guardar clave privada automáticamente", LOG_ERR);
				$privateKeyExported = false; // Mark as not exported if could not save
			}
		} else {
			dol_syslog("VERIFACTU DEBUG: ❌ No se pudo extraer clave privada - privateKeyExported: " . ($privateKeyExported ? 'true' : 'false') . ", privateKeyPem length: " . strlen($privateKeyPem ?? ''), LOG_ERR);
		}

		// Build result message
		$resultMessage = $certDisplayInfo;

		if ($privateKeyExported) {
			$resultMessage .= '<br><strong>'.$GLOBALS['langs']->trans("CertificateProcessedSuccessfully").'</strong><br>';
			$resultMessage .= '<ul>';
			$resultMessage .= '<li>'.$GLOBALS['langs']->trans("PrivateKey").': '.$GLOBALS['langs']->trans("ExtractedAndStored").' ('.$GLOBALS['langs']->trans("Method").': '.$privateKeyMethod.')</li>';
			$resultMessage .= '<li>'.$GLOBALS['langs']->trans("ExtractionMethod").': '.htmlspecialchars($extractionMethod).'</li>';
			$resultMessage .= '<li>'.$GLOBALS['langs']->trans("Status").': '.$GLOBALS['langs']->trans("ConfigurationComplete").'</li>';
			$resultMessage .= '</ul>';

			return [
				'success' => true,
				'message' => $resultMessage
			];
		} else {
			$resultMessage .= '<br><strong>'.$GLOBALS['langs']->trans("CertificateExtractionError").'</strong><br>';
			$resultMessage .= '<ul>';
			$resultMessage .= '<li>'.$GLOBALS['langs']->trans("PrivateKey").': '.$GLOBALS['langs']->trans("CouldNotExtract").'</li>';
			$resultMessage .= '<li>'.$GLOBALS['langs']->trans("ExtractionMethod").': '.htmlspecialchars($extractionMethod).'</li>';
			$resultMessage .= '<li>'.$GLOBALS['langs']->trans("Status").': '.$GLOBALS['langs']->trans("CheckPasswordAndRetry").'</li>';
			$resultMessage .= '</ul>';

			return [
				'success' => false,
				'message' => $resultMessage
			];
		}
	} catch (Exception $e) {
		return ['success' => false, 'message' => 'Error de excepción: ' . $e->getMessage()];
	}
}

/*
 * View
 */

$form = new Form($db);
$help_url = '';
$page_name = "ManageCertificates";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ? $backtopage : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = verifactuAdminPrepareHead();
print dol_get_fiche_head($head, 'manage_certificates', $langs->trans($page_name), -1, "verifactu@verifactu");

// Show messages
if (!empty($errorMsg)) {
	setEventMessages($errorMsg, null, 'errors');
}
if (!empty($successMsg)) {
	setEventMessages($successMsg, null, 'mesgs');
}

print '<br>';

// Información sobre la funcionalidad
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("CertificateProcessingInfo").'</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>';
print '<ul>';
print '<li><strong>'.$langs->trans("AutomaticExtraction").':</strong> '.$langs->trans("AutomaticExtractionDesc").'</li>';
print '<li><strong>'.$langs->trans("PrivateKeyLocal").':</strong> '.$langs->trans("PrivateKeyLocalDesc").'</li>';
print '<li><strong>'.$langs->trans("PublicKeyRemote").':</strong> '.$langs->trans("PublicKeyRemoteDesc").'</li>';
print '<li><strong>'.$langs->trans("AutomaticConfiguration").':</strong> '.$langs->trans("AutomaticConfigurationDesc").'</li>';
print '</ul>';
print '</td>';
print '</tr>';
print '</table>';
print '</div>';
print '<br>';

// Formulario de subida
print load_fiche_titre($langs->trans("UploadCertificate"), '', '');

print '<form method="post" enctype="multipart/form-data" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="upload_certificate">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans("CertificateFile").'</td>';
print '</tr>';

// File field
print '<tr class="oddeven">';
print '<td class="titlefieldcreate fieldrequired">'.$langs->trans("File").'</td>';
print '<td>';
print '<input type="file" class="flat minwidth400" id="certificate_file" name="certificate_file" accept=".pfx,.p12" required>';
print '<br><span class="opacitymedium">'.$langs->trans("AcceptedFormats").': .pfx, .p12</span>';
print '</td>';
print '</tr>';

// Password field
print '<tr class="oddeven">';
print '<td class="fieldrequired">'.$langs->trans("Password").'</td>';
print '<td>';
print '<input type="password" class="flat minwidth300" id="cert_password" name="cert_password" required placeholder="'.$langs->trans("CertificatePassword").'">';
print '</td>';
print '</tr>';

print '</table>';
print '</div>';

print '<div class="tabsAction">';
print '<input type="submit" class="butAction" value="'.$langs->trans("Upload").'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
