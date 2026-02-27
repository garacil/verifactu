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
 * \file    verifactu/lib/functions/functions.query.php
 * \ingroup verifactu
 * \brief   AEAT invoice query functions for VeriFactu
 */

use Sietekas\Verifactu\VerifactuManager;
use Sietekas\Verifactu\VerifactuInvoiceQuery;

/**
 * Executes an invoice query in VeriFactu
 *
 * @param array $queryFilter Query filters
 * @return mixed AEAT response or false on error
 */
function executeVerifactuQuery($queryFilter)
{
	return execVERIFACTUQuery($queryFilter);
}

/**
 * Executes an invoice query in VeriFactu
 *
 * @param array $filtroConsulta Query filters
 * @return mixed AEAT response or false on error
 */
function execVERIFACTUQuery($filtroConsulta)
{
	global $conf, $langs;

	try {
		dol_syslog("VERIFACTU: Starting query", LOG_DEBUG);
		dol_syslog("VERIFACTU: Complete filter received: " . json_encode($filtroConsulta), LOG_DEBUG);

		// Get certificate configuration
		$certOptions = getCertificateOptions();
		if (!$certOptions) {
			dol_syslog("VERIFACTU: Error - Could not get certificate configuration", LOG_ERR);
			$errorMessage = 'Certificate error: ' . (isset($GLOBALS['verifactu_cert_error']) ? $GLOBALS['verifactu_cert_error'] : 'Unknown error configuring certificate');
			setEventMessage($errorMessage, 'errors');
			return false;
		}
		dol_syslog("VERIFACTU: Certificates configured correctly", LOG_DEBUG);

		// Validate certificate and private key before proceeding
		$certPath = $certOptions['local_cert'] ?? '';
		$certPassphrase = $certOptions['passphrase'] ?? '';

		if (!validateCertificateAndKey($certPath, $certPassphrase)) {
			dol_syslog("VERIFACTU: Error - Certificate key is not valid", LOG_ERR);
			$errorMessage = 'Certificate error: The certificate passphrase is incorrect or the certificate is invalid';
			setEventMessage($errorMessage, 'errors');
			return false;
		}
		dol_syslog("VERIFACTU: Certificate and key validated correctly for query", LOG_DEBUG);

		// Company information from configuration
		$environment = getEnvironment();
		$issuerNif = $conf->global->VERIFACTU_HOLDER_NIF ?? '';
		$issuerName = $conf->global->VERIFACTU_HOLDER_COMPANY_NAME ?? '';

		dol_syslog("VERIFACTU: Issuer NIF: $issuerNif, Name: $issuerName", LOG_DEBUG);

		if (empty($issuerNif) || empty($issuerName)) {
			dol_syslog("VERIFACTU: Error - Issuer information incomplete", LOG_ERR);
			setEventMessage('Error: Issuer information not configured in VeriFactu', 'errors');
			return false;
		}

		// System configuration for AEAT
		$systemConfig = getSystemConfig();
		dol_syslog("VERIFACTU: System configured: " . json_encode($systemConfig), LOG_DEBUG);

		// Initialize VerifactuManager
		$certType = $conf->global->VERIFACTU_CERT_TYPE ?? 'normal';

		dol_syslog("VERIFACTU: Environment: $environment, Cert type: $certType", LOG_DEBUG);

		$manager = new VerifactuManager(
			$issuerNif,
			$issuerName,
			$environment,
			'verifactu',
			$systemConfig,
			$certType
		);

		// Build query object
		$query = buildQueryFromFilter($filtroConsulta, $issuerNif, $issuerName);

		if (!$query) {
			dol_syslog("VERIFACTU: Error - Could not create query object", LOG_ERR);
			return false;
		}

		// Execute query
		dol_syslog("VERIFACTU: Executing query to AEAT...", LOG_DEBUG);

		try {
			$response = $manager->queryInvoice($query, $certOptions);

			if ($response) {
				dol_syslog("VERIFACTU: Query executed successfully", LOG_INFO);
				dol_syslog("VERIFACTU: Response type: " . gettype($response), LOG_DEBUG);

				// Apply post-query filters if necessary
				$response = applyPostFilters($response, $filtroConsulta);

				return $response;
			} else {
				dol_syslog("VERIFACTU: Query returned empty response", LOG_WARNING);
				return false;
			}
		} catch (Exception $queryException) {
			dol_syslog("VERIFACTU: Specific error in queryInvoice - " . $queryException->getMessage(), LOG_ERR);
			dol_syslog("VERIFACTU: queryInvoice stack trace - " . $queryException->getTraceAsString(), LOG_ERR);
			throw $queryException;
		}
	} catch (Exception $e) {
		dol_syslog("VERIFACTU: Error in query - " . $e->getMessage(), LOG_ERR);
		dol_syslog("VERIFACTU: Stack trace - " . $e->getTraceAsString(), LOG_ERR);
		setEventMessage('Error in VeriFactu query: ' . $e->getMessage(), 'errors');
		return false;
	}
}

