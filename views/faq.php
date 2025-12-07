<?php
/* Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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
	$res = @include dirname(substr($tmp, 0, ($i + 1)) . "/main.inc.php");
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

$langs->loadLangs(array("admin", "other", "verifactu@verifactu"));

if (!$user->rights->verifactu->manage) {
	accessforbidden();
}

$title = $langs->trans("VERIFACTU_FAQ_TITLE");
llxHeader('', $title, '');

print load_fiche_titre($langs->trans("VERIFACTU_FAQ_TITLE"), '', 'verifactu@verifactu');

// CSS for accordion
print '<style>
.faq-section { margin-bottom: 8px; border: 1px solid #ccc; border-radius: 4px; }
.faq-header { background: #f5f5f5; padding: 12px 15px; cursor: pointer; font-weight: bold; user-select: none; }
.faq-header:hover { background: #e8e8e8; }
.faq-arrow { display: inline-block; width: 20px; transition: transform 0.2s; }
.faq-arrow.open { transform: rotate(90deg); }
.faq-content { display: none; padding: 15px; border-top: 1px solid #ddd; }
.faq-row { padding: 8px 0; border-bottom: 1px solid #eee; }
.faq-row:last-child { border-bottom: none; }
.code { display: inline-block; min-width: 35px; padding: 2px 6px; margin-right: 8px; border-radius: 3px; text-align: center; font-weight: bold; color: white; font-size: 12px; }
.code-f { background: #28a745; }
.code-r { background: #dc3545; }
.code-tax { background: #007bff; }
.code-reg { background: #6f42c1; }
.code-qual { background: #fd7e14; }
.code-ex { background: #20c997; }
.code-id { background: #6c757d; }
</style>';

print '<div class="fichecenter">';

// GUÍA RÁPIDA
print '<div class="faq-section">';
print '<div class="faq-header" onclick="toggleFaq(this)">';
print '<span class="faq-arrow open">&#9654;</span> ';
print img_picto('', 'setup', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_QUICK_GUIDE");
print '</div>';
print '<div class="faq-content" style="display:block;">';
print '<div class="faq-row"><strong>' . $langs->trans("VERIFACTU_FAQ_WHAT_IS") . '</strong><br>' . $langs->trans("VERIFACTU_FAQ_WHAT_IS_DESC") . '</div>';
print '<div class="faq-row warning" style="padding:10px;"><strong>' . $langs->trans("VERIFACTU_FAQ_IMPORTANT_CONFIG") . '</strong><br>' . $langs->trans("VERIFACTU_FAQ_IMPORTANT_CONFIG_DESC") . '</div>';
print '<div class="faq-row"><strong>' . $langs->trans("VERIFACTU_FAQ_INITIAL_CONFIG") . '</strong><ul><li>' . $langs->trans("VERIFACTU_FAQ_STEP1") . '</li><li>' . $langs->trans("VERIFACTU_FAQ_STEP2") . '</li><li>' . $langs->trans("VERIFACTU_FAQ_STEP3") . '</li></ul></div>';
print '</div></div>';

// TIPOS DE FACTURA
print '<div class="faq-section">';
print '<div class="faq-header" onclick="toggleFaq(this)">';
print '<span class="faq-arrow">&#9654;</span> ';
print img_picto('', 'bill', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_INVOICE_TYPES");
print '</div>';
print '<div class="faq-content">';
$items = array(
	'F1' => array('Factura (art. 6 o 7.2 o 7.3 del RD 1619/2012)', 'VERIFACTU_FAQ_F1_DESC'),
	'F2' => array('Factura Simplificada (art. 6.1.d RD 1619/2012)', 'VERIFACTU_FAQ_F2_DESC'),
	'F3' => array('Factura en sustitución de simplificadas', 'VERIFACTU_FAQ_F3_DESC'),
	'R1' => array('Factura Rectificativa (Art. 80 Uno, Dos y Seis LIVA)', 'VERIFACTU_FAQ_R1_DESC'),
	'R2' => array('Factura Rectificativa (Art. 80.3)', 'VERIFACTU_FAQ_R2_DESC'),
	'R3' => array('Factura Rectificativa (Art. 80.4)', 'VERIFACTU_FAQ_R3_DESC'),
	'R4' => array('Factura Rectificativa (Resto de casos)', 'VERIFACTU_FAQ_R4_DESC'),
	'R5' => array('Factura Rectificativa en simplificadas', 'VERIFACTU_FAQ_R5_DESC'),
);
foreach ($items as $c => $d) {
	$cls = (substr($c, 0, 1) == 'F') ? 'code-f' : 'code-r';
	print '<div class="faq-row"><span class="code ' . $cls . '">' . $c . '</span><strong>' . $d[0] . '</strong><br><span class="opacitymedium">' . $langs->trans($d[1]) . '</span></div>';
}
print '</div></div>';

// TIPOS DE IMPUESTO
print '<div class="faq-section">';
print '<div class="faq-header" onclick="toggleFaq(this)">';
print '<span class="faq-arrow">&#9654;</span> ';
print img_picto('', 'payment', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_TAX_TYPES");
print '</div>';
print '<div class="faq-content">';
$items = array(
	'01' => array('IVA - Impuesto sobre el Valor Añadido', 'VERIFACTU_FAQ_TAX_01_DESC'),
	'02' => array('IPSI - Ceuta y Melilla', 'VERIFACTU_FAQ_TAX_02_DESC'),
	'03' => array('IGIC - Canarias', 'VERIFACTU_FAQ_TAX_03_DESC'),
	'05' => array('Otros impuestos', 'VERIFACTU_FAQ_TAX_05_DESC'),
);
foreach ($items as $c => $d) {
	print '<div class="faq-row"><span class="code code-tax">' . $c . '</span><strong>' . $d[0] . '</strong><br><span class="opacitymedium">' . $langs->trans($d[1]) . '</span></div>';
}
print '</div></div>';

// CLAVE DE RÉGIMEN
print '<div class="faq-section">';
print '<div class="faq-header" onclick="toggleFaq(this)">';
print '<span class="faq-arrow">&#9654;</span> ';
print img_picto('', 'category', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_REGIME_KEY");
print '</div>';
print '<div class="faq-content">';
$items = array(
	'01' => array('Operación de Régimen General', 'VERIFACTU_FAQ_REGIME_01_DESC'),
	'02' => array('Exportación', 'VERIFACTU_FAQ_REGIME_02_DESC'),
	'03' => array('Bienes usados, arte y antigüedades', 'VERIFACTU_FAQ_REGIME_03_DESC'),
	'04' => array('Oro de inversión', 'VERIFACTU_FAQ_REGIME_04_DESC'),
	'05' => array('Agencias de viajes', 'VERIFACTU_FAQ_REGIME_05_DESC'),
	'07' => array('Criterio de Caja', 'VERIFACTU_FAQ_REGIME_07_DESC'),
	'18' => array('Recargo de equivalencia', 'VERIFACTU_FAQ_REGIME_18_DESC'),
	'19' => array('REAGYP', 'VERIFACTU_FAQ_REGIME_19_DESC'),
	'20' => array('Régimen simplificado', 'VERIFACTU_FAQ_REGIME_20_DESC'),
);
foreach ($items as $c => $d) {
	print '<div class="faq-row"><span class="code code-reg">' . $c . '</span><strong>' . $d[0] . '</strong><br><span class="opacitymedium">' . $langs->trans($d[1]) . '</span></div>';
}
print '</div></div>';

// CALIFICACIÓN
print '<div class="faq-section">';
print '<div class="faq-header" onclick="toggleFaq(this)">';
print '<span class="faq-arrow">&#9654;</span> ';
print img_picto('', 'bookmark', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_QUALIFICATION");
print '</div>';
print '<div class="faq-content">';
$items = array(
	'S1' => array('Sujeta y No Exenta - Sin inversión', 'VERIFACTU_FAQ_S1_DESC'),
	'S2' => array('Sujeta y No Exenta - Con inversión', 'VERIFACTU_FAQ_S2_DESC'),
	'N1' => array('No sujeta (art. 7, 14)', 'VERIFACTU_FAQ_N1_DESC'),
	'N2' => array('No sujeta (localización)', 'VERIFACTU_FAQ_N2_DESC'),
);
foreach ($items as $c => $d) {
	print '<div class="faq-row"><span class="code code-qual">' . $c . '</span><strong>' . $d[0] . '</strong><br><span class="opacitymedium">' . $langs->trans($d[1]) . '</span></div>';
}
print '</div></div>';

// EXENCIONES
print '<div class="faq-section">';
print '<div class="faq-header" onclick="toggleFaq(this)">';
print '<span class="faq-arrow">&#9654;</span> ';
print img_picto('', 'generic', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_EXEMPTIONS");
print '</div>';
print '<div class="faq-content">';
$items = array(
	'E1' => array('Exenta por Artículo 20', 'VERIFACTU_FAQ_E1_DESC'),
	'E2' => array('Exenta por Artículo 21', 'VERIFACTU_FAQ_E2_DESC'),
	'E3' => array('Exenta por Artículo 22', 'VERIFACTU_FAQ_E3_DESC'),
	'E4' => array('Exenta por Artículos 23 y 24', 'VERIFACTU_FAQ_E4_DESC'),
	'E5' => array('Exenta por Artículo 25', 'VERIFACTU_FAQ_E5_DESC'),
	'E6' => array('Exenta por otros motivos', 'VERIFACTU_FAQ_E6_DESC'),
);
foreach ($items as $c => $d) {
	print '<div class="faq-row"><span class="code code-ex">' . $c . '</span><strong>' . $d[0] . '</strong><br><span class="opacitymedium">' . $langs->trans($d[1]) . '</span></div>';
}
print '</div></div>';

// TIPOS DE IDENTIFICACIÓN
print '<div class="faq-section">';
print '<div class="faq-header" onclick="toggleFaq(this)">';
print '<span class="faq-arrow">&#9654;</span> ';
print img_picto('', 'user', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_ID_TYPES");
print '</div>';
print '<div class="faq-content">';
$items = array(
	'02' => array('NIF-IVA', 'VERIFACTU_FAQ_ID_02_DESC'),
	'03' => array('Pasaporte', 'VERIFACTU_FAQ_ID_03_DESC'),
	'04' => array('Documento oficial del país', 'VERIFACTU_FAQ_ID_04_DESC'),
	'05' => array('Certificado de residencia', 'VERIFACTU_FAQ_ID_05_DESC'),
	'06' => array('Otro documento probatorio', 'VERIFACTU_FAQ_ID_06_DESC'),
	'07' => array('No censado', 'VERIFACTU_FAQ_ID_07_DESC'),
);
foreach ($items as $c => $d) {
	print '<div class="faq-row"><span class="code code-id">' . $c . '</span><strong>' . $d[0] . '</strong><br><span class="opacitymedium">' . $langs->trans($d[1]) . '</span></div>';
}
print '</div></div>';

// ERRORES COMUNES
print '<div class="faq-section">';
print '<div class="faq-header" onclick="toggleFaq(this)">';
print '<span class="faq-arrow">&#9654;</span> ';
print img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_COMMON_ERRORS");
print '</div>';
print '<div class="faq-content">';
print '<div class="faq-row"><strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_UK") . '</strong><br><div class="warning" style="margin-top:5px;padding:8px;">' . $langs->trans("VERIFACTU_FAQ_ERROR_UK_DESC") . '</div></div>';
print '<div class="faq-row"><strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_NIF") . '</strong><br><div class="info" style="margin-top:5px;padding:8px;">' . $langs->trans("VERIFACTU_FAQ_ERROR_NIF_DESC") . '</div></div>';
print '<div class="faq-row"><strong>' . $langs->trans("VERIFACTU_FAQ_RESEND") . '</strong><br>' . $langs->trans("VERIFACTU_FAQ_RESEND_DESC") . '</div>';
print '<div class="faq-row"><strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_CODES") . '</strong><br>' . $langs->trans("VERIFACTU_FAQ_ERROR_CODES_DESC") . '<br><a href="https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/errores.properties" target="_blank" class="button small" style="margin-top:8px;">' . img_picto('', 'globe', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_ERROR_CODES_LINK") . '</a></div>';
print '</div></div>';

print '</div>';

// JavaScript for accordion toggle
print '<script type="text/javascript">
function toggleFaq(header) {
	var content = header.nextElementSibling;
	var arrow = header.querySelector(".faq-arrow");
	if (content.style.display === "none" || content.style.display === "") {
		content.style.display = "block";
		arrow.classList.add("open");
	} else {
		content.style.display = "none";
		arrow.classList.remove("open");
	}
}
</script>';

llxFooter();
$db->close();
