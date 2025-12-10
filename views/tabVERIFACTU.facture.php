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
 * \file    verifactu/views/tabVERIFACTU.facture.php
 * \ingroup verifactu
 * \brief   VeriFactu tab on invoice card.
 *
 * Displays VeriFactu status information and allows:
 * - Submit invoices to AEAT (Creation, Modification, Cancellation)
 * - Verify invoice status in AEAT
 * - Query sent data and received responses
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
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
if (!($user->rights->verifactu->manage || $user->admin)) {
	accessForbidden();
}
// Check if SOAP extension is installed
if (!extension_loaded('soap')) {
	accessForbidden($langs->trans('SOAP_EXTENSION_NOT_INSTALLED'));
}

dol_include_once('/verifactu/lib/dumper.php');
dol_include_once('/verifactu/lib/verifactu.lib.php');
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/discount.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/invoice.lib.php';
if ($conf->project->enabled) {
	require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
}

// Load translation files required by the page
$langs->loadLangs(array('companies', 'bills'));

$id = (GETPOST('id', 'int') ? GETPOST('id', 'int') : GETPOST('facid', 'int')); // For backward compatibility
$ref = GETPOST('ref', 'alpha');
$socid = GETPOST('socid', 'int');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$object = new Facture($db);
// Load object
if ($id > 0 || !empty($ref)) {
	$object->fetch($id, $ref, '', '', (!empty($conf->global->INVOICE_USE_SITUATION) ? $conf->global->INVOICE_USE_SITUATION : 0));
}

$permissionnote = $user->rights->facture->creer; // Used by the include of actions_setnotes.inc.php

// Security check
$socid = 0;
if ($user->socid) {
	$socid = $user->socid;
}
$hookmanager->initHooks(array('invoicecard'));

$result = restrictedArea($user, 'facture', $id, '');


/*
 * Actions
 */