/**
 * Builds query object from received filter
 *
 * @param array $filtroConsulta Query filter
 * @param string $issuerNif Issuer NIF
 * @param string $issuerName Issuer name
 * @return VerifactuInvoiceQuery|null Query object or null on error
 */
function buildQueryFromFilter($filtroConsulta, $issuerNif, $issuerName)
{
	$query = null;

	// Detect if we have complete or simplified structure
	$realFilter = null;
	if (isset($filtroConsulta['ConsultaFactuSistemaFacturacion']['FiltroConsulta'])) {
		$realFilter = $filtroConsulta['ConsultaFactuSistemaFacturacion']['FiltroConsulta'];
		dol_syslog("VERIFACTU: Detected complete ConsultaFactuSistemaFacturacion structure", LOG_DEBUG);
	} else {
		$realFilter = $filtroConsulta;
		dol_syslog("VERIFACTU: Detected simplified structure", LOG_DEBUG);
	}

	// If filtering by specific invoice, use RangoFechaExpedicion
	if (isset($realFilter['IDFactura']) && isset($realFilter['IDFactura']['NumSerieFacturaEmisor'])) {
		$query = buildQueryForSpecificInvoice($realFilter, $issuerNif, $issuerName);
	} else {
		// General query
		$query = new VerifactuInvoiceQuery(null, null, $issuerNif, $issuerName);

		if (isset($realFilter['PeriodoImputacion'])) {
			$period = $realFilter['PeriodoImputacion'];
			if (isset($period['Ejercicio']) && isset($period['Periodo'])) {
				$query->setFiscalPeriod($period['Ejercicio'], $period['Periodo']);
				dol_syslog("VERIFACTU: Period filter applied - Year: {$period['Ejercicio']}, Period: {$period['Periodo']}", LOG_DEBUG);
			}
		}
	}

	return $query;
}

/**
 * Builds query for specific invoice
 *
 * @param array $realFilter Extracted real filter
 * @param string $issuerNif Issuer NIF
 * @param string $issuerName Issuer name
 * @return VerifactuInvoiceQuery|null
 */
function buildQueryForSpecificInvoice($realFilter, $issuerNif, $issuerName)
{
	$invoiceId = $realFilter['IDFactura'];
	dol_syslog("VERIFACTU: Processing specific invoice filter with RangoFechaExpedicion: " . $invoiceId['NumSerieFacturaEmisor'], LOG_DEBUG);

	// Check if data is already properly structured
	if (isset($invoiceId['FechaExpedicionFacturaEmisor']) && isset($invoiceId['NIF'])) {
		$invoiceNumber = $invoiceId['NumSerieFacturaEmisor'];
		$invoiceDate = $invoiceId['FechaExpedicionFacturaEmisor'];
		$invoiceIssuerNif = $invoiceId['NIF'];

		dol_syslog("VERIFACTU: Data structured correctly - Invoice: $invoiceNumber, Date: $invoiceDate, NIF: $invoiceIssuerNif", LOG_DEBUG);
	} else {
		// Try to decode as JSON
		$invoiceData = json_decode($invoiceId['NumSerieFacturaEmisor'], true);
		if ($invoiceData && is_array($invoiceData)) {
			$invoiceNumber = $invoiceData['NumSerieFactura'] ?? '';
			$invoiceDate = $invoiceData['FechaExpedicionFactura'] ?? '';
			$invoiceIssuerNif = $invoiceData['IDEmisorFactura'] ?? $issuerNif;

			dol_syslog("VERIFACTU: Data decoded from JSON - Invoice: $invoiceNumber, Date: $invoiceDate, NIF: $invoiceIssuerNif", LOG_DEBUG);
		} else {
			$invoiceNumber = $invoiceId['NumSerieFacturaEmisor'];
			$invoiceDate = $invoiceId['FechaExpedicionFacturaEmisor'] ?? '';
			$invoiceIssuerNif = $issuerNif;

			dol_syslog("VERIFACTU: Using direct invoice number (not JSON) - Invoice: $invoiceNumber, Date: $invoiceDate, NIF: $invoiceIssuerNif", LOG_DEBUG);
		}
	}

	if (!empty($invoiceNumber) && !empty($invoiceDate)) {
		// STRATEGY 1: Use specific RangoFechaExpedicion
		$query = new VerifactuInvoiceQuery(null, null, $invoiceIssuerNif, $issuerName);

		$dateTo = date('d-m-Y', strtotime($invoiceDate . ' +1 day'));
		$query->setDateRange($invoiceDate, $dateTo);
		$query->setSpecificInvoice($invoiceNumber, $invoiceDate, $invoiceIssuerNif);

		dol_syslog("VERIFACTU: STRATEGY 1 - Query with RangoFechaExpedicion: $invoiceDate to $dateTo", LOG_DEBUG);

		return $query;
	} else if (!empty($invoiceNumber)) {
		// STRATEGY 2: Use PeriodoImputacion with post-filtering
		$query = new VerifactuInvoiceQuery(null, null, $invoiceIssuerNif, $issuerName);

		if (isset($realFilter['PeriodoImputacion'])) {
			$query->setFiscalPeriod(
				$realFilter['PeriodoImputacion']['Ejercicio'],
				$realFilter['PeriodoImputacion']['Periodo']
			);
			dol_syslog("VERIFACTU: STRATEGY 2 - Using PeriodoImputacion with post-filtering by number: $invoiceNumber", LOG_DEBUG);
		}

		$customFilters = [
			'IDFactura' => [
				'NumSerieFacturaEmisor' => $invoiceNumber
			]
		];
		if ($invoiceDate) {
			$customFilters['IDFactura']['FechaExpedicionFacturaEmisor'] = $invoiceDate;
		}
		$query->addCustomFilters($customFilters);

		return $query;
	}

	dol_syslog("VERIFACTU: Error - Incomplete specific invoice data", LOG_ERR);
	return null;
}

