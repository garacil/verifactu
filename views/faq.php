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

$langs->loadLangs(array("admin", "other", "verifactu@verifactu"));

if (!$user->rights->verifactu->manage) {
	accessforbidden();
}

$title = $langs->trans("VERIFACTU_FAQ_TITLE");
llxHeader('', $title, '');

print load_fiche_titre($langs->trans("VERIFACTU_FAQ_TITLE"), '', 'verifactu@verifactu');

// CSS coherente con Dolibarr
print '<style>
/* Contenedor principal */
.vf-help-doc { max-width: 960px; }

/* Índice de contenidos */
.vf-toc {
	background: linear-gradient(135deg, var(--colorbacktitle1, #f4f4f4) 0%, var(--colorbackbody, #fff) 100%);
	border: 1px solid var(--colorborder, #e0e0e0);
	border-radius: 8px;
	padding: 20px 25px;
	margin-bottom: 25px;
	box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}
.vf-toc-title {
	margin: 0 0 15px 0;
	padding-bottom: 12px;
	border-bottom: 2px solid var(--colortext, #333);
	font-size: 1.1em;
	color: var(--colortext, #333);
}
.vf-toc-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
	gap: 8px 20px;
}
.vf-toc-list li { margin: 0; }
.vf-toc-list a {
	color: var(--colortextlink, #0066cc);
	text-decoration: none;
	display: block;
	padding: 6px 10px;
	border-radius: 4px;
	transition: all 0.2s ease;
}
.vf-toc-list a:hover {
	background: rgba(0,102,204,0.08);
	color: var(--colortextlink, #0066cc);
}

/* Secciones */
.vf-section {
	margin-bottom: 30px;
	padding: 20px;
	background: var(--colorbackbody, #fff);
	border: 1px solid var(--colorborder, #e0e0e0);
	border-radius: 8px;
	box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.vf-section-title {
	margin: 0 0 20px 0;
	padding: 0 0 12px 0;
	border-bottom: 1px solid var(--colorborder, #e0e0e0);
	font-size: 1.15em;
	color: var(--colortext, #333);
	display: flex;
	align-items: center;
	gap: 8px;
}
.vf-section-title .badge-status { margin-right: 8px; }

/* Cajas de información */
.vf-box {
	border-radius: 6px;
	padding: 15px 18px;
	margin-bottom: 18px;
}
.vf-box-info {
	background: linear-gradient(135deg, #e8f4fd 0%, #f0f8ff 100%);
	border-left: 4px solid #17a2b8;
}
.vf-box-warning {
	background: linear-gradient(135deg, #fff8e6 0%, #fffdf5 100%);
	border-left: 4px solid #ffc107;
}
.vf-box-success {
	background: linear-gradient(135deg, #e8f5e9 0%, #f1f8f1 100%);
	border-left: 4px solid #28a745;
}
.vf-box-title {
	font-weight: 600;
	margin-bottom: 8px;
	display: flex;
	align-items: center;
	gap: 6px;
}
.vf-box-warning .vf-box-title { color: #856404; }
.vf-box-info .vf-box-title { color: #0c5460; }

/* Pasos */
.vf-steps {
	background: var(--colorbacktitle1, #f8f9fa);
	border-radius: 6px;
	padding: 18px 20px;
	margin-bottom: 18px;
}
.vf-steps-title {
	font-weight: 600;
	margin-bottom: 12px;
	color: var(--colortext, #333);
}
.vf-steps ol {
	margin: 0;
	padding-left: 22px;
}
.vf-steps li {
	margin-bottom: 10px;
	line-height: 1.5;
}
.vf-steps li:last-child { margin-bottom: 0; }

/* Tablas */
.vf-table {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	margin-bottom: 15px;
	border-radius: 6px;
	overflow: hidden;
	border: 1px solid var(--colorborder, #dee2e6);
}
.vf-table th {
	background: var(--colorbacktitle1, #f8f9fa);
	text-align: left;
	padding: 12px 15px;
	font-weight: 600;
	color: var(--colortext, #333);
	border-bottom: 2px solid var(--colorborder, #dee2e6);
}
.vf-table td {
	padding: 12px 15px;
	border-bottom: 1px solid var(--colorborder, #eee);
	vertical-align: top;
	line-height: 1.5;
}
.vf-table tr:last-child td { border-bottom: none; }
.vf-table tr:hover td { background: rgba(0,0,0,0.015); }

/* Badges de código */
.vf-code {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 38px;
	padding: 4px 10px;
	border-radius: 4px;
	font-weight: 600;
	color: white;
	font-size: 12px;
	text-shadow: 0 1px 1px rgba(0,0,0,0.15);
}
.vf-code-f { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
.vf-code-r { background: linear-gradient(135deg, #dc3545 0%, #e74c3c 100%); }
.vf-code-tax { background: linear-gradient(135deg, #007bff 0%, #17a2b8 100%); }
.vf-code-reg { background: linear-gradient(135deg, #6f42c1 0%, #9561e2 100%); }
.vf-code-qual { background: linear-gradient(135deg, #fd7e14 0%, #f39c12 100%); }
.vf-code-ex { background: linear-gradient(135deg, #20c997 0%, #38d9a9 100%); }
.vf-code-id { background: linear-gradient(135deg, #6c757d 0%, #868e96 100%); }

/* Enlace volver */
.vf-back-link {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	margin-top: 15px;
	padding: 6px 12px;
	color: var(--colortextlink, #6c757d);
	text-decoration: none;
	font-size: 0.9em;
	border-radius: 4px;
	transition: all 0.2s ease;
}
.vf-back-link:hover {
	background: var(--colorbacktitle1, #f8f9fa);
	color: var(--colortextlink, #0066cc);
}

/* Lista en tabla */
.vf-table ul {
	margin: 8px 0 0 0;
	padding-left: 18px;
}
.vf-table li { margin-bottom: 4px; }

/* Responsive */
@media (max-width: 768px) {
	.vf-toc-list { grid-template-columns: 1fr; }
	.vf-section { padding: 15px; }
	.vf-table th, .vf-table td { padding: 10px; }
}
</style>';

print '<div class="fichecenter vf-help-doc">';

// ==================== ÍNDICE ====================
print '<div class="vf-toc" id="indice">';
print '<h3 class="vf-toc-title">' . img_picto('', 'list', 'class="pictofixedwidth"') . 'Índice de Contenidos</h3>';
print '<ul class="vf-toc-list">';
print '<li>' . img_picto('', 'setup', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#guia-rapida">1. Guía Rápida</a></li>';
print '<li>' . img_picto('', 'bill', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#tipos-factura">2. Tipos de Factura</a></li>';
print '<li>' . img_picto('', 'payment', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#tipos-impuesto">3. Tipos de Impuesto</a></li>';
print '<li>' . img_picto('', 'category', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#clave-regimen">4. Clave de Régimen</a></li>';
print '<li>' . img_picto('', 'bookmark', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#calificacion">5. Calificación de Operación</a></li>';
print '<li>' . img_picto('', 'generic', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#exenciones">6. Exenciones</a></li>';
print '<li>' . img_picto('', 'user', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#tipos-identificacion">7. Tipos de Identificación</a></li>';
print '<li>' . img_picto('', 'warning', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#errores-comunes">8. Errores Comunes</a></li>';
print '<li>' . img_picto('', 'technic', 'class="pictofixedwidth em080 opacitymedium"') . '<a href="#flujo-trabajo">9. Flujo de Trabajo</a></li>';
print '</ul>';
print '</div>';

// ==================== 1. GUÍA RÁPIDA ====================
print '<div class="vf-section" id="guia-rapida">';
print '<h2 class="vf-section-title">' . img_picto('', 'setup', 'class="pictofixedwidth"') . '1. Guía Rápida</h2>';

print '<div class="vf-box vf-box-info">';
print '<div class="vf-box-title">' . img_picto('', 'info', 'class="pictofixedwidth"') . '¿Qué es VeriFactu?</div>';
print 'VeriFactu es el sistema de la Agencia Tributaria Española (AEAT) para verificar la integridad y autenticidad de las facturas electrónicas. ';
print 'Este módulo integra Dolibarr con VeriFactu para cumplir con las obligaciones fiscales españolas establecidas en el Real Decreto 1007/2023.';
print '</div>';

print '<div class="vf-box vf-box-warning">';
print '<div class="vf-box-title">' . img_picto('', 'warning', 'class="pictofixedwidth"') . 'Configuración Manual Obligatoria</div>';
print 'Este módulo <strong>NO</strong> realiza inferencia automática de parámetros fiscales. Todos los campos (Tipo de Impuesto, Clave de Régimen, Calificación y Operación Exenta) ';
print 'deben configurarse manualmente según las características de cada operación. <strong>Consulte siempre con su asesor fiscal en caso de duda.</strong>';
print '</div>';

print '<div class="vf-steps">';
print '<div class="vf-steps-title">' . img_picto('', 'tick', 'class="pictofixedwidth"') . 'Pasos de Configuración Inicial</div>';
print '<ol>';
print '<li><strong>Activar el módulo:</strong> Vaya a Configuración → Módulos → VeriFactu y active el módulo.</li>';
print '<li><strong>Configurar licencia:</strong> Obtenga y configure la licencia del módulo en la página de configuración.</li>';
print '<li><strong>Certificado digital:</strong> Configure el certificado digital de firma (FNMT o similar) para firmar las facturas.</li>';
print '<li><strong>Valores por defecto:</strong> Configure los valores fiscales por defecto (Impuesto, Régimen, etc.) en la configuración del módulo.</li>';
print '<li><strong>Configurar clientes:</strong> Revise la configuración fiscal de cada tercero/cliente según corresponda.</li>';
print '</ol>';
print '</div>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

// ==================== 2. TIPOS DE FACTURA ====================
print '<div class="vf-section" id="tipos-factura">';
print '<h2 class="vf-section-title">' . img_picto('', 'bill', 'class="pictofixedwidth"') . '2. Tipos de Factura</h2>';

print '<p class="opacitymedium">VeriFactu distingue entre facturas normales (F) y rectificativas (R). El tipo se determina según la naturaleza de la operación y el artículo aplicable del RD 1619/2012.</p>';

print '<table class="vf-table">';
print '<tr><th style="width:70px;">Código</th><th style="width:230px;">Tipo</th><th>Descripción y Uso</th></tr>';

print '<tr><td><span class="vf-code vf-code-f">F1</span></td>';
print '<td><strong>Factura Completa</strong><br><span class="opacitymedium">Art. 6, 7.2, 7.3 RD 1619/2012</span></td>';
print '<td>Factura completa estándar para operaciones B2B (empresa a empresa). Incluye todos los datos obligatorios: NIF emisor y receptor, descripción detallada, desglose de IVA. Es el tipo más común.</td></tr>';

print '<tr><td><span class="vf-code vf-code-f">F2</span></td>';
print '<td><strong>Factura Simplificada</strong><br><span class="opacitymedium">Art. 6.1.d RD 1619/2012</span></td>';
print '<td>Para ventas al consumidor final (B2C). Límite máximo de 400€ IVA incluido (3.000€ en ciertos sectores). No requiere datos completos del destinatario. Típica en comercio minorista, hostelería.</td></tr>';

print '<tr><td><span class="vf-code vf-code-f">F3</span></td>';
print '<td><strong>Factura Sustitutiva</strong></td>';
print '<td>Factura completa que sustituye a una simplificada previamente declarada. Se usa cuando el cliente solicita factura completa después de recibir ticket/factura simplificada.</td></tr>';

print '<tr><td><span class="vf-code vf-code-r">R1</span></td>';
print '<td><strong>Rectificativa por Error</strong><br><span class="opacitymedium">Art. 80.1, 80.2, 80.6 LIVA</span></td>';
print '<td>Corrige errores en base imponible o cuota tributaria. Usar para errores de cálculo, aplicación incorrecta del tipo de IVA, o datos erróneos que afectan al importe.</td></tr>';

print '<tr><td><span class="vf-code vf-code-r">R2</span></td>';
print '<td><strong>Rectificativa por Modificación</strong><br><span class="opacitymedium">Art. 80.3 LIVA</span></td>';
print '<td>Para modificación de condiciones después de facturar. Ejemplos: descuentos posteriores, rappels, modificaciones contractuales que afectan al precio.</td></tr>';

print '<tr><td><span class="vf-code vf-code-r">R3</span></td>';
print '<td><strong>Rectificativa por Anulación</strong><br><span class="opacitymedium">Art. 80.4 LIVA</span></td>';
print '<td>Para operaciones que quedan sin efecto: devolución de mercancías, resolución de contratos, impago certificado judicialmente, declaración de concurso.</td></tr>';

print '<tr><td><span class="vf-code vf-code-r">R4</span></td>';
print '<td><strong>Rectificativa Otros</strong></td>';
print '<td>Rectificativa para casos no contemplados en R1, R2 o R3. Usar como último recurso cuando ninguna categoría anterior aplica.</td></tr>';

print '<tr><td><span class="vf-code vf-code-r">R5</span></td>';
print '<td><strong>Rectificativa Simplificada</strong></td>';
print '<td>Rectificativa específica para corregir facturas simplificadas (F2). Aplican los mismos criterios que R1-R4 pero sobre facturas simplificadas.</td></tr>';

print '</table>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

// ==================== 3. TIPOS DE IMPUESTO ====================
print '<div class="vf-section" id="tipos-impuesto">';
print '<h2 class="vf-section-title">' . img_picto('', 'payment', 'class="pictofixedwidth"') . '3. Tipos de Impuesto</h2>';

print '<p class="opacitymedium">El tipo de impuesto depende del territorio donde se realiza la operación. España tiene diferentes regímenes fiscales según la zona geográfica.</p>';

print '<table class="vf-table">';
print '<tr><th style="width:70px;">Código</th><th style="width:180px;">Impuesto</th><th>Territorio y Tipos Aplicables</th></tr>';

print '<tr><td><span class="vf-code vf-code-tax">01</span></td>';
print '<td><strong>IVA</strong><br><span class="opacitymedium">Impuesto sobre el Valor Añadido</span></td>';
print '<td><strong>Territorio:</strong> Península y Baleares<ul>';
print '<li><strong>21%</strong> - Tipo general (mayoría de bienes y servicios)</li>';
print '<li><strong>10%</strong> - Tipo reducido (alimentos, transporte, hostelería)</li>';
print '<li><strong>4%</strong> - Tipo superreducido (pan, leche, libros, medicamentos)</li>';
print '<li><strong>0%</strong> - Operaciones exentas con derecho a deducción</li>';
print '</ul></td></tr>';

print '<tr><td><span class="vf-code vf-code-tax">02</span></td>';
print '<td><strong>IPSI</strong><br><span class="opacitymedium">Impuesto Producción y Servicios</span></td>';
print '<td><strong>Territorio:</strong> Ceuta y Melilla<br>';
print '<strong>Tipos:</strong> Variables según producto (0,5% a 10%). Generalmente más bajos que IVA peninsular por régimen fiscal especial.</td></tr>';

print '<tr><td><span class="vf-code vf-code-tax">03</span></td>';
print '<td><strong>IGIC</strong><br><span class="opacitymedium">Impuesto General Indirecto Canario</span></td>';
print '<td><strong>Territorio:</strong> Islas Canarias<ul>';
print '<li><strong>7%</strong> - Tipo general</li>';
print '<li><strong>3%</strong> - Tipo reducido</li>';
print '<li><strong>0%</strong> - Tipo cero (primera necesidad)</li>';
print '<li><strong>9,5% / 13,5% / 15%</strong> - Tipos incrementados</li>';
print '</ul></td></tr>';

print '<tr><td><span class="vf-code vf-code-tax">05</span></td>';
print '<td><strong>Otros</strong></td>';
print '<td>Impuestos especiales no contemplados en categorías anteriores. Uso excepcional para situaciones específicas.</td></tr>';

print '</table>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

// ==================== 4. CLAVE DE RÉGIMEN ====================
print '<div class="vf-section" id="clave-regimen">';
print '<h2 class="vf-section-title">' . img_picto('', 'category', 'class="pictofixedwidth"') . '4. Clave de Régimen Especial</h2>';

print '<div class="vf-box vf-box-warning">';
print '<div class="vf-box-title">' . img_picto('', 'warning', 'class="pictofixedwidth"') . 'Importante</div>';
print 'La Clave de Régimen solo es aplicable para operaciones con <strong>IVA (01)</strong> o <strong>IGIC (03)</strong>. No se utiliza para IPSI ni Otros impuestos.';
print '</div>';

print '<table class="vf-table">';
print '<tr><th style="width:70px;">Código</th><th style="width:200px;">Régimen</th><th>Descripción y Aplicación</th></tr>';

print '<tr><td><span class="vf-code vf-code-reg">01</span></td>';
print '<td><strong>Régimen General</strong></td>';
print '<td>Régimen estándar para la mayoría de operaciones comerciales. <strong>Valor por defecto más común.</strong> Aplicar cuando no corresponda ningún régimen especial.</td></tr>';

print '<tr><td><span class="vf-code vf-code-reg">02</span></td>';
print '<td><strong>Exportación</strong></td>';
print '<td>Ventas de bienes fuera de la UE. Operación exenta con derecho a deducción. Requiere documentación aduanera (DUA).</td></tr>';

print '<tr><td><span class="vf-code vf-code-reg">03</span></td>';
print '<td><strong>Bienes Usados, Arte</strong></td>';
print '<td>Régimen especial de bienes usados, arte y antigüedades. El IVA se calcula solo sobre el margen de beneficio.</td></tr>';

print '<tr><td><span class="vf-code vf-code-reg">04</span></td>';
print '<td><strong>Oro de Inversión</strong></td>';
print '<td>Operaciones con oro de inversión (lingotes, monedas). Generalmente exentas según art. 140 LIVA.</td></tr>';

print '<tr><td><span class="vf-code vf-code-reg">05</span></td>';
print '<td><strong>Agencias de Viajes</strong></td>';
print '<td>Régimen especial REAV. El IVA se aplica sobre el margen bruto de la agencia.</td></tr>';

print '<tr><td><span class="vf-code vf-code-reg">07</span></td>';
print '<td><strong>Criterio de Caja</strong></td>';
print '<td>RECC: IVA se devenga al cobrar, no al facturar. Mejora flujo de caja para PYMES (facturación < 2M€).</td></tr>';

print '<tr><td><span class="vf-code vf-code-reg">18</span></td>';
print '<td><strong>Recargo de Equivalencia</strong></td>';
print '<td>Régimen para comerciantes minoristas. Recargos: 5,2% (21%), 1,4% (10%), 0,5% (4%).</td></tr>';

print '<tr><td><span class="vf-code vf-code-reg">19</span></td>';
print '<td><strong>REAGYP</strong></td>';
print '<td>Régimen Especial de Agricultura, Ganadería y Pesca. Para agricultores en estimación objetiva.</td></tr>';

print '<tr><td><span class="vf-code vf-code-reg">20</span></td>';
print '<td><strong>Régimen Simplificado</strong></td>';
print '<td>Estimación objetiva de cuotas de IVA mediante módulos para pequeños empresarios.</td></tr>';

print '</table>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

// ==================== 5. CALIFICACIÓN DE OPERACIÓN ====================
print '<div class="vf-section" id="calificacion">';
print '<h2 class="vf-section-title">' . img_picto('', 'bookmark', 'class="pictofixedwidth"') . '5. Calificación de la Operación</h2>';

print '<p class="opacitymedium">La calificación indica si la operación está sujeta al impuesto y, en caso de estarlo, si hay inversión del sujeto pasivo.</p>';

print '<table class="vf-table">';
print '<tr><th style="width:70px;">Código</th><th style="width:200px;">Calificación</th><th>Descripción y Cuándo Aplicar</th></tr>';

print '<tr><td><span class="vf-code vf-code-qual">S1</span></td>';
print '<td><strong>Sujeta y No Exenta</strong><br><span class="opacitymedium">Sin Inversión Sujeto Pasivo</span></td>';
print '<td><span class="badge badge-status4 badge-status">CASO MÁS COMÚN</span><br>El vendedor repercute el IVA al cliente y lo declara a Hacienda. Aplica a la inmensa mayoría de operaciones comerciales.</td></tr>';

print '<tr><td><span class="vf-code vf-code-qual">S2</span></td>';
print '<td><strong>Sujeta y No Exenta</strong><br><span class="opacitymedium">Con Inversión Sujeto Pasivo</span></td>';
print '<td>El comprador declara el IVA. Casos típicos:<ul>';
print '<li>Obras de construcción (Art. 84.Uno.2º.f LIVA)</li>';
print '<li>Transmisión de inmuebles en ejecución de garantía</li>';
print '<li>Entregas de oro sin elaborar</li>';
print '</ul></td></tr>';

print '<tr><td><span class="vf-code vf-code-qual">N1</span></td>';
print '<td><strong>No Sujeta</strong><br><span class="opacitymedium">Art. 7 y 14 LIVA</span></td>';
print '<td>Operación no sujeta por su naturaleza:<ul>';
print '<li>Transmisiones de patrimonio empresarial</li>';
print '<li>Entregas gratuitas de muestras publicitarias</li>';
print '<li>Operaciones de entes públicos</li>';
print '</ul></td></tr>';

print '<tr><td><span class="vf-code vf-code-qual">N2</span></td>';
print '<td><strong>No Sujeta</strong><br><span class="opacitymedium">Por Localización</span></td>';
print '<td>No sujeta en España por reglas de localización. El servicio se entiende prestado en otro territorio (Art. 69-70 LIVA).</td></tr>';

print '</table>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

// ==================== 6. EXENCIONES ====================
print '<div class="vf-section" id="exenciones">';
print '<h2 class="vf-section-title">' . img_picto('', 'generic', 'class="pictofixedwidth"') . '6. Causas de Exención</h2>';

print '<p class="opacitymedium">Las exenciones se aplican cuando la operación está sujeta al IVA pero existe una causa legal que la libera del impuesto.</p>';

print '<table class="vf-table">';
print '<tr><th style="width:70px;">Código</th><th style="width:200px;">Exención</th><th>Operaciones Exentas</th></tr>';

print '<tr><td><span class="vf-code vf-code-ex">E1</span></td>';
print '<td><strong>Art. 20 LIVA</strong><br><span class="opacitymedium">Operaciones interiores</span></td>';
print '<td>Servicios de interés general: médicos, educativos, sociales, deportivos sin ánimo de lucro, seguros, arrendamiento de viviendas.</td></tr>';

print '<tr><td><span class="vf-code vf-code-ex">E2</span></td>';
print '<td><strong>Art. 21 LIVA</strong><br><span class="opacitymedium">Exportaciones</span></td>';
print '<td>Entregas de bienes fuera de la UE, entregas a viajeros (tax-free), servicios relacionados con exportaciones.</td></tr>';

print '<tr><td><span class="vf-code vf-code-ex">E3</span></td>';
print '<td><strong>Art. 22 LIVA</strong><br><span class="opacitymedium">Zonas francas</span></td>';
print '<td>Operaciones en zonas y depósitos francos, depósitos aduaneros.</td></tr>';

print '<tr><td><span class="vf-code vf-code-ex">E4</span></td>';
print '<td><strong>Art. 23-24 LIVA</strong><br><span class="opacitymedium">Entregas intracomunitarias</span></td>';
print '<td>Entregas de bienes a otros Estados de la UE cuando el adquirente es sujeto pasivo del IVA. Requiere NIF-IVA intracomunitario válido.</td></tr>';

print '<tr><td><span class="vf-code vf-code-ex">E5</span></td>';
print '<td><strong>Art. 25 LIVA</strong><br><span class="opacitymedium">Importaciones</span></td>';
print '<td>Importaciones con exención: reimportación, importaciones temporales.</td></tr>';

print '<tr><td><span class="vf-code vf-code-ex">E6</span></td>';
print '<td><strong>Otros motivos</strong></td>';
print '<td>Otras exenciones contempladas en normativa fiscal. Consultar asesor fiscal.</td></tr>';

print '</table>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

// ==================== 7. TIPOS DE IDENTIFICACIÓN ====================
print '<div class="vf-section" id="tipos-identificacion">';
print '<h2 class="vf-section-title">' . img_picto('', 'user', 'class="pictofixedwidth"') . '7. Tipos de Identificación del Destinatario</h2>';

print '<p class="opacitymedium">El tipo de identificación se usa para destinatarios extranjeros. Para clientes españoles con NIF válido, no se especifica.</p>';

print '<table class="vf-table">';
print '<tr><th style="width:70px;">Código</th><th style="width:160px;">Tipo</th><th>Uso y Observaciones</th></tr>';

print '<tr><td><span class="vf-code vf-code-id">02</span></td>';
print '<td><strong>NIF-IVA</strong></td>';
print '<td><span class="badge badge-status4 badge-status">USO MÁS FRECUENTE (95%)</span><br>NIF español o NIF-IVA intracomunitario (formato: código país + número, ej: FR12345678901). Verificar en sistema VIES.</td></tr>';

print '<tr><td><span class="vf-code vf-code-id">03</span></td>';
print '<td><strong>Pasaporte</strong></td>';
print '<td>Para extranjeros sin residencia fiscal ni NIF-IVA. Típico en ventas a turistas.</td></tr>';

print '<tr><td><span class="vf-code vf-code-id">04</span></td>';
print '<td><strong>Documento Oficial</strong></td>';
print '<td>DNI o documento de identidad oficial del país de origen.</td></tr>';

print '<tr><td><span class="vf-code vf-code-id">05</span></td>';
print '<td><strong>Certificado Residencia</strong></td>';
print '<td>Certificado de autoridades fiscales del país de residencia. Uso excepcional.</td></tr>';

print '<tr><td><span class="vf-code vf-code-id">06</span></td>';
print '<td><strong>Otro Documento</strong></td>';
print '<td>Otros documentos cuando no aplican categorías anteriores. Incluye clientes de Reino Unido (post-Brexit).</td></tr>';

print '<tr><td><span class="vf-code vf-code-id">07</span></td>';
print '<td><strong>No Censado</strong></td>';
print '<td><span class="badge badge-status1 badge-status">USO MUY RESTRINGIDO</span><br>Solo para clientes sin ningún documento. La AEAT puede requerir justificación.</td></tr>';

print '</table>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

// ==================== 8. ERRORES COMUNES ====================
print '<div class="vf-section" id="errores-comunes">';
print '<h2 class="vf-section-title">' . img_picto('', 'warning', 'class="pictofixedwidth"') . '8. Errores Comunes y Soluciones</h2>';

print '<table class="vf-table">';
print '<tr><th style="width:30%;">Error</th><th>Causa y Solución</th></tr>';

print '<tr><td><strong>' . img_picto('', 'error', 'class="pictofixedwidth"') . 'Cliente UK con NIF-IVA Intracomunitario</strong></td>';
print '<td><div class="vf-box vf-box-warning" style="margin:0;">';
print '<strong>Problema:</strong> Reino Unido no es UE desde 2021 (Brexit). Los NIF-IVA con prefijo GB no son válidos como intracomunitarios.<br><br>';
print '<strong>Solución:</strong> Cambiar tipo de identificación a "Otro Documento Probatorio" (06) o "Documento Oficial" (04).';
print '</div></td></tr>';

print '<tr><td><strong>' . img_picto('', 'error', 'class="pictofixedwidth"') . 'Cliente sin NIF válido</strong></td>';
print '<td><div class="vf-box vf-box-info" style="margin:0;">';
print '<strong>Problema:</strong> VeriFactu requiere identificación válida, incluso para particulares.<br><br>';
print '<strong>Solución:</strong> Configurar DNI/NIE del cliente en su ficha. Para extranjeros, usar pasaporte u otro documento.';
print '</div></td></tr>';

print '<tr><td><strong>' . img_picto('', 'error', 'class="pictofixedwidth"') . 'Error en Base Imponible</strong></td>';
print '<td><strong>Problema:</strong> Discrepancia entre importes de Dolibarr y AEAT.<br><br>';
print '<strong>Solución:</strong> Usar "Verificar Estado" en pestaña VeriFactu. Si hay diferencias, emitir factura rectificativa (R1-R4).</td></tr>';

print '<tr><td><strong>' . img_picto('', 'error', 'class="pictofixedwidth"') . 'Factura rechazada por AEAT</strong></td>';
print '<td><strong>Solución:</strong><ol style="margin:5px 0;padding-left:20px;">';
print '<li>Consultar código de error en "Última respuesta"</li>';
print '<li>Corregir datos según el error indicado</li>';
print '<li>Reenviar desde pestaña VeriFactu → "Enviar"</li>';
print '</ol></td></tr>';

print '</table>';

print '<div class="vf-steps" style="margin-top:20px;">';
print '<div class="vf-steps-title">' . img_picto('', 'refresh', 'class="pictofixedwidth"') . 'Procedimiento para Reenviar Factura con Errores</div>';
print '<ol>';
print '<li>Corrija la configuración del cliente/tercero si el error está en sus datos</li>';
print '<li>Abra la factura afectada</li>';
print '<li>Vaya a la pestaña "VeriFactu"</li>';
print '<li>Revise los campos fiscales y corrija si es necesario</li>';
print '<li>Pulse el botón "Enviar a VeriFactu"</li>';
print '<li>Verifique que el estado cambia a "Enviada" y aparece el código CSV</li>';
print '</ol>';
print '</div>';

print '<p style="margin-top:15px;"><strong>Consulta de Códigos de Error Oficiales:</strong><br>';
print '<a href="https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/errores.properties" target="_blank" class="button small">';
print img_picto('', 'globe', 'class="pictofixedwidth"') . 'Ver listado oficial de errores AEAT</a></p>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

// ==================== 9. FLUJO DE TRABAJO ====================
print '<div class="vf-section" id="flujo-trabajo">';
print '<h2 class="vf-section-title">' . img_picto('', 'technic', 'class="pictofixedwidth"') . '9. Flujo de Trabajo con VeriFactu</h2>';

print '<div class="vf-steps">';
print '<div class="vf-steps-title">' . img_picto('', 'projecttask', 'class="pictofixedwidth"') . 'Proceso Típico de Facturación</div>';
print '<ol>';
print '<li><strong>Crear factura:</strong> Crear la factura normalmente en Dolibarr con todos los datos del cliente y líneas de producto/servicio.</li>';
print '<li><strong>Revisar datos fiscales:</strong> En la pestaña VeriFactu, verificar que Tipo de Impuesto, Clave de Régimen, Calificación y Exención son correctos.</li>';
print '<li><strong>Validar factura:</strong> Validar la factura en Dolibarr (cambiar estado a "Validada").</li>';
print '<li><strong>Envío automático o manual:</strong>';
print '<ul style="margin-top:5px;">';
print '<li>Si tiene activado "Envío automático al validar", se enviará automáticamente.</li>';
print '<li>Si no, pulse manualmente "Enviar a VeriFactu" en la pestaña VeriFactu.</li>';
print '</ul></li>';
print '<li><strong>Verificar respuesta:</strong> Comprobar que el estado es "Enviada" y aparece el código CSV.</li>';
print '<li><strong>Generar PDF con QR:</strong> El PDF incluirá automáticamente el código QR de VeriFactu.</li>';
print '</ol>';
print '</div>';

print '<div class="vf-box vf-box-success" style="margin-top:20px;">';
print '<div class="vf-box-title">' . img_picto('', 'check', 'class="pictofixedwidth"') . 'Verificación de Facturas en la AEAT</div>';
print 'Los clientes pueden verificar la autenticidad de las facturas escaneando el código QR o accediendo a la sede electrónica de la AEAT con los datos de la factura.';
print '</div>';

print '<a href="#indice" class="vf-back-link">' . img_picto('', 'chevron-double-up', 'class="pictofixedwidth"') . 'Volver al índice</a>';
print '</div>';

print '</div>'; // vf-help-doc

llxFooter();
$db->close();
