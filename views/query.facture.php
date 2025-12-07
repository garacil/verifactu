<?php
/* Copyright (C) 2002-2006 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004      Eric Seigne           <eric.seigne@ryxeo.com>
 * Copyright (C) 2004-2016 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyrprint '<td class="nowrap  bold cspan="50" le="font-size:1.2eprint '</tr>';
print '</tbody>';
print '</table>';

// jQuery script to sync NIF and Name
print '<script>$(document).ready(function() {$("#search_nif").on("keyup",function() {$("#search_nom").val($(this).val());});});</script>';padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6;">';

// Create filter table inside the cell
print '<table style="width: 100%; border-collapse: collapse;">';
print '<tr>';
print '<td style="width: 18%; padding: 5px; vertical-align: top;">';
print '<label for="search_facture" style="font-weight: bold; display: block; margin-bottom: 5px; font-size: 0.8em;">' . $langs->trans('FILTER_NUMERO_SERIE_FACTURA') . ':</label>';
print '<input class="flat" type="text" id="search_facture" name="search_facture" style="width: 100%;" value="' . $search_facture . '">';
print '</td>';
print '<td style="width: 15%; padding: 5px; vertical-align: top;">';
print '<label for="search_nif" style="font-weight: bold; display: block; margin-bottom: 5px; font-size: 0.8em;">' . $langs->trans('FILTER_NIF') . ':</label>';
print '<input class="flat" type="text" id="search_nif" name="search_nif" style="width: 100%;" value="' . $search_nif . '">';
print '</td>';
print '<td style="width: 18%; padding: 5px; vertical-align: top;">';
print '<label for="search_nom" style="font-weight: bold; display: block; margin-bottom: 5px; font-size: 0.8em;">' . $langs->trans('FILTER_NOM') . ':</label>';
print '<input class="flat" type="text" id="search_nom" name="search_nom" style="width: 100%;" value="' . $search_nom . '">';
print '</td>';
print '<td style="width: 15%; padding: 5px; vertical-align: top;">';
print '<label for="search_year" style="font-weight: bold; display: block; margin-bottom: 5px; font-size: 0.8em;">' . $langs->trans('FILTER_EJERCICIO_FACTURACION') . ':</label>';
print $input_year;
print '</td>';
print '<td style="width: 19%; padding: 5px; vertical-align: top;">';
print '<label for="search_month" style="font-weight: bold; display: block; margin-bottom: 5px; font-size: 0.8em;">' . $langs->trans('FILTER_PERIODO_FACTURACION') . ':</label>';
print $input_month;
print '</td>';
print '<td style="width: 15%; padding: 5px; text-align: center; vertical-align: bottom;">';
print '<input class="butAction" type="submit" value="' . $langs->trans('FilterButton') . '">';
print '</td>';
print '</tr>';
print '</table>';

print '</td>';ght (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2015 Regis Houssin         <regis.houssin@inodbox.com>
 * Copyright (C) 2006      Andre Cianfarani      <acianfa@free.fr>
 * Copyright (C) 2010-2020 Juanjo Menent         <jmenent@2byte.es>
 * Copyright (C) 2012      Christophe Battarel   <christophe.battarel@altairis.fr>
 * Copyright (C) 2013      Florian Henry         <florian.henry@open-concept.pro>
 * Copyright (C) 2013      Cédric Salvador       <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015      Jean-François Ferry   <jfefe@aternatik.fr>
 * Copyright (C) 2015-2022 Ferran Marcet         <fmarcet@2byte.es>
 * Copyright (C) 2017      Josep Lluís Amador    <joseplluis@lliuretic.cat>
 * Copyright (C) 2018      Charlene Benke        <charlie@patas-monkey.com>
 * Copyright (C) 2019-2021 Alexandre Spangaro    <aspangaro@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       verifactu/views/query.facture.php
 *	\ingroup    verifactu
 *	\brief      Query customer invoices from VERIFACTU
 *
 *  ADAPTED FOR VERIFACTU:
 *  - Changed from SII to VERIFACTU
 *  - Fixed translation references
 *  - Updated response structure (RegistroRespuestaConsultaFactuSistemaFacturacion)
 *  - Improved VAT breakdown logic for VERIFACTU
 *  - Protected access to optional fields (DatosPresentacion, EstadoFactura)
 *  - Fixed initialization of totals arrays
 *  - Implemented period vs specific invoice logic (PeriodoImputacion vs RangoFechaExpedicion)
 *  - Migrated to use VerifactuInvoiceQuery class for consistent query building
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
dol_include_once('/compta/facture/class/facture.class.php');
dol_include_once('/societe/class/societe.class.php');
dol_include_once('/core/class/htm.form.class.php');

/**
 * Function to show value with clipboard copy button
 *
 * @param string $value Value to display
 * @param int $showEmpty Show empty values (0 = no, 1 = yes)
 * @param string $title Tooltip title for the button
 * @return string HTML output with value and copy button
 */