/**
 * Applies post-query filters to response
 *
 * @param object $response AEAT response
 * @param array $filtroConsulta Original filter
 * @return object Filtered response
 */
function applyPostFilters($response, $filtroConsulta)
{
	// Extract real filter
	$realFilter = null;
	if (isset($filtroConsulta['ConsultaFactuSistemaFacturacion']['FiltroConsulta'])) {
		$realFilter = $filtroConsulta['ConsultaFactuSistemaFacturacion']['FiltroConsulta'];
	} else {
		$realFilter = $filtroConsulta;
	}

	// Basic log of response structure
	if (is_object($response)) {
		$responseVars = get_object_vars($response);
		$responseKeys = array_keys($responseVars);
		dol_syslog("VERIFACTU: Response structure - Keys: " . implode(', ', $responseKeys), LOG_DEBUG);
		dol_syslog("VERIFACTU: ResultadoConsulta received: " . ($response->ResultadoConsulta ?? 'NOT_DEFINED'), LOG_DEBUG);

		if (isset($response->RegistroRespuestaConsultaFactuSistemaFacturacion)) {
			$numInvoices = is_array($response->RegistroRespuestaConsultaFactuSistemaFacturacion)
				? count($response->RegistroRespuestaConsultaFactuSistemaFacturacion)
				: 1;
			dol_syslog("VERIFACTU: Number of invoices in response: $numInvoices", LOG_DEBUG);

			// Apply IDFactura filter
			$response = applyInvoiceIdFilter($response, $realFilter);

			// Apply Counterparty filter
			$response = applyCounterpartyFilter($response, $realFilter);
		}
	}

	return $response;
}

/**
 * Applies filter by IDFactura
 *
 * @param object $response Response
 * @param array $realFilter Filter
 * @return object Filtered response
 */
