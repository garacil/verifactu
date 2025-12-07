<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
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
 *	\file       verifactu/verifactuindex.php
 *	\ingroup    verifactu
 *	\brief      Home page of verifactu top menu - Statistics dashboard
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
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

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/dolgraph.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

// Load translation files required by the page
$langs->loadLangs(array("verifactu@verifactu", "bills", "companies"));

$action = GETPOST('action', 'aZ09');

// Security check
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

/*
 * Statistics queries
 */

// Get invoices by status
$sqlByStatus = "SELECT fe.verifactu_estado as estado, COUNT(*) as total";
$sqlByStatus .= " FROM " . MAIN_DB_PREFIX . "facture f";
$sqlByStatus .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
$sqlByStatus .= " WHERE fe.verifactu_estado IS NOT NULL AND fe.verifactu_estado != ''";
$sqlByStatus .= " AND f.entity IN (" . getEntity('invoice') . ")";
$sqlByStatus .= " GROUP BY fe.verifactu_estado";
$sqlByStatus .= " ORDER BY total DESC";

$resqlByStatus = $db->query($sqlByStatus);
$invoicesByStatus = array();
$totalSent = 0;
if ($resqlByStatus) {
	while ($obj = $db->fetch_object($resqlByStatus)) {
		$invoicesByStatus[$obj->estado] = intval($obj->total);
		$totalSent += intval($obj->total);
	}
	$db->free($resqlByStatus);
}

// Get invoices with errors
$sqlErrors = "SELECT COUNT(*) as total FROM " . MAIN_DB_PREFIX . "facture f";
$sqlErrors .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
$sqlErrors .= " WHERE fe.verifactu_error IS NOT NULL AND fe.verifactu_error != ''";
$sqlErrors .= " AND f.fk_statut > 0";
$sqlErrors .= " AND f.entity IN (" . getEntity('invoice') . ")";
$resqlErrors = $db->query($sqlErrors);
$totalErrors = 0;
if ($resqlErrors) {
	$obj = $db->fetch_object($resqlErrors);
	$totalErrors = intval($obj->total);
	$db->free($resqlErrors);
}

// Get invoices by month for current year (for bar chart)
$currentYear = date('Y');
$sqlByMonth = "SELECT MONTH(f.datef) as month, COUNT(*) as total";
$sqlByMonth .= " FROM " . MAIN_DB_PREFIX . "facture f";
$sqlByMonth .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
$sqlByMonth .= " WHERE fe.verifactu_estado IS NOT NULL AND fe.verifactu_estado != ''";
$sqlByMonth .= " AND YEAR(f.datef) = " . $currentYear;
$sqlByMonth .= " AND f.entity IN (" . getEntity('invoice') . ")";
$sqlByMonth .= " GROUP BY MONTH(f.datef)";
$sqlByMonth .= " ORDER BY MONTH(f.datef)";

$resqlByMonth = $db->query($sqlByMonth);
$invoicesByMonth = array_fill(1, 12, 0);
if ($resqlByMonth) {
	while ($obj = $db->fetch_object($resqlByMonth)) {
		$invoicesByMonth[intval($obj->month)] = intval($obj->total);
	}
	$db->free($resqlByMonth);
}

// Get last 5 invoices sent to VeriFactu
$sqlLastSent = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, fe.verifactu_estado, fe.verifactu_ultimafecha_modificacion, s.rowid as socid, s.nom as socname";
$sqlLastSent .= " FROM " . MAIN_DB_PREFIX . "facture f";
$sqlLastSent .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
$sqlLastSent .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON f.fk_soc = s.rowid";
$sqlLastSent .= " WHERE fe.verifactu_estado IS NOT NULL AND fe.verifactu_estado != ''";
$sqlLastSent .= " AND f.entity IN (" . getEntity('invoice') . ")";
$sqlLastSent .= " ORDER BY fe.verifactu_ultimafecha_modificacion DESC";
$sqlLastSent .= " LIMIT 5";

$resqlLastSent = $db->query($sqlLastSent);
$lastSentInvoices = array();
if ($resqlLastSent) {
	while ($obj = $db->fetch_object($resqlLastSent)) {
		$lastSentInvoices[] = $obj;
	}
	$db->free($resqlLastSent);
}

