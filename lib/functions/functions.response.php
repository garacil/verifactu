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
 * \file    verifactu/lib/functions/functions.response.php
 * \ingroup verifactu
 * \brief   AEAT response processing functions for VeriFactu
 */

/**
 * Stores the instant from which AEAT accepts another submission.
 *
 * Every response carries TiempoEsperaEnvio (RespuestaSuministro.xsd), the number
 * of seconds AEAT asks the system to wait before submitting again.
 *
 * @param object $response AEAT response
 * @return int Number of seconds requested by AEAT
 */
function registerAEATWaitTime($response)
{
	global $conf, $db;

	$waitSeconds = is_object($response) ? (int) ($response->TiempoEsperaEnvio ?? 0) : 0;
	if ($waitSeconds <= 0) {
		return 0;
	}
	if (!function_exists('dolibarr_set_const')) {
		require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
	}
	dolibarr_set_const($db, 'VERIFACTU_NEXT_SEND_AT', (string) (dol_now() + $waitSeconds), 'chaine', 0, '', $conf->entity);

	return $waitSeconds;
}

/**
 * Returns the AEAT throttling delay still pending for the active entity.
 *
 * @return int Remaining seconds, zero when a submission is allowed right away
 */
function getAEATWaitTimeRemaining()
{
	return max(0, getDolGlobalInt('VERIFACTU_NEXT_SEND_AT') - dol_now());
}

/**
 * Checks whether an AEAT response accepted every submitted record.
 *
 * RespuestaSuministro.xsd defines EstadoEnvio as Correcto, ParcialmenteCorrecto
 * or Incorrecto, and EstadoRegistro per line as Correcto, AceptadoConErrores or
 * Incorrecto. A globally partial response therefore carries both accepted and
 * rejected lines, and a rejected record must never be stored as sent.
 *
 * @param object $response AEAT response
 * @return bool True when every submitted record was accepted
 */
function isAEATResponseAccepted($response)
{
	if (!is_object($response) || empty($response->EstadoEnvio)) {
		return false;
	}
	if ($response->EstadoEnvio === 'Correcto') {
		return true;
	}
	if ($response->EstadoEnvio !== 'ParcialmenteCorrecto' || !isset($response->RespuestaLinea)) {
		return false;
	}

	$lines = is_array($response->RespuestaLinea) ? $response->RespuestaLinea : array($response->RespuestaLinea);
	if (empty($lines)) {
		return false;
	}
	foreach ($lines as $line) {
		$status = is_object($line) ? ($line->EstadoRegistro ?? '') : '';
		if (!in_array($status, array('Correcto', 'AceptadoConErrores'), true)) {
			return false;
		}
	}

	return true;
}

/**
 * Returns the SQL literal Dolibarr stores for an invoice extrafield value.
 *
 * Mirrors the conversion CommonObject::insertExtraFields() applies to the types
 * the VeriFactu fields use (varchar, text, select, date and datetime): an empty
 * value is stored as NULL, a date or datetime goes through $db->idate(), and
 * anything else is stored as an escaped string.
 *
 * @param object $db    Database handler (escape() and idate())
 * @param string $type  Extrafield type
 * @param mixed  $value Value to store
 * @return string SQL literal
 */
function getVerifactuExtraFieldSqlValue($db, $type, $value)
{
	if ($value === null || $value === '' || $value === false) {
		return 'null';
	}

	if ($type === 'date' || $type === 'datetime') {
		if (!is_numeric($value) || $value != intval($value)) {
			$value = strtotime((string) $value);
			if ($value === false) {
				return 'null';
			}
		}
		return "'" . $db->escape($db->idate((int) $value)) . "'";
	}

	return "'" . $db->escape((string) $value) . "'";
}

/**
 * Limits how long the independent connection waits for a row lock.
 *
 * The independent connection writes while the transaction of the main
 * connection is still open. When that transaction already holds the row to
 * write (a TakePOS refund, for instance, updates the invoice and its
 * extrafields before validating it), waiting cannot succeed: the main
 * connection only releases the row after the caller returns. Giving up quickly
 * lets the caller fall back to the main connection instead of waiting for
 * innodb_lock_wait_timeout (50 s by default) (issue #57).
 *
 * Only MySQL/MariaDB has innodb_lock_wait_timeout; other handlers are left alone.
 *
 * @param object $independentDb Independent database handler
 * @return void
 */
