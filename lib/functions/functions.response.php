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
 * \file    verifactu/lib/functions/functions.response.php
 * \ingroup verifactu
 * \brief   AEAT response processing functions for VeriFactu
 */

/**
 * Saves VeriFactu error data using an independent connection
 * to prevent data loss in case of transaction rollback
 *
 * @param Facture $facture Invoice object
 * @param array $errorData Array with error data
 * @return bool True if saved successfully, false otherwise
 */
function saveVerifactuErrorData(Facture $facture, $errorData)
{
	global $conf, $dolibarr_main_db_pass;

	try {
		// Create new independent DB connection to avoid rollback
		$dbType = $conf->db->type ? $conf->db->type : 'mysqli';
		$independentDb = getDoliDBInstance(
			$dbType,
			$conf->db->host,
			$conf->db->user,
			$dolibarr_main_db_pass,
			$conf->db->name,
			($conf->db->port ? $conf->db->port : 3306)
		);
		if (!$independentDb || $independentDb->error) {
			dol_syslog("VERIFACTU: Could not create independent DB connection", LOG_ERR);
			return false;
		}

		// For PostgreSQL, don't close connection as it may be the same as main
		$canCloseConnection = ($dbType !== 'pgsql');

		// Create new invoice instance with independent connection
		$factureIndependent = new Facture($independentDb);
		$result = $factureIndependent->fetch($facture->id);

		if ($result <= 0) {
			dol_syslog("VERIFACTU: Could not load invoice with independent connection: " . $facture->id, LOG_ERR);
			if ($canCloseConnection) {
				$independentDb->close();
			}
			return false;
		}

		// Update fields with error data
		if (isset($errorData['estado'])) {
			$factureIndependent->array_options['options_verifactu_estado'] = $errorData['estado'];
		}
		if (isset($errorData['error'])) {
			$factureIndependent->array_options['options_verifactu_error'] = $errorData['error'];
		}
		if (isset($errorData['ultima_salida'])) {
			$factureIndependent->array_options['options_verifactu_ultima_salida'] = $errorData['ultima_salida'];
		}
		if (isset($errorData['fecha_modificacion'])) {
			$factureIndependent->array_options['options_verifactu_ultimafecha_modificacion'] = $errorData['fecha_modificacion'];
		}
		if (isset($errorData['csv_factura'])) {
			$factureIndependent->array_options['options_verifactu_csv_factura'] = $errorData['csv_factura'];
		}
		if (isset($errorData['huella'])) {
			$factureIndependent->array_options['options_verifactu_huella'] = $errorData['huella'];
		}
		if (isset($errorData['fecha_hora_generacion'])) {
			$factureIndependent->array_options['options_verifactu_fecha_hora_generacion'] = $errorData['fecha_hora_generacion'];
		}

		// Save extrafields using independent connection
		$result = $factureIndependent->insertExtraFields();

		if ($result > 0) {
			dol_syslog("VERIFACTU: Error data saved successfully in independent DB for invoice " . $facture->ref, LOG_DEBUG);
			if ($canCloseConnection) {
				$independentDb->close();
			}
			return true;
		} else {
			dol_syslog("VERIFACTU: Error saving extrafields with independent connection: " . implode(', ', $factureIndependent->errors), LOG_ERR);
			if ($canCloseConnection) {
				$independentDb->close();
			}
			return false;
		}
	} catch (Exception $e) {
		dol_syslog("VERIFACTU: Exception saving error data: " . $e->getMessage(), LOG_ERR);
		if (isset($independentDb) && isset($canCloseConnection) && $canCloseConnection) {
			$independentDb->close();
		}
		return false;
	}
}

/**
 * Saves VeriFactu success data using an independent connection
 * to prevent data loss in case of transaction rollback
 *
 * @param Facture $facture Invoice object
 * @param array $successData Array with success data
 * @return bool True if saved successfully, false otherwise
 */