function applyInvoiceIdFilter($response, $realFilter)
{
	if (
		!isset($realFilter['IDFactura']) ||
		!isset($realFilter['IDFactura']['NumSerieFacturaEmisor']) ||
		$response->ResultadoConsulta != 'ConDatos'
	) {
		return $response;
	}

	$searchedInvoiceNumber = $realFilter['IDFactura']['NumSerieFacturaEmisor'];
	$searchedInvoiceDate = $realFilter['IDFactura']['FechaExpedicionFacturaEmisor'] ?? null;

	if ($searchedInvoiceDate) {
		dol_syslog("VERIFACTU: Applying post-query filter by invoice and date - Searching: $searchedInvoiceNumber, Date: $searchedInvoiceDate", LOG_DEBUG);
	} else {
		dol_syslog("VERIFACTU: Applying post-query filter by invoice number only - Searching: $searchedInvoiceNumber", LOG_DEBUG);
	}

	$foundInvoices = [];
	$allInvoices = is_array($response->RegistroRespuestaConsultaFactuSistemaFacturacion)
		? $response->RegistroRespuestaConsultaFactuSistemaFacturacion
		: [$response->RegistroRespuestaConsultaFactuSistemaFacturacion];

	dol_syslog("VERIFACTU: Starting post-filtering. Total invoices to review: " . count($allInvoices), LOG_DEBUG);

	foreach ($allInvoices as $index => $invoice) {
		if (isset($invoice->IDFactura)) {
			$invoiceNumber = $invoice->IDFactura->NumSerieFactura ?? 'NOT_DEFINED';
			$invoiceDate = $invoice->IDFactura->FechaExpedicionFactura ?? 'NOT_DEFINED';

			dol_syslog("VERIFACTU: Invoice #$index - Number: '$invoiceNumber', Date: '$invoiceDate'", LOG_DEBUG);

			$numberMatches = $invoiceNumber === $searchedInvoiceNumber;
			$dateMatches = !$searchedInvoiceDate || ($invoiceDate === $searchedInvoiceDate);

			dol_syslog("VERIFACTU: Invoice #$index - Number matches: " . ($numberMatches ? 'YES' : 'NO') .
				", Date matches: " . ($dateMatches ? 'YES' : 'NO'), LOG_DEBUG);

			if ($numberMatches && $dateMatches) {
				$foundInvoices[] = $invoice;
				dol_syslog("VERIFACTU: MATCH! Invoice #$index added to results", LOG_DEBUG);
			}
		} else {
			dol_syslog("VERIFACTU: Invoice #$index does not have IDFactura field", LOG_WARNING);
		}
	}

	if (!empty($foundInvoices)) {
		$response->RegistroRespuestaConsultaFactuSistemaFacturacion = $foundInvoices;
		$numFound = count($foundInvoices);
		dol_syslog("VERIFACTU: Post-query filter applied successfully - $numFound invoice(s) found", LOG_INFO);
	} else {
		// IMPORTANT: If specific invoice not found, mark as NoData
		// to avoid returning incorrect invoices from same day
		dol_syslog("VERIFACTU: Specific invoice '$searchedInvoiceNumber' not found - marking as NoData", LOG_INFO);
		$response->RegistroRespuestaConsultaFactuSistemaFacturacion = [];
		$response->ResultadoConsulta = 'SinDatos';
	}

	return $response;
}

/**
 * Applies filter by Counterparty (NIF/Name)
 *
 * @param object $response Response
 * @param array $realFilter Filter
 * @return object Filtered response
 */
function applyCounterpartyFilter($response, $realFilter)
{
	if (
		!isset($realFilter['Contraparte']) ||
		!isset($realFilter['Contraparte']['NIF']) ||
		$response->ResultadoConsulta != 'ConDatos'
	) {
		return $response;
	}

	$counterpartyNif = $realFilter['Contraparte']['NIF'];
	$counterpartyName = $realFilter['Contraparte']['NombreRazon'] ?? null;

	dol_syslog("VERIFACTU: Applying post-query filter by counterparty - NIF: $counterpartyNif" .
		($counterpartyName ? ", Name: $counterpartyName" : ""), LOG_DEBUG);

	$filteredByCounterparty = [];
	$allInvoices = is_array($response->RegistroRespuestaConsultaFactuSistemaFacturacion)
		? $response->RegistroRespuestaConsultaFactuSistemaFacturacion
		: [$response->RegistroRespuestaConsultaFactuSistemaFacturacion];

	foreach ($allInvoices as $invoice) {
		$counterpartyMatches = false;

		if (isset($invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario)) {
			$recipient = $invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario;

			if (isset($recipient->NIF) && $recipient->NIF === $counterpartyNif) {
				$counterpartyMatches = true;

				if ($counterpartyName && isset($recipient->NombreRazon)) {
					$counterpartyMatches = stripos($recipient->NombreRazon, $counterpartyName) !== false;
				}
			}
		}

		if ($counterpartyMatches) {
			$filteredByCounterparty[] = $invoice;
		}
	}

	if (!empty($filteredByCounterparty)) {
		$response->RegistroRespuestaConsultaFactuSistemaFacturacion = $filteredByCounterparty;
		$numFiltered = count($filteredByCounterparty);
		dol_syslog("VERIFACTU: Post-query filter by counterparty applied - $numFiltered invoice(s) found", LOG_INFO);
	} else {
		dol_syslog("VERIFACTU: WARNING - No invoices found for NIF '$counterpartyNif'", LOG_WARNING);
		$response->RegistroRespuestaConsultaFactuSistemaFacturacion = [];
		$response->ResultadoConsulta = 'SinDatos';
	}

	return $response;
}

