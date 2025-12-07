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
 * \file    verifactu/lib/functions/functions.cancellation.php
 * \ingroup verifactu
 * \brief   Invoice cancellation functions for VeriFactu
 */

use Sietekas\Verifactu\VerifactuInvoiceCancel;

/**
 * Handles invoice cancellation in VeriFactu
 *
 * @param VerifactuManager $manager Manager instance
 * @param Facture $facture Invoice object
 * @param array $certOptions Certificate options
 * @param string $issuerNif Issuer NIF
 * @param string $issuerName Issuer name
 * @param string $cancellationType Type of cancellation: 'normal', 'rechazo_previo', 'sin_registro_previo'
 * @return bool|object True/Response if success, false if error
 */
function handleInvoiceCancellation($manager, Facture $facture, $certOptions, $issuerNif, $issuerName, $cancellationType = 'normal')
{
	global $langs;

	// Get invoice data for cancellation
	$invoiceNumberToCancel = $facture->ref;
	$invoiceDateToCancel = date('d-m-Y', $facture->date);
	$issuerNifToCancel = $issuerNif;

	// Create cancellation according to requested type
	$cancellation = createCancellationByType($cancellationType, $invoiceNumberToCancel, $invoiceDateToCancel, $issuerNifToCancel, $issuerNif, $issuerName);

	if (!$cancellation) {
		dol_syslog("VERIFACTU: Invalid cancellation type: " . $cancellationType, LOG_ERR);
		return false;
	}

	// Configure chaining
	$previousHash = getLastInvoiceHash();
	if ($previousHash) {
		$cancellation->setChainLink(
			$issuerNif,
			$previousHash['numero'],
			$previousHash['fecha'],
			$previousHash['hash']
		);
		dol_syslog("VERIFACTU: Chaining configured with previous invoice: " . $previousHash['numero'], LOG_DEBUG);
	}

	// Send cancellation
	$response = $manager->sendCancellation($cancellation, $certOptions);

	// Process response
	return processCancellationResponse($response, $facture, $invoiceNumberToCancel, $langs);
}

/**
 * Creates cancellation object according to type
 *
 * @param string $cancellationType Type of cancellation
 * @param string $invoiceNumber Invoice number
 * @param string $invoiceDate Invoice date
 * @param string $issuerNifToCancel NIF of the issuer of invoice to cancel
 * @param string $issuerNif Current issuer NIF
 * @param string $issuerName Current issuer name
 * @return VerifactuInvoiceCancel|false Cancellation object or false if invalid type
 */
function createCancellationByType($cancellationType, $invoiceNumber, $invoiceDate, $issuerNifToCancel, $issuerNif, $issuerName)
{
	switch ($cancellationType) {
		case 'normal':
			dol_syslog("VERIFACTU: Creating normal cancellation for invoice " . $invoiceNumber, LOG_INFO);
			return VerifactuInvoiceCancel::createNormal(
				$invoiceNumber,
				$invoiceDate,
				$issuerNifToCancel,
				$issuerNif,
				$issuerName
			);

		case 'rechazo_previo':
			dol_syslog("VERIFACTU: Creating prior rejection cancellation for invoice " . $invoiceNumber, LOG_INFO);
			return VerifactuInvoiceCancel::createForPreviousRejection(
				$invoiceNumber,
				$invoiceDate,
				$issuerNifToCancel,
				$issuerNif,
				$issuerName
			);

		case 'sin_registro_previo':
			dol_syslog("VERIFACTU: Creating cancellation without prior record for invoice " . $invoiceNumber, LOG_INFO);
			return VerifactuInvoiceCancel::createWithoutPreviousRecord(
				$invoiceNumber,
				$invoiceDate,
				$issuerNifToCancel,
				$issuerNif,
				$issuerName
			);

		default:
			return false;
	}
}

/**
 * Processes AEAT cancellation response
 *
 * @param object $response AEAT response
 * @param Facture $facture Invoice
 * @param string $invoiceNumber Invoice number
 * @param Translate $langs Languages
 * @return bool|object True/Response if success, false if error
 */