if (!function_exists('showValueWithClipboardCPButton')) {
	function showValueWithClipboardCPButton($value, $showEmpty = 0, $title = 'Copy to clipboard')
	{
	global $langs;

	// If value is empty and we don't want to show empty values
	if (empty($value) && !$showEmpty) {
		return '-';
	}

	// Generate unique ID for each button
	$uniqueId = 'clipboard_' . md5(uniqid($value, true));

	// Escape value for HTML
	$displayValue = htmlspecialchars($value);
	$escapedValue = htmlspecialchars($value, ENT_QUOTES);

	// Truncate displayed value if too long (more than 50 characters)
	$truncatedDisplay = (strlen($displayValue) > 50) ? substr($displayValue, 0, 47) . '...' : $displayValue;

	$output = '<div style="display: inline-flex; align-items: center; gap: 5px;">';
	$output .= '<span title="' . $escapedValue . '">' . $truncatedDisplay . '</span>';
	$output .= '<button type="button" id="' . $uniqueId . '" class="clipboard-btn" ';
	$output .= 'style="background: none; border: none; cursor: pointer; padding: 2px 5px; color: #4a90e2; font-size: 14px;" ';
	$output .= 'title="' . htmlspecialchars($title) . '" ';
	$output .= 'onclick="copyToClipboard' . $uniqueId . '(); return false;">';
	$output .= '<i class="fa fa-clipboard"></i>';
	$output .= '</button>';
	$output .= '</div>';

	// Script to copy to clipboard
	$output .= '<script>';
	$output .= 'function copyToClipboard' . $uniqueId . '() {';
	$output .= '  var text = ' . json_encode($value) . ';';
	$output .= '  if (navigator.clipboard && navigator.clipboard.writeText) {';
	$output .= '    navigator.clipboard.writeText(text).then(function() {';
	$output .= '      var btn = document.getElementById("' . $uniqueId . '");';
	$output .= '      btn.style.color = "#28a745";';
	$output .= '      btn.innerHTML = \'<i class="fa fa-check"></i>\';';
	$output .= '      setTimeout(function() {';
	$output .= '        btn.style.color = "#4a90e2";';
	$output .= '        btn.innerHTML = \'<i class="fa fa-clipboard"></i>\';';
	$output .= '      }, 2000);';
	$output .= '    }).catch(function(err) {';
	$output .= '      console.error("Copy error: ", err);';
	$output .= '      fallbackCopy' . $uniqueId . '(text);';
	$output .= '    });';
	$output .= '  } else {';
	$output .= '    fallbackCopy' . $uniqueId . '(text);';
	$output .= '  }';
	$output .= '}';
	$output .= 'function fallbackCopy' . $uniqueId . '(text) {';
	$output .= '  var textarea = document.createElement("textarea");';
	$output .= '  textarea.value = text;';
	$output .= '  textarea.style.position = "fixed";';
	$output .= '  textarea.style.opacity = 0;';
	$output .= '  document.body.appendChild(textarea);';
	$output .= '  textarea.select();';
	$output .= '  try {';
	$output .= '    document.execCommand("copy");';
	$output .= '    var btn = document.getElementById("' . $uniqueId . '");';
	$output .= '    btn.style.color = "#28a745";';
	$output .= '    btn.innerHTML = \'<i class="fa fa-check"></i>\';';
	$output .= '    setTimeout(function() {';
	$output .= '      btn.style.color = "#4a90e2";';
	$output .= '      btn.innerHTML = \'<i class="fa fa-clipboard"></i>\';';
	$output .= '    }, 2000);';
	$output .= '  } catch(err) {';
	$output .= '    console.error("Copy error (fallback): ", err);';
	$output .= '  }';
	$output .= '  document.body.removeChild(textarea);';
	$output .= '}';
	$output .= '</script>';

	return $output;
	}
}