function limitVerifactuIndependentLockWait($independentDb)
{
	global $conf;

	if (!in_array($independentDb->type, array('mysql', 'mysqli'), true)) {
		return;
	}

	$seconds = max(1, (int) ($conf->global->VERIFACTU_INDEPENDENT_LOCK_WAIT_TIMEOUT ?? 1));
	if (!$independentDb->query("SET SESSION innodb_lock_wait_timeout = " . $seconds)) {
		dol_syslog("VERIFACTU: could not limit the lock wait of the independent connection: " . $independentDb->lasterror(), LOG_WARNING);
	}
}

/**
 * Writes VeriFactu fields of an invoice touching only its extrafields table.
 *
 * Since Dolibarr 23, CommonObject::insertExtraFields() and updateExtraField()
 * also run "UPDATE llx_facture SET fk_user_modif = ..." on the parent table.
 * The independent connection writes while the validation transaction of the
 * main connection still holds that row, so on MySQL/MariaDB it waited for
 * innodb_lock_wait_timeout (50 s by default) on every validation (issue #57).
 * Writing the extrafields row directly keeps the purpose of the independent
 * connection, an AEAT response that survives a rollback, without touching the
 * invoice row. Used for MySQL/MariaDB, see storeVerifactuIndependentData().
 *
 * Values are converted as CommonObject::insertExtraFields() does. Attributes
 * that are not invoice extrafields, separators and unexpected names are ignored.
 *
 * @param object     $db         Database handler to write with
 * @param int        $invoiceId  Invoice id
 * @param array      $values     attribute => value, attribute without the 'options_' prefix
 * @param array|null $attributes Invoice extrafields definition, as in
 *                               ExtraFields::$attributes['facture']; read when null
 * @return bool True when the values were stored
 */
function writeVerifactuInvoiceExtraFields($db, $invoiceId, array $values, $attributes = null)
{
	$invoiceId = (int) $invoiceId;
	if ($invoiceId <= 0) {
		return false;
	}

	if ($attributes === null) {
		if (!class_exists('ExtraFields')) {
			require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
		}
		$extrafields = new ExtraFields($db);
		$extrafields->fetch_name_optionals_label('facture');
		$attributes = $extrafields->attributes['facture'] ?? array();
	}
	$types = $attributes['type'] ?? array();

	$columns = array();
	foreach ($values as $attribute => $value) {
		if (!isset($types[$attribute]) || $types[$attribute] === 'separate' || !preg_match('/^[A-Za-z0-9_]+$/', $attribute)) {
			continue;
		}
		$columns[$attribute] = getVerifactuExtraFieldSqlValue($db, $types[$attribute], $value);
	}
	if (empty($columns)) {
		if (empty($values)) {
			return true;
		}
		// Nothing would be stored: let the caller fall back instead of reporting success
		dol_syslog("VERIFACTU: none of " . implode(', ', array_keys($values)) . " is an invoice extrafield", LOG_WARNING);
		return false;
	}

	// The invoice must be visible to this connection. One created inside a
	// transaction that is still open is not, and writing its extrafields row
	// from here would leave the row behind if that transaction rolled back.
	$resql = $db->query("SELECT rowid FROM " . MAIN_DB_PREFIX . "facture WHERE rowid = " . $invoiceId);
	if (!$resql) {
		return false;
	}
	$invoiceVisible = (bool) $db->fetch_object($resql);
	$db->free($resql);
	if (!$invoiceVisible) {
		dol_syslog("VERIFACTU: invoice " . $invoiceId . " is not visible to the independent connection yet", LOG_WARNING);
		return false;
	}

	$table = MAIN_DB_PREFIX . "facture_extrafields";
	$resql = $db->query("SELECT rowid FROM " . $table . " WHERE fk_object = " . $invoiceId);
	if (!$resql) {
		return false;
	}
	$rowExists = (bool) $db->fetch_object($resql);
	$db->free($resql);

	if ($rowExists) {
		$assignments = array();
		foreach ($columns as $column => $sqlValue) {
			$assignments[] = $column . " = " . $sqlValue;
		}
		$sql = "UPDATE " . $table . " SET " . implode(', ', $assignments) . " WHERE fk_object = " . $invoiceId;
	} else {
		// As insertExtraFields(): fields that are mandatory in other entities
		// get a default value, so their NOT NULL columns do not make it fail
		foreach (($attributes['mandatoryfieldsofotherentities'] ?? array()) as $attribute => $type) {
			if (isset($columns[$attribute]) || !preg_match('/^[A-Za-z0-9_]+$/', $attribute)) {
				continue;
			}
			$columns[$attribute] = in_array($type, array('int', 'double', 'price'), true) ? '0' : "''";
		}
		$sql = "INSERT INTO " . $table . " (fk_object, " . implode(', ', array_keys($columns)) . ")";
		$sql .= " VALUES (" . $invoiceId . ", " . implode(', ', $columns) . ")";
	}

	if (!$db->query($sql)) {
		dol_syslog("VERIFACTU: could not write the VeriFactu fields of invoice " . $invoiceId . ": " . $db->lasterror(), LOG_ERR);
		return false;
	}

	return true;
}

