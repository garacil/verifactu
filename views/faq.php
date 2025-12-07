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

// CSS para acordeón
print '<style>
.faq-accordion { margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
.faq-header { background: #f5f5f5; padding: 12px 15px; cursor: pointer; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
.faq-header:hover { background: #e8e8e8; }
.faq-header .faq-icon { transition: transform 0.3s; }
.faq-header.active .faq-icon { transform: rotate(180deg); }
.faq-content { display: none; padding: 15px; border-top: 1px solid #ddd; }
.faq-item-row { padding: 10px 0; border-bottom: 1px solid #eee; }
.faq-item-row:last-child { border-bottom: none; }
.faq-code { display: inline-block; min-width: 40px; padding: 2px 8px; margin-right: 10px; border-radius: 3px; text-align: center; font-weight: bold; color: white; }
.faq-code-f { background: #28a745; }
.faq-code-r { background: #dc3545; }
.faq-code-tax { background: #007bff; }
.faq-code-reg { background: #6f42c1; }
.faq-code-qual { background: #fd7e14; }
.faq-code-ex { background: #20c997; }
.faq-code-id { background: #6c757d; }
</style>';

print '<div class="fichecenter"><div class="fichehalfleft" style="width: 100%;">';

// ========== GUÍA RÁPIDA ==========
print '<div class="faq-accordion">';
print '<div class="faq-header active">';
print img_picto('', 'setup', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_QUICK_GUIDE");
print '<span class="faq-icon">▼</span></div>';
print '<div class="faq-content" style="display:block;">';
print '<div class="faq-item-row"><strong>' . $langs->trans("VERIFACTU_FAQ_WHAT_IS") . '</strong><br>' . $langs->trans("VERIFACTU_FAQ_WHAT_IS_DESC") . '</div>';
print '<div class="faq-item-row warning" style="padding:10px;"><strong>' . $langs->trans("VERIFACTU_FAQ_IMPORTANT_CONFIG") . '</strong><br>' . $langs->trans("VERIFACTU_FAQ_IMPORTANT_CONFIG_DESC") . '</div>';
print '<div class="faq-item-row"><strong>' . $langs->trans("VERIFACTU_FAQ_INITIAL_CONFIG") . '</strong><ul>';
print '<li>' . $langs->trans("VERIFACTU_FAQ_STEP1") . '</li>';
print '<li>' . $langs->trans("VERIFACTU_FAQ_STEP2") . '</li>';
print '<li>' . $langs->trans("VERIFACTU_FAQ_STEP3") . '</li>';
print '</ul></div>';
print '</div></div>';

// ========== TIPOS DE FACTURA ==========
print '<div class="faq-accordion">';
print '<div class="faq-header">';
print img_picto('', 'bill', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_INVOICE_TYPES");
print '<span class="faq-icon">▼</span></div>';
print '<div class="faq-content">';
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
	$codeClass = (substr($code, 0, 1) == 'F') ? 'faq-code-f' : 'faq-code-r';
	print '<div class="faq-item-row"><span class="faq-code ' . $codeClass . '">' . $code . '</span><strong>' . $data['title'] . '</strong><br><span class="opacitymedium" style="margin-left:50px;display:block;">' . $langs->trans($data['desc']) . '</span></div>';
}
print '</div></div>';

// ========== TIPOS DE IMPUESTO ==========
print '<div class="faq-accordion">';
print '<div class="faq-header">';
print img_picto('', 'payment', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_TAX_TYPES");
print '<span class="faq-icon">▼</span></div>';
print '<div class="faq-content">';
$taxTypes = array(
	'01' => array('title' => 'IVA - Impuesto sobre el Valor Añadido', 'desc' => 'VERIFACTU_FAQ_TAX_01_DESC'),
	'02' => array('title' => 'IPSI - Ceuta y Melilla', 'desc' => 'VERIFACTU_FAQ_TAX_02_DESC'),
	'03' => array('title' => 'IGIC - Canarias', 'desc' => 'VERIFACTU_FAQ_TAX_03_DESC'),
	'05' => array('title' => 'Otros impuestos', 'desc' => 'VERIFACTU_FAQ_TAX_05_DESC'),
);
foreach ($taxTypes as $code => $data) {
	print '<div class="faq-item-row"><span class="faq-code faq-code-tax">' . $code . '</span><strong>' . $data['title'] . '</strong><br><span class="opacitymedium" style="margin-left:50px;display:block;">' . $langs->trans($data['desc']) . '</span></div>';
}
print '</div></div>';

// ========== CLAVE DE RÉGIMEN ==========
print '<div class="faq-accordion">';
print '<div class="faq-header">';
print img_picto('', 'category', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_REGIME_KEY");
print '<span class="faq-icon">▼</span></div>';
print '<div class="faq-content">';
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
	print '<div class="faq-item-row"><span class="faq-code faq-code-reg">' . $code . '</span><strong>' . $data['title'] . '</strong><br><span class="opacitymedium" style="margin-left:50px;display:block;">' . $langs->trans($data['desc']) . '</span></div>';
}
print '</div></div>';

// ========== CALIFICACIÓN DE LA OPERACIÓN ==========
print '<div class="faq-accordion">';
print '<div class="faq-header">';
print img_picto('', 'bookmark', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_QUALIFICATION");
print '<span class="faq-icon">▼</span></div>';
print '<div class="faq-content">';
$qualifications = array(
	'S1' => array('title' => 'Sujeta y No Exenta - Sin inversión', 'desc' => 'VERIFACTU_FAQ_S1_DESC'),
	'S2' => array('title' => 'Sujeta y No Exenta - Con inversión', 'desc' => 'VERIFACTU_FAQ_S2_DESC'),
	'N1' => array('title' => 'No sujeta (art. 7, 14)', 'desc' => 'VERIFACTU_FAQ_N1_DESC'),
	'N2' => array('title' => 'No sujeta (localización)', 'desc' => 'VERIFACTU_FAQ_N2_DESC'),
);
foreach ($qualifications as $code => $data) {
	print '<div class="faq-item-row"><span class="faq-code faq-code-qual">' . $code . '</span><strong>' . $data['title'] . '</strong><br><span class="opacitymedium" style="margin-left:50px;display:block;">' . $langs->trans($data['desc']) . '</span></div>';
}
print '</div></div>';

// ========== EXENCIONES ==========
print '<div class="faq-accordion">';
print '<div class="faq-header">';
print img_picto('', 'generic', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_EXEMPTIONS");
print '<span class="faq-icon">▼</span></div>';
print '<div class="faq-content">';
$exemptions = array(
	'E1' => array('title' => 'Exenta por Artículo 20', 'desc' => 'VERIFACTU_FAQ_E1_DESC'),
	'E2' => array('title' => 'Exenta por Artículo 21', 'desc' => 'VERIFACTU_FAQ_E2_DESC'),
	'E3' => array('title' => 'Exenta por Artículo 22', 'desc' => 'VERIFACTU_FAQ_E3_DESC'),
	'E4' => array('title' => 'Exenta por Artículos 23 y 24', 'desc' => 'VERIFACTU_FAQ_E4_DESC'),
	'E5' => array('title' => 'Exenta por Artículo 25', 'desc' => 'VERIFACTU_FAQ_E5_DESC'),
	'E6' => array('title' => 'Exenta por otros motivos', 'desc' => 'VERIFACTU_FAQ_E6_DESC'),
);
foreach ($exemptions as $code => $data) {
	print '<div class="faq-item-row"><span class="faq-code faq-code-ex">' . $code . '</span><strong>' . $data['title'] . '</strong><br><span class="opacitymedium" style="margin-left:50px;display:block;">' . $langs->trans($data['desc']) . '</span></div>';
}
print '</div></div>';

// ========== TIPOS DE IDENTIFICACIÓN ==========
print '<div class="faq-accordion">';
print '<div class="faq-header">';
print img_picto('', 'user', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_ID_TYPES");
print '<span class="faq-icon">▼</span></div>';
print '<div class="faq-content">';
$idTypes = array(
	'02' => array('title' => 'NIF-IVA', 'desc' => 'VERIFACTU_FAQ_ID_02_DESC'),
	'03' => array('title' => 'Pasaporte', 'desc' => 'VERIFACTU_FAQ_ID_03_DESC'),
	'04' => array('title' => 'Documento oficial del país', 'desc' => 'VERIFACTU_FAQ_ID_04_DESC'),
	'05' => array('title' => 'Certificado de residencia', 'desc' => 'VERIFACTU_FAQ_ID_05_DESC'),
	'06' => array('title' => 'Otro documento probatorio', 'desc' => 'VERIFACTU_FAQ_ID_06_DESC'),
	'07' => array('title' => 'No censado', 'desc' => 'VERIFACTU_FAQ_ID_07_DESC'),
);
foreach ($idTypes as $code => $data) {
	print '<div class="faq-item-row"><span class="faq-code faq-code-id">' . $code . '</span><strong>' . $data['title'] . '</strong><br><span class="opacitymedium" style="margin-left:50px;display:block;">' . $langs->trans($data['desc']) . '</span></div>';
}
print '</div></div>';

// ========== ERRORES COMUNES ==========
print '<div class="faq-accordion">';
print '<div class="faq-header">';
print img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_COMMON_ERRORS");
print '<span class="faq-icon">▼</span></div>';
print '<div class="faq-content">';
print '<div class="faq-item-row"><strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_UK") . '</strong><br><div class="warning" style="margin-top:5px;padding:8px;">' . $langs->trans("VERIFACTU_FAQ_ERROR_UK_DESC") . '</div></div>';
print '<div class="faq-item-row"><strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_NIF") . '</strong><br><div class="info" style="margin-top:5px;padding:8px;">' . $langs->trans("VERIFACTU_FAQ_ERROR_NIF_DESC") . '</div></div>';
print '<div class="faq-item-row"><strong>' . $langs->trans("VERIFACTU_FAQ_RESEND") . '</strong><br>' . $langs->trans("VERIFACTU_FAQ_RESEND_DESC") . '</div>';
print '<div class="faq-item-row"><strong>' . $langs->trans("VERIFACTU_FAQ_ERROR_CODES") . '</strong><br>' . $langs->trans("VERIFACTU_FAQ_ERROR_CODES_DESC");
print '<br><a href="https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/errores.properties" target="_blank" class="button small" style="margin-top:8px;">';
print img_picto('', 'globe', 'class="pictofixedwidth"') . $langs->trans("VERIFACTU_FAQ_ERROR_CODES_LINK") . '</a></div>';
print '</div></div>';

print '</div></div>'; // fichecenter

// JavaScript con jQuery (ya cargado en Dolibarr)
print '<script type="text/javascript">
jQuery(document).ready(function($) {
	$(".faq-header").on("click", function() {
		var content = $(this).next(".faq-content");
		content.slideToggle(200);
		$(this).toggleClass("active");
	});
});
</script>';

llxFooter();
$db->close();