$search_year = GETPOST('search_year', 'int') > 0 ? GETPOST('search_year', 'int') : date('Y');
$search_month = GETPOST('search_month') ? GETPOST('search_month') : sprintf('%02d', date('m'));
$search_facture = GETPOST('search_facture', 'alpha') ?? null;
$search_nif = GETPOST('search_nif', 'alpha') ?? null;
$search_nom = GETPOST('search_nom', 'alpha') ?? null;
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');

$hookmanager->initHooks(array('invoicelistVERIFACTU'));

// Load translation files required by the page
$langs->loadLangs(array('bills', 'companies', 'products', 'categories', 'verifactu@verifactu'));



llxHeader('', $langs->trans('CustomersInvoicesVERIFACTUQuery'), 'EN:Customers_Invoices|FR:Factures_Clients|ES:Facturas_a_clientes');
$form = new Form($db);
print load_fiche_titre($langs->trans("CustomersInvoicesVERIFACTUQuery"), '', 'verifactu.png@verifactu');

print '<div class="fichecenter">';

// Build query filter for VERIFACTU using VerifactuInvoiceQuery
global $conf;
$nombreEmisor = (!empty($conf->global->VERIFACTU_HOLDER_COMPANY_NAME)) ? $conf->global->VERIFACTU_HOLDER_COMPANY_NAME : '';
$nifEmisor = (!empty($conf->global->VERIFACTU_HOLDER_NIF)) ? $conf->global->VERIFACTU_HOLDER_NIF : '';

$consultaQuery = new \Sietekas\Verifactu\VerifactuInvoiceQuery(
	null,
	null,
	$nifEmisor,
	$nombreEmisor
);

// For all queries, use PeriodoImputacion as base
$consultaQuery->setFiscalPeriod($search_year, $search_month);

// If a specific invoice is specified, add post-query filter
if ($search_facture) {
	// Search for the real invoice date in database to improve search
	$sql = "SELECT date_creation, datef FROM " . MAIN_DB_PREFIX . "facture WHERE ref = '" . $db->escape($search_facture) . "'";
	$result = $db->query($sql);

	$fechaFactura = null;
	if ($result && $db->num_rows($result) > 0) {
		$obj = $db->fetch_object($result);
		// Use invoice date if available, otherwise use creation date
		$fechaTimestamp = !empty($obj->datef) ? $obj->datef : (!empty($obj->date_creation) ? $obj->date_creation : null);
		if ($fechaTimestamp !== null) {
			$fechaFactura = date('d-m-Y', $fechaTimestamp);

			// If the found date is not in the queried period, adjust period
			$fechaObj = DateTime::createFromFormat('d-m-Y', $fechaFactura);
			if ($fechaObj !== false) {
				$yearFactura = $fechaObj->format('Y');
				$monthFactura = $fechaObj->format('m');

				// If invoice is in a different period, use that period
				if ($yearFactura != $search_year || $monthFactura != $search_month) {
					$consultaQuery->setFiscalPeriod($yearFactura, $monthFactura);
				}
			}
		}
	}

	// Add post-query filter for serial number (always)
	$filtrosPersonalizados = [
		'IDFactura' => [
			'NumSerieFacturaEmisor' => $search_facture
		]
	];

	// If we have exact date, add it to the filter as well
	if ($fechaFactura) {
		$filtrosPersonalizados['IDFactura']['FechaExpedicionFacturaEmisor'] = $fechaFactura;
	}

	$consultaQuery->addCustomFilters($filtrosPersonalizados);
}

// Add counterparty filters if specified (allow independent filters)
if ($search_nif || $search_nom) {
	// If we only have NIF, use empty name
	$nombre = $search_nom ? $search_nom : '';
	$nif = $search_nif ? $search_nif : '';

	if ($nif) {
		$consultaQuery->setCounterparty($nif, $nombre);
	}
}

$filtroConsulta = $consultaQuery->getData();


$res = execVERIFACTUQuery($filtroConsulta);