/**
 * Queries a specific invoice in AEAT by number and date
 *
 * @param string $invoiceNumber Invoice number
 * @param string $invoiceDate Invoice date (format dd-mm-yyyy)
 * @return object|false Invoice data from AEAT or false if not exists/error
 */
function queryInvoiceInAEAT($invoiceNumber, $invoiceDate)
{
	return consultarFacturaEnAEAT($invoiceNumber, $invoiceDate);
}

/**
 * Queries a specific invoice in AEAT by number and date
 *
 * @param string $numeroFactura Invoice number
 * @param string $fechaExpedicion Invoice date (format dd-mm-yyyy)
 * @return object|false Invoice data from AEAT or false if not exists/error
 */
function consultarFacturaEnAEAT($numeroFactura, $fechaExpedicion)
{
	global $conf;

	dol_syslog("VERIFACTU: Querying invoice in AEAT - Number: $numeroFactura, Date: $fechaExpedicion", LOG_INFO);

	$issuerNif = $conf->global->VERIFACTU_HOLDER_NIF ?? '';

	$filtroConsulta = array(
		'IDFactura' => array(
			'NumSerieFacturaEmisor' => $numeroFactura,
			'FechaExpedicionFacturaEmisor' => $fechaExpedicion,
			'NIF' => $issuerNif
		)
	);

	$response = execVERIFACTUQuery($filtroConsulta);

	if ($response && isset($response->ResultadoConsulta) && $response->ResultadoConsulta === 'ConDatos') {
		$invoices = $response->RegistroRespuestaConsultaFactuSistemaFacturacion ?? null;

		if ($invoices) {
			// Can be array or single object
			$invoice = is_array($invoices) ? $invoices[0] : $invoices;
			dol_syslog("VERIFACTU: Invoice found in AEAT", LOG_INFO);
			return $invoice;
		}
	}

	dol_syslog("VERIFACTU: Invoice not found in AEAT or query error", LOG_WARNING);
	return false;
}

/**
 * Extracts total amount from an AEAT invoice
 *
 * @param object $aeatInvoice Invoice data from AEAT
 * @return float|null Total amount or null if cannot extract
 */
function extractAEATTotalAmount($aeatInvoice)
{
	return extraerImporteTotalAEAT($aeatInvoice);
}

/**
 * Extracts total amount from an AEAT invoice
 *
 * @param object $facturaAEAT Invoice data from AEAT
 * @return float|null Total amount or null if cannot extract
 */
function extraerImporteTotalAEAT($facturaAEAT)
{
	if (!$facturaAEAT || !isset($facturaAEAT->DatosRegistroFacturacion)) {
		dol_syslog("VERIFACTU: extractAEATTotalAmount - No DatosRegistroFacturacion", LOG_WARNING);
		return null;
	}

	$data = $facturaAEAT->DatosRegistroFacturacion;

	// Option 1: ImporteTotal directly in DatosRegistroFacturacion (real AEAT structure)
	if (isset($data->ImporteTotal)) {
		dol_syslog("VERIFACTU: extractAEATTotalAmount - ImporteTotal found: " . $data->ImporteTotal, LOG_DEBUG);
		return (float) $data->ImporteTotal;
	}

	// Option 2: ImporteTotal inside DatosFactura (alternative structure)
	if (isset($data->DatosFactura->ImporteTotal)) {
		dol_syslog("VERIFACTU: extractAEATTotalAmount - ImporteTotal in DatosFactura: " . $data->DatosFactura->ImporteTotal, LOG_DEBUG);
		return (float) $data->DatosFactura->ImporteTotal;
	}

	// Option 3: Calculate from Desglose (BaseImponible + CuotaRepercutida)
	if (isset($data->Desglose)) {
		$total = 0;
		$breakdown = $data->Desglose;

		if (isset($breakdown->DetalleDesglose)) {
			$details = is_array($breakdown->DetalleDesglose) ? $breakdown->DetalleDesglose : [$breakdown->DetalleDesglose];

			foreach ($details as $detail) {
				$base = (float) ($detail->BaseImponibleOImporteNoSujeto ?? $detail->BaseImponibleOimporteNoSujeto ?? 0);
				$tax = (float) ($detail->CuotaRepercutida ?? 0);
				$total += $base + $tax;
			}
		}

		if ($total > 0) {
			dol_syslog("VERIFACTU: extractAEATTotalAmount - Total calculated from breakdown: " . $total, LOG_DEBUG);
			return $total;
		}
	}

	dol_syslog("VERIFACTU: extractAEATTotalAmount - Could not extract total amount", LOG_WARNING);
	return null;
}