function processCancellationResponse($response, $facture, $invoiceNumber, $langs)
{
	if ($response) {
		if (isset($response->EstadoEnvio) && in_array($response->EstadoEnvio, ['Correcto', 'ParcialmenteCorrecto'])) {
			return processCancellationSuccess($response, $facture, $invoiceNumber, $langs);
		} else {
			return processCancellationError($response, $facture, $invoiceNumber, $langs);
		}
	} else {
		// No response
		dol_syslog("VERIFACTU: No response received from server for invoice cancellation " . $invoiceNumber, LOG_ERR);

		$facture->array_options['options_verifactu_error'] = 'No response received from server';
		$facture->array_options['options_verifactu_estado'] = 'Error';
		$facture->array_options['options_verifactu_ultimafecha_modificacion'] = date('Y-m-d H:i:s');
		$facture->insertExtraFields();

		return false;
	}
}

/**
 * Processes a successful cancellation response
 *
 * @param object $response AEAT response
 * @param Facture $facture Invoice
 * @param string $invoiceNumber Invoice number
 * @param Translate $langs Languages
 * @return object Response
 */
function processCancellationSuccess($response, $facture, $invoiceNumber, $langs)
{
	$statusMessage = $response->EstadoEnvio === 'Correcto' ? 'successfully' : 'with partial success';
	dol_syslog("VERIFACTU: Cancellation sent {$statusMessage} for invoice " . $invoiceNumber, LOG_INFO);

	// Cancelled success badge
	$cancelledBadge = '<div class="center"><span class="badge badge-status6 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_ANULADA') . '">' . $langs->trans('VERIFACTU_STATUS_ANULADA') . '</span></div>';
	$facture->array_options['options_verifactu_estado'] = $cancelledBadge;
	$facture->array_options['options_verifactu_csv_factura'] = $response->CSV ?? '';
	$facture->array_options['options_verifactu_error'] = '';

	// Update response data if available
	if (isset($response->RespuestaLinea)) {
		$respuestaLinea = $response->RespuestaLinea;

		if (isset($respuestaLinea->IDFactura)) {
			$invoiceIdData = array(
				'IDEmisorFactura' => $respuestaLinea->IDFactura->IDEmisorFactura ?? '',
				'NumSerieFactura' => $respuestaLinea->IDFactura->NumSerieFactura ?? '',
				'FechaExpedicionFactura' => $respuestaLinea->IDFactura->FechaExpedicionFactura ?? ''
			);
			$facture->array_options['options_verifactu_id_factura'] = json_encode($invoiceIdData);
		}

		if (isset($respuestaLinea->Operacion->TipoOperacion)) {
			$facture->array_options['options_verifactu_tipo_operacion'] = $respuestaLinea->Operacion->TipoOperacion;
		}
	}

	if (isset($response->DatosPresentacion)) {
		$facture->array_options['options_verifactu_fecha_hora_generacion'] = $response->DatosPresentacion->TimestampPresentacion ?? '';
	}

	$facture->array_options['options_verifactu_ultimafecha_modificacion'] = date('Y-m-d H:i:s');
	$facture->array_options['options_verifactu_ultima_salida'] = json_encode($response);

	$facture->insertExtraFields();

	dol_syslog("VERIFACTU: Extra fields updated correctly for cancelled invoice " . $invoiceNumber, LOG_INFO);

	return $response;
}

/**
 * Processes a cancellation error response
 *
 * @param object $response AEAT response
 * @param Facture $facture Invoice
 * @param string $invoiceNumber Invoice number
 * @param Translate $langs Languages
 * @return bool|object False if error, Response if already cancelled
 */