// Get results from AEAT
$registros = array();
if ($res && isset($res->ResultadoConsulta) && $res->ResultadoConsulta && isset($res->RegistroRespuestaConsultaFactuSistemaFacturacion)) {
	$registros = $res->RegistroRespuestaConsultaFactuSistemaFacturacion;
	if (!is_array($registros)) {
		$registros = array($registros);
	}
}

// Apply local partial search filters (approximate matching)
if (!empty($registros) && ($search_facture || $search_nif || $search_nom)) {
	$registros_filtrados = array();
	foreach ($registros as $invoice) {
		$match = true;

		// Filter by invoice number (partial match)
		if ($search_facture && $match) {
			$numSerie = isset($invoice->IDFactura->NumSerieFactura) ? $invoice->IDFactura->NumSerieFactura : '';
			if (stripos($numSerie, $search_facture) === false) {
				$match = false;
			}
		}

		// Filter by NIF (partial match)
		if ($search_nif && $match) {
			$nifFactura = '';
			if (isset($invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario->NIF)) {
				$nifFactura = $invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario->NIF;
			}
			if (stripos($nifFactura, $search_nif) === false) {
				$match = false;
			}
		}

		// Filter by name (partial match)
		if ($search_nom && $match) {
			$nombreFactura = '';
			if (isset($invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario->NombreRazon)) {
				$nombreFactura = $invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario->NombreRazon;
			}
			if (stripos($nombreFactura, $search_nom) === false) {
				$match = false;
			}
		}

		if ($match) {
			$registros_filtrados[] = $invoice;
		}
	}
	$registros = $registros_filtrados;
}

$num_facturas = count($registros);

// Prepare select inputs
$years = range(2020, 2030);
$input_year = $form->selectarray('search_year', $years, $search_year, 0, 0, 1, '', 0, 0, 0, '', 'minwidth75');
$input_month = $form->selectarray('search_month', array(
	'01' => '01', '02' => '02', '03' => '03', '04' => '04',
	'05' => '05', '06' => '06', '07' => '07', '08' => '08',
	'09' => '09', '10' => '10', '11' => '11', '12' => '12',
), $search_month, 0, 0, 0, '', 0, 0, 0, '', 'minwidth50');

// Form and table - standard Dolibarr style
print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . (function_exists('newToken') ? newToken() : $_SESSION['newtoken']) . '">';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste listwithfilterbefore">';
print '<tbody>';

// Filter row - standard Dolibarr style
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"><input class="flat maxwidth100" type="text" name="search_facture" value="' . dol_escape_htmltag($search_facture) . '" placeholder="' . $langs->trans('Ref') . '"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"><input class="flat maxwidth75" type="text" name="search_nif" id="search_nif" value="' . dol_escape_htmltag($search_nif) . '" placeholder="NIF"></td>';
print '<td class="liste_titre"><input class="flat maxwidth150" type="text" name="search_nom" id="search_nom" value="' . dol_escape_htmltag($search_nom) . '" placeholder="' . $langs->trans('Name') . '"></td>';
print '<td class="liste_titre"></td>';
// Add empty cells for IVA columns
$SQL = "SELECT COUNT(*) as cnt FROM " . MAIN_DB_PREFIX . "c_tva WHERE fk_pays=" . $mysoc->country_id . " AND ACTIVE=1 AND taux NOT IN(4,5)";
$resqlcnt = $db->query($SQL);
$ivaCount = 0;
if ($resqlcnt) {
	$objcnt = $db->fetch_object($resqlcnt);
	$ivaCount = $objcnt->cnt;
}
for ($i = 0; $i < $ivaCount; $i++) {
	print '<td class="liste_titre"></td>';
}
print '<td class="liste_titre"></td>'; // RE
print '<td class="liste_titre"></td>'; // Total IVA
print '<td class="liste_titre"></td>'; // Total TTC
print '<td class="liste_titre"></td>'; // Status
print '</tr>';

// Period filter row
print '<tr class="liste_titre">';
print '<td colspan="4" class="liste_titre">' . $langs->trans('FILTER_EJERCICIO_FACTURACION') . ': ' . $input_year . ' - ' . $langs->trans('FILTER_PERIODO_FACTURACION') . ': ' . $input_month . '</td>';
print '<td colspan="2" class="liste_titre right"><input class="button" type="submit" name="button_search" value="' . $langs->trans('Search') . '"></td>';
print '<td colspan="20" class="liste_titre right opacitymedium">' . $langs->trans('NumberOfInvoices', $num_facturas) . '</td>';
print '</tr>';

