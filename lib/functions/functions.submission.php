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
 * \file    verifactu/lib/functions/functions.submission.php
 * \ingroup verifactu
 * \brief   Invoice submission functions (Create/Modify) for VeriFactu
 */

use Sietekas\Verifactu\VerifactuInvoice;
use Sietekas\Verifactu\VerifactuManager;

/**
 * Main function that orchestrates invoice submission to AEAT (Create/Modify/Cancel)
 *
 * @param Facture $facture Dolibarr invoice object
 * @param string $action Action to perform: 'Alta' (create), 'Mod' (modify) or 'Baja' (cancel)
 * @return bool True if submission was successful, false otherwise
 */
function executeVerifactuCall(Facture $facture, $action = 'Alta')
{
	return execVERIFACTUCall($facture, $action);
}

/**
 * Main function that orchestrates invoice submission to AEAT (Create/Modify/Cancel)
 *
 * @param Facture $facture Dolibarr invoice object
 * @param string $actionVERIFACTU Action to perform: 'Alta' (create), 'Mod' (modify) or 'Baja' (cancel)
 * @return bool True if submission was successful, false otherwise
 */
function execVERIFACTUCall(Facture $facture, $actionVERIFACTU = 'Alta')
{
	global $conf, $langs, $user, $db;

	$langs->load("verifactu@verifactu");

	dol_syslog("VERIFACTU execVERIFACTUCall START: Invoice id=" . $facture->id . " ref=" . $facture->ref . " action=" . $actionVERIFACTU, LOG_DEBUG);

	// Reload invoice values in case something was modified
	$res = $facture->fetch($facture->id);
	if ($res < 0) {
		setEventMessage($langs->trans('ERROR_FETCHING_INVOICE', $facture->id), 'errors');
		dol_syslog("VERIFACTU execVERIFACTUCall: ERROR fetching invoice, returning false", LOG_ERR);
		return false;
	}

	dol_syslog("VERIFACTU execVERIFACTUCall: After fetch - ref=" . $facture->ref . " status=" . $facture->status, LOG_DEBUG);

	// Verify actionVERIFACTU is valid
	if (!in_array($actionVERIFACTU, array('Alta', 'Mod', 'Baja'))) {
		setEventMessage($langs->trans('INVALID_VERIFACTU_ACTION', $actionVERIFACTU), 'errors');
		return false;
	}

	// PRE-CHECK: For Create, verify if invoice already exists correctly in AEAT
	// This avoids error 3000 (duplicate) and inconsistent state problem
	if ($actionVERIFACTU == 'Alta') {
		$preCheckResult = preCheckInvoiceInAEAT($facture);
		if ($preCheckResult['exists'] && $preCheckResult['amounts_match']) {
			// Invoice already exists in AEAT with correct amounts - do nothing
			dol_syslog("VERIFACTU: PRE-CHECK - Invoice {$facture->ref} already exists in AEAT with correct amounts. Skipping submission.", LOG_INFO);

			// Update status to sent successfully if not already
			$currentStatus = $facture->array_options['options_verifactu_estado'] ?? '';
			$alreadySent = (strpos($currentStatus, 'badge-status4') !== false || strpos($currentStatus, 'VERIFACTU_STATUS_SEND') !== false);

			if (!$alreadySent) {
				$successBadge = '<div class="center"><span class="badge badge-status4 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_SEND') . '">' . $langs->trans('VERIFACTU_STATUS_SEND') . '</span></div>';

				$successData = array(
					'estado' => $successBadge,
					'ultima_salida' => $langs->trans('VERIFACTU_PRECHECK_ALREADY_EXISTS'),
					'fecha_modificacion' => dol_now()
				);

				if (isset($preCheckResult['huella']) && !empty($preCheckResult['huella'])) {
					$successData['huella'] = $preCheckResult['huella'];
				}

				$saved = saveVerifactuSuccessData($facture, $successData);
				if (!$saved) {
					$facture->array_options['options_verifactu_estado'] = $successBadge;
					$facture->array_options['options_verifactu_error'] = '';
					$facture->array_options['options_verifactu_ultima_salida'] = $successData['ultima_salida'];
					$facture->array_options['options_verifactu_ultimafecha_modificacion'] = dol_now();
					$facture->insertExtraFields();
				}
			}

			setEventMessage($langs->trans('VERIFACTU_PRECHECK_SKIP_SEND', $facture->ref), 'warnings');
			return true; // Return success because invoice is already correctly in AEAT
		} else if ($preCheckResult['exists'] && !$preCheckResult['amounts_match']) {
			// Invoice exists but with different amounts - warn user
			dol_syslog("VERIFACTU: PRE-CHECK - Invoice {$facture->ref} exists in AEAT but with different amounts", LOG_WARNING);
			setEventMessage($langs->trans(
				'VERIFACTU_PRECHECK_AMOUNTS_DIFFER',
				$facture->ref,
				price($preCheckResult['importe_dolibarr']),
				price($preCheckResult['importe_aeat'])
			), 'errors');
			return false;
		}
		// If doesn't exist in AEAT, continue with normal submission
	}

	try {
		// Get module configuration
		$environment = getEnvironment();
		$issuerNif = $conf->global->VERIFACTU_HOLDER_NIF ?? '';
		$issuerName = $conf->global->VERIFACTU_HOLDER_COMPANY_NAME ?? '';

		// Get certificate configuration
		$certOptions = getCertificateOptions();
		if (!$certOptions) {
			$errorMessage = 'Certificate error: ' . (isset($GLOBALS['verifactu_cert_error']) ? $GLOBALS['verifactu_cert_error'] : 'Unknown error configuring certificate');
			setEventMessage($errorMessage, 'errors');
			return false;
		}

		// Validate certificate and private key before proceeding
		$certPath = $certOptions['local_cert'] ?? '';
		$certPassphrase = $certOptions['passphrase'] ?? '';

		if (!validateCertificateAndKey($certPath, $certPassphrase)) {
			dol_syslog("VERIFACTU: Error - Certificate key is not valid for invoice {$facture->ref}", LOG_ERR);
			$errorMessage = 'Certificate error: The certificate passphrase is incorrect or the certificate is invalid';
			setEventMessage($errorMessage, 'errors');
			return false;
		}
		dol_syslog("VERIFACTU: Certificate and key validated successfully for invoice {$facture->ref}", LOG_DEBUG);

		if (empty($issuerNif) || empty($issuerName)) {
			setEventMessage($langs->trans('VERIFACTU_CONFIG_INCOMPLETE'), 'errors');
			return false;
		}

		// System configuration for AEAT
		$systemConfig = getSystemConfig();
		dol_syslog("VERIFACTU: System configured: " . json_encode($systemConfig), LOG_DEBUG);

		// Initialize VerifactuManager
		$certType = $conf->global->VERIFACTU_CERT_TYPE ?? 'normal';

		dol_syslog("VERIFACTU: Environment: $environment, Cert type: $certType", LOG_DEBUG);

		// Initialize VerifactuManager
		$manager = new VerifactuManager(
			$issuerNif,
			$issuerName,
			$environment,
			'verifactu',
			$systemConfig,
			$certType
		);

		$response = false;
		switch ($actionVERIFACTU) {
			case 'Alta':
				$response = handleInvoiceCreationOrSubsanation($manager, $facture, $certOptions, $issuerNif, $issuerName, $systemConfig);
				break;
			case 'Mod':
				$response = handleInvoiceCreationOrSubsanation($manager, $facture, $certOptions, $issuerNif, $issuerName, $systemConfig, true);
				break;
			case 'Baja':
				// Get cancellation type from POST or use default
				$cancellationType = GETPOST('tipo_anulacion', 'alpha') ?: 'normal';
				$response = handleInvoiceCancellation($manager, $facture, $certOptions, $issuerNif, $issuerName, $cancellationType);
				break;
		}

		if ($response) {
			return true;
		} else {
			return false;
		}
	} catch (Exception $e) {
		// Save exception error information in extrafields
		$errorMessage = $e->getMessage();
		dol_syslog("Verifactu Error: " . $errorMessage, LOG_ERR);

		// Error badge (red) to indicate exception
		$errorBadge = '<div class="center"><span class="badge badge-status8 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_ERROR') . '">' . $langs->trans('VERIFACTU_STATUS_ERROR') . '</span></div>';
		$facture->array_options['options_verifactu_estado'] = $errorBadge;

		// Save complete error message
		$facture->array_options['options_verifactu_error'] = 'Exception: ' . $errorMessage;

		// Save error summary in ultima_salida
		$errorSummary = "VeriFactu Exception: " . $errorMessage;
		$facture->array_options['options_verifactu_ultima_salida'] = $errorSummary;

		// Update last modification date
		$facture->array_options['options_verifactu_ultimafecha_modificacion'] = dol_now();

		// Clear previous success data
		$facture->array_options['options_verifactu_csv_factura'] = '';
		$facture->array_options['options_verifactu_huella'] = '';

		// Prepare error data to save with independent connection
		$errorData = array(
			'estado' => $errorBadge,
			'error' => 'Exception: ' . $errorMessage,
			'ultima_salida' => $errorSummary,
			'fecha_modificacion' => dol_now(),
			'csv_factura' => '',
			'huella' => ''
		);

		// Try to save using external function with independent connection
		$saved = saveVerifactuErrorData($facture, $errorData);
		if (!$saved) {
			// Fallback: try with current connection
			dol_syslog("VERIFACTU: Fallback to insertExtraFields() for exception", LOG_WARNING);
			$facture->insertExtraFields();
		}

		setEventMessage($langs->trans('VERIFACTU_EXCEPTION', $errorMessage), 'errors');
		return false;
	}
}

