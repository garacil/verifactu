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
 * \file    core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php
 * \ingroup verifactu
 * \brief   Main VeriFactu triggers for billing events.
 *
 * Handles the following events:
 * - BILL_CREATE: Initializes VeriFactu extrafields on new invoices
 * - BILL_VALIDATE: Sends invoices to AEAT automatically when validating
 * - BILL_PAYED: Updates status when marked as paid
 *
 * Priority 999 to execute after other triggers.
 */

require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/verifactu/lib/verifactu.lib.php');

/**
 *  Class of triggers for Verifactu module
 */
class InterfaceVerifactuTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "verifactu";
		$this->description = "Verifactu triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = '1.0';
		$this->picto = 'verifactu.png@verifactu';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (empty($conf->verifactu) || empty($conf->verifactu->enabled)) {
			return 0; // If module is not enabled, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		};

		$langs->load('verifactu@verifactu');

		return 0;
	}
	public function billCreate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		dol_include_once('/verifactu/lib/verifactu.lib.php');
		// Load third party from invoice object
		$object->fetch_thirdparty();

		// In case it comes from cloning or similar
		foreach ($object->array_options as $key => $value) {
			$object->array_options[$key] = '';
		}

		// Set status to not sent when invoice is created
		$object->array_options['options_verifactu_estado'] = '<div class="center"><span class="badge badge-status8 classfortooltip badge-status" attr-status="' . $langs->trans('VERIFACTU_STATUS_NOT_SEND') . '">' . $langs->trans('VERIFACTU_STATUS_NOT_SEND') . '</span></div>';
		$object->array_options['options_verifactu_impuesto'] = GETPOST('options_verifactu_impuesto', 'alpha') ?: Sietekas\Verifactu\VerifactuInvoice::TAX_VAT;
		$object->array_options['options_verifactu_clave_regimen'] =  GETPOST('options_verifactu_clave_regimen', 'alpha') ?: Sietekas\Verifactu\VerifactuInvoice::REGIME_GENERAL; // Set general regime by default
		$object->array_options['options_verifactu_calificacion_operacion'] =  GETPOST('options_verifactu_calificacion_operacion', 'alpha') ?: Sietekas\Verifactu\VerifactuInvoice::QUAL_TAXABLE; // Set operation qualification by default
		$object->array_options['options_verifactu_operacion_exenta'] =  GETPOST('options_verifactu_operacion_exenta', 'alpha') ?: 0; // Set exempt operation by default


		/*
		Invoice type determination based on source:

		- If from TakePOS: Always generates simplified invoices (F2) or their credit notes (R5)
		- If Facturesim (POS 2byte): The POS module applies a limit of N€:
		  • If total ≤ N€: Creates Facturesim → type F2 (simplified invoice)
		  • If total > N€: Creates Facture → type F1 (full invoice)

		See constants: TYPE_STANDARD, TYPE_SIMPLIFIED, TYPE_CREDIT_NOTE_SIMPLIFIED
		 */
		if ($object->module_source == 'takepos' || $object instanceof Facturesim || $object->thirdparty->array_options['options_verifactu_factura_simplificada'] == 1) {
			// Check if it's a TakePOS credit note (refund)
			if ($object->type == $object::TYPE_CREDIT_NOTE) {
				// Simplified refund (takepos refund) -> R5
				$object->array_options['options_verifactu_factura_tipo'] = Sietekas\Verifactu\VerifactuInvoice::TYPE_CREDIT_NOTE_SIMPLIFIED;
			} else {
				// Simplified invoice (TakePOS) -> F2 (Simplified invoice or without recipient ID art. 6.1.d)
				$object->array_options['options_verifactu_factura_tipo'] = Sietekas\Verifactu\VerifactuInvoice::TYPE_SIMPLIFIED;
			}
		} else { // Set invoice type fields based on Dolibarr type

			switch ($object->type) {
				case $object::TYPE_STANDARD:
				case $object::TYPE_SITUATION:
					// Standard and situation invoice -> F1 (Invoice art. 6, 7.2 and 7.3 of RD 1619/2012)
					$object->array_options['options_verifactu_factura_tipo'] = Sietekas\Verifactu\VerifactuInvoice::TYPE_STANDARD;
					break;
				case $object::TYPE_REPLACEMENT:
					// Corrective invoice -> R2 (Art. 80.3 LIVA) - Doli doesn't allow selecting specific type
					$object->array_options['options_verifactu_factura_tipo'] = Sietekas\Verifactu\VerifactuInvoice::TYPE_CREDIT_NOTE_80_3;
					break;
				case $object::TYPE_CREDIT_NOTE:
					// Credit note invoice -> R2 (Corrective for total or partial return Art. 80.3 LIVA)
					$object->array_options['options_verifactu_factura_tipo'] = Sietekas\Verifactu\VerifactuInvoice::TYPE_CREDIT_NOTE_80_3;
					break;
				case $object::TYPE_DEPOSIT:
					// Deposit invoice -> F1 (Considered normal "Invoice" - advance payment)
					$object->array_options['options_verifactu_factura_tipo'] = Sietekas\Verifactu\VerifactuInvoice::TYPE_STANDARD;
					break;
				case $object::TYPE_PROFORMA:
					// Proforma invoice -> F1 (Considered normal invoice)
					$object->array_options['options_verifactu_factura_tipo'] = Sietekas\Verifactu\VerifactuInvoice::TYPE_STANDARD;
					break;
			}
		}

		// Apply configurations by specificity order (lowest to highest):
		// 1. Global default values (general system configuration)
		if (!empty($conf->global->VERIFACTU_DEFAULT_TAX_TYPE)) {
			$object->array_options['options_verifactu_impuesto'] = $conf->global->VERIFACTU_DEFAULT_TAX_TYPE;
		}
		if (!empty($conf->global->VERIFACTU_DEFAULT_TAX_REGIME)) {
			$object->array_options['options_verifactu_clave_regimen'] = $conf->global->VERIFACTU_DEFAULT_TAX_REGIME;
		}
		if (!empty($conf->global->VERIFACTU_DEFAULT_OPERATION_QUALIFICATION)) {
			$object->array_options['options_verifactu_calificacion_operacion'] = $conf->global->VERIFACTU_DEFAULT_OPERATION_QUALIFICATION;
		}
		if (!empty($conf->global->VERIFACTU_DEFAULT_EXEMPT_OPERATION)) {
			$object->array_options['options_verifactu_operacion_exenta'] = $conf->global->VERIFACTU_DEFAULT_EXEMPT_OPERATION;
		}

		// 2. Third party (customer) specific values - highest priority
		if ($object->thirdparty->id > 0 && $object->thirdparty->fetch_optionals() > 0) {

			// If third party has specific configuration, use it instead of global
			if (!empty($object->thirdparty->array_options['options_verifactu_impuesto'])) {
				$object->array_options['options_verifactu_impuesto'] = $object->thirdparty->array_options['options_verifactu_impuesto'];
				setEventMessage($langs->trans('VERIFACTU_DEFAULT_SOCIETE_IMPUESTO_SET', $object->thirdparty->name), 'warnings');
			}
			if (!empty($object->thirdparty->array_options['options_verifactu_clave_regimen'])) {
				$object->array_options['options_verifactu_clave_regimen'] = $object->thirdparty->array_options['options_verifactu_clave_regimen'];
				setEventMessage($langs->trans('VERIFACTU_DEFAULT_SOCIETE_CLAVE_REGIMEN_SET', $object->thirdparty->name), 'warnings');
			}
			if (!empty($object->thirdparty->array_options['options_verifactu_calificacion_operacion'])) {
				$object->array_options['options_verifactu_calificacion_operacion'] = $object->thirdparty->array_options['options_verifactu_calificacion_operacion'];
				setEventMessage($langs->trans('VERIFACTU_DEFAULT_SOCIETE_CALIFICACION_OPERACION_SET', $object->thirdparty->name), 'warnings');
			}
			if (!empty($object->thirdparty->array_options['options_verifactu_operacion_exenta'])) {
				$object->array_options['options_verifactu_operacion_exenta'] = $object->thirdparty->array_options['options_verifactu_operacion_exenta'];
				setEventMessage($langs->trans('VERIFACTU_DEFAULT_SOCIETE_OPERACION_EXENTA_SET', $object->thirdparty->name), 'warnings');
			}
		}


		// Finally set all extra fields
		$res = $object->insertExtraFields();
	}
	public function billValidate($action, $object, User $user, Translate $langs, Conf $conf)
	{
		// Include utilities class to process pending invoices
		dol_include_once('/verifactu/class/verifactu.utils.php');

		dol_syslog("VERIFACTU TRIGGER billValidate START: Invoice id=" . $object->id . " ref=" . $object->ref . " newref=" . ($object->newref ?? 'NULL') . " status=" . $object->status . " thirdparty_id=" . $object->socid, LOG_DEBUG);

		// Verify invoice reference is correct
		$object->fetch_thirdparty();

		// Determine reference to validate
		$objectref = substr($object->ref, 1, 4);

		if ($objectref == 'PROV') {
			$numref = $object->getNextNumRef($object->thirdparty);

			// Array to accumulate validation errors
			$validationErrors = array();

			// Validate reference is alphanumeric without spaces (max 60 characters)
			if (!empty($numref)) {
				// Check if contains spaces
				if (strpos($numref, ' ') !== false) {
					$validationErrors[] = $langs->trans('VERIFACTU_INVOICE_REF_CONTAINS_SPACES');
				}

				// Verify it's alphanumeric (allows: letters, numbers, hyphens, underscores and slashes)
				if (!preg_match('/^[a-zA-Z0-9\-_\/]+$/', $numref)) {
					$validationErrors[] = $langs->trans('VERIFACTU_INVOICE_REF_NOT_ALPHANUMERIC', $numref);
				}

				// Verify maximum length (60 characters)
				if (strlen($numref) > 60) {
					$validationErrors[] = $langs->trans('VERIFACTU_INVOICE_REF_TOO_LONG', $numref, strlen($numref));
				}

				// If there are errors, show them all together
				if (!empty($validationErrors)) {
					setEventMessages(null, $validationErrors, 'errors');
					return -1;
				}
			}
		}


		// Check internet connection once
		$hasInternetConnection = checkInternetConnection();

		// If internet connection available, first process pending invoices with connection errors
		if ($hasInternetConnection) {
			try {
				$verifactuUtils = new VerifactuUtils($this->db);
				$result = $verifactuUtils->sendInvoicesWithErrors();

				// Log the result of pending invoices processing
				if ($result > 0) {
					dol_syslog("billValidate: Pending invoices processed successfully before validating invoice " . $object->ref, LOG_INFO);
				} elseif ($result < 0) {
					dol_syslog("billValidate: Error processing pending invoices before validating invoice " . $object->ref . ": " . $verifactuUtils->error, LOG_WARNING);
				} else {
					dol_syslog("billValidate: No pending invoices to process before validating invoice " . $object->ref, LOG_DEBUG);
				}

				// Show information to user if invoices were processed
				if (!empty($verifactuUtils->result) && is_array($verifactuUtils->result)) {
					$processed = $verifactuUtils->result['processed'] ?? 0;
					$errors = $verifactuUtils->result['errors'] ?? 0;

					if ($processed > 0) {
						$message = sprintf("%d pending invoices processed automatically", $processed);
						if ($errors > 0) {
							$message .= sprintf(" (%d with errors)", $errors);
						}
						setEventMessage($message, 'mesgs');
					}
				}
			} catch (Exception $e) {
				dol_syslog("billValidate: Exception processing pending invoices: " . $e->getMessage(), LOG_ERR);
			}
		}





		$conditionToDirectCall = $object->module_source == 'takepos' || ($object->module_source != 'takepos' && $conf->global->VERIFACTU_DIRECT_CALL_ON_VALIDATE);
		if ($conditionToDirectCall) {
			$object->fetch_thirdparty();
			$object->thirdparty->fetch_optionals();

			// Variables to control validation errors
			$hasErrors = false;
			$errorMessages = array();

			// Validation 1: CIF required for Spain
			if ($object->thirdparty->country_code == "ES" && empty($object->thirdparty->idprof1) && $object->module_source != 'takepos' && ($object->thirdparty->array_options['options_verifactu_factura_simplificada'] != 1)) {
				$hasErrors = true;
				$errorMessages[] = $langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_VALID_CIF_REQUIRED', $object->ref);
			}

			// Validation 2: Intra-community VAT required for EU countries (not Spain)
			if ($object->thirdparty->country_code != "ES" && empty($object->thirdparty->tva_intra) && $object->module_source != 'takepos' && ($object->thirdparty->array_options['options_verifactu_factura_simplificada'] != 1)) {
				$hasErrors = true;
				$errorMessages[] = $langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_VALID_TVA_INTRA_REQUIRED', $object->ref);
			}

			// Validation 3: Address required
			if (empty($object->thirdparty->address) && $object->module_source != 'takepos' && ($object->thirdparty->array_options['options_verifactu_factura_simplificada'] != 1)) {
				$hasErrors = true;
				$errorMessages[] = $langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_VALID_ADDRESS_REQUIRED', $object->ref);
			}

			// If validation errors, show them and abort
			if ($hasErrors) {
				foreach ($errorMessages as $errorMsg) {
					setEventMessage($errorMsg, 'errors');
				}
				return -1;
			}
			// Use already performed connection check
			if (!$hasInternetConnection) {
				$badgeSinConexion = '<div class="center"><span class="badge badge-status1 classfortooltip badge-status" attr-status="' . $langs->transnoentities('VERIFACTU_STATUS_NO_INTERNET') . '">' . $langs->transnoentities('VERIFACTU_STATUS_NO_INTERNET') . '</span></div>';
				$object->array_options['options_verifactu_estado'] = $badgeSinConexion;
				$object->array_options['options_verifactu_error'] = 'NO_INTERNET_CONNECTION';
				// Mark Incident='S' per VeriFactu regulations for later resubmissions
				$object->array_options['options_verifactu_incidencia'] = 'S';
				$object->updateExtraField('verifactu_estado');
				$object->updateExtraField('verifactu_error');
				$object->updateExtraField('verifactu_incidencia');
				setEventMessage($langs->trans('VERIFACTU_HAS_NO_INTERNET_CONNECTION', $object->newref ?? $object->ref), 'warnings');
				return 1;
			}

			// If all validations pass, proceed with VeriFactu
			// DO NOT use transaction here - let validate() function handle main transaction
			dol_syslog("VERIFACTU TRIGGER: About to call execVERIFACTUCall for invoice id=" . $object->id . " ref=" . $object->ref . " newref=" . ($object->newref ?? 'NULL') . " status=" . $object->status, LOG_DEBUG);

			$res = execVERIFACTUCall($object);

			dol_syslog("VERIFACTU TRIGGER: execVERIFACTUCall returned: " . var_export($res, true) . " for invoice " . ($object->newref ?? $object->ref), LOG_DEBUG);

			if ($res) {
				setEventMessage($langs->trans('VERIFACTU_SUCCESS'), 'mesgs');
				dol_syslog("VERIFACTU TRIGGER: Returning 1 (success) for invoice " . ($object->newref ?? $object->ref), LOG_DEBUG);
				return 1; // Success - allows continuing with validation
			} else {
				// ERROR: VeriFactu failed (exception or response error)
				// Error data already saved in execVERIFACTUCall via independent DB connection
				//
				// CRITICAL FIX: Dolibarr's rollback doesn't work properly with nested transactions.
				// We must manually revert the invoice to draft status with PROV reference.
				$failedRef = $object->newref ?? $object->ref;
				dol_syslog("VERIFACTU TRIGGER: VeriFactu failed for invoice " . $failedRef . " - Reverting to draft status", LOG_ERR);

				// Generate PROV reference for this invoice
				$provRef = '(PROV' . $object->id . ')';

				// Use independent connection to revert invoice to draft status
				// This ensures the change persists even if main transaction rolls back
				global $conf, $dolibarr_main_db_pass;
				$dbType = $conf->db->type ? $conf->db->type : 'mysqli';
				$defaultPort = ($dbType === 'pgsql') ? 5432 : 3306;
				$revertDb = getDoliDBInstance(
					$dbType,
					$conf->db->host,
					$conf->db->user,
					$dolibarr_main_db_pass,
					$conf->db->name,
					($conf->db->port ? $conf->db->port : $defaultPort)
				);

				// For PostgreSQL, don't close connection as it may be the same as main
				$canCloseConnection = ($dbType !== 'pgsql');

				if ($revertDb && !$revertDb->error) {
					// Revert invoice to draft: set status=0 (draft), ref=PROV*, clear date_valid
					$sql = "UPDATE " . MAIN_DB_PREFIX . "facture SET ";
					$sql .= " fk_statut = 0,";  // STATUS_DRAFT = 0
					$sql .= " ref = '" . $revertDb->escape($provRef) . "',";
					$sql .= " date_valid = NULL,";
					$sql .= " fk_user_valid = NULL";
					$sql .= " WHERE rowid = " . ((int) $object->id);

					$resql = $revertDb->query($sql);
					if ($resql) {
						dol_syslog("VERIFACTU TRIGGER: Invoice " . $object->id . " reverted to draft with ref " . $provRef, LOG_WARNING);

						// Also update the object in memory
						$object->ref = $provRef;
						$object->status = 0;
						$object->statut = 0;
						$object->date_valid = null;
					} else {
						dol_syslog("VERIFACTU TRIGGER: Failed to revert invoice to draft: " . $revertDb->lasterror(), LOG_ERR);
					}

					if ($canCloseConnection) {
						$revertDb->close();
					}
				} else {
					dol_syslog("VERIFACTU TRIGGER: Could not create independent DB connection for revert", LOG_ERR);
				}

				setEventMessage($langs->trans('VERIFACTU_ERROR_INVOICE_NOT_VALIDATED', $failedRef), 'errors');
				$this->error = $langs->trans('VERIFACTU_ERROR_INVOICE_NOT_VALIDATED', $failedRef);
				$this->errors[] = $this->error;
				return -1; // Block validation - invoice has been reverted to draft
			}
		}
		return 1; // VeriFactu not executed, continue normally
	}
	public function companyModify($action, $object, User $user, Translate $langs, Conf $conf)
	{
		global $langs;
		// Restriction: if CIF is modified for company with invoices sent to VeriFactu, new CIF must match old one and notify user
		$oldCif = $object->oldcopy->idprof1;
		$newCif = $object->idprof1;
		$oldCifIntra = $object->oldcopy->tva_intra;
		$newCifIntra = $object->tva_intra;
		$countryCode = $object->country_code;


		// Check if company has invoices sent to VeriFactu
		$sql = "SELECT COUNT(*) as count FROM " . MAIN_DB_PREFIX . "facture f ";
		$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object ";
		$sql .= "WHERE fk_soc = " . $object->id . " AND fe.verifactu_csv_factura IS NOT NULL AND fe.verifactu_huella IS NOT NULL";
		$sql .= "  AND f.entity IN (" . getEntity('invoice') . ")";

		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj->count > 0) {
				// Company has invoices sent to VeriFactu

				// Spanish customer
				if ($countryCode == 'ES') {
					// Case 1: Trying to change CIF to different value
					if (!empty($oldCif) && !empty($newCif) && $oldCif != $newCif) {
						$object->idprof1 = $oldCif; // Revert the change
						$object->setValueFrom('siren', $oldCif); // Update value in object
						setEventMessage($langs->trans('SOCIETE_HAVE_INVOICES_NOT_IDPROF1_MODIFIED', $object->name, $obj->count), 'warnings');
					}

					// Case 2: Trying to empty CIF when it had one
					if (!empty($oldCif) && empty($newCif)) {
						$object->idprof1 = $oldCif; // Revert the change (restore original CIF)
						$object->setValueFrom('siren', $oldCif); // Update value in object
						setEventMessage($langs->trans('SOCIETE_HAVE_INVOICES_NOT_IDPROF1_MODIFIED', $object->name, $obj->count), 'warnings');
					}
				} else {
					// Case for non-Spanish customers
					// Case 1: Trying to change Intra CIF to different value
					if (!empty($oldCifIntra) && !empty($newCifIntra) && $oldCifIntra != $newCifIntra) {
						$object->idprof1 = $oldCifIntra; // Revert the change
						$object->setValueFrom('tva_intra', $oldCifIntra); // Update value in object
						setEventMessage($langs->trans('SOCIETE_HAVE_INVOICES_NOT_TVAINTRA_MODIFIED', $object->name, $obj->count), 'warnings');
					}

					// Case 2: Trying to empty Intra CIF when it had one
					if (!empty($oldCifIntra) && empty($newCifIntra)) {
						$object->idprof1 = $oldCifIntra; // Revert the change (restore original CIF)
						$object->setValueFrom('tva_intra', $oldCifIntra); // Update value in object
						setEventMessage($langs->trans('SOCIETE_HAVE_INVOICES_NOT_TVAINTRA_MODIFIED', $object->name, $obj->count), 'warnings');
					}
				}
			}
		}
	}
}