// Header row with titles
print '<tr class="liste_titre">';
print_liste_field_titre('COLUM_FACTURE_ID', "", '', '', "", 'class="center minwidth100"');
print_liste_field_titre('COLUM_FACTURE_ASSOC', "", '', '', "", 'class="center minwidth150"');
print_liste_field_titre('COLUM_FACTURE_DATE', "", '', '', "", 'class="center minwidth100"');
print_liste_field_titre('COLUM_FACTURE_PRESENTATION_DATE', "", '', '', "", 'class="center minwidth100"');
print_liste_field_titre('COLUM_FACTURE_SOC_NIF', "", '', '', "", 'class="center minwidth100"');
print_liste_field_titre('COLUM_FACTURE_SOC_NAME', "", '', '', "", 'class="center minwidth200"');

print_liste_field_titre('AmountHT', "", '', '', "", 'class="center minwidth80"');
$SQL = "SELECT rowid,taux,localtax1 FROM " . MAIN_DB_PREFIX . "c_tva WHERE fk_pays=$mysoc->country_id AND ACTIVE= 1 AND taux NOT IN(4,5) GROUP BY taux ORDER BY taux ASC";
$resql = $db->query($SQL);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$taux = $obj->taux;
		print_liste_field_titre($langs->trans('IVA') . ' ' . $taux, "", '', '', "", 'class="center minwidth80"');
	}
}
print_liste_field_titre('AmountLT1ES', "", '', '', "", 'class="center minwidth80"');
print_liste_field_titre('TotalVAT', "", '', '', "", 'class="center minwidth80"');
/* $SQL = "SELECT rowid,taux,localtax1 FROM " . MAIN_DB_PREFIX . "c_tva WHERE fk_pays=$mysoc->country_id AND ACTIVE= 1 AND taux NOT IN(4,5) GROUP BY taux ORDER BY taux ASC";
$resql = $db->query($SQL);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$localtax1 = $obj->localtax1;
		print_liste_field_titre($langs->trans('RE') . ' ' . $localtax1, "", '', '', "", 'class="center"');
	}
} */

print_liste_field_titre('AmountTTC', "", '', '', "", 'class="center minwidth80"');
print_liste_field_titre('COLUM_FACTURE_ESTADO_REGISTRO', "", '', '', "", 'class="center minwidth100"');
print '</tr>';