// Get invoices with errors (last 5)
$sqlWithErrors = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, fe.verifactu_estado, fe.verifactu_error, s.rowid as socid, s.nom as socname";
$sqlWithErrors .= " FROM " . MAIN_DB_PREFIX . "facture f";
$sqlWithErrors .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
$sqlWithErrors .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON f.fk_soc = s.rowid";
$sqlWithErrors .= " WHERE fe.verifactu_error IS NOT NULL AND fe.verifactu_error != ''";
$sqlWithErrors .= " AND f.fk_statut > 0";
$sqlWithErrors .= " AND f.entity IN (" . getEntity('invoice') . ")";
$sqlWithErrors .= " ORDER BY f.datef DESC";
$sqlWithErrors .= " LIMIT 5";

$resqlWithErrors = $db->query($sqlWithErrors);
$errorInvoices = array();
if ($resqlWithErrors) {
	while ($obj = $db->fetch_object($resqlWithErrors)) {
		$errorInvoices[] = $obj;
	}
	$db->free($resqlWithErrors);
}

// Get pending invoices (validated but not sent)
$sqlPending = "SELECT f.rowid, f.ref, f.datef, f.total_ttc, s.rowid as socid, s.nom as socname";
$sqlPending .= " FROM " . MAIN_DB_PREFIX . "facture f";
$sqlPending .= " LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
$sqlPending .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe s ON f.fk_soc = s.rowid";
$sqlPending .= " WHERE f.fk_statut = 1"; // Validated
$sqlPending .= " AND (fe.verifactu_estado IS NULL OR fe.verifactu_estado = '')";
$sqlPending .= " AND f.entity IN (" . getEntity('invoice') . ")";
$sqlPending .= " ORDER BY f.datef DESC";
$sqlPending .= " LIMIT 5";

$resqlPending = $db->query($sqlPending);
$pendingInvoices = array();
$totalPending = 0;
if ($resqlPending) {
	while ($obj = $db->fetch_object($resqlPending)) {
		$pendingInvoices[] = $obj;
		$totalPending++;
	}
	$db->free($resqlPending);
}

// Count total pending
$sqlPendingCount = "SELECT COUNT(*) as total";
$sqlPendingCount .= " FROM " . MAIN_DB_PREFIX . "facture f";
$sqlPendingCount .= " LEFT JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
$sqlPendingCount .= " WHERE f.fk_statut = 1";
$sqlPendingCount .= " AND (fe.verifactu_estado IS NULL OR fe.verifactu_estado = '')";
$sqlPendingCount .= " AND f.entity IN (" . getEntity('invoice') . ")";
$resqlPendingCount = $db->query($sqlPendingCount);
if ($resqlPendingCount) {
	$obj = $db->fetch_object($resqlPendingCount);
	$totalPending = intval($obj->total);
	$db->free($resqlPendingCount);
}

/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);
$staticfacture = new Facture($db);
$staticsoc = new Societe($db);

llxHeader("", $langs->trans("VerifactuArea"));

print load_fiche_titre($langs->trans("VerifactuArea"), '', 'bill');

print '<div class="fichecenter">';

// LEFT COLUMN
print '<div class="fichethirdleft">';

// Box: Invoices by month (bar chart)
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . $langs->trans("VERIFACTU_STAT_BY_MONTH") . ' ' . $currentYear . '</th></tr>';
print '<tr class="oddeven"><td class="center">';

$monthNames = array(
	1 => 'Ene',
	2 => 'Feb',
	3 => 'Mar',
	4 => 'Abr',
	5 => 'May',
	6 => 'Jun',
	7 => 'Jul',
	8 => 'Ago',
	9 => 'Sep',
	10 => 'Oct',
	11 => 'Nov',
	12 => 'Dic'
);

$dataMonth = array();
foreach ($invoicesByMonth as $month => $count) {
	$dataMonth[] = array($monthNames[$month], $count);
}

$graphMonth = new DolGraph();
$graphMonth->SetData($dataMonth);
$graphMonth->SetLegend(array($langs->trans("NbOfInvoices")));
$graphMonth->SetType(array('bars'));
$graphMonth->SetWidth(380);
$graphMonth->SetHeight(180);
$graphMonth->SetMinValue(0);
$graphMonth->SetMaxValue($graphMonth->GetCeilMaxValue());
$graphMonth->draw('verifactu_by_month');
print $graphMonth->show();