/**
 * Compares amounts between a Dolibarr invoice and AEAT data
 *
 * @param Facture $facture Dolibarr invoice
 * @param object $aeatInvoice Invoice data from AEAT
 * @param float $tolerance Tolerance for rounding differences (default 0.01)
 * @return array ['coinciden' => bool, 'importe_dolibarr' => float, 'importe_aeat' => float, 'diferencia' => float]
 */
function compareInvoiceAmounts($facture, $aeatInvoice, $tolerance = 0.01)
{
	return compararImportesFactura($facture, $aeatInvoice, $tolerance);
}

/**
 * Compares amounts between a Dolibarr invoice and AEAT data
 *
 * @param Facture $facture Dolibarr invoice
 * @param object $facturaAEAT Invoice data from AEAT
 * @param float $tolerancia Tolerance for rounding differences (default 0.01)
 * @return array ['coinciden' => bool, 'importe_dolibarr' => float, 'importe_aeat' => float, 'diferencia' => float]
 */
function compararImportesFactura($facture, $facturaAEAT, $tolerancia = 0.01)
{
	$dolibarrAmount = getVerifactuImporteTotal($facture);
	$aeatAmount = extraerImporteTotalAEAT($facturaAEAT);

	if ($aeatAmount === null) {
		dol_syslog("VERIFACTU: Could not extract AEAT amount for comparison", LOG_WARNING);
		return array(
			'coinciden' => false,
			'match' => false,  // English alias
			'importe_dolibarr' => $dolibarrAmount,
			'dolibarr_amount' => $dolibarrAmount,  // English alias
			'importe_aeat' => null,
			'aeat_amount' => null,  // English alias
			'diferencia' => null,
			'difference' => null,  // English alias
			'error' => 'Could not extract AEAT amount'
		);
	}

	// For credit notes, Dolibarr stores negative amounts but AEAT stores positive
	// Compare absolute values so -1.21 matches 1.21
	$difference = abs(abs($dolibarrAmount) - abs($aeatAmount));
	$match = $difference <= $tolerancia;

	dol_syslog("VERIFACTU: Amount comparison - Dolibarr: $dolibarrAmount, AEAT: $aeatAmount, Difference: $difference, Match: " . ($match ? 'YES' : 'NO'), LOG_INFO);

	return array(
		'coinciden' => $match,
		'match' => $match,  // English alias
		'importe_dolibarr' => $dolibarrAmount,
		'dolibarr_amount' => $dolibarrAmount,  // English alias
		'importe_aeat' => $aeatAmount,
		'aeat_amount' => $aeatAmount,  // English alias
		'diferencia' => $difference,
		'difference' => $difference  // English alias
	);
}

/**
 * Pre-check: Verifies if an invoice already exists in AEAT before sending
 *
 * This function is used before a Create operation to avoid error 3000 (duplicate).
 * Queries AEAT and verifies if invoice exists and with what amounts.
 *
 * @param Facture $facture Dolibarr invoice
 * @return array ['exists' => bool, 'amounts_match' => bool, 'importe_dolibarr' => float, 'importe_aeat' => float|null, 'huella' => string|null]
 */
function preCheckInvoiceInAEAT($facture)
{
	global $conf;

	dol_syslog("VERIFACTU: PRE-CHECK - Verifying if invoice {$facture->ref} exists in AEAT", LOG_DEBUG);

	// Get invoice date in AEAT format (dd-mm-yyyy)
	$invoiceDate = date('d-m-Y', $facture->date);

	// Query invoice in AEAT
	$aeatInvoice = consultarFacturaEnAEAT($facture->ref, $invoiceDate);

	if (!$aeatInvoice) {
		// Invoice does NOT exist in AEAT - can be sent
		dol_syslog("VERIFACTU: PRE-CHECK - Invoice {$facture->ref} does NOT exist in AEAT", LOG_DEBUG);
		return array(
			'exists' => false,
			'amounts_match' => false,
			'importe_dolibarr' => getVerifactuImporteTotal($facture),
			'importe_aeat' => null,
			'huella' => null
		);
	}

	// Invoice exists in AEAT - compare amounts
	$comparison = compararImportesFactura($facture, $aeatInvoice);

	// Extract fingerprint if available
	$fingerprint = null;
	if (isset($aeatInvoice->DatosRegistroFacturacion->Huella)) {
		$fingerprint = $aeatInvoice->DatosRegistroFacturacion->Huella;
	}

	dol_syslog("VERIFACTU: PRE-CHECK - Invoice {$facture->ref} exists in AEAT. Amounts match: " . ($comparison['coinciden'] ? 'YES' : 'NO'), LOG_DEBUG);

	return array(
		'exists' => true,
		'amounts_match' => $comparison['coinciden'],
		'importe_dolibarr' => $comparison['importe_dolibarr'],
		'importe_aeat' => $comparison['importe_aeat'],
		'huella' => $fingerprint
	);
}