/**
 * Handles invoice creation or modification in VeriFactu
 *
 * @param VerifactuManager $manager Manager instance
 * @param Facture $facture Invoice object
 * @param array $certOptions Certificate options
 * @param string $issuerNif Issuer NIF
 * @param string $issuerName Issuer name
 * @param array|null $systemConfig System configuration
 * @param bool $isSubsanacion If this is a correction/modification
 * @return bool True if submission was successful
 */
function handleInvoiceCreationOrSubsanation($manager, Facture $facture, $certOptions, $issuerNif, $issuerName, $systemConfig = null, $isSubsanacion = false)
{
	global $langs, $user, $db, $conf;
	$langs->load("verifactu@verifactu");

	// Create VeriFactu invoice
	if ($isSubsanacion) {
		$invoice = VerifactuInvoice::createSubsanacion(
			$facture->ref,
			date('d-m-Y', $facture->date),
			$issuerNif,
			$issuerName
		);
	} else {
		$invoice = new VerifactuInvoice(
			$facture->ref,
			date('d-m-Y', $facture->date),
			$issuerNif,
			$issuerName
		);
	}

	// Set the system information
	if (!empty($systemConfig)) {
		$invoice->setSystemInfo($systemConfig);
	}

	// Set description
	$description = 'Invoice ' . $facture->ref . '' . ($facture->ref_client ? ' Customer Ref: ' . $facture->ref_client : '');
	$invoice->setDescription($description);
	$invoice->setType($facture->array_options['options_verifactu_factura_tipo'] ?? VerifactuInvoice::TYPE_STANDARD);

	// Set VeriFactu incidence flag (default 'N' - no incidence)
	$incidence = $facture->array_options['options_verifactu_incidencia'] ?? 'N';
	$invoice->setIncidence($incidence);

	// External reference for internal identification
	$invoice->setExternalReference($facture->id);

	// Handle credit notes and corrective invoices
	if ($facture->type == Facture::TYPE_CREDIT_NOTE) {
		// Credit note - R1 type I (by differences)
		if ($facture->module_source == 'takepos') {
			$invoice->setType(VerifactuInvoice::TYPE_CREDIT_NOTE_SIMPLIFIED);
			$invoice->setRectificationType(VerifactuInvoice::RECT_DIFFERENCES);
		} else {
			$invoice->setType(VerifactuInvoice::TYPE_CREDIT_NOTE_LEGAL);
			$invoice->setRectificationType(VerifactuInvoice::RECT_DIFFERENCES);
		}
	} else if ($facture->type == Facture::TYPE_REPLACEMENT) {
		// Replacement invoice - R1 type S (by substitution)
		$invoice->setType(VerifactuInvoice::TYPE_CREDIT_NOTE_LEGAL);
		$invoice->setRectificationType(VerifactuInvoice::RECT_SUBSTITUTION);

		if ($facture->fk_facture_source) {
			$staticInvoice = new Facture($db);
			if ($staticInvoice->fetch($facture->fk_facture_source) > 0) {
				// Add FacturasRectificadas block with AEAT structure
				$issuerId = $issuerNif ?? '';
				$invoiceNumber = $staticInvoice->ref;
				$invoiceDate = dol_print_date($staticInvoice->date, '%d-%m-%Y');

				// For substitutions, use original invoice amounts
				$rectifiedBase = abs(getInvoiceTotalHT($staticInvoice));
				$rectifiedTax = abs(getInvoiceTotalTVA($staticInvoice));

				// Calculate CuotaRecargoRectificado from original invoice
				$rectifiedSurcharge = 0;
				if (isset($staticInvoice->total_localtax1) && $staticInvoice->total_localtax1 > 0) {
					$rectifiedSurcharge = abs($staticInvoice->total_localtax1);
				}

				$invoice->addRectifiedInvoice(
					$issuerId,
					$invoiceNumber,
					$invoiceDate,
					$rectifiedBase,
					$rectifiedTax,
					$rectifiedSurcharge > 0 ? $rectifiedSurcharge : null
				);
			}
		}
	}

	// Get customer data
	$facture->fetch_thirdparty();
	if ($facture->thirdparty && !in_array($facture->array_options['options_verifactu_factura_tipo'], [VerifactuInvoice::TYPE_SIMPLIFIED, VerifactuInvoice::TYPE_CREDIT_NOTE_SIMPLIFIED])) {

		$facture->thirdparty->fetch_optionals();
		$clientNif = $facture->thirdparty->idprof1 ?? '';
		$clientName = $facture->thirdparty->name;
		$clientVatIntra = $facture->thirdparty->tva_intra;

		if ($facture->thirdparty->country_code === 'ES' || empty($facture->thirdparty->country_code)) {
			// National client (Spain) - verify CIF/NIF
			if (!empty($clientNif)) {
				$invoice->addRecipient($clientNif, $clientName);
			}
		} else {
			// Foreign client - verify intra-community VAT
			if (!empty($clientVatIntra)) {
				$countryCode = $facture->thirdparty->country_code;
				$idType = $facture->thirdparty->array_options['options_verifactu_tipo_identificacion'] ?
					$facture->thirdparty->array_options['options_verifactu_tipo_identificacion'] :
					VerifactuInvoice::ID_TYPE_EU_VAT;
				$invoice->addForeignRecipient($clientName, $idType, $clientVatIntra, $countryCode);
			}
		}
	}

	// Process invoice lines
	$facture->fetch_lines();
	$totalBase = 0;
	$totalTax = 0;

	// Get base parameters once
	$verifactuParamsBase = getVerifactuParams($facture);

	$baseTaxType = $verifactuParamsBase['tipoImpuesto'];
	$baseRegimeKey = $verifactuParamsBase['claveRegimen'];
	$baseOperationQualification = $verifactuParamsBase['calificacionOperacion'];
	$baseExemptOperation = $verifactuParamsBase['operacionExenta'];

	// VALIDATION: Critical fields must be manually configured
	if (empty($baseTaxType)) {
		throw new Exception("ERROR: Tax Type not configured. Must be manually configured in the VeriFactu tab of the invoice after consulting with your tax advisor.");
	}

	// Specific validation: RegimeKey mandatory for VAT and IGIC, empty for IPSI and Others
	if (in_array($baseTaxType, [VerifactuInvoice::TAX_VAT, VerifactuInvoice::TAX_IGIC])) {
		if (empty($baseRegimeKey)) {
			throw new Exception("ERROR: Regime Key not configured. For VAT and IGIC it is mandatory. Must be manually configured after consulting with your tax advisor.");
		}
	} elseif (in_array($baseTaxType, [VerifactuInvoice::TAX_IPSI, VerifactuInvoice::TAX_OTHER])) {
		if (!empty($baseRegimeKey)) {
			throw new Exception("ERROR: Regime Key must be empty for IPSI and Other taxes according to AEAT specifications. Consult with your tax advisor.");
		}
	}

	// For credit notes, AEAT expects POSITIVE amounts
	// Dolibarr stores them as negative, so we need to use absolute value
	$isCreditNote = ($facture->type == Facture::TYPE_CREDIT_NOTE);

	foreach ($facture->lines as $line) {
		$taxType = $baseTaxType;
		$regimeKey = $baseRegimeKey;
		$operationQualification = $baseOperationQualification;
		$exemptOperation = $baseExemptOperation;

		$taxRate = floatval($line->tva_tx);
		$taxBase = floatval(isset($line->total_ht) ? $line->total_ht : (isset($line->total) ? $line->total : 0));
		$taxAmount = floatval(isset($line->total_tva) ? $line->total_tva : (isset($line->tva) ? $line->tva : 0));

		// For credit notes, convert to positive values (AEAT doesn't accept negatives)
		if ($isCreditNote) {
			$taxBase = abs($taxBase);
			$taxAmount = abs($taxAmount);
		}

		// DETECT EQUIVALENCE SURCHARGE
		$surchargeRate = floatval($line->localtax1_tx ?? 0);
		$surchargeAmount = floatval($line->total_localtax1 ?? 0);
		// For credit notes, also convert surcharge to positive
		if ($isCreditNote) {
			$surchargeAmount = abs($surchargeAmount);
		}
		$hasSurcharge = ($surchargeRate > 0 && abs($surchargeAmount) > 0);

		// If there's equivalence surcharge, adjust regime key
		if ($hasSurcharge && $taxType == VerifactuInvoice::TAX_VAT) {
			$regimeKey = VerifactuInvoice::REGIME_EQUIVALENCE_SURCHARGE;
			dol_syslog("VERIFACTU: Line with equivalence surcharge detected: VAT {$taxRate}% + Surcharge {$surchargeRate}%", LOG_INFO);
		}

		// Adjust qualification per line if necessary
		if ($taxRate == 0 && empty($exemptOperation)) {
			dol_syslog("VERIFACTU: Line with 0% VAT without specific exemption - may require manual review", LOG_WARNING);
		}

		// CRITICAL CONTROL: For NOT SUBJECT operations (N1/N2) VAT fields cannot be reported
		$isNotSubjectOperation = in_array($operationQualification, [
			VerifactuInvoice::QUAL_NOT_SUBJECT,
			VerifactuInvoice::QUAL_NOT_SUBJECT_LOCATION
		]);

		if ($isNotSubjectOperation) {
			dol_syslog("VERIFACTU: NOT SUBJECT operation ({$operationQualification}) - Only tax base without VAT fields", LOG_INFO);

			$invoice->addDesglose(
				$operationQualification,
				$taxBase,
				null,
				$taxType,
				$regimeKey
			);
		} elseif ($hasSurcharge) {
			dol_syslog("VERIFACTU DEBUG SURCHARGE: Calling addDesglose with taxType='" . $taxType . "' (length: " . strlen($taxType) . ")", LOG_DEBUG);

			$invoice->addDesglose(
				$operationQualification,
				$taxBase,
				$exemptOperation,
				$taxType,
				$regimeKey,
				$taxRate,
				$taxAmount,
				null,
				$surchargeRate,
				$surchargeAmount
			);

			dol_syslog("VERIFACTU: Breakdown with surcharge - Base: {$taxBase}, VAT: {$taxAmount}, Surcharge: {$surchargeAmount}, Line total: " . ($taxBase + $taxAmount + $surchargeAmount), LOG_INFO);
		} else {
			dol_syslog("VERIFACTU DEBUG NORMAL: Calling addDesglose with taxType='" . $taxType . "' (length: " . strlen($taxType) . ")", LOG_DEBUG);

			$invoice->addDesglose(
				$operationQualification,
				$taxBase,
				$exemptOperation,
				$taxType,
				$regimeKey,
				$taxRate,
				$taxAmount
			);

			dol_syslog("VERIFACTU: Normal breakdown - Base: {$taxBase}, VAT: {$taxAmount}", LOG_INFO);
		}

		$totalBase += $taxBase;
		if (!$isNotSubjectOperation) {
			$totalTax += $taxAmount + ($hasSurcharge ? $surchargeAmount : 0);
		}
	}

	// Configure chaining
	$previousHash = getLastInvoiceHash();
	if ($previousHash) {
		$invoice->setChainLink(
			$issuerNif,
			$previousHash['numero'],
			$previousHash['fecha'],
			$previousHash['hash']
		);
	} else {
		$invoice->setAsFirstInChain();
	}

	// Send invoice
	try {
		$response = $manager->sendInvoice($invoice, $certOptions);
	} catch (\Exception $e) {
		// Check if it's a service unavailable error (code 503)
		if (
			$e->getCode() === 503 ||
			strpos($e->getMessage(), 'service unavailable') !== false ||
			strpos($e->getMessage(), 'Service Unavailable') !== false ||
			strpos($e->getMessage(), 'HTTP 503') !== false
		) {
			dol_syslog("VERIFACTU: Service unavailable detected for invoice {$facture->ref}. Marking for later resend.", LOG_WARNING);

			$pendingBadge = '<div class="center"><span class="badge badge-status1 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_PENDING_SERVICE') . '">' . $langs->trans('VERIFACTU_STATUS_PENDING_SERVICE') . '</span></div>';
			$facture->array_options['options_verifactu_estado'] = $pendingBadge;
			$facture->array_options['options_verifactu_error'] = 'SERVICE_UNAVAILABLE';
			$facture->array_options['options_verifactu_ultimafecha_modificacion'] = dol_now();
			// Mark Incidence='S' according to VeriFactu regulations for later resends
			$facture->array_options['options_verifactu_incidencia'] = 'S';

			$facture->updateExtraField('verifactu_estado');
			$facture->updateExtraField('verifactu_error');
			$facture->updateExtraField('verifactu_ultimafecha_modificacion');
			$facture->updateExtraField('verifactu_incidencia');

			setEventMessage($langs->trans('VERIFACTU_SERVICE_UNAVAILABLE_INFO', $facture->ref, $e->getMessage()), 'warnings');

			return true;
		}

		throw $e;
	}

	// Process VeriFactu response
	return processInvoiceSendResponse($response, $facture, $manager, $langs, $conf);
}

