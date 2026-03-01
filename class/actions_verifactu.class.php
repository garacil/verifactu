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
 * \file    verifactu/class/actions_verifactu.class.php
 * \ingroup verifactu
 * \brief   Hooks for VeriFactu integration with Dolibarr.
 *
 * Implements hooks for:
 * - Modify invoice cards (formObjectOptions, addMoreActionsButtons)
 * - Customize PDF generation (beforePDFCreation, afterPDFCreation)
 * - Add extra fields (formAddObjectLine, formEditObjectLine)
 * - Manage ODT substitutions (ODTSubstitution)
 */

/**
 * Class ActionsVerifactu
 */
dol_include_once('/verifactu/lib/verifactu.lib.php');
class ActionsVerifactu
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var array Errors
	 */
	public $errors = array();


	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;


	/**
	 * Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Execute action
	 *
	 * @param	array			$parameters		Array of parameters
	 * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param	string			$action      	'add', 'update', 'view'
	 * @return	int         					<0 if KO,
	 *                           				=0 if OK but we want to process standard actions too,
	 *                            				>0 if OK and we want to replace standard actions.
	 */
	public function getNomUrl($parameters, &$object, &$action)
	{
		global $db, $langs, $conf, $user;
		dol_include_once('/core/class/html.form.class.php');
		$form = new Form($db);

		if ($object instanceof Facture) {
			$object->fetch_optionals();
			$this->resprints = '<div style="display: inline-block; padding-left: 5px;">' . (img_object('', 'verifactu@verifactu', 'width="20"') . '<div style="display: inline-block; padding-left: 5px;">' . $object->array_options['options_verifactu_estado'] . '</div>') . '</div>';
		}

		return 0;
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		$langs->load("verifactu@verifactu");

		if (array_intersect(['thirdpartycomm', 'thirdpartycard'], explode(':', $parameters['context']))) {
			// Override translations for CIF and EU VAT number
			if (is_object($langs)) {
				$vatIntraTranslation = $langs->transnoentitiesnoconv("VATIntraVerifactu");
				if (!empty($vatIntraTranslation) && $vatIntraTranslation != "VATIntraVerifactu") {
					$langs->tab_translate["VATIntraShort"] = $vatIntraTranslation;
					$langs->tab_translate["VATIntra"] = $vatIntraTranslation;
				}

				$profId1Translation = $langs->transnoentitiesnoconv("ProfId1ESVerifactu");
				if (!empty($profId1Translation) && $profId1Translation != "ProfId1ESVerifactu") {
					$langs->tab_translate["ProfId1ES"] = $profId1Translation;
				}
			}
		}

		$error = 0; // Error counter

		// =====================================================================
		// MASS VALIDATE INTERCEPTION
		// Dolibarr core wraps all invoices in a single transaction during mass
		// validation. If any invoice fails (e.g. VeriFactu error), ALL invoices
		// are rolled back - including those already sent to AEAT.
		// We intercept this action and validate each invoice in its own
		// independent transaction, so a failure in one does not affect the rest.
		// =====================================================================
		if (array_intersect(['invoicelist', 'invoicelistverifactu'], explode(':', $parameters['context']))) {
			$massaction = GETPOST('massaction', 'alpha');
			$confirmmassaction = GETPOST('confirmmassaction', 'alpha');
			$toselect = GETPOST('toselect', 'array');

			if ($massaction == 'validate' && !empty($confirmmassaction) && !empty($toselect) && $user->hasRight('facture', 'creer')) {

				// Replicate Dolibarr's pre-check: block if stock is calculated on bill validation
				if (isModEnabled('stock') && getDolGlobalString('STOCK_CALCULATE_ON_BILL')) {
					$langs->load("errors");
					setEventMessages($langs->trans('ErrorMassValidationNotAllowedWhenStockIncreaseOnAction'), null, 'errors');
					return 1; // Block standard code, do nothing
				}

				$nbok = 0;
				$nberrors = 0;
				$errorDetails = array();

				foreach ($toselect as $toselectid) {
					$objecttmp = new Facture($this->db);
					$result = $objecttmp->fetch($toselectid);

					if ($result <= 0) {
						$nberrors++;
						$errorDetails[] = $langs->trans('VERIFACTU_MASS_VALIDATE_FETCH_ERROR', $toselectid);
						continue;
					}

					// Individual transaction per invoice
					$this->db->begin();

					if (method_exists($objecttmp, 'validate')) {
						$result = $objecttmp->validate($user);
					} elseif (method_exists($objecttmp, 'setValid')) {
						$result = $objecttmp->setValid($user);
					} else {
						$result = -1;
					}

					if ($result > 0) {
						$this->db->commit();
						$nbok++;
					} elseif ($result == 0) {
						// Already validated or cannot be validated from current status
						$this->db->rollback();
						$nberrors++;
						$errorDetails[] = $langs->trans('VERIFACTU_MASS_VALIDATE_ALREADY_VALIDATED', $objecttmp->ref);
					} else {
						// Error during validation (VeriFactu failure, data error, etc.)
						$this->db->rollback();
						$nberrors++;
						$errorMsg = !empty($objecttmp->error) ? $objecttmp->error : implode(', ', $objecttmp->errors);
						$errorDetails[] = $objecttmp->ref . ': ' . $errorMsg;
					}
				}

				// Summary messages
				if ($nbok > 0) {
					setEventMessages($langs->trans("RecordsModified", $nbok), null, 'mesgs');
				}
				if ($nbok > 0 && $nberrors > 0) {
					setEventMessages($langs->trans('VERIFACTU_MASS_VALIDATE_PARTIAL', $nbok, $nberrors), null, 'warnings');
				}
				if (!empty($errorDetails)) {
					setEventMessages(null, $errorDetails, 'errors');
				}

				// Info: VeriFactu handles mass validation with individual transactions
				setEventMessages($langs->trans('VERIFACTU_MASS_VALIDATE_INFO'), null, 'mesgs');

				return 1; // Block standard mass action code (single-transaction pattern)
			}
		}

		if (array_intersect(['invoicecard'], explode(':', $parameters['context']))) {

			// Force validation date to be invoice date, required by VeriFactu
			$conf->global->FAC_FORCE_DATE_VALIDATION = false;
			if ($action == 'valid'  && strpos($object->ref, 'PROV') !== false) {
				$conf->global->FAC_FORCE_DATE_VALIDATION = true;
				$object->date = dol_now();
				$object->setValueFrom('datef', $this->db->idate($object->date));
			}

			// Override validation confirmation translation for customer invoices
			if ($action == 'valid' && is_object($object) && $object instanceof Facture && $object->id > 0) {
				// Override ConfirmValidateBill translation with VERIFACTU version
				// We use transnoentitiesnoconv to keep HTML unconverted
				if (is_object($langs) && isset($object->ref)) {
					$object->fetch_thirdparty();

					$objectref = substr($object->ref, 1, 4);
					if ($objectref == 'PROV') {
						$numref = $object->getNextNumRef($object->thirdparty);
						// $object->date=$savdate;
					} else {
						$numref = $object->ref;
					}
					$confirmTranslation = $langs->transnoentitiesnoconv("ConfirmValidateBillVerifactu", $numref);
					if (!empty($confirmTranslation) && $confirmTranslation != "ConfirmValidateBillVerifactu") {
						$langs->tab_translate["ConfirmValidateBill"] = $confirmTranslation;
					}
				}
			}
		}
		return 0;
	}

	public function dolGetButtonAction($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;
		if (array_intersect(['invoicecard'], explode(':', $parameters['context']))) {
			if (strpos($object->ref, 'PROV') === false) {
				if (strpos($parameters['url'], 'action=modif') !== false) {
					$parameters['url'] = str_replace('action=modif', 'action=view', $parameters['url']);
					$this->resprints = '<span class="butActionRefused classfortooltip" title="' . $langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED', $object->ref) . '">' . $langs->trans('Modify') . '</span>';
					return 1;
				}
				if (strpos($parameters['url'], 'action=delete') !== false) {
					$parameters['url'] = str_replace('action=delete', 'action=view', $parameters['url']);
					$this->resprints = '<span class="butActionRefused classfortooltip" title="' . $langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED', $object->ref) . '">' . $langs->trans('Delete') . '</span>';
					return 1;
				}
			} else {
				if (strpos($parameters['url'], 'action=valid') !== false) {
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

					// Validation 2: EU VAT number required for EU countries (not Spain)
					if ($object->thirdparty->country_code != "ES" && empty($object->thirdparty->tva_intra) && $object->module_source != 'takepos' && ($object->thirdparty->array_options['options_verifactu_factura_simplificada'] != 1)) {
						$hasErrors = true;
						$errorMessages[] = $langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_VALID_TVA_INTRA_REQUIRED', $object->ref);
					}

					// Validation 3: Address required
					if (empty($object->thirdparty->address) && $object->module_source != 'takepos' && ($object->thirdparty->array_options['options_verifactu_factura_simplificada'] != 1)) {
						$hasErrors = true;
						$errorMessages[] = $langs->trans('VERIFACTU_INVOICE_CAN_NOT_BE_VALID_ADDRESS_REQUIRED', $object->ref);
					}

					// If there are errors, disable button and show all errors
					if ($hasErrors) {
						$parameters['url'] = str_replace('action=valid', 'action=view', $parameters['url']);

						// Combine all error messages in the tooltip
						$combinedErrorMessage = implode('&#10;', $errorMessages); // &#10; is line break in HTML

						$this->resprints = '<span class="butActionRefused classfortooltip" title="' . $combinedErrorMessage . '">' . $langs->trans('Validate') . '</span>';
						return 1;
					}
				}
			}
		}
		return 0;
	}



	/**
	 * Overloading the doMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function doMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			foreach ($parameters['toselect'] as $objectid) {
				// Do action on each object id
			}
		}

		if (!$error) {
			$this->results = array('myreturn' => 999);
			$this->resprints = 'A text to show';
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		// Empty function - help functionality removed
		return 0;
	}


	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		/* print_r($parameters); print_r($object); echo "action: " . $action; */
		if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
			$this->resprints = '<option value="0"' . ($disabled ? ' disabled="disabled"' : '') . '>' . $langs->trans("VerifactuMassAction") . '</option>';
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}


	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$pdfhandler     PDF builder handler
	 * @param   string	$action         'add', 'update', 'view'
	 * @return  int 		            <0 if KO,
	 *                                  =0 if OK but we want to process standard actions too,
	 *                                  >0 if OK and we want to replace standard actions.
	 */
	public function afterPDFCreation($parameters, &$pdfhandler, &$action)
	{
		global $conf, $user, $langs;
		global $hookmanager;

		$outputlangs = $langs;

		$ret = 0;
		$deltemp = array();
		dol_syslog(get_class($this) . '::executeHooks action=' . $action);

		// Check if it's an invoice and if QR is enabled
		if (
			isset($parameters['object']) && $parameters['object'] instanceof Facture &&
			$parameters['object']->id > 0
		) {
			try {
				$this->addQRToPDFWithTCPDI($parameters, $outputlangs);
			} catch (Exception $e) {
				dol_syslog("Verifactu afterPDFCreation QR Error: " . $e->getMessage(), LOG_ERR);
			}
		}

		return $ret;
	}

	/**
	 * Adds VeriFactu QR to PDF using TCPDI (compatible with older Dolibarr versions)
	 *
	 * @param array $parameters Hook parameters
	 * @param object $outputlangs Language object
	 */
	private function addQRToPDFWithTCPDI($parameters, $outputlangs)
	{
		global $conf, $langs;

		$object = $parameters['object'];
		$file = $parameters['file'];

		// Check if invoice is sent to VeriFactu
		$langs->load("verifactu@verifactu");
		if (isFactureSendToVerifactu($object) === false) {
			return; // Only process invoices sent to VeriFactu
		}

		// Check that PDF file exists
		if (!file_exists($file)) {
			dol_syslog("Verifactu TCPDI: PDF file not found: " . $file, LOG_WARNING);
			return;
		}

		try {
			// Check that TCPDI is available
			if (!class_exists('TCPDI')) {
				dol_syslog("Verifactu TCPDI: TCPDI class not found", LOG_WARNING);
				return;
			}

			// Get QR configuration according to official AEAT specifications
			// Size: Between 30-40mm according to official specification (Art. 21.1)
			$qrSize = !empty($conf->global->VERIFACTU_QR_SIZE) ? floatval($conf->global->VERIFACTU_QR_SIZE) : 35;

			// Validate that size is within official range


			$qrPositionX = $conf->global->VERIFACTU_QR_POSITION_X ?? 'center';
			$qrPositionY = $conf->global->VERIFACTU_QR_POSITION_Y >= 0 ? floatval($conf->global->VERIFACTU_QR_POSITION_Y) : 15;
			$qrShowText = !empty($conf->global->VERIFACTU_QR_SHOW_TEXT) ? true : false;
			$qrTextSize = !empty($conf->global->VERIFACTU_QR_TEXT_SIZE) ? intval($conf->global->VERIFACTU_QR_TEXT_SIZE) : 8;

			// Get QR URL
			$qrUrl = getQRUrl($object);
			if (empty($qrUrl)) {
				dol_syslog("Verifactu TCPDI: QR URL is empty", LOG_WARNING);
				return;
			}

			// Create new TCPDI instance
			$pdf = pdf_getInstance();

			// Disable automatic headers and footers from TCPDF to avoid unwanted lines
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);

			// Count pages from original PDF
			$pageCount = $pdf->setSourceFile($file);
			// Copy all pages and add QR according to configuration
			for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
				$templateId = $pdf->importPage($pageNo);
				$size = $pdf->getTemplateSize($templateId);

				// Add page with same size as original
				$pdf->AddPage($size['w'] > $size['h'] ? 'L' : 'P', array($size['w'], $size['h']));
				$pdf->useTemplate($templateId);

				// Decide on which pages to add QR
				$addQRToThisPage = false;
				if (!empty($conf->global->VERIFACTU_QR_ALL_PAGES)) {
					// QR on all pages
					$addQRToThisPage = true;
				} else {
					// QR only on first page (default behavior)
					$addQRToThisPage = ($pageNo == 1);
				}

				if ($addQRToThisPage) {
					$this->addQRToTCPDIPage($pdf, $size, $qrUrl, $qrSize, $qrPositionX, $qrPositionY, $qrShowText, $qrTextSize, $outputlangs);
				}
			}

			// Save modified PDF overwriting the original
			$pdf->Output($file, 'F');

			dol_syslog("Verifactu TCPDI: QR added successfully to PDF", LOG_INFO);
		} catch (Exception $e) {
			dol_syslog("Verifactu TCPDI Error: " . $e->getMessage(), LOG_ERR);
		}
	}

	/**
	 * Adds QR to a specific PDF page using TCPDI according to AEAT specifications
	 * Complies with Art. 20.1 and 21 of the ministerial order
	 *
	 * @param object $pdf TCPDI instance
	 * @param array $pageSize Page size
	 * @param string $qrUrl QR URL
	 * @param float $qrSize QR size (must be between 30-40mm according to specification)
	 * @param mixed $qrPositionX QR X position
	 * @param float $qrPositionY QR Y position
	 * @param bool $qrShowText Show explanatory text
	 * @param int $qrTextSize Text size
	 * @param object $outputlangs Language object
	 */
	private function addQRToTCPDIPage($pdf, $pageSize, $qrUrl, $qrSize, $qrPositionX, $qrPositionY, $qrShowText, $qrTextSize, $outputlangs)
	{
		// Calculate X position according to configuration
		$x = $this->calculateQRPositionX($qrPositionX, $pageSize['w'], $qrSize);

		// Reserve space for "QR tributario:" text that MUST go ABOVE the QR
		$textHeight = 4; // Height for upper text
		$spacingBetweenTextAndQR = 1; // Space between text and QR
		$qrMargin = 2; // Minimum margin around QR (specification: minimum 2mm)

		// Adjust Y position to include space for upper text and margins
		$actualY = $qrPositionY + $textHeight + $spacingBetweenTextAndQR;

		// Verify that QR fits on page with safety margins
		if (
			$x - $qrMargin < 0 || $x + $qrSize + $qrMargin > $pageSize['w'] ||
			$actualY - $qrMargin < 0 || $actualY + $qrSize + $qrMargin > $pageSize['h']
		) {
			dol_syslog("Verifactu TCPDI: QR position out of page bounds with required margins", LOG_WARNING);
			return;
		}

		// MANDATORY TEXT: "QR tributario:" - MUST go ABOVE the QR according to specification
		if ($qrShowText) {
			$pdf->SetTextColor(0, 0, 0);
			$pdf->SetFont('', 'B', $qrTextSize);
			$pdf->SetXY($x, $qrPositionY);
			$pdf->Cell($qrSize, $textHeight, 'QR tributario:', 0, 0, 'C');
		}


		// Configure QR style according to ISO/IEC 18004:2015 specifications with M level correction
		$styleQr = array(
			'border' => 0,
			'vpadding' => 0,
			'hpadding' => 0,
			'fgcolor' => array(0, 0, 0),
			'bgcolor' => array(255, 255, 255), // White background for maximum contrast
			'module_width' => 1,
			'module_height' => 1
		);

		// Generate QR with M level error correction (official specification)
		$pdf->write2DBarcode($qrUrl, 'QRCODE,M', $x, $actualY, $qrSize, $qrSize, $styleQr, 'N');

		// ADDITIONAL TEXT FOR VERIFACTU SYSTEMS: Below the QR
		// According to official specification: "Factura verificable en la sede electrónica de la AEAT" or "VeriFactu"
		// Chosen option: "VeriFactu" (more compact and legally valid)
		if ($qrShowText) {
			$pdf->SetFont('', 'B', $qrTextSize - 1); // Bold for greater visibility according to specification
			$textYBelow = $actualY + $qrSize + 1;
			$pdf->SetXY($x, $textYBelow);
			$pdf->Cell($qrSize, 3, 'VeriFactu', 0, 0, 'C');
		}
	}



	/**
	 * Overloading the loadDataForCustomReports function : returns data to complete the customreport tool
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function loadDataForCustomReports($parameters, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$langs->load("verifactu@verifactu");

		$this->results = array();

		$head = array();
		$h = 0;

		if ($parameters['tabfamily'] == 'verifactu') {
			$head[$h][0] = dol_buildpath('/module/index.php', 1);
			$head[$h][1] = $langs->trans("Home");
			$head[$h][2] = 'home';
			$h++;

			$this->results['title'] = $langs->trans("Verifactu");
			$this->results['picto'] = 'verifactu@verifactu';
		}

		$head[$h][0] = 'customreports.php?objecttype=' . $parameters['objecttype'] . (empty($parameters['tabfamily']) ? '' : '&tabfamily=' . $parameters['tabfamily']);
		$head[$h][1] = $langs->trans("CustomReports");
		$head[$h][2] = 'customreports';

		$this->results['head'] = $head;

		return 1;
	}

	public function updateSession($parameters)
	{
		global $conf;

		// Detect if coming from POS 2byte and redirect to TPL with VERIFACTU support
		// If URL contains pos/frontend/tpl/facture.tpl.php redirect to our TPL
		if (strpos($_SERVER['REQUEST_URI'], 'pos/frontend/tpl/facture.tpl.php') !== false) {
			// Redirect to VERIFACTU TPL with QR support
			// The TPL will include the tax QR code according to AEAT specifications
			header("Location: " . dol_buildpath('/verifactu/views/pos.facture.php', 1) . '?' . http_build_query($_GET));
			exit();
		}

		// Force SOCIETE_IDPROF1_INVOICE_MANDATORY to false to avoid errors when validating invoice
		// The trigger handles these fields during validation
		$conf->global->SOCIETE_IDPROF1_INVOICE_MANDATORY = false;


		// Prevent deletion of VERIFACTU extra fields in multiple pages
		$protectedPages = array(
			'societe/admin/societe_extrafields.php',
			'compta/facture/admin/facture_cust_extrafields.php',
			'compta/facture/admin/invoice_cust_extrafields.php'
		);

		foreach ($protectedPages as $page) {
			if (strpos($_SERVER['REQUEST_URI'], $page) !== false && GETPOST('action') == 'delete') {
				global $langs;
				$langs->load("verifactu@verifactu");
				$attrname = GETPOST('attrname', 'alpha');

				// If attrname starts with verifactu_ prevent deletion
				if (strpos($attrname, 'verifactu_') === 0) {
					setEventMessages($langs->trans('VERIFACTU_CANNOT_DELETE_EXTRA_FIELD'), null, 'errors');
					header("Location: " . dol_buildpath('/' . $page, 1));
					exit();
				}
			}
		}
		return 0;
	}

	/**
	 * Calculates the X position of the QR according to configuration
	 */
	private function calculateQRPositionX($positionConfig, $pageWidth, $qrSize)
	{
		global $conf;

		// If it's a numeric value, use it directly
		if (is_numeric($positionConfig)) {
			return floatval($positionConfig);
		}

		// Standard PDF margins
		$leftMargin = 10;
		$rightMargin = 10;

		switch (strtolower($positionConfig)) {
			case 'left':
				return $leftMargin;

			case 'right':
				return $pageWidth - $rightMargin - $qrSize;

			case 'custom':
				// Use custom value if configured
				$customX = !empty($conf->global->VERIFACTU_QR_POSITION_X_CUSTOM) ? floatval($conf->global->VERIFACTU_QR_POSITION_X_CUSTOM) : 50;
				return $customX;

			case 'center':
			default:
				return ($pageWidth - $qrSize) / 2;
		}
	}

	/* Add here any other hooked methods... */
	public function moreHtmlStatus(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		try {
			if ($object instanceof Facture && $object->id > 0) {
				if (isFactureSendToVerifactu($object)) {
					// Generate QR with official texts according to AEAT specifications
					$qrHtml = '<div class="center" style="margin: 5px 0;">';

					// Upper text: "QR tributario:" (mandatory according to Art. 21)
					$qrHtml .= '<div style="font-weight: bold; font-size: 10px; margin-bottom: 2px;">QR tributario:</div>';

					// QR Code
					$qrHtml .= getQrImage($object, 150, 0);

					// Lower text: "VeriFactu" (mandatory for VERIFACTU systems according to Art. 20.1.b)
					$qrHtml .= '<div style="font-weight: bold; font-size: 9px; margin-top: 2px;">VeriFactu</div>';

					$qrHtml .= '</div>';

					$this->resprints = '<br>' . $qrHtml;
				}
			}
		} catch (\Throwable $th) {
			dol_syslog(get_class($this) . '::moreHtmlStatus error: ' . $th->getMessage(), LOG_ERR);
		}
		return 0;
	}

	public function formDolBanner(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		try {
			if ($object instanceof Facture && $object->id > 0) {
				if (isFactureSendToVerifactu($object)) {
					// Generate QR with official texts according to AEAT specifications
					$qrHtml = '<div class="center" style="margin: 5px 0;">';

					// Upper text: "QR tributario:" (mandatory according to Art. 21)
					$qrHtml .= '<div style="font-weight: bold; font-size: 10px; margin-bottom: 2px;">QR tributario:</div>';

					// QR Code
					$qrHtml .= getQrImage($object, 150, 0);

					// Lower text: "VeriFactu" (mandatory for VERIFACTU systems according to Art. 20.1.b)
					$qrHtml .= '<div style="font-weight: bold; font-size: 9px; margin-top: 2px;">VeriFactu</div>';

					$qrHtml .= '</div>';

					$this->resprints = '<br>' . $qrHtml;
				}
			}
		} catch (\Throwable $th) {
			// Handle exception
			dol_syslog(get_class($this) . '::formDolBanner error: ' . $th->getMessage(), LOG_ERR);
		}
		return 0;
	}

	public function printCommonFooter(&$parameters, &$object, &$action, $hookmanager)
	{
		if ($_SERVER['PHP_SELF'] === '/admin/facture.php') {
			global $conf;
			// Code to print common footer
			// Disable select with id forcedate
			echo '<script>document.getElementById("forcedate").disabled = true;</script>';
		}
		return 0;
	}

	public function TakeposReceipt(&$parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $db, $mysoc, $user;

		// Load required translations (includes verifactu for QR texts if applicable)
		$langs->loadLangs(array('main', 'bills', 'cashdesk', 'companies', 'verifactu@verifactu'));

		// Request parameters
		$gift = GETPOSTINT('gift');
		$receiptAction = GETPOST('action', 'aZ09'); // receipt specific (can be 'without_details')

		// Build complete ticket HTML (identical to receipt.php) and add QR
		$html = '';
		$html .= "<html>\n<body>\n";
		$html .= "<style>\n";
		$html .= ".right {text-align: right;}\n";
		$html .= ".center {text-align: center;}\n";
		$html .= ".left {text-align: left;}\n";
		$html .= ".centpercent {width: 100%;}\n";
		$html .= ".verifactu-qr-header {margin: 10px 0; padding: 8px; border: 1px dashed #ccc; background: #f9f9f9;}\n";
		$html .= ".verifactu-qr-text {font-size: 0.7em; font-weight: bold; margin: 2px 0;}\n";
		$html .= "@media only screen and (min-width: 1024px){body {margin-left: 50px; margin-right: 50px;}}\n";
		$html .= "</style>\n";

		// ============ VERIFACTU QR IN HEADER ============
		// Show QR only if invoice is sent to VeriFactu
		try {
			if ($object instanceof Facture && $object->id > 0) {
				if (function_exists('isFactureSendToVerifactu') && isFactureSendToVerifactu($object)) {
					$qrShowText = !empty($conf->global->VERIFACTU_QR_SHOW_TEXT_TPV) ? true : false;

					// QR in header (compact format for ticket)
					$html .= '<div class="verifactu-qr-header center">';
					if ($qrShowText) {
						$html .= '<div class="verifactu-qr-text">QR tributario:</div>';
					}
					if (function_exists('getQrImage')) {
						$html .= getQrImage($object, 120); // Reduced size for header
					}
					if ($qrShowText) {
						$html .= '<div class="verifactu-qr-text">VeriFactu</div>';
					}
					$html .= '</div>';
				}
			}
		} catch (Exception $e) {
			// Don't break ticket if QR fails
		}
		// ============ END QR HEADER ============

		$html .= "<center><div style=\"font-size: 1.5em\"><b>" . dol_escape_htmltag($mysoc->name) . "</b></div></center>\n";
		$html .= "<br>\n";

		// Free header
		$html .= "<p class=\"left\">\n";
		$constFreeText = 'TAKEPOS_HEADER' . (empty($_SESSION['takeposterminal']) ? '0' : $_SESSION['takeposterminal']);
		if (!empty($conf->global->TAKEPOS_HEADER) || !empty($conf->global->$constFreeText)) {
			$newfreetext = '';
			$substitutionarray = getCommonSubstitutionArray($langs);
			complete_substitutions_array($substitutionarray, $langs, $object);
			if (!empty($conf->global->TAKEPOS_HEADER)) {
				$newfreetext .= make_substitutions($conf->global->TAKEPOS_HEADER, $substitutionarray);
			}
			if (!empty($conf->global->$constFreeText)) {
				$newfreetext .= make_substitutions($conf->global->$constFreeText, $substitutionarray);
			}
			$html .= nl2br($newfreetext);
		}
		$html .= "</p>\n";

		// Right header with date, reference, customer...
		$html .= "<p class=\"right\">\n";
		$html .= dol_escape_htmltag($langs->trans('Date')) . " " . dol_print_date($object->date, 'day') . '<br>';
		if (!empty($conf->global->TAKEPOS_RECEIPT_NAME)) {
			$html .= dol_escape_htmltag($conf->global->TAKEPOS_RECEIPT_NAME) . " ";
		}
		if ($object->status == Facture::STATUS_DRAFT) {
			// Show terminal and table with friendly format
			$ref = $object->ref;
			$ref = str_replace('(PROV-POS', $langs->trans('Terminal') . ' ', $ref);
			$ref = str_replace('-', ' ' . $langs->trans('Place') . ' ', $ref);
			$ref = str_replace(')', '', $ref);
			$html .= dol_escape_htmltag($ref);
		} else {
			$html .= dol_escape_htmltag($object->ref);
		}
		if (!empty($conf->global->TAKEPOS_SHOW_CUSTOMER)) {
			if ($object->socid != (int)($conf->global->{'CASHDESK_ID_THIRDPARTY' . $_SESSION['takeposterminal']})) {
				// Show customer if not the generic terminal customer
				dol_include_once('/societe/class/societe.class.php');
				$soc = new Societe($db);
				if ($object->socid > 0) {
					$soc->fetch($object->socid);
				} else {
					$soc->fetch((int)($conf->global->{'CASHDESK_ID_THIRDPARTY' . $_SESSION['takeposterminal']}));
				}
				$html .= '<br>' . dol_escape_htmltag($langs->trans('Customer')) . ': ' . dol_escape_htmltag($soc->name);
			}
		}
		if (!empty($conf->global->TAKEPOS_SHOW_DATE_OF_PRINING)) {
			$html .= '<br>' . dol_escape_htmltag($langs->trans('DateOfPrinting')) . ': ' . dol_print_date(dol_now(), 'dayhour', 'tzuserrel') . '<br>';
		}
		$html .= "</p>\n<br>\n";

		// Lines table
		$html .= '<table class="centpercent" style="border-top-style: double;">';
		$html .= '<thead><tr>';
		$html .= '<th class="center">' . dol_escape_htmltag($langs->trans('Label')) . '</th>';
		$html .= '<th class="right">' . dol_escape_htmltag($langs->trans('Qty')) . '</th>';
		$html .= '<th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('Price')) : '') . '</th>';
		if (!empty($conf->global->TAKEPOS_SHOW_HT_RECEIPT)) {
			$html .= '<th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('TotalHT')) : '') . '</th>';
		}
		$html .= '<th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('TotalTTC')) : '') . '</th>';
		$html .= '</tr></thead>';
		$html .= '<tbody>';

		if ($receiptAction == 'without_details') {
			$qty = (GETPOSTINT('qty') > 0 ? GETPOSTINT('qty') : 1);
			$html .= '<tr>';
			$html .= '<td>' . GETPOST('label', 'alphanohtml') . '</td>';
			$html .= '<td class="right">' . $qty . '</td>';
			$html .= '<td class="right">' . price(price2num($object->total_ttc / $qty, 'MU'), 1) . '</td>';
			if (!empty($conf->global->TAKEPOS_SHOW_HT_RECEIPT)) {
				$html .= '<td class="right">' . price($object->total_ht, 1) . '</td>';
			}
			$html .= '<td class="right">' . price($object->total_ttc, 1) . '</td>';
			$html .= '</tr>';
		} else {
			foreach ((array) $object->lines as $line) {
				$html .= '<tr>';
				$html .= '<td>' . (!empty($line->product_label) ? dol_escape_htmltag($line->product_label) : $line->desc) . '</td>';
				$html .= '<td class="right">' . dol_escape_htmltag($line->qty) . '</td>';
				$html .= '<td class="right">' . ($gift != 1 ? price(price2num($line->total_ttc / max(1, $line->qty), 'MT'), 1) : '') . '</td>';
				if (!empty($conf->global->TAKEPOS_SHOW_HT_RECEIPT)) {
					$html .= '<td class="right">' . ($gift != 1 ? price($line->total_ht, 1) : '') . '</td>';
				}
				$html .= '<td class="right">' . ($gift != 1 ? price($line->total_ttc, 1) : '') . '</td>';
				$html .= '</tr>';
			}
		}
		$html .= '</tbody></table>';
		$html .= '<br>';

		// Totals
		$html .= '<table class="right centpercent">';
		$html .= '<tr><th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('TotalHT')) : '') . '</th>';
		$html .= '<td class="right">' . ($gift != 1 ? price($object->total_ht, 1, '', 1, -1, -1, $conf->currency) : '') . "\n" . '</td></tr>';

		// Grouped or ordinary VAT
		if (!empty($conf->global->TAKEPOS_TICKET_VAT_GROUPPED)) {
			$vat_groups = array();
			foreach ((array) $object->lines as $line) {
				if (!array_key_exists($line->tva_tx, $vat_groups)) $vat_groups[$line->tva_tx] = 0;
				$vat_groups[$line->tva_tx] += $line->total_tva;
			}
			foreach ($vat_groups as $key => $val) {
				$html .= '<tr>';
				$html .= '<th align="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('VAT')) . ' ' . vatrate($key, true) : '') . '</th>';
				$html .= '<td align="right">' . ($gift != 1 ? price($val, 1, '', 1, -1, -1, $conf->currency) : '') . "\n" . '</td>';
				$html .= '</tr>';
			}
		} else {
			$html .= '<tr><th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('TotalVAT')) . '</th><td class="right">' . price($object->total_tva, 1, '', 1, -1, -1, $conf->currency) . "\n" : '') . '</td></tr>';
		}

		// Local taxes
		if (price2num($object->total_localtax1, 'MU') || $mysoc->useLocalTax(1)) {
			$html .= '<tr><th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('TotalLT1')) . '</th><td class="right">' . price($object->total_localtax1, 1, '', 1, -1, -1, $conf->currency) . "\n" : '') . '</td></tr>';
		}
		if (price2num($object->total_localtax2, 'MU') || $mysoc->useLocalTax(2)) {
			$html .= '<tr><th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('TotalLT2')) . '</th><td class="right">' . price($object->total_localtax2, 1, '', 1, -1, -1, $conf->currency) . "\n" : '') . '</td></tr>';
		}

		$html .= '<tr><th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('TotalTTC')) . '</th><td class="right">' . price($object->total_ttc, 1, '', 1, -1, -1, $conf->currency) . "\n" : '') . '</td></tr>';

		// Customer multicurrency on ticket
		if ($conf->multicurrency->enabled && !empty($_SESSION['takeposcustomercurrency']) && $_SESSION['takeposcustomercurrency'] != '' && $conf->currency != $_SESSION['takeposcustomercurrency']) {
			include_once DOL_DOCUMENT_ROOT . '/multicurrency/class/multicurrency.class.php';
			$multicurrency = new MultiCurrency($db);
			$multicurrency->fetch(0, $_SESSION['takeposcustomercurrency']);
			$html .= '<tr><th class="right">' . ($gift != 1 ? dol_escape_htmltag($langs->trans('TotalTTC')) . ' ' . dol_escape_htmltag($_SESSION['takeposcustomercurrency']) . '</th><td class="right">' . price($object->total_ttc * $multicurrency->rate->rate, 1, '', 1, -1, -1, $_SESSION['takeposcustomercurrency']) . "\n" : '') . '</td></tr>';
		}

		// Payment methods (if applicable)
		if (!empty($conf->global->TAKEPOS_PRINT_PAYMENT_METHOD)) {
			if (empty($object->id)) {
				// Specimen
				$html .= '<tr><td class="right">' . $langs->transnoentitiesnoconv('PaymentTypeShortLIQ') . '</td><td class="right">' . price(0, 1, '', 1, -1, -1, $conf->currency) . '</td></tr>';
			} else {
				$sql = "SELECT p.pos_change as pos_change, p.datep as date, p.fk_paiement, p.num_paiement as num, ";
				$sql .= " f.multicurrency_code, pf.amount as amount, pf.multicurrency_amount, cp.code";
				$sql .= " FROM " . MAIN_DB_PREFIX . "paiement_facture as pf, " . MAIN_DB_PREFIX . "facture as f, " . MAIN_DB_PREFIX . "paiement as p";
				$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_paiement as cp ON p.fk_paiement = cp.id";
				$sql .= " WHERE pf.fk_facture = f.rowid AND pf.fk_paiement = p.rowid AND pf.fk_facture = " . ((int) $object->id);
				$sql .= " ORDER BY p.datep";
				$resql = $db->query($sql);
				if ($resql) {
					$num = $db->num_rows($resql);
					$i = 0;
					while ($i < $num) {
						$row = $db->fetch_object($resql);
						$html .= '<tr>';
						$html .= '<td class="right">' . $langs->transnoentitiesnoconv('PaymentTypeShort' . $row->code) . '</td>';
						$html .= '<td class="right">';
						$amount_payment = ($conf->multicurrency->enabled && $object->multicurrency_tx != 1) ? $row->multicurrency_amount : $row->amount;
						if ((!$conf->multicurrency->enabled || $object->multicurrency_tx == 1) && $row->code == 'LIQ' && $row->pos_change > 0) {
							$amount_payment += $row->pos_change; // include cash received + change
							$currency = $conf->currency;
						} else {
							$currency = $row->multicurrency_code;
						}
						$html .= price($amount_payment, 1, '', 1, -1, -1, $currency);
						$html .= '</td>';
						$html .= '</tr>';
						if ((!$conf->multicurrency->enabled || $object->multicurrency_tx == 1) && $row->code == 'LIQ' && $row->pos_change > 0) {
							$html .= '<tr><td class="right">' . dol_escape_htmltag($langs->trans('Change')) . '</td><td class="right">' . price($row->pos_change, 1, '', 1, -1, -1, $currency) . '</td></tr>';
						}
						$i++;
					}
				}
			}
		}
		$html .= '</table>';

		// Separator/footer area
		$html .= '<div style="border-top-style: double;">';

		// Free footer
		$constFreeText = 'TAKEPOS_FOOTER' . (empty($_SESSION['takeposterminal']) ? '0' : $_SESSION['takeposterminal']);
		if (!empty($conf->global->TAKEPOS_FOOTER) || !empty($conf->global->$constFreeText)) {
			$newfreetext = '';
			$substitutionarray = getCommonSubstitutionArray($langs);
			complete_substitutions_array($substitutionarray, $langs, $object);
			if (!empty($conf->global->$constFreeText)) {
				$newfreetext .= make_substitutions($conf->global->$constFreeText, $substitutionarray);
			}
			if (!empty($conf->global->TAKEPOS_FOOTER)) {
				$newfreetext .= make_substitutions($conf->global->TAKEPOS_FOOTER, $substitutionarray);
			}
			$html .= $newfreetext;
		}

		// Autoprint only if not specimen
		$html .= "\n<script type=\"text/javascript\">";
		if (!empty($object->id)) {
			$html .= 'window.print();';
		}
		$html .= "</script>\n";

		$html .= "</div>\n"; // close div with top border
		$html .= "</body>\n</html>\n";

		$this->resprints = $html;
	}

	public function ODTSubstitution(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;
		$langs->load("verifactu@verifactu");

		if ($parameters['object'] instanceof Facture && isFactureSendToVerifactu($parameters['object'])) {
			// Only generate 3 fixed sizes: 30, 35 and 40 mm (simplified)
			$sizes = [30, 35, 40];

			// Generate high quality base QR once
			$qrBase64 = getQrBase64($parameters['object'], 300, 0);
			$tempOriginal = null;

			if (!empty($qrBase64)) {
				try {
					// Extract clean Base64 and save once
					$base64Clean = substr($qrBase64, strpos($qrBase64, ',') + 1);
					$imageData = base64_decode($base64Clean);
					$tempOriginal = sys_get_temp_dir() . '/qr_verifactu_base.png';
					file_put_contents($tempOriginal, $imageData);
				} catch (Exception $e) {
					dol_syslog("Error generating base QR code: " . $e->getMessage(), LOG_ERR);
				}
			}

			// Generate the 3 sizes with "QR tributario:" text above and "VeriFactu" below
			dol_include_once('/core/lib/images.lib.php');

			// Get text size from configuration (default 8)
			$textSize = !empty($conf->global->VERIFACTU_QR_TEXT_SIZE) ? (int)$conf->global->VERIFACTU_QR_TEXT_SIZE : 8;
			// Map configured size (8-14) to GD font (1-5)
			// Size 8 or less -> font 3, 9-11 -> font 4, 12+ -> font 5
			if ($textSize <= 8) {
				$font = 3;
			} elseif ($textSize <= 11) {
				$font = 4;
			} else {
				$font = 5;
			}

			foreach ($sizes as $mm) {
				if ($tempOriginal && file_exists($tempOriginal)) {
					try {
						// Calculate pixels needed for size in mm
						$dpi = 96;
						$targetPixels = (int) round(($mm * $dpi) / 25.4);

						// Resize QR using Dolibarr function
						$tempResized = sys_get_temp_dir() . '/qr_verifactu_' . $mm . 'mm.png';
						$result = dol_imageResizeOrCrop($tempOriginal, 0, $targetPixels, $targetPixels, 0, 0, $tempResized, 100);

						if (!is_string($result) || strpos($result, 'Error') === 0) {
							$qrPath = $tempOriginal;
						} else {
							$qrPath = $tempResized;
						}

						// Create image with text above and below
						$qrImage = imagecreatefrompng($qrPath);
						$qrWidth = imagesx($qrImage);
						$qrHeight = imagesy($qrImage);

						// Texts
						$topText = $langs->trans('VerifactuQRTributario'); // "QR tributario:"
						$bottomText = $langs->trans('VerifactuQRLegend'); // "VeriFactu"

						// Calculate text dimensions with configured font
						$charWidth = imagefontwidth($font);
						$charHeight = imagefontheight($font);

						$topTextWidth = strlen($topText) * $charWidth;
						$topTextHeight = $charHeight;
						$bottomTextWidth = strlen($bottomText) * $charWidth;
						$bottomTextHeight = $charHeight;

						// Very reduced margin between text and QR (closer)
						$textMargin = max(2, (int)($targetPixels * 0.02)); // Minimum 2px, maximum 2% of QR

						// Create new image with space for texts (no extra side margins)
						$totalWidth = max($qrWidth, $topTextWidth, $bottomTextWidth);
						$totalHeight = $topTextHeight + $textMargin + $qrHeight + $textMargin + $bottomTextHeight;
						$finalImage = imagecreatetruecolor($totalWidth, $totalHeight);

						// White background
						$white = imagecolorallocate($finalImage, 255, 255, 255);
						$black = imagecolorallocate($finalImage, 0, 0, 0);
						imagefill($finalImage, 0, 0, $white);

						// Draw centered upper text (closer to QR)
						$topX = (int)(($totalWidth - $topTextWidth) / 2);
						$topY = 0;
						imagestring($finalImage, $font, $topX, $topY, $topText, $black);

						// Copy QR in center (just below text)
						$qrX = (int)(($totalWidth - $qrWidth) / 2);
						$qrY = $topTextHeight + $textMargin;
						imagecopy($finalImage, $qrImage, $qrX, $qrY, 0, 0, $qrWidth, $qrHeight);

						// Draw centered lower text (closer to QR)
						$bottomX = (int)(($totalWidth - $bottomTextWidth) / 2);
						$bottomY = $qrY + $qrHeight + $textMargin;
						imagestring($finalImage, $font, $bottomX, $bottomY, $bottomText, $black);

						// Save final image
						$tempFinal = sys_get_temp_dir() . '/qr_verifactu_' . $mm . 'mm_final.png';
						imagepng($finalImage, $tempFinal);

						// Free memory
						imagedestroy($qrImage);
						imagedestroy($finalImage);

						$parameters['substitutionarray']['verifactuqrcode' . $mm . '_logo'] = $tempFinal;
					} catch (Exception $e) {
						dol_syslog("Error creating QR with text for {$mm}mm: " . $e->getMessage(), LOG_ERR);
						$parameters['substitutionarray']['verifactuqrcode' . $mm . '_logo'] = $tempOriginal;
					}
				} else {
					$parameters['substitutionarray']['verifactuqrcode' . $mm . '_logo'] = '';
				}
			}
		} else {
			// If not sent to VeriFactu, initialize the 3 variables as empty
			foreach ([30, 35, 40] as $mm) {
				$parameters['substitutionarray']['verifactuqrcode' . $mm . '_logo'] = '';
			}
		}
	}

	public function pdf_build_address(&$parameters, &$object, &$action, $hookmanager)
	{
		global $langs;
		$langs->load("verifactu@verifactu");
		// If invoice is simplified, recipient address field must be empty
		if ($parameters['mode'] == 'target' && $object->fetch_optionals() && $object->array_options['options_verifactu_factura_tipo'] == Sietekas\Verifactu\VerifactuInvoice::TYPE_SIMPLIFIED) {
			$this->resprints =  $langs->trans("verifactu_SIMPLIFIED_INVOICE_CUSTOMER");
			return 1;
		}
	}


	/**
	 * Execute action
	 *
	 * @param	array	$parameters     Array of parameters
	 * @param   Object	$object		   	Object output on PDF
	 * @param   string	$action     	'add', 'update', 'view'
	 * @return  int 		        	<0 if KO,
	 *                          		=0 if OK but we want to process standard actions too,
	 *  	                            >0 if OK and we want to replace standard actions.
	 */
	public function beforePDFCreation($parameters, &$object, &$action)
	{
		global  $langs;

		$langs->load("verifactu@verifactu");
		if ($object->fetch_optionals() && $object->array_options['options_verifactu_factura_tipo'] == Sietekas\Verifactu\VerifactuInvoice::TYPE_SIMPLIFIED) {
			$langs->tab_translate["PdfInvoiceTitle"] = $langs->trans("verifactu_FACTURA_Simplificada"); // Translation ID for "Simplified Invoice"
		}
		return 0;
	}

}