/**
 * Verifies invoice status in AEAT and reconciles with Dolibarr
 *
 * This function queries AEAT to verify actual invoice status,
 * compares amounts and updates status in Dolibarr if appropriate.
 *
 * @param Facture $facture Dolibarr invoice
 * @return array ['success' => bool, 'message' => string, 'action' => string]
 */
function verifyInvoiceStatusInAEAT($facture)
{
	return verificarEstadoFacturaAEAT($facture);
}

/**
 * Verifies invoice status in AEAT and reconciles with Dolibarr
 *
 * @param Facture $facture Dolibarr invoice
 * @return array ['success' => bool, 'message' => string, 'action' => string]
 */
function verificarEstadoFacturaAEAT($facture)
{
	global $langs, $conf, $db;

	$langs->load("verifactu@verifactu");

	dol_syslog("VERIFACTU: Starting status verification in AEAT for invoice {$facture->ref}", LOG_INFO);

	// Get invoice date in AEAT format (dd-mm-yyyy)
	$invoiceDate = date('d-m-Y', $facture->date);

	// Query invoice in AEAT
	$aeatInvoice = consultarFacturaEnAEAT($facture->ref, $invoiceDate);

	if (!$aeatInvoice) {
		// Invoice doesn't exist in AEAT - remove ALL VeriFactu marks
		// Leave invoice as if never processed by module
		dol_syslog("VERIFACTU: Invoice {$facture->ref} doesn't exist in AEAT - removing all VeriFactu marks", LOG_INFO);

		// Clear ALL VeriFactu fields - leave completely empty
		$facture->array_options['options_verifactu_estado'] = '';
		$facture->array_options['options_verifactu_error'] = '';
		$facture->array_options['options_verifactu_ultima_salida'] = '';
		$facture->array_options['options_verifactu_csv'] = '';
		$facture->array_options['options_verifactu_id_factura'] = '';
		$facture->array_options['options_verifactu_huella'] = '';
		$facture->array_options['options_verifactu_fecha_huella'] = '';
		$facture->array_options['options_verifactu_ultimafecha_modificacion'] = '';
		$facture->insertExtraFields();

		return array(
			'success' => true,
			'message' => $langs->trans('VERIFACTU_VERIFY_NOT_FOUND_CLEARED', $facture->ref),
			'action' => 'cleared'
		);
	}

	// Invoice exists in AEAT - compare amounts
	$comparison = compararImportesFactura($facture, $aeatInvoice);

	if ($comparison['coinciden']) {
		// Amounts match - update status to "Sent successfully"
		dol_syslog("VERIFACTU: Successful verification - Amounts match for invoice {$facture->ref}", LOG_INFO);

		// Build success badge
		$successBadge = '<div class="center"><span class="badge badge-status4 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_SEND') . '">' . $langs->trans('VERIFACTU_STATUS_SEND') . '</span></div>';

		// Extract additional data from AEAT if available
		$fingerprint = '';
		if (isset($aeatInvoice->DatosRegistroFacturacion->Huella)) {
			$fingerprint = $aeatInvoice->DatosRegistroFacturacion->Huella;
		}

		// Prepare data to save
		$successData = array(
			'estado' => $successBadge,
			'ultima_salida' => $langs->trans('VERIFACTU_VERIFY_SUCCESS_SUMMARY', price($comparison['importe_dolibarr']), price($comparison['importe_aeat'])),
			'fecha_modificacion' => dol_now()
		);

		if (!empty($fingerprint)) {
			$successData['huella'] = $fingerprint;
		}

		// Save using independent connection
		$saved = saveVerifactuSuccessData($facture, $successData);

		if (!$saved) {
			// Fallback: save directly
			$facture->array_options['options_verifactu_estado'] = $successBadge;
			$facture->array_options['options_verifactu_error'] = '';
			$facture->array_options['options_verifactu_ultima_salida'] = $successData['ultima_salida'];
			$facture->array_options['options_verifactu_ultimafecha_modificacion'] = dol_now();
			if (!empty($fingerprint)) {
				$facture->array_options['options_verifactu_huella'] = $fingerprint;
			}
			$facture->insertExtraFields();
		}

		return array(
			'success' => true,
			'message' => $langs->trans('VERIFACTU_VERIFY_STATUS_CORRECTED', $facture->ref, price($comparison['importe_dolibarr'])),
			'action' => 'corrected'
		);
	} else {
		// Amounts DON'T match
		dol_syslog("VERIFACTU: Verification - Amounts DON'T match for invoice {$facture->ref}. Dolibarr: {$comparison['importe_dolibarr']}, AEAT: {$comparison['importe_aeat']}", LOG_WARNING);

		return array(
			'success' => false,
			'message' => $langs->trans(
				'VERIFACTU_VERIFY_AMOUNTS_DIFFER',
				$facture->ref,
				price($comparison['importe_dolibarr']),
				price($comparison['importe_aeat']),
				price($comparison['diferencia'])
			),
			'action' => 'amounts_differ',
			'comparacion' => $comparison
		);
	}
}