/**
 * Processes the invoice submission response
 *
 * @param object $response AEAT response
 * @param Facture $facture Invoice
 * @param VerifactuManager $manager Manager
 * @param Translate $langs Languages
 * @param Conf $conf Configuration
 * @return bool True if successful
 */
function processInvoiceSendResponse($response, $facture, $manager, $langs, $conf)
{
	if ($response) {
		if (isset($response->EstadoEnvio) && in_array($response->EstadoEnvio, ['Correcto', 'ParcialmenteCorrecto'])) {
			// SUCCESS
			$statusMessage = $response->EstadoEnvio === 'Correcto' ? 'successfully' : 'with partial success';
			dol_syslog("VERIFACTU: Invoice {$facture->ref} sent {$statusMessage}", LOG_INFO);

			if ($response->EstadoEnvio === 'Correcto') {
				$successBadge = '<div class="center"><span class="badge badge-status4 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_SEND') . '">' . $langs->trans('VERIFACTU_STATUS_SEND') . '</span></div>';
			} else {
				$successBadge = '<div class="center"><span class="badge badge-status1 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_SEND_PARTIAL') . '">' . $langs->trans('VERIFACTU_STATUS_SEND_PARTIAL') . '</span></div>';
			}

			// Prepare success data to save with independent connection
			$successData = array(
				'estado' => $successBadge,
				'csv_factura' => $response->CSV ?? '',
				'payload' => json_encode($manager->getLastSendInvoiceData()),
				'entorno' => $manager->getEnvironment(),
				'modo' => '<span class="badge  badge-primary badge-status" attr-status="Mode">' . ($conf->global->VERIFACTU_MODE ?? 'verifactu') . '</span>',
				'ultima_salida' => buildVerifactuOutputSummary($response, false),
				'fecha_modificacion' => dol_now()
			);

			if (isset($response->RespuestaLinea->IDFactura)) {
				$invoiceIdData = array(
					'IDEmisorFactura' => $response->RespuestaLinea->IDFactura->IDEmisorFactura ?? '',
					'NumSerieFactura' => $response->RespuestaLinea->IDFactura->NumSerieFactura ?? '',
					'FechaExpedicionFactura' => $response->RespuestaLinea->IDFactura->FechaExpedicionFactura ?? ''
				);
				$successData['id_factura'] = json_encode($invoiceIdData);
			}

			if (isset($response->RespuestaLinea->IDFactura->FechaExpedicionFactura)) {
				$invoiceDate = $response->RespuestaLinea->IDFactura->FechaExpedicionFactura;
				if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $invoiceDate, $matches)) {
					$successData['fecha_factura'] = $matches[3] . '-' . $matches[2] . '-' . $matches[1];
				}
			}

			$generatedHash = $manager->getLastGeneratedHash();
			$generatedTimestamp = $manager->getLastGeneratedTimestamp();

			if ($generatedHash) {
				$successData['huella'] = $generatedHash;
				dol_syslog("VERIFACTU: Hash captured during submission for invoice {$facture->ref}: " . $generatedHash, LOG_DEBUG);
			}

			if ($generatedTimestamp) {
				$successData['fecha_hora_generacion'] = $generatedTimestamp;
				dol_syslog("VERIFACTU: Generation timestamp saved for invoice {$facture->ref}: " . $generatedTimestamp, LOG_DEBUG);
			}

			// Save using independent connection to avoid loss in case of rollback
			$saved = saveVerifactuSuccessData($facture, $successData);
			if (!$saved) {
				// Fallback: try with current connection
				dol_syslog("VERIFACTU: Fallback to insertExtraFields() for submission success", LOG_WARNING);
				$facture->array_options['options_verifactu_estado'] = $successBadge;
				$facture->array_options['options_verifactu_csv_factura'] = $successData['csv_factura'];
				$facture->array_options['options_verifactu_payload'] = $successData['payload'];
				$facture->array_options['options_verifactu_entorno'] = $successData['entorno'];
				$facture->array_options['options_verifactu_modo'] = $successData['modo'];
				$facture->array_options['options_verifactu_ultima_salida'] = $successData['ultima_salida'];
				$facture->array_options['options_verifactu_ultimafecha_modificacion'] = $successData['fecha_modificacion'];
				if (isset($successData['id_factura'])) {
					$facture->array_options['options_verifactu_id_factura'] = $successData['id_factura'];
				}
				if (isset($successData['fecha_factura'])) {
					$facture->array_options['options_verifactu_fecha_factura'] = $successData['fecha_factura'];
				}
				if (isset($successData['huella'])) {
					$facture->array_options['options_verifactu_huella'] = $successData['huella'];
				}
				if (isset($successData['fecha_hora_generacion'])) {
					$facture->array_options['options_verifactu_fecha_hora_generacion'] = $successData['fecha_hora_generacion'];
				}
				$facture->array_options['options_verifactu_error'] = '';
				$facture->insertExtraFields();
			}

			$successMessage = $response->EstadoEnvio === 'Correcto' ?
				"Invoice $facture->ref sent successfully to VeriFactu" :
				"Invoice $facture->ref sent to VeriFactu with partial success (some records may have warnings)";
			setEventMessage($successMessage, 'mesgs');
			dol_syslog("VERIFACTU execVERIFACTUCall: SUCCESS, returning TRUE for invoice " . $facture->ref . " - EstadoEnvio=" . $response->EstadoEnvio, LOG_DEBUG);
			return true;
		} else {
			// ERROR
			$submissionStatus = $response->EstadoEnvio ?? 'Unknown';
			$errorMsg = "Error in VeriFactu submission - Status: {$submissionStatus}";
			$errorCode = null;

			if (isset($response->RespuestaLinea)) {
				$respuesta = $response->RespuestaLinea;
				if (isset($respuesta->CodigoErrorRegistro)) {
					$errorCode = $respuesta->CodigoErrorRegistro;
					$errorDescription = $respuesta->DescripcionErrorRegistro ?? 'No description';
					$errorMsg .= " - Code: {$errorCode} - {$errorDescription}";
				}
			}

			// SPECIAL CASE: Error 3000 = Duplicate record = Invoice already exists in AEAT
			if ($errorCode == '3000') {
				dol_syslog("VERIFACTU: Detected error 3000 (duplicate) - Querying AEAT to verify amounts", LOG_INFO);

				$duplicateResult = manejarFacturaDuplicada($facture);

				switch ($duplicateResult['accion']) {
					case 'marcar_enviada':
						// Amounts match - mark as sent successfully
						dol_syslog("VERIFACTU: Duplicate invoice with matching amounts - marking as sent", LOG_INFO);

						$sentBadge = '<div class="center"><span class="badge badge-status4 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_SENT_DUPLICATE') . '">' . $langs->trans('VERIFACTU_STATUS_SENT_DUPLICATE') . '</span></div>';

						$facture->array_options['options_verifactu_estado'] = $sentBadge;
						$facture->array_options['options_verifactu_error'] = null;
						$facture->array_options['options_verifactu_ultimafecha_modificacion'] = dol_now();

						// Try to extract fingerprint from AEAT invoice if available
						if (isset($duplicateResult['datos_aeat']->DatosRegistroFacturacion->Huella)) {
							$facture->array_options['options_verifactu_huella'] = $duplicateResult['datos_aeat']->DatosRegistroFacturacion->Huella;
						}

						$facture->updateExtraField('verifactu_estado');
						$facture->updateExtraField('verifactu_error');
						$facture->updateExtraField('verifactu_ultimafecha_modificacion');
						if (!empty($facture->array_options['options_verifactu_huella'])) {
							$facture->updateExtraField('verifactu_huella');
						}

						setEventMessage($duplicateResult['mensaje'], 'warnings');
						return true; // Success - invoice was already in AEAT

					case 'preguntar_usuario':
						// Amounts DON'T match - mark with special status for user to decide
						dol_syslog("VERIFACTU: Duplicate invoice with different amounts - requires user action", LOG_WARNING);

						$duplicateDifferentBadge = '<div class="center"><span class="badge badge-status1 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_DUPLICATE_DIFF') . '">' . $langs->trans('VERIFACTU_STATUS_DUPLICATE_DIFF') . '</span></div>';

						$duplicateErrorMsg = $langs->trans(
							'VERIFACTU_DUPLICATE_AMOUNTS_DIFFER_DETAIL',
							$facture->ref,
							price($duplicateResult['comparacion']['importe_dolibarr']),
							price($duplicateResult['comparacion']['importe_aeat']),
							price($duplicateResult['comparacion']['diferencia'])
						);

						$facture->array_options['options_verifactu_estado'] = $duplicateDifferentBadge;
						$facture->array_options['options_verifactu_error'] = $duplicateErrorMsg;
						$facture->array_options['options_verifactu_ultimafecha_modificacion'] = dol_now();

						$facture->updateExtraField('verifactu_estado');
						$facture->updateExtraField('verifactu_error');
						$facture->updateExtraField('verifactu_ultimafecha_modificacion');

						// Show message with options to user
						setEventMessage($duplicateResult['mensaje'], 'warnings');
						setEventMessage($langs->trans('VERIFACTU_DUPLICATE_ACTION_REQUIRED'), 'warnings');

						// Return false so invoice is not automatically validated
						// User must decide: create corrective or adjust Dolibarr
						return false;

					case 'error':
					default:
						// Could not query AEAT - treat as normal error
						dol_syslog("VERIFACTU: Error querying duplicate invoice in AEAT", LOG_ERR);
						setEventMessage($duplicateResult['mensaje'], 'errors');
						// Continue with normal error flow below
						break;
				}
			}

			dol_syslog("VERIFACTU ERROR: " . $errorMsg, LOG_ERR);

			$errorBadge = '<div class="center"><span class="badge badge-status8 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_ERROR') . '">' . $langs->trans('VERIFACTU_STATUS_ERROR') . '</span></div>';

			$errorSummary = buildVerifactuOutputSummary($response, true);

			$errorData = array(
				'estado' => $errorBadge,
				'error' => $errorMsg,
				'ultima_salida' => $errorSummary,
				'fecha_modificacion' => dol_now()
			);

			$saved = saveVerifactuErrorData($facture, $errorData);
			if (!$saved) {
				dol_syslog("VERIFACTU: Fallback to insertExtraFields() for submission error", LOG_WARNING);
				$facture->array_options['options_verifactu_estado'] = $errorBadge;
				$facture->array_options['options_verifactu_error'] = $errorMsg;
				$facture->array_options['options_verifactu_ultima_salida'] = $errorSummary;
				$facture->array_options['options_verifactu_ultimafecha_modificacion'] = dol_now();
				$facture->insertExtraFields();
			}

			setEventMessage($errorMsg, 'errors');
			dol_syslog("VERIFACTU execVERIFACTUCall: AEAT error response, returning FALSE for invoice " . $facture->ref, LOG_ERR);
			return false;
		}
	} else {
		// No response from server
		$errorMsg = "No response received from VeriFactu server";
		dol_syslog("VERIFACTU ERROR: " . $errorMsg, LOG_ERR);

		$errorBadge = '<div class="center"><span class="badge badge-status8 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_ERROR') . '">' . $langs->trans('VERIFACTU_STATUS_ERROR') . '</span></div>';

		$errorData = array(
			'estado' => $errorBadge,
			'error' => $errorMsg,
			'fecha_modificacion' => dol_now()
		);

		$saved = saveVerifactuErrorData($facture, $errorData);
		if (!$saved) {
			dol_syslog("VERIFACTU: Fallback to insertExtraFields() for no response", LOG_WARNING);
			$facture->array_options['options_verifactu_estado'] = $errorBadge;
			$facture->array_options['options_verifactu_error'] = $errorMsg;
			$facture->array_options['options_verifactu_ultimafecha_modificacion'] = dol_now();
			$facture->insertExtraFields();
		}

		setEventMessage($errorMsg, 'errors');
		dol_syslog("VERIFACTU execVERIFACTUCall: No response from server, returning FALSE for invoice " . $facture->ref, LOG_ERR);
		return false;
	}
}