function saveVerifactuSuccessData(Facture $facture, $successData)
{
	global $conf, $dolibarr_main_db_pass;

	try {
		// Create new independent DB connection to avoid rollback
		$dbType = $conf->db->type ? $conf->db->type : 'mysqli';
		$independentDb = getDoliDBInstance(
			$dbType,
			$conf->db->host,
			$conf->db->user,
			$dolibarr_main_db_pass,
			$conf->db->name,
			($conf->db->port ? $conf->db->port : 3306)
		);
		if (!$independentDb || $independentDb->error) {
			dol_syslog("VERIFACTU: Could not create independent DB connection for success", LOG_ERR);
			return false;
		}

		// For PostgreSQL, don't close connection as it may be the same as main
		$canCloseConnection = ($dbType !== 'pgsql');

		// Create new invoice instance with independent connection
		$factureIndependent = new Facture($independentDb);
		$result = $factureIndependent->fetch($facture->id);

		if ($result <= 0) {
			dol_syslog("VERIFACTU: Could not load invoice with independent connection: " . $facture->id, LOG_ERR);
			if ($canCloseConnection) {
				$independentDb->close();
			}
			return false;
		}

		// Update fields with success data
		if (isset($successData['estado'])) {
			$factureIndependent->array_options['options_verifactu_estado'] = $successData['estado'];
		}
		if (isset($successData['csv_factura'])) {
			$factureIndependent->array_options['options_verifactu_csv_factura'] = $successData['csv_factura'];
		}
		if (isset($successData['payload'])) {
			$factureIndependent->array_options['options_verifactu_payload'] = $successData['payload'];
		}
		if (isset($successData['entorno'])) {
			$factureIndependent->array_options['options_verifactu_entorno'] = $successData['entorno'];
		}
		if (isset($successData['modo'])) {
			$factureIndependent->array_options['options_verifactu_modo'] = $successData['modo'];
		}
		if (isset($successData['id_factura'])) {
			$factureIndependent->array_options['options_verifactu_id_factura'] = $successData['id_factura'];
		}
		if (isset($successData['fecha_factura'])) {
			$factureIndependent->array_options['options_verifactu_fecha_factura'] = $successData['fecha_factura'];
		}
		if (isset($successData['ultima_salida'])) {
			$factureIndependent->array_options['options_verifactu_ultima_salida'] = $successData['ultima_salida'];
		}
		if (isset($successData['fecha_modificacion'])) {
			$factureIndependent->array_options['options_verifactu_ultimafecha_modificacion'] = $successData['fecha_modificacion'];
		}
		if (isset($successData['huella'])) {
			$factureIndependent->array_options['options_verifactu_huella'] = $successData['huella'];
		}
		if (isset($successData['fecha_hora_generacion'])) {
			$factureIndependent->array_options['options_verifactu_fecha_hora_generacion'] = $successData['fecha_hora_generacion'];
		}
		// Clear error field
		$factureIndependent->array_options['options_verifactu_error'] = '';

		// Save extrafields using independent connection
		$result = $factureIndependent->insertExtraFields();

		if ($result > 0) {
			dol_syslog("VERIFACTU: Success data saved successfully in independent DB for invoice " . $facture->ref, LOG_DEBUG);
			if ($canCloseConnection) {
				$independentDb->close();
			}
			return true;
		} else {
			dol_syslog("VERIFACTU: Error saving success extrafields with independent connection: " . implode(', ', $factureIndependent->errors), LOG_ERR);
			if ($canCloseConnection) {
				$independentDb->close();
			}
			return false;
		}
	} catch (Exception $e) {
		dol_syslog("VERIFACTU: Exception saving success data: " . $e->getMessage(), LOG_ERR);
		if (isset($independentDb) && isset($canCloseConnection) && $canCloseConnection) {
			$independentDb->close();
		}
		return false;
	}
}

/**
 * Generates a status badge HTML for VeriFactu
 *
 * @param string $status Status code (SEND, ERROR, NOT_SEND, etc.)
 * @param string $statusClass CSS class for the badge (badge-status4=green, badge-status8=red, etc.)
 * @return string HTML badge
 */
function buildVerifactuStatusBadge($status, $statusClass = 'badge-status8')
{
	global $langs;
	$langs->load("verifactu@verifactu");

	$statusText = $langs->trans('VERIFACTU_STATUS_' . $status);
	return '<div class="center"><span class="badge ' . $statusClass . ' classfortooltip badge-status" attr-status="' . $statusText . '">' . $statusText . '</span></div>';
}

/**
 * Extracts relevant information from an AEAT response
 *
 * @param object $response AEAT response
 * @return array Array with extracted data
 */
function extractAEATResponseData($response)
{
	$data = [
		'submission_status' => $response->EstadoEnvio ?? 'Unknown',
		'estado_envio' => $response->EstadoEnvio ?? 'Unknown', // Backward compatibility
		'csv' => $response->CSV ?? '',
		'timestamp' => $response->DatosPresentacion->TimestampPresentacion ?? '',
		'record_status' => '',
		'estado_registro' => '', // Backward compatibility
		'error_code' => '',
		'codigo_error' => '', // Backward compatibility
		'error_description' => '',
		'descripcion_error' => '', // Backward compatibility
		'invoice_id' => null,
		'id_factura' => null // Backward compatibility
	];

	if (isset($response->RespuestaLinea)) {
		$linea = $response->RespuestaLinea;
		$data['record_status'] = $linea->EstadoRegistro ?? '';
		$data['estado_registro'] = $data['record_status']; // Backward compatibility
		$data['error_code'] = $linea->CodigoErrorRegistro ?? '';
		$data['codigo_error'] = $data['error_code']; // Backward compatibility
		$data['error_description'] = $linea->DescripcionErrorRegistro ?? '';
		$data['descripcion_error'] = $data['error_description']; // Backward compatibility

		if (isset($linea->IDFactura)) {
			$invoiceId = [
				'IDEmisorFactura' => $linea->IDFactura->IDEmisorFactura ?? '',
				'NumSerieFactura' => $linea->IDFactura->NumSerieFactura ?? '',
				'FechaExpedicionFactura' => $linea->IDFactura->FechaExpedicionFactura ?? ''
			];
			$data['invoice_id'] = $invoiceId;
			$data['id_factura'] = $invoiceId; // Backward compatibility
		}
	}

	return $data;
}

/**
 * Builds an output summary to save in extrafields
 *
 * @param object $response AEAT response
 * @param bool $isError Whether this is an error response
 * @return string Formatted summary
 */
function buildVerifactuOutputSummary($response, $isError = false)
{
	$data = extractAEATResponseData($response);

	$summary = "Status: " . $data['submission_status'];

	if (!empty($data['csv'])) {
		$summary .= " | CSV: " . $data['csv'];
	}
	if (!empty($data['timestamp'])) {
		$summary .= " | Sent: " . $data['timestamp'];
	}
	if (!empty($data['record_status'])) {
		$summary .= " | Record: " . $data['record_status'];
	}
	if (!empty($data['error_code'])) {
		$summary .= " | Error Code: " . $data['error_code'];
	}
	if (!empty($data['error_description'])) {
		$summary .= " | Error: " . $data['error_description'];
	}

	return $summary;
}