/**
 * Stores VeriFactu fields of an invoice through the independent connection.
 *
 * MySQL/MariaDB: the extrafields row is written directly, see
 * writeVerifactuInvoiceExtraFields(), and the session gives up on a held row
 * after a short wait, see limitVerifactuIndependentLockWait() (issue #57).
 *
 * Other databases keep the insertExtraFields() path used so far. On PostgreSQL
 * pg_connect() hands back the main connection for the same connection string,
 * so the independent handler shares the session of the main connection and
 * does not wait for its locks; its behaviour is left as it was.
 *
 * @param object  $independentDb Independent database handler
 * @param Facture $facture       Invoice
 * @param array   $values        attribute => value, attribute without the 'options_' prefix
 * @return bool True when the values were stored
 */
function storeVerifactuIndependentData($independentDb, Facture $facture, array $values)
{
	if (in_array($independentDb->type, array('mysql', 'mysqli'), true)) {
		limitVerifactuIndependentLockWait($independentDb);
		return writeVerifactuInvoiceExtraFields($independentDb, $facture->id, $values);
	}

	$factureIndependent = new Facture($independentDb);
	if ($factureIndependent->fetch($facture->id) <= 0) {
		dol_syslog("VERIFACTU: Could not load invoice with independent connection: " . $facture->id, LOG_ERR);
		return false;
	}
	foreach ($values as $attribute => $value) {
		$factureIndependent->array_options['options_' . $attribute] = $value;
	}
	if ($factureIndependent->insertExtraFields() > 0) {
		return true;
	}
	dol_syslog("VERIFACTU: Error saving extrafields with independent connection: " . implode(', ', $factureIndependent->errors), LOG_ERR);
	return false;
}

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

		// Fields to store, attribute => value
		$values = array();
		if (isset($errorData['estado'])) {
			$values['verifactu_estado'] = $errorData['estado'];
		}
		if (isset($errorData['error'])) {
			$values['verifactu_error'] = $errorData['error'];
		}
		if (isset($errorData['ultima_salida'])) {
			$values['verifactu_ultima_salida'] = $errorData['ultima_salida'];
		}
		if (isset($errorData['fecha_modificacion'])) {
			$values['verifactu_ultimafecha_modificacion'] = $errorData['fecha_modificacion'];
		}
		if (isset($errorData['csv_factura'])) {
			$values['verifactu_csv_factura'] = $errorData['csv_factura'];
		}
		if (isset($errorData['huella'])) {
			$values['verifactu_huella'] = $errorData['huella'];
		}
		if (isset($errorData['fecha_hora_generacion'])) {
			$values['verifactu_fecha_hora_generacion'] = $errorData['fecha_hora_generacion'];
		}

		// Save without waiting for the invoice row the validation holds (#57)
		$result = storeVerifactuIndependentData($independentDb, $facture, $values);

		if ($result) {
			dol_syslog("VERIFACTU: Error data saved successfully in independent DB for invoice " . $facture->ref, LOG_DEBUG);
			if ($canCloseConnection) {
				$independentDb->close();
			}
			return true;
		} else {
			dol_syslog("VERIFACTU: Error saving extrafields with independent connection for invoice " . $facture->ref . ": " . $independentDb->lasterror(), LOG_ERR);
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

		// Fields to store, attribute => value
		$values = array();
		if (isset($successData['estado'])) {
			$values['verifactu_estado'] = $successData['estado'];
		}
		if (isset($successData['csv_factura'])) {
			$values['verifactu_csv_factura'] = $successData['csv_factura'];
		}
		if (isset($successData['payload'])) {
			$values['verifactu_payload'] = $successData['payload'];
		}
		if (isset($successData['entorno'])) {
			$values['verifactu_entorno'] = $successData['entorno'];
		}
		if (isset($successData['modo'])) {
			$values['verifactu_modo'] = $successData['modo'];
		}
		if (isset($successData['id_factura'])) {
			$values['verifactu_id_factura'] = $successData['id_factura'];
		}
		if (isset($successData['fecha_factura'])) {
			$values['verifactu_fecha_factura'] = $successData['fecha_factura'];
		}
		if (isset($successData['ultima_salida'])) {
			$values['verifactu_ultima_salida'] = $successData['ultima_salida'];
		}
		if (isset($successData['fecha_modificacion'])) {
			$values['verifactu_ultimafecha_modificacion'] = $successData['fecha_modificacion'];
		}
		if (isset($successData['huella'])) {
			$values['verifactu_huella'] = $successData['huella'];
		}
		if (isset($successData['fecha_hora_generacion'])) {
			$values['verifactu_fecha_hora_generacion'] = $successData['fecha_hora_generacion'];
		}
		// Clear error field
		$values['verifactu_error'] = '';

		// Save without waiting for the invoice row the validation holds (#57)
		$result = storeVerifactuIndependentData($independentDb, $facture, $values);

		if ($result) {
			dol_syslog("VERIFACTU: Success data saved successfully in independent DB for invoice " . $facture->ref, LOG_DEBUG);
			if ($canCloseConnection) {
				$independentDb->close();
			}
			return true;
		} else {
			dol_syslog("VERIFACTU: Error saving success extrafields with independent connection for invoice " . $facture->ref . ": " . $independentDb->lasterror(), LOG_ERR);
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

/**
 * Builds the data persisted when a VeriFactu operation ends with an exception
 *
 * A record already accepted by AEAT (the invoice has a fingerprint) stays
 * registered whatever happens to a later operation: a failed cancellation or
 * correction must not erase the fingerprint or the CSV, nor replace the "sent"
 * status, otherwise the local chain loses a record that AEAT still holds and
 * the QR disappears from an invoice that is still registered. In that case only
 * the error fields are written. Without a fingerprint nothing was ever accepted,
 * so the error status is shown as before.
 *
 * The returned keys are the ones accepted by saveVerifactuErrorData(). The
 * fingerprint and the CSV are never among them.
 *
 * @param array  $currentOptions array_options of the invoice as stored (options_verifactu_*)
 * @param string $errorBadge     HTML badge of the error status
 * @param string $errorMessage   Message of the exception
 * @param int    $now            Timestamp of the failure
 * @return array                 Data to persist
 */
function buildVerifactuExceptionErrorData(array $currentOptions, $errorBadge, $errorMessage, $now)
{
	$errorData = array(
		'error' => 'Exception: ' . $errorMessage,
		'ultima_salida' => 'VeriFactu Exception: ' . $errorMessage,
		'fecha_modificacion' => $now,
	);

	$hasAcceptedRecord = !empty($currentOptions['options_verifactu_huella']);
	if (!$hasAcceptedRecord) {
		$errorData['estado'] = $errorBadge;
	}

	return $errorData;
}