print '</td></tr>';
print '</table>';
print '</div>';

print '<br>';

// Box: Invoices by status (bar chart)
if (!empty($invoicesByStatus)) {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>' . $langs->trans("VERIFACTU_STAT_STATUS_DISTRIBUTION") . '</th></tr>';
	print '<tr class="oddeven"><td class="center">';

	// Group statuses by processing badge HTML to extract actual text
	$processedStatus = array();
	foreach ($invoicesByStatus as $status => $count) {
		// Extract status text from badge HTML
		// Badge format: <span ... attr-status="Sent">Sent</span>
		$statusLabel = $status;

		// Try to extract from attr-status
		if (preg_match('/attr-status=["\']([^"\']+)["\']/', $status, $matches)) {
			$statusLabel = $matches[1];
		}
		// Otherwise, try to extract text between > and <
		elseif (preg_match('/>([^<]+)</', $status, $matches)) {
			$statusLabel = trim($matches[1]);
		}
		// Otherwise, use strip_tags
		else {
			$statusLabel = trim(strip_tags($status));
		}

		// If label is empty, use "Unknown"
		if (empty($statusLabel)) {
			$statusLabel = 'Desconocido';
		}

		// Group counts by processed label
		if (isset($processedStatus[$statusLabel])) {
			$processedStatus[$statusLabel] += $count;
		} else {
			$processedStatus[$statusLabel] = $count;
		}
	}

	$dataStatus = array();
	foreach ($processedStatus as $statusLabel => $count) {
		$dataStatus[] = array($statusLabel, $count);
	}

	$graphStatus = new DolGraph();
	$graphStatus->SetData($dataStatus);
	$graphStatus->SetLegend(array($langs->trans("NbOfInvoices")));
	$graphStatus->SetType(array('bars'));
	$graphStatus->SetWidth(380);
	$graphStatus->SetHeight(150);
	$graphStatus->SetMinValue(0);
	$graphStatus->SetMaxValue($graphStatus->GetCeilMaxValue());
	$graphStatus->draw('verifactu_by_status');
	print $graphStatus->show();

	print '</td></tr>';
	print '</table>';
	print '</div>';
}

print '</div>'; // End fichethirdleft

// RIGHT COLUMN
print '<div class="fichetwothirdright">';

// Box: Last invoices sent to VeriFactu
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th colspan="4">' . $langs->trans("VERIFACTU_LAST_SENT_INVOICES") . ' <span class="badge">' . count($lastSentInvoices) . '</span></th>';
print '</tr>';

if (!empty($lastSentInvoices)) {
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans("Ref") . '</td>';
	print '<td>' . $langs->trans("Company") . '</td>';
	print '<td class="right">' . $langs->trans("AmountTTC") . '</td>';
	print '<td class="center">' . $langs->trans("DateModification") . '</td>';
	print '</tr>';

	foreach ($lastSentInvoices as $invoice) {
		$staticfacture->id = $invoice->rowid;
		$staticfacture->ref = $invoice->ref;
		$staticsoc->id = $invoice->socid;
		$staticsoc->name = $invoice->socname;

		// Status badge
		$statusBadge = '';
		if ($invoice->verifactu_estado) {
			$statusClass = 'badge-status4'; // Default blue
			if (stripos($invoice->verifactu_estado, 'error') !== false || stripos($invoice->verifactu_estado, 'incorrecto') !== false) {
				$statusClass = 'badge-status8'; // Red
			} elseif (stripos($invoice->verifactu_estado, 'anulada') !== false) {
				$statusClass = 'badge-status6'; // Orange
			} elseif (stripos($invoice->verifactu_estado, 'enviada') !== false || stripos($invoice->verifactu_estado, 'correcto') !== false) {
				$statusClass = 'badge-status4'; // Green
			}
			$statusLabel = $langs->trans('VERIFACTU_STATUS_' . strtoupper($invoice->verifactu_estado));
			if ($statusLabel == 'VERIFACTU_STATUS_' . strtoupper($invoice->verifactu_estado)) {
				$statusLabel = ucfirst($invoice->verifactu_estado);
			}
			$statusBadge = ' <span class="badge ' . $statusClass . '">' . $statusLabel . '</span>';
		}

		print '<tr class="oddeven">';
		print '<td class="nowraponall">' . $staticfacture->getNomUrl(1) . $statusBadge . '</td>';
		print '<td class="tdoverflowmax200">' . ($invoice->socid > 0 ? $staticsoc->getNomUrl(1, '', 24) : '-') . '</td>';
		print '<td class="right nowraponall">' . price($invoice->total_ttc) . '</td>';
		print '<td class="center nowraponall">' . dol_print_date($db->jdate($invoice->verifactu_ultimafecha_modificacion), 'dayhour') . '</td>';
		print '</tr>';
	}
} else {
	print '<tr class="oddeven"><td colspan="4" class="opacitymedium">' . $langs->trans("NoRecordFound") . '</td></tr>';
}
print '</table>';
print '</div>';

