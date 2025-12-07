<?php

// Required Dolibarr includes
dol_include_once('/compta/facture/class/facture.class.php');
dol_include_once('/verifactu/lib/verifactu.lib.php');
dol_include_once('/verifactu/lib/functions/funciones.utilidades.php');

/**
 *		Class to manage utility methods
 */
class VerifactuUtils
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	public $output; // Used by Cron method to return message
	public $result; // Used by Cron method to return data
	public $error; // Used by Cron method to return error message
	/**
	 *	Constructor
	 *
	 *  @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}
	/* Send invoices with previous errors */
	public function sendInvoicesWithErrors()
	{
		global $conf, $langs, $user;

		$langs->load("verifactu@verifactu");

		dol_syslog("VerifactuUtils::sendInvoicesWithErrors - Starting scheduled task", LOG_INFO);

		$this->output = '';
		$this->error = '';
		$this->result = array();

		try {
			// Search for invoices with connection errors
			$invoicesWithErrors = $this->getInvoicesWithConnectionErrors();

			if (empty($invoicesWithErrors)) {
				$this->output = "No pending invoices found with connection errors";
				dol_syslog("VerifactuUtils::sendInvoicesWithErrors - " . $this->output, LOG_INFO);
				return 0;
			}

			// Check internet connection only if there are pending invoices
			if (!checkInternetConnection()) {
				$this->error = "No internet connection available";
				$this->output = $this->error;
				dol_syslog("VerifactuUtils::sendInvoicesWithErrors - " . $this->error, LOG_WARNING);
				return -1;
			}



			$processed = 0;
			$errors = 0;
			$skipped = 0;

			dol_syslog("VerifactuUtils::sendInvoicesWithErrors - Found " . count($invoicesWithErrors) . " invoices to process", LOG_INFO);

			// Process each invoice
			foreach ($invoicesWithErrors as $invoiceData) {
				// Check connection before each processing
				if (!checkInternetConnection()) {
					$skipped++;
					$this->output .= "Invoice " . $invoiceData['ref'] . ": No internet connection, skipping\n";
					dol_syslog("VerifactuUtils::sendInvoicesWithErrors - Invoice " . $invoiceData['ref'] . " skipped due to lack of connection", LOG_WARNING);
					continue;
				}

				try {
					$result = $this->processInvoiceWithConnectionError($invoiceData);
					if ($result > 0) {
						$processed++;
						$this->output .= "Invoice " . $invoiceData['ref'] . ": Processed successfully\n";
						dol_syslog("VerifactuUtils::sendInvoicesWithErrors - Invoice " . $invoiceData['ref'] . " processed successfully", LOG_INFO);
					} else {
						$errors++;
						$this->output .= "Invoice " . $invoiceData['ref'] . ": Processing error\n";
						dol_syslog("VerifactuUtils::sendInvoicesWithErrors - Error processing invoice " . $invoiceData['ref'], LOG_ERR);
					}
				} catch (Exception $e) {
					$errors++;
					$this->output .= "Invoice " . $invoiceData['ref'] . ": Exception - " . $e->getMessage() . "\n";
					dol_syslog("VerifactuUtils::sendInvoicesWithErrors - Exception processing invoice " . $invoiceData['ref'] . ": " . $e->getMessage(), LOG_ERR);
				}

				// Small pause between processing to avoid overload
				usleep(500000); // 0.5 seconds
			}

			// Final summary
			$summary = "Task completed - Processed: $processed, Errors: $errors, Skipped: $skipped";
			$this->output .= "\n" . $summary;
			$this->result = array(
				'processed' => $processed,
				'errors' => $errors,
				'skipped' => $skipped,
				'total_found' => count($invoicesWithErrors)
			);

			dol_syslog("VerifactuUtils::sendInvoicesWithErrors - " . $summary, LOG_INFO);

			return ($errors > 0) ? -1 : 0;
		} catch (Exception $e) {
			$this->error = "Error in scheduled task: " . $e->getMessage();
			$this->output = $this->error;
			dol_syslog("VerifactuUtils::sendInvoicesWithErrors - General error: " . $e->getMessage(), LOG_ERR);
			return -1;
		}
	}

	/**
	 * Gets invoices with pending connection errors to process
	 * @return array List of invoices with connection errors
	 */
	private function getInvoicesWithConnectionErrors()
	{
		global $conf;

		$sql = "SELECT f.rowid, f.ref, f.datef, f.entity";
		$sql .= " FROM " . MAIN_DB_PREFIX . "facture f";
		$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
		$sql .= " WHERE f.entity = " . getEntity('invoice');
		$sql .= " AND f.fk_statut > 0"; // Only validated invoices
		$sql .= " AND f.ref NOT LIKE '%PROV%'"; // Exclude provisional invoices
		$sql .= " AND (fe.verifactu_error LIKE '%NO_INTERNET_CONNECTION%' OR fe.verifactu_error LIKE '%SERVICE_UNAVAILABLE%')"; // With connection error
		$sql .= " AND (fe.verifactu_huella IS NULL OR fe.verifactu_huella = '')"; // No fingerprint (not sent)
		$sql .= " ORDER BY f.datef ASC, f.rowid ASC"; // From oldest to newest

		dol_syslog("VerifactuUtils::getInvoicesWithConnectionErrors SQL: " . $sql, LOG_DEBUG);

		$resql = $this->db->query($sql);
		$invoices = array();

		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$invoices[] = array(
					'id' => $obj->rowid,
					'ref' => $obj->ref,
					'datef' => $obj->datef,
					'entity' => $obj->entity
				);
			}
			$this->db->free($resql);
		} else {
			dol_syslog("VerifactuUtils::getInvoicesWithConnectionErrors - Query error: " . $this->db->lasterror(), LOG_ERR);
		}

		return $invoices;
	}

	/**
	 * Processes an invoice with connection error
	 * @param array $invoiceData Invoice data
	 * @return int 1 if OK, 0 if error
	 */
	private function processInvoiceWithConnectionError($invoiceData)
	{
		global $conf, $user;

		// Load the invoice
		$facture = new Facture($this->db);
		$result = $facture->fetch($invoiceData['id']);

		if ($result <= 0) {
			dol_syslog("VerifactuUtils::processInvoiceWithConnectionError - Error loading invoice " . $invoiceData['ref'], LOG_ERR);
			return 0;
		}

		// Load extrafields to check incident status
		$facture->fetch_optionals();

		// IMPORTANT: Keep original issue date per VeriFactu regulations
		// Issue date MUST NOT be modified, only submission date can differ
		dol_syslog("VerifactuUtils::processInvoiceWithConnectionError - Processing invoice " . $invoiceData['ref'] . " with original date: " . dol_print_date($facture->date, 'day'), LOG_INFO);

		// Verify incident is marked as 'S' for resubmissions (per regulations)
		if (empty($facture->array_options['options_verifactu_incidencia']) || $facture->array_options['options_verifactu_incidencia'] !== 'S') {
			// Mark incident if not already marked (compatibility with older invoices)
			$facture->array_options['options_verifactu_incidencia'] = 'S';
			$facture->updateExtraField('verifactu_incidencia');
			dol_syslog("VerifactuUtils::processInvoiceWithConnectionError - Incident marked as 'S' for invoice " . $invoiceData['ref'], LOG_INFO);
		}

		// Execute VeriFactu call (incident 'S' will be used in handleInvoiceCreationOrSubsanation)
		$response = execVERIFACTUCall($facture, 'Alta');

		// Check if response indicates success (can be boolean true or array with success)
		$isSuccess = false;
		if ($response === true) {
			$isSuccess = true;
		} elseif (is_array($response) && isset($response['success']) && $response['success']) {
			$isSuccess = true;
		}

		if ($isSuccess) {
			// If processed successfully, clear connection error
			$this->clearConnectionError($facture);
			dol_syslog("VerifactuUtils::processInvoiceWithConnectionError - Invoice " . $invoiceData['ref'] . " processed and error cleared", LOG_INFO);
			return 1;
		} else {
			dol_syslog("VerifactuUtils::processInvoiceWithConnectionError - Error processing invoice " . $invoiceData['ref'], LOG_ERR);
			return 0;
		}
	}

	/**
	 * Clears connection error from invoice after successful resubmission
	 * NOTE: Incident='S' field is maintained per VeriFactu regulations to document
	 * that there was an incident during original submission process
	 *
	 * @param Facture $facture Invoice object
	 * @return bool true if OK, false if error
	 */
	private function clearConnectionError($facture)
	{
		// Use invoice ID we already have
		$factureId = isset($facture->id) ? $facture->id : $facture->rowid;
		$factureRef = isset($facture->ref) ? $facture->ref : 'ID:' . $factureId;

		// Get current error
		$facture->fetch_optionals();
		$currentError = $facture->array_options['options_verifactu_error'] ?? '';

		// Clear both NO_INTERNET_CONNECTION and SERVICE_UNAVAILABLE
		$cleanedError = str_replace(array('NO_INTERNET_CONNECTION', 'SERVICE_UNAVAILABLE'), '', $currentError);
		$cleanedError = trim(str_replace(array('  ', '\n\n'), array(' ', '\n'), $cleanedError));

		// If empty, set to NULL
		if (empty($cleanedError)) {
			$cleanedError = null;
		}

		// Update error field
		// NOTE: Incident='S' is intentionally maintained to document incident per regulations
		$sql = "UPDATE " . MAIN_DB_PREFIX . "facture_extrafields";
		$sql .= " SET verifactu_error = " . ($cleanedError ? "'" . $this->db->escape($cleanedError) . "'" : "NULL");
		$sql .= " WHERE fk_object = " . ((int) $factureId);

		$result = $this->db->query($sql);

		if (!$result) {
			dol_syslog("VerifactuUtils::clearConnectionError - Error updating error for invoice " . $factureRef . ": " . $this->db->lasterror(), LOG_ERR);
			return false;
		}

		dol_syslog("VerifactuUtils::clearConnectionError - Connection error cleared for invoice " . $factureRef . " (Incident='S' maintained per regulations)", LOG_INFO);
		return true;
	}
}