/**
 * Handles duplicate invoice case (error 3000) in AEAT
 * Queries AEAT, compares amounts and decides what to do
 *
 * @param Facture $facture Dolibarr invoice
 * @return array ['accion' => string, 'mensaje' => string, 'datos_aeat' => object|null, 'comparacion' => array|null]
 *               accion can be: 'marcar_enviada', 'preguntar_usuario', 'error'
 */
function handleDuplicateInvoice($facture)
{
	return manejarFacturaDuplicada($facture);
}

/**
 * Handles duplicate invoice case (error 3000) in AEAT
 *
 * @param Facture $facture Dolibarr invoice
 * @return array ['accion' => string, 'mensaje' => string, 'datos_aeat' => object|null, 'comparacion' => array|null]
 */
function manejarFacturaDuplicada($facture)
{
	global $langs, $conf;

	$langs->load("verifactu@verifactu");

	dol_syslog("VERIFACTU: Handling duplicate invoice (error 3000) for {$facture->ref}", LOG_INFO);

	// Get invoice date in AEAT format (dd-mm-yyyy)
	$invoiceDate = date('d-m-Y', $facture->date);

	// Query invoice in AEAT
	$aeatInvoice = consultarFacturaEnAEAT($facture->ref, $invoiceDate);

	if (!$aeatInvoice) {
		// Could not query - may be connection error or invoice doesn't really exist
		dol_syslog("VERIFACTU: Could not query duplicate invoice in AEAT", LOG_WARNING);
		return array(
			'accion' => 'error',
			'action' => 'error',  // English alias
			'mensaje' => $langs->trans('VERIFACTU_DUPLICATE_QUERY_ERROR'),
			'message' => $langs->trans('VERIFACTU_DUPLICATE_QUERY_ERROR'),  // English alias
			'datos_aeat' => null,
			'aeat_data' => null,  // English alias
			'comparacion' => null,
			'comparison' => null  // English alias
		);
	}

	// Compare amounts
	$comparison = compararImportesFactura($facture, $aeatInvoice);

	if ($comparison['coinciden']) {
		// Amounts match - mark as sent
		dol_syslog("VERIFACTU: Duplicate invoice with matching amounts - marking as sent", LOG_INFO);
		return array(
			'accion' => 'marcar_enviada',
			'action' => 'mark_sent',  // English alias
			'mensaje' => $langs->trans('VERIFACTU_DUPLICATE_AMOUNTS_MATCH', $facture->ref),
			'message' => $langs->trans('VERIFACTU_DUPLICATE_AMOUNTS_MATCH', $facture->ref),  // English alias
			'datos_aeat' => $aeatInvoice,
			'aeat_data' => $aeatInvoice,  // English alias
			'comparacion' => $comparison,
			'comparison' => $comparison  // English alias
		);
	} else {
		// Amounts DON'T match - ask user
		dol_syslog("VERIFACTU: Duplicate invoice with DIFFERENT amounts - requires user decision", LOG_WARNING);
		return array(
			'accion' => 'preguntar_usuario',
			'action' => 'ask_user',  // English alias
			'mensaje' => $langs->trans(
				'VERIFACTU_DUPLICATE_AMOUNTS_DIFFER',
				$facture->ref,
				price($comparison['importe_dolibarr']),
				price($comparison['importe_aeat']),
				price($comparison['diferencia'])
			),
			'message' => $langs->trans(
				'VERIFACTU_DUPLICATE_AMOUNTS_DIFFER',
				$facture->ref,
				price($comparison['importe_dolibarr']),
				price($comparison['importe_aeat']),
				price($comparison['diferencia'])
			),  // English alias
			'datos_aeat' => $aeatInvoice,
			'aeat_data' => $aeatInvoice,  // English alias
			'comparacion' => $comparison,
			'comparison' => $comparison  // English alias
		);
	}
}
