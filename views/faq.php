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
 * \file       views/faq.php
 * \ingroup    verifactu
 * \brief      Guía de ayuda y preguntas frecuentes del módulo VeriFactu
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

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formadmin.class.php';

// Load translation files
$langs->loadLangs(array("admin", "other", "verifactu@verifactu"));

// Security check
if (!$user->rights->verifactu->manage) {
	accessforbidden();
}

/*
 * View
 */

$title = $langs->trans("VERIFACTU_FAQ_TITLE");
$help_url = '';

llxHeader('', $title, $help_url);

print load_fiche_titre($langs->trans("VERIFACTU_FAQ_TITLE"), '', 'verifactu@verifactu');

print '<div class="fichecenter">';
print '<div class="fichethirdleft">';

// Panel de navegación rápida
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>' . $langs->trans("VERIFACTU_FAQ_QUICK_NAV") . '</th></tr>';
print '<tr class="oddeven"><td><a href="#guia-rapida" class="classfortooltip">' . img_picto('', 'setup', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_QUICK_GUIDE") . '</a></td></tr>';
print '<tr class="oddeven"><td><a href="#tipos-factura" class="classfortooltip">' . img_picto('', 'bill', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_INVOICE_TYPES") . '</a></td></tr>';
print '<tr class="oddeven"><td><a href="#tipos-impuesto" class="classfortooltip">' . img_picto('', 'payment', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_TAX_TYPES") . '</a></td></tr>';
print '<tr class="oddeven"><td><a href="#clave-regimen" class="classfortooltip">' . img_picto('', 'category', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_REGIME_KEY") . '</a></td></tr>';
print '<tr class="oddeven"><td><a href="#calificacion" class="classfortooltip">' . img_picto('', 'bookmark', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_QUALIFICATION") . '</a></td></tr>';
print '<tr class="oddeven"><td><a href="#exenciones" class="classfortooltip">' . img_picto('', 'generic', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_EXEMPTIONS") . '</a></td></tr>';
print '<tr class="oddeven"><td><a href="#tipos-identificacion" class="classfortooltip">' . img_picto('', 'user', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_ID_TYPES") . '</a></td></tr>';
print '<tr class="oddeven"><td><a href="#errores-comunes" class="classfortooltip">' . img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_COMMON_ERRORS") . '</a></td></tr>';
print '</table>';
print '</div>';

print '</div>'; // fichethirdleft

print '<div class="fichetwothirdright">';

// Barra de búsqueda
print '<div class="info" style="margin-bottom: 20px;">';
print '<input type="text" id="faq-search" class="flat minwidth300" placeholder="' . $langs->trans("VERIFACTU_FAQ_SEARCH_PLACEHOLDER") . '" style="padding: 8px;">';
print ' <span id="search-results" class="opacitymedium"></span>';
print '</div>';

// ========== GUÍA RÁPIDA ==========
print '<div class="div-table-responsive-no-min" id="guia-rapida">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . img_picto('', 'setup', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_QUICK_GUIDE") . '</th></tr>';

print '<tr class="oddeven faq-item">';
print '<td colspan="2">';
print '<strong>' . $langs->trans("VERIFACTU_FAQ_WHAT_IS") . '</strong><br>';
print $langs->trans("VERIFACTU_FAQ_WHAT_IS_DESC");
print '</td></tr>';

print '<tr class="oddeven faq-item">';
print '<td colspan="2">';
print '<div class="warning">';
print '<strong>' . $langs->trans("VERIFACTU_FAQ_IMPORTANT_CONFIG") . '</strong><br>';
print $langs->trans("VERIFACTU_FAQ_IMPORTANT_CONFIG_DESC");
print '</div>';
print '</td></tr>';

print '<tr class="oddeven faq-item">';
print '<td colspan="2">';
print '<strong>' . $langs->trans("VERIFACTU_FAQ_INITIAL_CONFIG") . '</strong><br>';
print '<ul>';
print '<li>' . $langs->trans("VERIFACTU_FAQ_STEP1") . '</li>';
print '<li>' . $langs->trans("VERIFACTU_FAQ_STEP2") . '</li>';
print '<li>' . $langs->trans("VERIFACTU_FAQ_STEP3") . '</li>';
print '</ul>';
print '</td></tr>';

print '</table>';
print '</div><br>';

// ========== TIPOS DE FACTURA ==========
print '<div class="div-table-responsive-no-min" id="tipos-factura">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . img_picto('', 'bill', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_INVOICE_TYPES") . '</th></tr>';

$invoiceTypes = array(
	'F1' => array('title' => 'Factura (art. 6 o 7.2 o 7.3 del RD 1619/2012)', 'desc' => 'VERIFACTU_FAQ_F1_DESC'),
	'F2' => array('title' => 'Factura Simplificada (art. 6.1.d RD 1619/2012)', 'desc' => 'VERIFACTU_FAQ_F2_DESC'),
	'F3' => array('title' => 'Factura en sustitución de simplificadas', 'desc' => 'VERIFACTU_FAQ_F3_DESC'),
	'R1' => array('title' => 'Factura Rectificativa (Art. 80 Uno, Dos y Seis LIVA)', 'desc' => 'VERIFACTU_FAQ_R1_DESC'),
	'R2' => array('title' => 'Factura Rectificativa (Art. 80.3)', 'desc' => 'VERIFACTU_FAQ_R2_DESC'),
	'R3' => array('title' => 'Factura Rectificativa (Art. 80.4)', 'desc' => 'VERIFACTU_FAQ_R3_DESC'),
	'R4' => array('title' => 'Factura Rectificativa (Resto de casos)', 'desc' => 'VERIFACTU_FAQ_R4_DESC'),
	'R5' => array('title' => 'Factura Rectificativa en simplificadas', 'desc' => 'VERIFACTU_FAQ_R5_DESC'),
);

foreach ($invoiceTypes as $code => $data) {
	print '<tr class="oddeven faq-item">';
	print '<td class="nowrap" style="width: 80px;"><span class="badge badge-primary">' . $code . '</span></td>';
	print '<td><strong>' . $data['title'] . '</strong><br><span class="opacitymedium">' . $langs->trans($data['desc']) . '</span></td>';
	print '</tr>';
}

print '</table>';
print '</div><br>';

// ========== TIPOS DE IMPUESTO ==========
print '<div class="div-table-responsive-no-min" id="tipos-impuesto">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . img_picto('', 'payment', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_TAX_TYPES") . '</th></tr>';

$taxTypes = array(
	'01' => array('title' => 'IVA - Impuesto sobre el Valor Añadido', 'desc' => 'VERIFACTU_FAQ_TAX_01_DESC'),
	'02' => array('title' => 'IPSI - Ceuta y Melilla', 'desc' => 'VERIFACTU_FAQ_TAX_02_DESC'),
	'03' => array('title' => 'IGIC - Canarias', 'desc' => 'VERIFACTU_FAQ_TAX_03_DESC'),
	'05' => array('title' => 'Otros impuestos', 'desc' => 'VERIFACTU_FAQ_TAX_05_DESC'),
);

foreach ($taxTypes as $code => $data) {
	print '<tr class="oddeven faq-item">';
	print '<td class="nowrap" style="width: 80px;"><span class="badge badge-status4">' . $code . '</span></td>';
	print '<td><strong>' . $data['title'] . '</strong><br><span class="opacitymedium">' . $langs->trans($data['desc']) . '</span></td>';
	print '</tr>';
}

print '</table>';
print '</div><br>';

// ========== CLAVE DE RÉGIMEN ==========
print '<div class="div-table-responsive-no-min" id="clave-regimen">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . img_picto('', 'category', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_REGIME_KEY") . '</th></tr>';

$regimeKeys = array(
	'01' => array('title' => 'Operación de Régimen General', 'desc' => 'VERIFACTU_FAQ_REGIME_01_DESC'),
	'02' => array('title' => 'Exportación', 'desc' => 'VERIFACTU_FAQ_REGIME_02_DESC'),
	'03' => array('title' => 'Bienes usados, arte y antigüedades', 'desc' => 'VERIFACTU_FAQ_REGIME_03_DESC'),
	'04' => array('title' => 'Oro de inversión', 'desc' => 'VERIFACTU_FAQ_REGIME_04_DESC'),
	'05' => array('title' => 'Agencias de viajes', 'desc' => 'VERIFACTU_FAQ_REGIME_05_DESC'),
	'07' => array('title' => 'Criterio de Caja', 'desc' => 'VERIFACTU_FAQ_REGIME_07_DESC'),
	'18' => array('title' => 'Recargo de equivalencia', 'desc' => 'VERIFACTU_FAQ_REGIME_18_DESC'),
	'19' => array('title' => 'REAGYP', 'desc' => 'VERIFACTU_FAQ_REGIME_19_DESC'),
	'20' => array('title' => 'Régimen simplificado', 'desc' => 'VERIFACTU_FAQ_REGIME_20_DESC'),
);

foreach ($regimeKeys as $code => $data) {
	print '<tr class="oddeven faq-item">';
	print '<td class="nowrap" style="width: 80px;"><span class="badge badge-status1">' . $code . '</span></td>';
	print '<td><strong>' . $data['title'] . '</strong><br><span class="opacitymedium">' . $langs->trans($data['desc']) . '</span></td>';
	print '</tr>';
}

print '</table>';
print '</div><br>';

// ========== CALIFICACIÓN DE LA OPERACIÓN ==========
print '<div class="div-table-responsive-no-min" id="calificacion">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . img_picto('', 'bookmark', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_QUALIFICATION") . '</th></tr>';

$qualifications = array(
	'S1' => array('title' => 'Sujeta y No Exenta - Sin inversión', 'desc' => 'VERIFACTU_FAQ_S1_DESC'),
	'S2' => array('title' => 'Sujeta y No Exenta - Con inversión', 'desc' => 'VERIFACTU_FAQ_S2_DESC'),
	'N1' => array('title' => 'No sujeta (art. 7, 14)', 'desc' => 'VERIFACTU_FAQ_N1_DESC'),
	'N2' => array('title' => 'No sujeta (localización)', 'desc' => 'VERIFACTU_FAQ_N2_DESC'),
);

foreach ($qualifications as $code => $data) {
	print '<tr class="oddeven faq-item">';
	print '<td class="nowrap" style="width: 80px;"><span class="badge badge-status5">' . $code . '</span></td>';
	print '<td><strong>' . $data['title'] . '</strong><br><span class="opacitymedium">' . $langs->trans($data['desc']) . '</span></td>';
	print '</tr>';
}

print '</table>';
print '</div><br>';

// ========== EXENCIONES ==========
print '<div class="div-table-responsive-no-min" id="exenciones">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . img_picto('', 'generic', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_EXEMPTIONS") . '</th></tr>';

$exemptions = array(
	'E1' => array('title' => 'Exenta por Artículo 20', 'desc' => 'VERIFACTU_FAQ_E1_DESC'),
	'E2' => array('title' => 'Exenta por Artículo 21', 'desc' => 'VERIFACTU_FAQ_E2_DESC'),
	'E3' => array('title' => 'Exenta por Artículo 22', 'desc' => 'VERIFACTU_FAQ_E3_DESC'),
	'E4' => array('title' => 'Exenta por Artículos 23 y 24', 'desc' => 'VERIFACTU_FAQ_E4_DESC'),
	'E5' => array('title' => 'Exenta por Artículo 25', 'desc' => 'VERIFACTU_FAQ_E5_DESC'),
	'E6' => array('title' => 'Exenta por otros motivos', 'desc' => 'VERIFACTU_FAQ_E6_DESC'),
);

foreach ($exemptions as $code => $data) {
	print '<tr class="oddeven faq-item">';
	print '<td class="nowrap" style="width: 80px;"><span class="badge badge-status6">' . $code . '</span></td>';
	print '<td><strong>' . $data['title'] . '</strong><br><span class="opacitymedium">' . $langs->trans($data['desc']) . '</span></td>';
	print '</tr>';
}

print '</table>';
print '</div><br>';

// ========== TIPOS DE IDENTIFICACIÓN ==========
print '<div class="div-table-responsive-no-min" id="tipos-identificacion">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . img_picto('', 'user', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_ID_TYPES") . '</th></tr>';

$idTypes = array(
	'02' => array('title' => 'NIF-IVA', 'desc' => 'VERIFACTU_FAQ_ID_02_DESC'),
	'03' => array('title' => 'Pasaporte', 'desc' => 'VERIFACTU_FAQ_ID_03_DESC'),
	'04' => array('title' => 'Documento oficial del país', 'desc' => 'VERIFACTU_FAQ_ID_04_DESC'),
	'05' => array('title' => 'Certificado de residencia', 'desc' => 'VERIFACTU_FAQ_ID_05_DESC'),
	'06' => array('title' => 'Otro documento probatorio', 'desc' => 'VERIFACTU_FAQ_ID_06_DESC'),
	'07' => array('title' => 'No censado', 'desc' => 'VERIFACTU_FAQ_ID_07_DESC'),
);

foreach ($idTypes as $code => $data) {
	print '<tr class="oddeven faq-item">';
	print '<td class="nowrap" style="width: 80px;"><span class="badge badge-status8">' . $code . '</span></td>';
	print '<td><strong>' . $data['title'] . '</strong><br><span class="opacitymedium">' . $langs->trans($data['desc']) . '</span></td>';
	print '</tr>';
}

print '</table>';
print '</div><br>';

// ========== ERRORES COMUNES ==========
print '<div class="div-table-responsive-no-min" id="errores-comunes">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="2">' . img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_COMMON_ERRORS") . '</th></tr>';

print '<tr class="oddeven faq-item">';
print '<td colspan="2">';
print '<strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_UK") . '</strong><br>';
print '<div class="warning">' . $langs->trans("VERIFACTU_FAQ_ERROR_UK_DESC") . '</div>';
print '</td></tr>';

print '<tr class="oddeven faq-item">';
print '<td colspan="2">';
print '<strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_NIF") . '</strong><br>';
print '<div class="info">' . $langs->trans("VERIFACTU_FAQ_ERROR_NIF_DESC") . '</div>';
print '</td></tr>';

print '<tr class="oddeven faq-item">';
print '<td colspan="2">';
print '<strong>' . $langs->trans("VERIFACTU_FAQ_RESEND") . '</strong><br>';
print $langs->trans("VERIFACTU_FAQ_RESEND_DESC");
print '</td></tr>';

print '<tr class="oddeven faq-item">';
print '<td colspan="2">';
print '<strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_CODES") . '</strong><br>';
print $langs->trans("VERIFACTU_FAQ_ERROR_CODES_DESC");
print '<br><a href="https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/errores.properties" target="_blank" class="button small">';
print img_picto('', 'globe', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_ERROR_CODES_LINK");
print '</a>';
print '</td></tr>';

print '</table>';
print '</div>';

print '</div>'; // fichetwothirdright
print '</div>'; // fichecenter

// JavaScript para búsqueda
print '<script>
document.addEventListener("DOMContentLoaded", function() {
	var searchInput = document.getElementById("faq-search");
	var searchResults = document.getElementById("search-results");
	var faqItems = document.querySelectorAll(".faq-item");

	searchInput.addEventListener("input", function() {
		var query = this.value.toLowerCase().trim();
		var visibleCount = 0;

		faqItems.forEach(function(item) {
			var text = item.textContent.toLowerCase();
			if (query === "" || text.includes(query)) {
				item.style.display = "";
				visibleCount++;
			} else {
				item.style.display = "none";
			}
		});

		if (query !== "") {
			searchResults.textContent = visibleCount + " ' . $langs->trans("VERIFACTU_FAQ_RESULTS_FOUND") . '";
		} else {
			searchResults.textContent = "";
		}
	});
});
</script>';

llxFooter();
$db->close();