print '<br>';

// Box: Invoices with errors
if ($totalErrors > 0) {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th colspan="4">' . $langs->trans("VERIFACTU_INVOICES_WITH_ERRORS") . ' <span class="badge badge-status8">' . $totalErrors . '</span></th>';
	print '</tr>';

	if (!empty($errorInvoices)) {
		print '<tr class="liste_titre">';
		print '<td>' . $langs->trans("Ref") . '</td>';
		print '<td>' . $langs->trans("Company") . '</td>';
		print '<td class="right">' . $langs->trans("AmountTTC") . '</td>';
		print '<td>' . $langs->trans("Error") . '</td>';
		print '</tr>';

		foreach ($errorInvoices as $invoice) {
			$staticfacture->id = $invoice->rowid;
			$staticfacture->ref = $invoice->ref;
			$staticsoc->id = $invoice->socid;
			$staticsoc->name = $invoice->socname;

			print '<tr class="oddeven">';
			print '<td class="nowraponall">' . $staticfacture->getNomUrl(1) . '</td>';
			print '<td class="tdoverflowmax150">' . ($invoice->socid > 0 ? $staticsoc->getNomUrl(1, '', 24) : '-') . '</td>';
			print '<td class="right nowraponall">' . price($invoice->total_ttc) . '</td>';
			print '<td class="tdoverflowmax200" title="' . dol_escape_htmltag($invoice->verifactu_error) . '">' . dol_trunc($invoice->verifactu_error, 40) . '</td>';
			print '</tr>';
		}
	}
	print '</table>';
	print '</div>';

	print '<br>';
}

// Box: Pending invoices (not sent yet)
if ($totalPending > 0) {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th colspan="4">' . $langs->trans("VERIFACTU_PENDING_INVOICES") . ' <span class="badge badge-status1">' . $totalPending . '</span></th>';
	print '</tr>';

	if (!empty($pendingInvoices)) {
		print '<tr class="liste_titre">';
		print '<td>' . $langs->trans("Ref") . '</td>';
		print '<td>' . $langs->trans("Company") . '</td>';
		print '<td class="right">' . $langs->trans("AmountTTC") . '</td>';
		print '<td class="center">' . $langs->trans("Date") . '</td>';
		print '</tr>';

		foreach ($pendingInvoices as $invoice) {
			$staticfacture->id = $invoice->rowid;
			$staticfacture->ref = $invoice->ref;
			$staticsoc->id = $invoice->socid;
			$staticsoc->name = $invoice->socname;

			print '<tr class="oddeven">';
			print '<td class="nowraponall">' . $staticfacture->getNomUrl(1) . '</td>';
			print '<td class="tdoverflowmax200">' . ($invoice->socid > 0 ? $staticsoc->getNomUrl(1, '', 24) : '-') . '</td>';
			print '<td class="right nowraponall">' . price($invoice->total_ttc) . '</td>';
			print '<td class="center nowraponall">' . dol_print_date($db->jdate($invoice->datef), 'day') . '</td>';
			print '</tr>';
		}

		if ($totalPending > 5) {
			print '<tr class="oddeven">';
			print '<td colspan="4" class="center">';
			print '<a href="' . dol_buildpath('/verifactu/views/list.facture.php', 1) . '">' . $langs->trans("More") . '... (' . ($totalPending - 5) . ')</a>';
			print '</td>';
			print '</tr>';
		}
	}
	print '</table>';
	print '</div>';
}

print '</div>'; // End fichetwothirdright

print '</div>'; // End fichecenter
print '<div class="clearboth"></div>';

// End of page
llxFooter();
$db->close();