$total_array = array();
try {
	if ($num_facturas > 0) {
		foreach ($registros as $invoice) {
			// Verify that required properties exist before accessing
			if (!isset($invoice->IDFactura) || !isset($invoice->IDFactura->NumSerieFactura)) {
				continue; // Skip this invoice if it doesn't have basic data
			}

			$refFacture = preg_replace('/\/\d+.*$/', '', $invoice->IDFactura->NumSerieFactura);

			// Safe access to nested properties
			$nif = '-';
			$getNomUrlSoc = '-';
			if (isset($invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario)) {
				if (isset($invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario->NIF)) {
					$nif = $invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario->NIF;
				}
				if (isset($invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario->NombreRazon)) {
					$getNomUrlSoc = $invoice->DatosRegistroFacturacion->Destinatarios->IDDestinatario->NombreRazon;
				}
			}

			$staticInvoice = new Facture($db);
			$res = $staticInvoice->fetch('', $invoice->IDFactura->NumSerieFactura);
			$getNomUrlInvoice = '-';
			$facturaEncontrada = false;

			if ($res > 0) {
				$facturaEncontrada = true;
				$getNomUrlInvoice = $staticInvoice->getNomUrl(1);
				// Fetch thirdparty
				$resThird = $staticInvoice->fetch_thirdparty();

				if ($resThird > 0 && isset($staticInvoice->thirdparty)) {
					if (isset($staticInvoice->thirdparty->idprof1) && $staticInvoice->thirdparty->idprof1 != $nif) {
						$nif = $staticInvoice->thirdparty->idprof1;
					}
					$getNomUrlSoc = $staticInvoice->thirdparty->getNomUrl(1);
				}
			}
			print '<tr class="oddeven" id="' . htmlspecialchars($invoice->IDFactura->NumSerieFactura) . '">';
			print '<td class="nowrap center">' . showValueWithClipboardCPButton($invoice->IDFactura->NumSerieFactura, 0) . '</td>';
			print '<td class="nowrap center">' . $getNomUrlInvoice . '</td>';
			if ($facturaEncontrada && $staticInvoice->id > 0) {
				print '<td class="nowrap center">' . dol_print_date($staticInvoice->date, 'day') . '</td>';
			} else {
				print '<td class="nowrap center"> - </td>';
			}

			// Verify that FechaExpedicionFactura exists before using it
			$fechaExpedicion = '-';
			if (isset($invoice->IDFactura->FechaExpedicionFactura)) {
				$timestamp = strtotime($invoice->IDFactura->FechaExpedicionFactura);
				if ($timestamp !== false) {
					$fechaExpedicion = dol_print_date($timestamp, 'day');
				}
			}
			print '<td class="nowrap center">' . $fechaExpedicion . '</td>';

			print '<td class="nowrap center">' . showValueWithClipboardCPButton($nif, 0) . '</td>';
			print '<td class="nowrap left">' . $getNomUrlSoc . '</td>';

			// FINANCIAL DATA PROCESSING ALWAYS FROM VERIFACTU
			// (Regardless of whether the local invoice is found)
			$totalBaseImponible = 0;
			$totalIVA = 0;
			$totalRecargo = 0;
			$totalConIVA = 0;

			// Arrays to store VAT rates and Surcharges
			$arrayIVAs = [];
			$arrayRecargos = [];

			// Initialize totals arrays if they don't exist
			if (!isset($total_array['total_ht'])) $total_array['total_ht'] = 0;
			if (!isset($total_array['total_tva'])) $total_array['total_tva'] = 0;
			if (!isset($total_array['total_ttc'])) $total_array['total_ttc'] = 0;
			if (!isset($total_array['total_recargo'])) $total_array['total_recargo'] = 0;

			// NEW VERIFACTU STRUCTURE: Direct access to breakdown
			$detallesIVA = array();

			// Check if breakdown exists in the new structure
			if (isset($invoice->DatosRegistroFacturacion->Desglose->DetalleDesglose)) {
				$desglose = $invoice->DatosRegistroFacturacion->Desglose->DetalleDesglose;
				// If it's a single object, convert to array
				if (!is_array($desglose)) {
					$desglose = array($desglose);
				}

				// Map to the structure expected by previous code
				foreach ($desglose as $detalle) {
					// Verify that the detail has required properties (support both capitalization cases)
					$baseImponible = $detalle->BaseImponibleOImporteNoSujeto ?? $detalle->BaseImponibleOimporteNoSujeto ?? null;
					if (!isset($detalle->TipoImpositivo) || $baseImponible === null || !isset($detalle->CuotaRepercutida)) {
						continue; // Skip this detail if information is missing
					}

					$aux = new stdClass();
					$aux->TipoImpositivo = (float) ($detalle->TipoImpositivo ?? 0);
					$aux->BaseImponible = (float) $baseImponible;
					$aux->CuotaRepercutida = (float) ($detalle->CuotaRepercutida ?? 0);

					// If there's equivalence surcharge
					if (isset($detalle->CuotaRecargoEquivalencia)) {
						$aux->CuotaRecargoEquivalencia = (float) ($detalle->CuotaRecargoEquivalencia ?? 0);
					}

					$detallesIVA[] = $aux;
				}
			}

			// Iterate through VAT details to sum values and fill arrays
			foreach ($detallesIVA as $detalle) {
				// Sum values to total variables (with property verification)
				$totalBaseImponible += isset($detalle->BaseImponible) ? (float) $detalle->BaseImponible : 0;
				$totalIVA += isset($detalle->CuotaRepercutida) ? (float) $detalle->CuotaRepercutida : 0;

				// Add data to VAT array (with verification)
				if (isset($detalle->TipoImpositivo) && isset($detalle->CuotaRepercutida)) {
					$tipoImp = (float) $detalle->TipoImpositivo;
					$arrayIVAs[$tipoImp] = (float) $detalle->CuotaRepercutida + ($arrayIVAs[$tipoImp] ?? 0);
				}

				// If equivalence surcharge exists, add it to total and array
				if (isset($detalle->CuotaRecargoEquivalencia)) {
					$totalRecargo += (float) $detalle->CuotaRecargoEquivalencia;
					// Add data to surcharges array
					if (isset($detalle->TipoImpositivo)) {
						$tipoImp = (float) $detalle->TipoImpositivo;
						$arrayRecargos[$tipoImp] = (float) $detalle->CuotaRecargoEquivalencia + ($arrayRecargos[$tipoImp] ?? 0);
					}
				}
			}

			// Calculate total with VAT (Tax Base + VAT + Surcharge)
			$totalConIVA = $totalBaseImponible + $totalIVA + $totalRecargo;

			$total_array['total_ht'] += $totalBaseImponible;
			$total_array['total_tva'] += $totalIVA;
			$total_array['total_ttc'] += $totalConIVA;
			$total_array['total_recargo'] += $totalRecargo;

			// Print financial data (always from VERIFACTU)
			print '<td class="nowrap right">' . price($totalBaseImponible) . '</td>';

			// Get system VAT rates and display data from VAT array
			$SQL = "SELECT rowid,taux,localtax1 FROM " . MAIN_DB_PREFIX . "c_tva WHERE fk_pays=$mysoc->country_id AND ACTIVE= 1 AND taux NOT IN(4,5) GROUP BY taux ORDER BY taux ASC";
			$resql = $db->query($SQL);
			// Execute query and print td cells according to VAT rate key in the array
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$taux = (float) ($obj->taux ?? 0);
					$localtax1 = $obj->localtax1;
					$cuota = $arrayIVAs[$taux] ?? 0;
					$cuotaRecargo = $arrayRecargos[$taux] ?? 0;
					print '<td class="nowrap right">' . price($cuota) . '</td>';
					// Initialize if it doesn't exist
					if (!isset($total_array['total_tva_' . $taux])) $total_array['total_tva_' . $taux] = 0;
					$total_array['total_tva_' . $taux] += $cuota;
				}
			}
			print '<td class="nowrap right">' . price($totalRecargo) . '</td>';
			print '<td class="nowrap right">' . price($totalIVA) . '</td>';
			print '<td class="nowrap right">' . price($totalConIVA) . '</td>';

			print '<td class="nowrap center">' . (isset($invoice->EstadoRegistro->EstadoRegistro) ? $invoice->EstadoRegistro->EstadoRegistro : '-') . '</td>';
			print '</tr>';
		}
		// Print totals row
		print '<tr class="liste_total">';
		print '<td>';
		if (is_object($form)) {
			print $form->textwithpicto($langs->trans("Total"), $langs->transnoentitiesnoconv("Totalforthispage"));
		} else {
			print $langs->trans("Totalforthispage");
		}
		print '</td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td></td>';
		print '<td>' . price(isset($total_array['total_ht']) ? $total_array['total_ht'] : 0) . '</td>';
		$SQL = "SELECT rowid,taux,localtax1 FROM " . MAIN_DB_PREFIX . "c_tva WHERE fk_pays=$mysoc->country_id AND ACTIVE= 1 AND taux NOT IN(4,5) GROUP BY taux ORDER BY taux ASC";
		$resql = $db->query($SQL);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$taux = $obj->taux;
				$total_value = isset($total_array['total_tva_' . $taux]) ? $total_array['total_tva_' . $taux] : 0;
				print '<td>' . price($total_value) . '</td>';
			}
		}
		print '<td>' . price(isset($total_array['total_recargo']) ? $total_array['total_recargo'] : 0) . '</td>';
		print '<td>' . price(isset($total_array['total_tva']) ? $total_array['total_tva'] : 0) . '</td>';
		print '<td>' . price(isset($total_array['total_ttc']) ? $total_array['total_ttc'] : 0) . '</td>';
		print '<td></td>'; // Record status
		print '</tr>';
	} else {
		print '<tr><td colspan="20" class="nowrap center bold"><div class="error center">' . $langs->trans('NO_RECORDS_FOUNDS') . '</div></td></tr>';
	}
} catch (\Throwable $th) {
	var_export($th);
}

print '</tbody>';
print '</table>';
print '</div>';
print '</form>';

print '</div>';
print '</div>';
// End of page
llxFooter();
$db->close();