function processCancellationError($response, $facture, $invoiceNumber, $langs)
{
	$errorMsg = "Error in VERIFACTU cancellation";
	$finalStatus = 'Error';

	if (isset($response->EstadoEnvio)) {
		$errorMsg .= " - Status: " . $response->EstadoEnvio;
	}

	if (isset($response->RespuestaLinea)) {
		$respuestaLinea = $response->RespuestaLinea;

		if (isset($respuestaLinea->IDFactura)) {
			$invoiceIdData = array(
				'IDEmisorFactura' => $respuestaLinea->IDFactura->IDEmisorFactura ?? '',
				'NumSerieFactura' => $respuestaLinea->IDFactura->NumSerieFactura ?? '',
				'FechaExpedicionFactura' => $respuestaLinea->IDFactura->FechaExpedicionFactura ?? ''
			);
			$facture->array_options['options_verifactu_id_factura'] = json_encode($invoiceIdData);
		}

		if (isset($respuestaLinea->EstadoRegistro)) {
			$recordStatus = $respuestaLinea->EstadoRegistro;
			$errorMsg .= " - Record Status: " . $recordStatus;

			if ($recordStatus === 'Incorrecto') {
				$finalStatus = 'Incorrecto';
			} else if ($recordStatus === 'Anulada') {
				$finalStatus = 'Anulada';
			}
		}

		if (isset($respuestaLinea->CodigoErrorRegistro)) {
			$errorMsg .= " - Code: " . $respuestaLinea->CodigoErrorRegistro;
		}
		if (isset($respuestaLinea->DescripcionErrorRegistro)) {
			$errorMsg .= " - Description: " . $respuestaLinea->DescripcionErrorRegistro;
		}

		// Handle duplicate record specific case
		if (isset($respuestaLinea->RegistroDuplicado)) {
			$duplicateRecord = $respuestaLinea->RegistroDuplicado;
			$errorMsg .= " - Duplicate record";
			if (isset($duplicateRecord->IdPeticionRegistroDuplicado)) {
				$errorMsg .= " (ID: " . $duplicateRecord->IdPeticionRegistroDuplicado . ")";
			}
			if (isset($duplicateRecord->EstadoRegistroDuplicado)) {
				$errorMsg .= " - Duplicate status: " . $duplicateRecord->EstadoRegistroDuplicado;
				if ($duplicateRecord->EstadoRegistroDuplicado === 'Anulada') {
					$finalStatus = 'Anulada';
				}
			}
		}

		if (isset($respuestaLinea->Operacion->TipoOperacion)) {
			$facture->array_options['options_verifactu_tipo_operacion'] = $respuestaLinea->Operacion->TipoOperacion;
		}
	}

	// Check errors at general response level (fallback)
	if (!isset($response->RespuestaLinea)) {
		if (isset($response->CodigoErrorRegistro)) {
			$errorMsg .= " - Code: " . $response->CodigoErrorRegistro;
		}
		if (isset($response->DescripcionErrorRegistro)) {
			$errorMsg .= " - Description: " . $response->DescripcionErrorRegistro;
		}
	}

	dol_syslog("VERIFACTU: " . $errorMsg, LOG_ERR);

	// Prepare error data to save with independent connection
	$errorData = array(
		'estado' => $finalStatus,
		'error' => $errorMsg,
		'ultima_salida' => json_encode($response),
		'fecha_modificacion' => date('Y-m-d H:i:s')
	);

	if (isset($response->DatosPresentacion)) {
		$errorData['fecha_hora_generacion'] = $response->DatosPresentacion->TimestampPresentacion ?? '';
	}

	$saved = saveVerifactuErrorData($facture, $errorData);
	if (!$saved) {
		dol_syslog("VERIFACTU: Fallback to insertExtraFields() for response errors", LOG_WARNING);
		$facture->array_options['options_verifactu_error'] = $errorMsg;
		$facture->array_options['options_verifactu_estado'] = $finalStatus;
		$facture->array_options['options_verifactu_ultima_salida'] = json_encode($response);
		$facture->array_options['options_verifactu_ultimafecha_modificacion'] = date('Y-m-d H:i:s');
		if (isset($response->DatosPresentacion)) {
			$facture->array_options['options_verifactu_fecha_hora_generacion'] = $response->DatosPresentacion->TimestampPresentacion ?? '';
		}
		$facture->insertExtraFields();
	}

	// If duplicate record is already cancelled, consider this as success
	if ($finalStatus === 'Anulada') {
		$cancelledBadge = '<div class="center"><span class="badge badge-status6 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_ANULADA') . '">' . $langs->trans('VERIFACTU_STATUS_ANULADA') . '</span></div>';

		$cancellationData = array(
			'estado' => $cancelledBadge
		);

		$saved = saveVerifactuErrorData($facture, $cancellationData);
		if (!$saved) {
			dol_syslog("VERIFACTU: Fallback to insertExtraFields() for cancelled badge", LOG_WARNING);
			$facture->array_options['options_verifactu_estado'] = $cancelledBadge;
			$facture->insertExtraFields();
		}

		dol_syslog("VERIFACTU: Invoice was already cancelled - operation completed", LOG_INFO);
		return $response;
	}

	return false;
}