$reshook = $hookmanager->executeHooks('doActions', array(), $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (in_array(GETPOST('EXECUTEVERIFACTU'), array('Alta', 'Mod', 'Baja')) && $action == 'confirm_EXECUTEVERIFACTU' && $confirm == 'yes') {


	$actionVERIFACTU = GETPOST('EXECUTEVERIFACTU');


	//
	try {
		execVERIFACTUCall($object, $actionVERIFACTU);
	} catch (\Throwable $e) {
		// Capture and display detailed error information
		setEventMessages($e->getMessage(), null, 'errors');
	}
	$confirm = 'no';
	$object->fetch($id, $ref);
}

// Action: Verify Status in AEAT
if (GETPOST('EXECUTEVERIFACTU') == 'VerificarEstado') {
	try {
		$resultVerificacion = verificarEstadoFacturaAEAT($object);
		if ($resultVerificacion['success']) {
			setEventMessages($resultVerificacion['message'], null, 'mesgs');
		} else {
			setEventMessages($resultVerificacion['message'], null, 'warnings');
		}
	} catch (\Throwable $e) {
		setEventMessages($e->getMessage(), null, 'errors');
	}
	$confirm = 'no';
	$object->fetch($id, $ref);
}


/*
 * View
 */

if (empty($object->id)) {
	llxHeader();
	$langs->load('errors');
	echo '<div class="error">' . $langs->trans("ErrorRecordNotFound") . '</div>';
	llxFooter();
	exit;
}

$title = $langs->trans('InvoiceCustomer') . " - " . $langs->trans('VeriFactu');
$helpurl = "EN:Customers_Invoices|FR:Factures_Clients|ES:Facturas_a_clientes";
llxHeader('', $title, $helpurl);

$form = new Form($db);


if ($id > 0 || !empty($ref)) {
	$object = new Facture($db);
	$object->fetch($id, $ref);

	$object->fetch_thirdparty();

	$head = facture_prepare_head($object);

	$totalpaid = $object->getSommePaiement();

	print dol_get_fiche_head($head, 'factureVERIFACTU_TAB', $langs->trans("InvoiceCustomer"), -1, 'bill');

	// Invoice content

	$linkback = '<a href="' . DOL_URL_ROOT . '/compta/facture/list.php?restore_lastsearch_values=1' . (!empty($socid) ? '&socid=' . $socid : '') . '">' . $langs->trans("BackToList") . '</a>';

	$morehtmlref = '<div class="refidno">';
	// Ref customer
	$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', 0, 1);
	$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', null, null, '', 1);
	// Thirdparty
	$morehtmlref .= '<br>' . $langs->trans('ThirdParty') . ' : ' . $object->thirdparty->getNomUrl(1, 'customer');
	// Project
	if ($conf->project->enabled) {
		$langs->load("projects");
		$morehtmlref .= '<br>' . $langs->trans('Project') . ' ';
		if ($user->rights->facture->creer) {
			if ($action != 'classify') {
				//$morehtmlref.='<a class="editfielda" href="' . $_SERVER['PHP_SELF'] . '?action=classify&token='.(function_exists('newToken') ? newToken() : $_SESSION['newtoken']).'&id=' . $object->id . '">' . img_edit($langs->transnoentitiesnoconv('SetProject')) . '</a> : ';
				$morehtmlref .= ' : ';
			}
			if ($action == 'classify') {
				//$morehtmlref.=$form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'projectid', 0, 0, 1, 1);
				$morehtmlref .= '<form method="post" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
				$morehtmlref .= '<input type="hidden" name="action" value="classin">';
				$morehtmlref .= '<input type="hidden" name="token" value="' . (function_exists('newToken') ? newToken() : $_SESSION['newtoken']) . '">';
				$morehtmlref .= $formproject->select_projects($object->socid, $object->fk_project, 'projectid', $maxlength, 0, 1, 0, 1, 0, 0, '', 1);
				$morehtmlref .= '<input type="submit" class="button valignmiddle" value="' . $langs->trans("Modify") . '">';
				$morehtmlref .= '</form>';
			} else {
				$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'] . '?id=' . $object->id, $object->socid, $object->fk_project, 'none', 0, 0, 0, 1);
			}
		} else {
			if (!empty($object->fk_project)) {
				$proj = new Project($db);
				$proj->fetch($object->fk_project);
				$morehtmlref .= ' : ' . $proj->getNomUrl(1);
				if ($proj->title) {
					$morehtmlref .= ' - ' . $proj->title;
				}
			} else {
				$morehtmlref .= '';
			}
		}
	}
	$morehtmlref .= '</div>';

	$morehtmlref .= '<table class="border centpercent tableforfield">';
	// Remove all array elements that don't have 'verifactu_' in their key
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $value) {
		if (strpos($key, 'verifactu_') === false || strpos($key, 'separador') !== false) {

			unset($extrafields->attributes[$object->table_element]['label'][$key]);
		}
	}
	ob_start();

	// Include the file (content is stored in the buffer)
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';

	// Get buffer content and store it in the $morehtmlref variable
	$morehtmlref .= ob_get_clean();



	$morehtmlref .= '</table>';

	$object->totalpaid = $totalpaid; // To give a chance to dol_banner_tab to use already paid amount to show correct status

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	// Display extrafields using HTML
	if (in_array(GETPOST('EXECUTEVERIFACTU'), array('Alta', 'Mod', 'Baja')) && $confirm != 'no') {
		$formquestion = array(
			array('type' => 'hidden', 'name' => 'EXECUTEVERIFACTU', 'value' => GETPOST('EXECUTEVERIFACTU')),
			array('type' => 'hidden', 'name' => 'id', 'value' => $object->id),
		);

		// If it's a Cancellation operation, add cancellation type selector
		if (GETPOST('EXECUTEVERIFACTU') == 'Baja') {
			$tiposAnulacion = array(
				'normal' => $langs->trans('VERIFACTU_ANULACION_NORMAL'),
				'rechazo_previo' => $langs->trans('VERIFACTU_ANULACION_RECHAZO_PREVIO'),
				'sin_registro_previo' => $langs->trans('VERIFACTU_ANULACION_SIN_REGISTRO_PREVIO')
			);

			// Add information about each cancellation type
			$infoAnulacion = '<div style="margin-top: 10px; padding: 10px; background-color: #f0f8ff; border: 1px solid #cce7ff; border-radius: 5px;">';
			$infoAnulacion .= '<strong>' . $langs->trans('VERIFACTU_TIPO_ANULACION') . ':</strong><br>';
			$infoAnulacion .= '• ' . $langs->trans('VERIFACTU_INFO_ANULACION_NORMAL') . '<br>';
			$infoAnulacion .= '• ' . $langs->trans('VERIFACTU_INFO_ANULACION_RECHAZO_PREVIO') . '<br>';
			$infoAnulacion .= '• ' . $langs->trans('VERIFACTU_INFO_ANULACION_SIN_REGISTRO_PREVIO');
			$infoAnulacion .= '</div>';

			$formquestion[] = array(
				'type' => 'select',
				'name' => 'tipo_anulacion',
				'label' => $langs->trans('VERIFACTU_TIPO_ANULACION'),
				'values' => $tiposAnulacion,
				'default' => 'normal'
			);

			$formquestion[] = array(
				'type' => 'other',
				'value' => $infoAnulacion
			);
		}

		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('CONFIRM_SEND_TO_AEAT_TITLE'), $langs->trans('CONFIRM_SEND_TO_AEAT_MESSAGE'), 'confirm_EXECUTEVERIFACTU', $formquestion, 0, 1, 600, 800, 1);
		print $formconfirm;
	}

	if (empty(GETPOST('EXECUTEVERIFACTU')) || (GETPOSTISSET('EXECUTEVERIFACTU')) && $confirm == 'no') {

		// Validations to determine if the invoice can be sent
		$canSend = true;
		$canSendAlta = true;
		$canSendMod = true;
		$canSendBaja = true;
		$errorMessage = '';
		$isAlreadySentCorrectly = false;
		$hasError = false;

		// Check current VeriFactu status
		$estadoVERIFACTU = $object->array_options['options_verifactu_estado'] ?? '';
		$errorVERIFACTU = $object->array_options['options_verifactu_error'] ?? '';
		$csvVERIFACTU = $object->array_options['options_verifactu_csv_factura'] ?? '';

		// Detect if the invoice is correctly sent (has CSV and success status)
		if (!empty($csvVERIFACTU) && !empty($estadoVERIFACTU)) {
			// Success states: SEND, Correcto, badge-status4 (green)
			if (strpos($estadoVERIFACTU, 'VERIFACTU_STATUS_SEND') !== false ||
				strpos($estadoVERIFACTU, 'badge-status4') !== false ||
				strpos($estadoVERIFACTU, 'Correcto') !== false) {
				$isAlreadySentCorrectly = true;
			}
		}

		// Detect if there's an error requiring verification (like duplicate with different amounts)
		if (!empty($estadoVERIFACTU)) {
			if (strpos($estadoVERIFACTU, 'VERIFACTU_STATUS_DUPLICATE_DIFF') !== false ||
				strpos($estadoVERIFACTU, 'badge-status1') !== false ||
				strpos($estadoVERIFACTU, 'badge-status8') !== false) {
				$hasError = true;
			}
		}

		// Validation 1: Invoice status must be greater than 0 (validated)
		if ($object->statut <= 0) {
			$canSend = false;
			$errorMessage .= '• ' . $langs->trans('VERIFACTU_VALIDATION_INVOICE_NOT_VALIDATED') . '<br>';
		}

		// Validation 2: Reference must not contain "PROV" (not provisional)
		if (strpos($object->ref, 'PROV') !== false) {
			$canSend = false;
			$errorMessage .= '• ' . $langs->trans('VERIFACTU_VALIDATION_INVOICE_PROVISIONAL') . '<br>';
		}

		// Validation 3: Invoice must not be cancelled in VERIFACTU
		if (!empty($estadoVERIFACTU)) {
			if (strpos($estadoVERIFACTU, 'Anulada') !== false || strpos($estadoVERIFACTU, 'VERIFACTU_STATUS_ANULADA') !== false) {
				$canSend = false;
				$errorMessage .= '• ' . $langs->trans('VERIFACTU_ERROR_FACTURA_YA_ANULADA') . '<br>';
			}
		}

		// Validation 4: If invoice is already sent correctly, disable Creation and Modification
		if ($isAlreadySentCorrectly) {
			$canSendAlta = false;
			$canSendMod = false;
			// Cancellation remains enabled to allow invoice cancellation if needed
		}

		print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
		print '<input type="hidden" name="id" value="' . $object->id . '">';
		print '<input type="hidden" name="token" value="' . (function_exists('newToken') ? newToken() : $_SESSION['newtoken']) . '">';

		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>' . $langs->trans('VERIFACTU_ACTIONS_SECTION_TITLE') . '</td>';
		print '</tr>';
		print '<tr class="oddeven">';
		print '<td class="center">';

		if ($canSend) {
			// Show informational message if invoice is already sent correctly
			if ($isAlreadySentCorrectly) {
				print '<div class="info">';
				print $langs->trans('VERIFACTU_INVOICE_ALREADY_SENT_CORRECTLY');
				print '</div>';
			}

			// Show buttons based on status
			print '<div class="tabsAction">';

			// Creation button
			if ($canSendAlta) {
				print '<button name="EXECUTEVERIFACTU" value="Alta" class="butAction">' . $langs->trans('VERIFACTU_BUTTON_SENDTO_ALTA') . '</button>';
			} else {
				print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('VERIFACTU_ALREADY_SENT_TOOLTIP')) . '">' . $langs->trans('VERIFACTU_BUTTON_SENDTO_ALTA') . '</span>';
			}

			// Modification button
			if ($canSendMod) {
				print '<button name="EXECUTEVERIFACTU" value="Mod" class="butAction">' . $langs->trans('VERIFACTU_BUTTON_SENDTO_MOD') . '</button>';
			} else {
				print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('VERIFACTU_ALREADY_SENT_TOOLTIP')) . '">' . $langs->trans('VERIFACTU_BUTTON_SENDTO_MOD') . '</span>';
			}

			// Cancellation button (always active if canSend)
			if ($canSendBaja) {
				print '<button name="EXECUTEVERIFACTU" value="Baja" class="butAction">' . $langs->trans('VERIFACTU_BUTTON_SENDTO_BAJA') . '</button>';
			}

			// Verify Status button - show if there's an error or to verify status
			if ($hasError || !empty($estadoVERIFACTU)) {
				print '<button name="EXECUTEVERIFACTU" value="VerificarEstado" class="butAction">' . $langs->trans('VERIFACTU_BUTTON_VERIFY_STATUS') . '</button>';
			}

			print '</div>';
		} else {
			// Show error message and disabled buttons
			print '<div class="warning">';
			print $langs->trans('VERIFACTU_CANNOT_SEND_TITLE') . '<br>';
			print $errorMessage;
			print '</div>';
			print '<div class="tabsAction">';
			print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('VERIFACTU_CANNOT_SEND_TITLE')) . '">' . $langs->trans('VERIFACTU_BUTTON_SENDTO_ALTA') . '</span>';
			print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('VERIFACTU_CANNOT_SEND_TITLE')) . '">' . $langs->trans('VERIFACTU_BUTTON_SENDTO_MOD') . '</span>';
			print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('VERIFACTU_CANNOT_SEND_TITLE')) . '">' . $langs->trans('VERIFACTU_BUTTON_SENDTO_BAJA') . '</span>';
			print '</div>';
		}

		print '</td>';
		print '</tr>';
		print '</table>';
		print '</div>';
		print '</form>';
	}
	print '<div class="underbanner clearboth"></div>';

	// BUTTONS
	/* QUERY */
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans('VERIFACTU_AEAT_DATA_SECTION_TITLE') . '</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>';

	if (!empty($object->array_options['options_verifactu_id_factura'])) {
		// Decode JSON with specific invoice data
		$facturaData = json_decode($object->array_options['options_verifactu_id_factura'], true);

		if ($facturaData && is_array($facturaData)) {
			$numeroFactura = $facturaData['NumSerieFactura'] ?? '';
			$fechaExpedicion = $facturaData['FechaExpedicionFactura'] ?? '';
			$nifEmisorFactura = $facturaData['IDEmisorFactura'] ?? '';

			if (!empty($numeroFactura) && !empty($fechaExpedicion)) {
				// Use date range with end date +1 day to avoid AEAT error
				global $conf;
				$nombreEmisor = (!empty($conf->global->VERIFACTU_NAME)) ? $conf->global->VERIFACTU_NAME : '';
				$nifEmisor = (!empty($conf->global->VERIFACTU_NIF)) ? $conf->global->VERIFACTU_NIF : '';

				// Create query object
				$consultaQuery = new \Sietekas\Verifactu\VerifactuInvoiceQuery(
					null,
					null,
					$nifEmisor,
					$nombreEmisor
				);

				// Configure query with specific date range (+1 day for end date)
				$fechaHasta = date('d-m-Y', strtotime($fechaExpedicion . ' +1 day'));
				$consultaQuery->setDateRange($fechaExpedicion, $fechaHasta);
				$consultaQuery->setSpecificInvoice($numeroFactura, $fechaExpedicion, $nifEmisorFactura);

				$filtroConsulta = $consultaQuery->getData();
				$res = execVERIFACTUQuery($filtroConsulta);
			} else {
				$res = false;
				print '<div class="error">';
				print 'Incomplete data: ';
				print 'Invoice data does not contain valid number or issue date.';
				print '</div>';
			}
		} else {
			// Fallback: use date range with +1 day
			global $conf;
			$nombreEmisor = (!empty($conf->global->VERIFACTU_NAME)) ? $conf->global->VERIFACTU_NAME : '';
			$nifEmisor = (!empty($conf->global->VERIFACTU_NIF)) ? $conf->global->VERIFACTU_NIF : '';

			// Try to extract date from current invoice
			$fechaFactura = '';
			if (!empty($object->date)) {
				$fechaFactura = date('d-m-Y', $object->date);
			} else {
				$fechaFactura = date('d-m-Y');
			}

			$consultaQuery = new \Sietekas\Verifactu\VerifactuInvoiceQuery(
				null,
				null,
				$nifEmisor,
				$nombreEmisor
			);

			// Configure query with specific date range (+1 day for end date)
			$fechaHasta = date('d-m-Y', strtotime($fechaFactura . ' +1 day'));
			$consultaQuery->setDateRange($fechaFactura, $fechaHasta);
			$consultaQuery->setSpecificInvoice(
				$object->array_options['options_verifactu_id_factura'],
				$fechaFactura,
				$nifEmisor
			);

			$filtroConsulta = $consultaQuery->getData();
			$res = execVERIFACTUQuery($filtroConsulta);
		}

		if ($res && $res->ResultadoConsulta == 'ConDatos') {
			$numFacturas = count($res->RegistroRespuestaConsultaFactuSistemaFacturacion);
			print '<div class="info">';
			print $langs->trans('Result') . ': ' . $numFacturas . ' ' . $langs->trans('Invoices') . '<br>';

			if (isset($numeroFactura)) {
				print $langs->trans('Ref') . ': ' . htmlspecialchars($numeroFactura) . '<br>';
				print $langs->trans('Date') . ': ' . htmlspecialchars($fechaExpedicion) . '<br>';
			}
			print '</div>';

			print '<div class="fichecenter">';
			print '<div class="fichehalfleft">';
			dumper(json_decode($object->array_options['options_verifactu_payload'], true), false, 'Payload Sent to AEAT');
			print '</div>';
			// Show only the first invoice (which should be the specific one)
			print '<div class="fichehalfright">';
			dumper($res->RegistroRespuestaConsultaFactuSistemaFacturacion[0], false, 'AEAT Query Response');
			print '</div>';
			print '</div>';
		} else if ($res && $res->ResultadoConsulta == 'SinDatos') {
			print '<div class="warning">';
			print 'No data found. The invoice does not exist in AEAT records or the search criteria do not match.';
			print '</div>';
		} else {
			print '<div class="error">';
			print 'Query error. Could not query the invoice in AEAT. Check the VeriFactu configuration.';
			print '</div>';
		}
	}

	print '</td>';
	print '</tr>';
	print '</table>';
	print '</div>';

	print dol_get_fiche_end();
}

// End of page
llxFooter();
$db->close();
