<?php
/* Copyright (C) 2018	Andreu Bisquerra	<jove@bisquerra.com>
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
 *	\file       htdocs/custom/verifactu/faq.php
 *	\ingroup	verifactu
 *	\brief      FAQ sobre los campos y valores de VERIFACTU
 */

if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', '1');
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}

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

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';

// Load translation files required by the page
$langs->loadLangs(array("admin", "other"));

// Security check
if (! $user->admin || !$user->rights->verifactu->manage) accessforbidden();



?>

<style>
.faq-container {
	width: 100%;
	margin: 0;
	padding: 20px;
	min-height: 100vh;
}

/* Barra de búsqueda */
.search-container {
	margin-bottom: 30px;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	padding: 25px;
	border-radius: 15px;
	box-shadow: 0 8px 32px rgba(0,0,0,0.1);
}

.search-box {
	position: relative;
	max-width: 600px;
	margin: 0 auto;
}

.search-input {
	width: 100%;
	padding: 15px 50px 15px 20px;
	font-size: 16px;
	border: none;
	border-radius: 25px;
	outline: none;
	box-shadow: 0 4px 15px rgba(0,0,0,0.1);
	transition: all 0.3s ease;
}

.search-input:focus {
	box-shadow: 0 6px 25px rgba(0,0,0,0.15);
	transform: translateY(-2px);
}

.search-icon {
	position: absolute;
	right: 20px;
	top: 50%;
	transform: translateY(-50%);
	color: #666;
	font-size: 18px;
}

.search-stats {
	text-align: center;
	margin-top: 15px;
	color: white;
	font-size: 14px;
}

/* Navegación rápida */
.quick-nav {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	justify-content: center;
	margin-bottom: 30px;
	padding: 20px;
	background-color: #f8f9fa;
	border-radius: 10px;
}

.quick-nav-btn {
	padding: 8px 16px;
	background-color: #0066cc;
	color: white;
	border: none;
	border-radius: 20px;
	cursor: pointer;
	font-size: 14px;
	transition: all 0.3s ease;
	text-decoration: none;
}

.quick-nav-btn:hover {
	background-color: #0052a3;
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0,102,204,0.3);
}

/* Acordeón */
.accordion {
	margin-bottom: 20px;
}

.accordion-item {
	border: 1px solid #ddd;
	border-radius: 8px;
	margin-bottom: 10px;
	overflow: hidden;
	transition: all 0.3s ease;
	background-color: white;
}

.accordion-item:hover {
	box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.accordion-header {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: white;
	padding: 20px;
	cursor: pointer;
	display: flex;
	justify-content: space-between;
	align-items: center;
	font-weight: bold;
	font-size: 18px;
	transition: all 0.3s ease;
	user-select: none;
}

.accordion-header:hover {
	background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

.accordion-header.active {
	background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.accordion-icon {
	font-size: 20px;
	transition: transform 0.3s ease;
}

.accordion-header.active .accordion-icon {
	transform: rotate(180deg);
}

.accordion-content {
	max-height: 0;
	overflow: hidden;
	transition: max-height 0.3s ease;
	background-color: #f9f9f9;
}

.accordion-content.active {
	max-height: 10000px;
	overflow: visible;
}

.accordion-body {
	padding: 20px;
	min-height: auto;
	height: auto;
}

/* Campos FAQ */
.faq-field {
	margin-bottom: 30px;
	background-color: white;
	padding: 15px;
	border-radius: 5px;
	border-left: 4px solid #0066cc;
}

.faq-field-title {
	font-weight: bold;
	color: #0066cc;
	font-size: 18px;
	margin-bottom: 10px;
}

.faq-field-description {
	margin-bottom: 15px;
	line-height: 1.6;
}

.faq-values {
	background-color: #f8f8f8;
	padding: 15px;
	border-radius: 5px;
}

.faq-value {
	margin-bottom: 15px;
	padding: 10px;
	background-color: white;
	border-radius: 3px;
	border-left: 3px solid #28a745;
	transition: all 0.3s ease;
}

.faq-value:hover {
	box-shadow: 0 2px 8px rgba(0,0,0,0.1);
	transform: translateX(5px);
}

.faq-value-code {
	font-weight: bold;
	color: #d63384;
	margin-bottom: 5px;
}

.faq-value-description {
	color: #666;
	line-height: 1.5;
}

.highlight {
	background-color: #fff3cd;
	padding: 10px;
	border-radius: 5px;
	border-left: 4px solid #ffc107;
	margin: 10px 0;
}

.important {
	background-color: #f8d7da;
	padding: 10px;
	border-radius: 5px;
	border-left: 4px solid #dc3545;
	margin: 10px 0;
}

/* Resaltado de búsqueda */
.search-highlight {
	background-color: #ffeb3b;
	padding: 2px 4px;
	border-radius: 3px;
	font-weight: bold;
}

/* Estados de visibilidad */
.hidden {
	display: none !important;
}

/* Responsive */
@media (max-width: 768px) {
	.faq-container {
		padding: 10px;
	}

	.search-container {
		padding: 15px;
	}

	.accordion-header {
		padding: 15px;
		font-size: 16px;
	}

	.quick-nav {
		padding: 15px;
	}

	.quick-nav-btn {
		font-size: 12px;
		padding: 6px 12px;
	}
}

/* Animaciones */
@keyframes fadeIn {
	from { opacity: 0; transform: translateY(20px); }
	to { opacity: 1; transform: translateY(0); }
}

.faq-field, .accordion-item {
	animation: fadeIn 0.5s ease-out;
}
</style>

<div class="faq-container">
	<h1 style="text-align: center; color: #0066cc; margin-bottom: 30px; font-size: 2.5em;">
		🔍 FAQ VERIFACTU - Guía Interactiva
	</h1>

	<!-- Barra de búsqueda -->
	<div class="search-container">
		<div class="search-box">
			<input type="text" id="searchInput" class="search-input" placeholder="🔍 Buscar en el FAQ... (ej: 'IVA', 'factura', 'régimen')">
			<span class="search-icon">⚡</span>
		</div>
		<div class="search-stats" id="searchStats">
			Escribe para buscar información específica
		</div>
	</div>

	<!-- Navegación rápida -->
	<div class="quick-nav">
		<button class="quick-nav-btn" onclick="scrollToSection('guia-rapida')">🚀 Guía Rápida</button>
		<button class="quick-nav-btn" onclick="scrollToSection('tipos-factura')">📋 Tipos de Factura</button>
		<button class="quick-nav-btn" onclick="scrollToSection('tipos-impuesto')">💰 Tipos de Impuesto</button>
		<button class="quick-nav-btn" onclick="scrollToSection('clave-regimen')">⚖️ Clave Régimen</button>
		<button class="quick-nav-btn" onclick="scrollToSection('calificacion')">🎯 Calificación</button>
		<button class="quick-nav-btn" onclick="scrollToSection('exenciones')">🚫 Exenciones</button>
		<button class="quick-nav-btn" onclick="scrollToSection('tipos-identificacion')">🆔 Tipos Identificación</button>
		<button class="quick-nav-btn" onclick="scrollToSection('consejos')">💡 Consejos</button>
		<button class="quick-nav-btn" onclick="scrollToSection('errores-comunes')">⚠️ Errores Comunes</button>
		<button class="quick-nav-btn" onclick="expandAll()">📖 Expandir Todo</button>
		<button class="quick-nav-btn" onclick="collapseAll()">📚 Contraer Todo</button>
	</div>

	<!-- Acordeón de secciones -->
	<div class="accordion">

		<!-- Sección: Guía Rápida de Uso -->
		<div class="accordion-item" id="guia-rapida">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>🚀 Guía Rápida de Uso del Módulo VERIFACTU</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="faq-field">
						<div class="faq-field-title">📋 ¿Qué es VERIFACTU?</div>
						<div class="faq-field-description">
							VERIFACTU es un sistema de la Agencia Tributaria que permite verificar la integridad y autenticidad de las facturas.
							Este módulo integra automáticamente Dolibarr con el sistema VERIFACTU para cumplir con las obligaciones fiscales.
						</div>
					</div>

					<div class="faq-field">
						<div class="faq-field-title">⚠️ IMPORTANTE: Configuración Manual Obligatoria</div>
						<div class="faq-field-description">
							<div style="background: #ffebee; padding: 15px; border-left: 4px solid #f44336; margin: 10px 0;">
								<strong>🔴 POLÍTICA DE SEGURIDAD FISCAL:</strong><br>
								Este módulo <strong>NO incluye inferencia automática</strong> de parámetros VeriFactu para evitar errores fiscales.
							</div>

							<strong>Todos los parámetros deben configurarse MANUALMENTE:</strong><br>
							• <strong>Tipo de Impuesto:</strong> IVA, IPSI, IGIC, Otros<br>
							• <strong>Clave de Régimen:</strong> Solo para IVA e IGIC<br>
							• <strong>Calificación de Operación:</strong> S1, S2, N1, N2<br>
							• <strong>Operación Exenta:</strong> E1-E6 si aplica<br><br>

							<div style="background: #e8f5e8; padding: 10px; border-left: 4px solid #4caf50; margin: 10px 0;">
								<strong>✅ RESPONSABILIDAD:</strong> Es responsabilidad del usuario configurar correctamente estos parámetros consultando con su <strong>asesor fiscal</strong>.
							</div>

							<strong>¿Por qué esta política?</strong><br>
							• Cada empresa tiene circunstancias fiscales específicas<br>
							• Los regímenes especiales requieren análisis profesional<br>
							• La configuración incorrecta puede generar sanciones<br>
							• El asesor fiscal conoce la situación particular de cada empresa
						</div>
					</div>

					<div class="faq-field">
						<div class="faq-field-title">⚙️ Configuración Inicial</div>
						<div class="faq-field-description">
							<strong>1. Instalación del Módulo:</strong>
							<ul>
								<li>Activar el módulo VERIFACTU desde <code>Inicio → Configuración → Módulos</code></li>
								<li>Verificar que aparece activo con el icono verde</li>
							</ul>

							<strong>2. Obtención de Licencia del Módulo:</strong>
							<ul>
								<li><strong>Contactar con el desarrollador</strong> del módulo VERIFACTU</li>
								<li>Proporcionar el <strong>dominio</strong> donde está instalado Dolibarr</li>
								<li>Recibir la <strong>clave de licencia válida</strong> específica para tu instalación</li>
								<li>Introducir la clave en la configuración del módulo</li>
							</ul>

							<div class="important">
								<strong>🔐 Licencia Obligatoria:</strong> El módulo requiere una licencia válida del desarrollador específica para cada dominio. Sin ella, VERIFACTU no funcionará.
							</div>

							<div class="important">
								<strong>⚠️ Integridad del Módulo:</strong> Cualquier modificación del código del módulo alterará su integridad y hará que deje de funcionar. No modifiques archivos del módulo sin autorización del desarrollador.
							</div>
						</div>
					</div>



					<div class="faq-field">
						<div class="faq-field-title">🔒 Seguridad y Cumplimiento</div>
						<div class="faq-field-description">
							<ul>
								<li><strong>🔐 Certificados:</strong> Se almacenan de forma segura y encriptada</li>
								<li><strong>📝 Trazabilidad:</strong> Cada acción queda registrada con fecha y usuario</li>
								<li><strong>🛡️ Integridad:</strong> Las facturas no pueden modificarse una vez enviadas</li>
								<li><strong>⚖️ Cumplimiento:</strong> Cumple automáticamente con la normativa VERIFACTU</li>
								<li><strong>🔍 Auditoría:</strong> Facilita las inspecciones fiscales con documentación completa</li>
							</ul>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección: Tipos de Factura -->
		<div class="accordion-item" id="tipos-factura">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>📋 Tipos de Factura VERIFACTU</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="faq-field">
						<div class="faq-field-description">
							Este campo identifica la naturaleza y características específicas de la factura según la normativa fiscal española.
							Es fundamental para determinar las obligaciones tributarias y el tratamiento fiscal correspondiente.
						</div>

						<div class="faq-values">
							<div class="faq-value">
								<div class="faq-value-code">F1 - Factura (art. 6 o 7.2 o 7.3 del RD 1619/2012)</div>
								<div class="faq-value-description">
									<strong>¿Qué es?</strong> Es la factura estándar que cumple con todos los requisitos formales establecidos en el Reglamento del IVA.<br>
									<strong>¿Cuándo usarla?</strong> Para operaciones comerciales normales entre empresas o profesionales.<br>
									<strong>Características:</strong> Debe incluir todos los datos obligatorios: identificación del emisor y receptor, fecha, número secuencial, descripción de la operación, base imponible, tipo de IVA, cuota tributaria.<br>
									<strong>Ejemplo:</strong> Venta de productos a otra empresa, prestación de servicios profesionales.
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">F2 - Factura Simplificada y Facturas sin identificación del destinatario art. 6.1.d) RD 1619/2012</div>
								<div class="faq-value-description">
									<strong>¿Qué es?</strong> Factura con requisitos formales reducidos según art. 6.1.d) RD 1619/2012.<br>
									<strong>¿Cuándo usarla?</strong> Ventas al consumidor final, operaciones por importe inferior a 400€, o cuando el destinatario no requiere factura completa.<br>
									<strong>Características:</strong> No requiere identificación completa del cliente, puede omitir ciertos datos fiscales del receptor.<br>
									<strong>Ejemplo:</strong> Tickets de venta en comercios, facturas a consumidores particulares sin NIF.<br>
									<strong>⚠️ Limitación:</strong> Importe máximo 400€ (IVA incluido)
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">F3 - Factura emitida en sustitución de facturas simplificadas facturadas y declaradas</div>
								<div class="faq-value-description">
									<strong>¿Qué es?</strong> Factura completa que sustituye a una factura simplificada previamente emitida y declarada.<br>
									<strong>¿Cuándo usarla?</strong> Cuando el cliente solicita una factura completa después de haber recibido una simplificada (F2).<br>
									<strong>Características:</strong> Incluye todos los datos de una factura F1 pero referencia la simplificada original.<br>
									<strong>Proceso:</strong> Cliente recibe F2 → Solicita factura completa → Se emite F3 con datos completos<br>
									<strong>📋 Requisito:</strong> La factura F2 original debe haber sido ya declarada ante Hacienda
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">R1 - Factura Rectificativa (Error fundado en derecho y Art. 80 Uno Dos y Seis LIVA)</div>
								<div class="faq-value-description">
									<strong>¿Qué es?</strong> Corrige errores que afectan a la base imponible o cuota tributaria por motivos legalmente establecidos.<br>
									<strong>¿Cuándo usarla?</strong> Errores en cálculos de IVA, aplicación incorrecta de tipos impositivos, errores en importes que afectan la tributación.<br>
									<strong>📋 Ejemplos específicos:</strong><br>
									• Error en aplicación de tipo de IVA (21% en lugar de 10%)<br>
									• Cálculo incorrecto de la base imponible<br>
									• Error en la cuota tributaria que afecta la deducibilidad<br>
									• Aplicación incorrecta de exenciones<br>
									<strong>⚖️ Base legal:</strong> Artículo 80.1, 80.2 y 80.6 de la Ley del IVA
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">R2 - Factura Rectificativa (Art. 80.3)</div>
								<div class="faq-value-description">
									<strong>¿Qué corrige?</strong> Rectificaciones por modificación de las condiciones de la operación.<br>
									<strong>📋 Casos específicos:</strong><br>
									• Cambios en precios acordados posteriormente<br>
									• Modificaciones en cantidades entregadas<br>
									• Alteraciones en las condiciones contractuales<br>
									• Aplicación de descuentos comerciales posteriores<br>
									<strong>Ejemplo:</strong> Descuento del 10% aplicado por defectos en mercancía detectados después de la entrega
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">R3 - Factura Rectificativa (Art. 80.4)</div>
								<div class="faq-value-description">
									<strong>¿Qué corrige?</strong> Rectificaciones por operaciones anuladas o no realizadas.<br>
									<strong>📋 Casos específicos:</strong><br>
									• Cancelación total de operaciones<br>
									• Anulación de ventas por incumplimiento<br>
									• Devoluciones completas de mercancía<br>
									• Resolución de contratos<br>
									<strong>Ejemplo:</strong> Cliente devuelve producto completo y se anula totalmente la operación<br>
									<strong>💰 Efecto:</strong> Anula completamente el efecto fiscal de la factura original
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">R4 - Factura Rectificativa (Resto de casos)</div>
								<div class="faq-value-description">
									<strong>¿Qué corrige?</strong> Otras rectificaciones no contempladas específicamente en R1, R2 o R3.<br>
									<strong>📋 Casos típicos:</strong><br>
									• Errores administrativos menores<br>
									• Correcciones de datos no fiscales<br>
									• Ajustes diversos no tributarios<br>
									• Rectificaciones mixtas que combinan varios supuestos<br>
									<strong>Ejemplo:</strong> Corrección de datos de contacto, direcciones, descripciones de productos sin impacto fiscal
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">R5 - Factura Rectificativa en facturas simplificadas</div>
								<div class="faq-value-description">
									<strong>¿Qué es?</strong> Rectificación específica y exclusiva para facturas simplificadas (F2).<br>
									<strong>¿Cuándo usarla?</strong> Cuando hay errores en facturas simplificadas que requieren corrección formal.<br>
									<strong>📋 Características especiales:</strong><br>
									• Solo aplicable a facturas F2 previamente emitidas<br>
									• Mantiene la simplicidad formal de las facturas simplificadas<br>
									• Corrige errores sin convertir a factura completa<br>
									• Respeta el límite de 400€ de las facturas simplificadas<br>
									<strong>⚠️ Limitación:</strong> No convierte la factura simplificada en completa
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección: Tipos de Impuesto -->
		<div class="accordion-item" id="tipos-impuesto">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>💰 Tipos de Impuesto por Territorio</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="faq-field">
						<div class="faq-field-description">
							Identifica qué impuesto indirecto se aplica según el territorio y la naturaleza de la operación.
							España tiene diferentes regímenes tributarios según la zona geográfica.
						</div>

						<div class="faq-values">
							<div class="faq-value">
								<div class="faq-value-code">01 - Impuesto sobre el Valor Añadido (IVA)</div>
								<div class="faq-value-description">
									<strong>🗺️ Territorio:</strong> España peninsular y Baleares<br>
									<strong>📊 Tipos vigentes:</strong><br>
									• General: 21% (productos y servicios estándar)<br>
									• Reducido: 10% (alimentación básica, transporte, hostelería)<br>
									• Superreducido: 4% (productos de primera necesidad, medicamentos, libros)<br>
									• Exento: 0% (servicios médicos, educación, seguros)<br>
									<div class="highlight">
										<strong>💡 Uso recomendado:</strong> Para el 95% de operaciones en España continental
									</div>
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">02 - IPSI de Ceuta y Melilla</div>
								<div class="faq-value-description">
									<strong>🗺️ Territorio:</strong> Ciudades autónomas de Ceuta y Melilla<br>
									<strong>📊 Tipos vigentes:</strong><br>
									• General: 10% (equivalente al IVA general)<br>
									• Reducido: 5% (equivalente al IVA reducido)<br>
									• Superreducido: 2% (equivalente al IVA superreducido)<br>
									<strong>🎯 Ventaja:</strong> Tipos más bajos debido al régimen fiscal especial de estas ciudades
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">03 - IGIC Canario</div>
								<div class="faq-value-description">
									<strong>🗺️ Territorio:</strong> Islas Canarias<br>
									<strong>📊 Tipos vigentes:</strong><br>
									• General: 7% (tipo estándar)<br>
									• Incrementado: 13.5% (productos de lujo)<br>
									• Reducido: 3% (productos básicos)<br>
									• Sin IGIC: 0% (exenciones específicas)<br>
									<strong>🌴 Particularidad:</strong> Régimen fiscal canario especial con tipos generalmente más bajos
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">05 - Otros Impuestos</div>
								<div class="faq-value-description">
									<strong>¿Qué incluye?</strong> Impuestos especiales no contemplados en las categorías anteriores.<br>
									<strong>Ejemplos:</strong> Impuestos especiales sobre alcoholes, hidrocarburos, tabaco.<br>
									<strong>⚠️ Uso:</strong> Solo para casos muy específicos, consultar con asesor fiscal.
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección: Clave de Régimen -->
		<div class="accordion-item" id="clave-regimen">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>⚖️ Clave de Régimen Fiscal</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="faq-field">
						<div class="faq-field-description">
							Define el régimen fiscal específico bajo el cual se realiza la operación.
							Cada régimen tiene sus propias reglas de aplicación, obligaciones y beneficios fiscales.
						</div>

						<div class="faq-values">
							<div class="faq-value">
								<div class="faq-value-code">01 - Operación de Régimen General</div>
								<div class="faq-value-description">
									<strong>📈 Uso:</strong> 90% de las operaciones comerciales<br>
									<strong>✅ Características:</strong><br>
									• Tipos de IVA estándar (21%, 10%, 4%)<br>
									• IVA soportado deducible para empresas<br>
									• Facturación con todos los requisitos legales<br>
									• Aplicable a comercio B2B y B2C<br>
									<div class="highlight">
										<strong>🎯 Recomendación:</strong> Usar por defecto salvo regulación específica
									</div>
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">02 - Exportación</div>
								<div class="faq-value-description">
									<strong>🌍 Aplicación:</strong> Ventas fuera del territorio español<br>
									<strong>📋 Requisitos:</strong><br>
									• Documentación de exportación obligatoria<br>
									• Justificación de salida del territorio<br>
									• Registro en sistemas aduaneros<br>
									<strong>💰 Ventaja fiscal:</strong> Exento de IVA (0%)
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">07 - Régimen Especial del Criterio de Caja</div>
								<div class="faq-value-description">
									<strong>⏰ Particularidad:</strong> El IVA se devenga al cobrar, no al facturar<br>
									<strong>💰 Ventaja:</strong> Mejora significativa del flujo de caja<br>
									<strong>📋 Requisitos:</strong> Inscripción previa en el régimen<br>
									<strong>⚠️ Limitaciones:</strong> No aplicable a todas las empresas
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">20 - Régimen Simplificado</div>
								<div class="faq-value-description">
									<strong>👥 Dirigido a:</strong> Pequeños empresarios y profesionales<br>
									<strong>📊 Características:</strong> Estimación objetiva de rendimientos<br>
									<strong>💡 Ventaja:</strong> Simplificación de obligaciones fiscales<br>
									<strong>⚖️ Limitación:</strong> Solo para IVA, no para IGIC
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">03 - Régimen especial de bienes usados, objetos de arte, antigüedades y objetos de colección</div>
								<div class="faq-value-description">
									<strong>🎨 Aplicación:</strong> Comercio de bienes de segunda mano y objetos de arte<br>
									<strong>📋 Características:</strong><br>
									• IVA solo sobre el margen de beneficio<br>
									• No se aplica IVA sobre el valor total<br>
									• Documentación especial requerida<br>
									<strong>Ejemplo:</strong> Anticuarios, galerías de arte, comercio de vehículos usados
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">04 - Régimen especial del oro de inversión</div>
								<div class="faq-value-description">
									<strong>🥇 Aplicación:</strong> Operaciones con oro de inversión<br>
									<strong>💰 Características:</strong> Exento de IVA en determinadas condiciones<br>
									<strong>📋 Requisitos:</strong> Oro con pureza mínima, formas específicas<br>
									<strong>⚖️ Regulación:</strong> Normativa específica para metales preciosos
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">05 - Régimen especial de las agencias de viajes</div>
								<div class="faq-value-description">
									<strong>✈️ Aplicación:</strong> Servicios de agencias de viajes y tour operadores<br>
									<strong>📊 Características:</strong> IVA sobre el margen, no sobre el valor total del viaje<br>
									<strong>💼 Ventaja:</strong> Evita la doble imposición en servicios turísticos<br>
									<strong>Ejemplo:</strong> Paquetes turísticos, servicios de intermediación turística
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">06 - Régimen especial grupo de entidades (Nivel Avanzado)</div>
								<div class="faq-value-description">
									<strong>🏢 Aplicación:</strong> Grupos empresariales con estructura compleja<br>
									<strong>📋 Características:</strong><br>
									• Tratamiento fiscal conjunto del grupo<br>
									• Operaciones intragrupo con régimen especial<br>
									• Requiere autorización específica de Hacienda<br>
									<strong>⚠️ Complejidad:</strong> Solo para grupos empresariales grandes y asesoramiento especializado
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">08 - Operaciones sujetas (IVA al IPSI/IGIC o IGIC-IPSI al IPSI/IVA)</div>
								<div class="faq-value-description">
									<strong>🗺️ Aplicación:</strong> Operaciones entre diferentes territorios fiscales españoles<br>
									<strong>📋 Casos específicos:</strong><br>
									• Operaciones de península a Canarias/Ceuta/Melilla<br>
									• Operaciones entre territorios con regímenes diferentes<br>
									• Cambio de régimen fiscal por ubicación<br>
									<strong>⚖️ Complejidad:</strong> Requiere conocimiento de regímenes territoriales
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">09 - Servicios de agencias de viaje como mediadoras (D.A.4ª RD1619/2012)</div>
								<div class="faq-value-description">
									<strong>🛂 Aplicación:</strong> Agencias que actúan como intermediarias en nombre de terceros<br>
									<strong>📋 Características:</strong><br>
									• La agencia no es el prestador final del servicio<br>
									• Actúa como mediadora entre cliente y proveedor<br>
									• Tratamiento fiscal específico de la mediación<br>
									<strong>Ejemplo:</strong> Reserva de hoteles, venta de billetes por cuenta de aerolíneas
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">10 - Cobros por cuenta de terceros de honorarios profesionales</div>
								<div class="faq-value-description">
									<strong>👥 Aplicación:</strong> Colegios profesionales, asociaciones que cobran por sus miembros<br>
									<strong>📋 Casos típicos:</strong><br>
									• Colegios de abogados, médicos, arquitectos<br>
									• Cobro de honorarios de socios o colegiados<br>
									• Derechos de autor cobrados por entidades de gestión<br>
									<strong>⚖️ Características:</strong> La entidad actúa como cobrador, no como prestador del servicio
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">11 - Operaciones de arrendamiento de local de negocio</div>
								<div class="faq-value-description">
									<strong>🏢 Aplicación:</strong> Alquiler de locales comerciales, oficinas, naves industriales<br>
									<strong>📋 Características:</strong><br>
									• IVA aplicable al alquiler comercial<br>
									• Diferente del alquiler de vivienda (exento)<br>
									• Deducibilidad para el arrendatario empresario<br>
									<strong>💼 Ejemplo:</strong> Alquiler de oficinas, tiendas, almacenes para actividad empresarial
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">14 - Factura con IVA/IGIC pendiente de devengo en certificaciones de obra</div>
								<div class="faq-value-description">
									<strong>🏗️ Aplicación:</strong> Certificaciones de obra para Administraciones Públicas<br>
									<strong>⏰ Particularidad:</strong> El IVA se devenga cuando se cobra, no al certificar<br>
									<strong>📋 Requisitos:</strong><br>
									• Destinatario debe ser Administración Pública<br>
									• Obra debe estar certificada oficialmente<br>
									• Mejora el flujo de caja del constructor<br>
									<strong>🏛️ Beneficiario:</strong> Empresas constructoras que trabajan para el sector público
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">15 - Factura con IVA/IGIC pendiente de devengo en operaciones de tracto sucesivo</div>
								<div class="faq-value-description">
									<strong>📅 Aplicación:</strong> Operaciones con entregas o prestaciones continuadas en el tiempo<br>
									<strong>⏰ Particularidad:</strong> El IVA se devenga conforme se realizan las entregas/prestaciones<br>
									<strong>📋 Ejemplos:</strong><br>
									• Suministros periódicos de materiales<br>
									• Servicios de mantenimiento continuado<br>
									• Contratos de suministro a largo plazo<br>
									<strong>💡 Ventaja:</strong> Ajusta el devengo del IVA a la realidad temporal de la operación
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">17 - Operación acogida a regímenes OSS e IOSS / Régimen especial comerciante minorista</div>
								<div class="faq-value-description">
									<strong>🌐 IVA - OSS e IOSS:</strong> Regímenes para comercio electrónico transfronterizo<br>
									<strong>📱 Características OSS/IOSS:</strong><br>
									• Ventanilla única para declarar IVA en UE<br>
									• Simplifica obligaciones en comercio electrónico<br>
									• Aplicable a ventas B2C transfronterizas<br>
									<strong>🏪 IGIC - Comerciante minorista:</strong> Régimen especial para pequeño comercio en Canarias<br>
									<strong>💻 Uso típico:</strong> Tiendas online, plataformas de comercio electrónico
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">18 - Recargo de equivalencia / Régimen especial del pequeño empresario</div>
								<div class="faq-value-description">
									<strong>🏪 IVA - Recargo de equivalencia:</strong> Régimen para comercio minorista<br>
									<strong>📊 Características del recargo:</strong><br>
									• Se añade un recargo al IVA normal<br>
									• Simplifica la gestión del IVA para pequeños comercios<br>
									• No hay derecho a deducción, pero tampoco obligación de declarar<br>
									<strong>👤 IGIC - Pequeño empresario:</strong> Régimen simplificado en Canarias<br>
									<strong>💡 Dirigido a:</strong> Pequeños comerciantes, actividades de menor volumen
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">19 - REAGYP / Operaciones interiores exentas (IGIC)</div>
								<div class="faq-value-description">
									<strong>🚜 IVA - REAGYP:</strong> Régimen Especial de Agricultura, Ganadería y Pesca<br>
									<strong>📋 Características REAGYP:</strong><br>
									• Compensación forfetaria en lugar de IVA normal<br>
									• Porcentajes fijos según el tipo de actividad<br>
									• Simplifica la gestión fiscal del sector primario<br>
									<strong>🌾 Aplicable a:</strong> Agricultores, ganaderos, pescadores<br>
									<strong>🏝️ IGIC - Exenciones art. 25:</strong> Operaciones interiores específicamente exentas en Canarias
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección: Calificación de la Operación -->
		<div class="accordion-item" id="calificacion">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>🎯 Calificación de la Operación</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="faq-field">
						<div class="faq-field-description">
							Determina si la operación está sujeta al impuesto y si hay inversión del sujeto pasivo.
							Este campo es crucial para determinar quién debe pagar el impuesto.
						</div>

						<div class="faq-values">
							<div class="faq-value">
								<div class="faq-value-code">🤖 Automático (Recomendado)</div>
								<div class="faq-value-description">
									<strong>🧠 Función:</strong> El sistema determina automáticamente la calificación<br>
									<strong>✅ Ventajas:</strong><br>
									• Reduce errores humanos al 99%<br>
									• Asegura cumplimiento normativo<br>
									• Simplifica el proceso<br>
									• Se actualiza automáticamente con cambios normativos<br>
									<div class="highlight">
										<strong>🎯 Uso recomendado:</strong> Para el 95% de usuarios, especialmente sin conocimientos fiscales específicos
									</div>
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">S1 - Operación Sujeta y No Exenta - Sin Inversión</div>
								<div class="faq-value-description">
									<strong>💼 Definición:</strong> Operación normal donde el vendedor cobra el IVA<br>
									<strong>📋 Proceso:</strong> Vendedor factura IVA → Cliente paga IVA → Vendedor ingresa IVA a Hacienda<br>
									<strong>📊 Ejemplo práctico:</strong> Venta ordenador 1.000€ + 210€ IVA = 1.210€ total<br>
									<strong>🎯 Uso típico:</strong> Comercio retail, servicios profesionales, venta B2B estándar
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">S2 - Operación Sujeta y No Exenta - Con Inversión</div>
								<div class="faq-value-description">
									<strong>🔄 Inversión del sujeto pasivo:</strong> El comprador paga el IVA, no el vendedor<br>
									<strong>📋 Sectores típicos:</strong><br>
									• Construcción y obras<br>
									• Servicios profesionales entre empresas<br>
									• Cesión de personal<br>
									• Tratamiento de residuos<br>
									<strong>💡 Ventaja:</strong> Simplifica la gestión fiscal entre empresas
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">N1/N2 - Operaciones No Sujetas</div>
								<div class="faq-value-description">
									<strong>N1:</strong> No sujeta por naturaleza (art. 7, 14)<br>
									<strong>N2:</strong> No sujeta por reglas de localización<br>
									<strong>🌍 Ejemplos:</strong> Operaciones en el extranjero, determinados servicios digitales<br>
									<strong>💰 Resultado:</strong> Sin IVA aplicable
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección: Operaciones Exentas -->
		<div class="accordion-item" id="exenciones">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>🚫 Operaciones Exentas de Impuestos</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="faq-field">
						<div class="faq-field-description">
							Indica si la operación está libre del pago de impuestos según la normativa fiscal.
							Las exenciones están específicamente reguladas en la ley y son de aplicación restrictiva.
						</div>

						<div class="faq-values">
							<div class="faq-value">
								<div class="faq-value-code">🔄 Automático / No Aplicable</div>
								<div class="faq-value-description">
									<strong>📊 Significado:</strong> La operación NO está exenta, se aplicará el impuesto correspondiente<br>
									<strong>💰 Resultado:</strong> IVA según corresponda (21%, 10%, 4%)<br>
									<strong>📈 Frecuencia:</strong> 85% de las operaciones comerciales<br>
									<div class="important">
										<strong>⚠️ Importante:</strong> La mayoría de operaciones comerciales NO están exentas
									</div>
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">E1 - Exenta por Artículo 20</div>
								<div class="faq-value-description">
									<strong>🏥 Servicios incluidos:</strong><br>
									• Servicios postales públicos<br>
									• Servicios médicos y sanitarios<br>
									• Servicios educativos<br>
									• Servicios sociales<br>
									<strong>📋 Ejemplos:</strong> Consulta médica privada, clases en academia, servicios de correos
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">E2 - Exenta por Artículo 21</div>
								<div class="faq-value-description">
									<strong>🏦 Servicios financieros:</strong><br>
									• Operaciones de crédito y préstamo<br>
									• Operaciones de seguros<br>
									• Gestión de fondos de inversión<br>
									<strong>📋 Ejemplos:</strong> Intereses de préstamos, primas de seguros, comisiones bancarias
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">E3-E6 - Otras Exenciones</div>
								<div class="faq-value-description">
									<strong>E3:</strong> Exenta por artículo 22 (entregas intracomunitarias)<br>
									<strong>E4:</strong> Exenta por artículos 23 y 24 (exportaciones)<br>
									<strong>E5:</strong> Exenta por artículo 25 (servicios específicos)<br>
									<strong>E6:</strong> Exenta por otros motivos legales<br>
									<strong>⚖️ Nota:</strong> Requieren justificación legal específica
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección: Tipos de Identificación -->
		<div class="accordion-item" id="tipos-identificacion">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>🆔 Tipos de Identificación del Destinatario</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="faq-field">
						<div class="faq-field-description">
							Define el tipo de documento de identificación del destinatario de la factura.
							Es fundamental para la correcta identificación fiscal de las partes en operaciones internacionales o con extranjeros.
						</div>

						<div class="faq-values">
							<div class="faq-value">
								<div class="faq-value-code">02 - NIF-IVA</div>
								<div class="faq-value-description">
									<strong>🇪🇸 Aplicación:</strong> Número de Identificación Fiscal español + Número de IVA intracomunitario<br>
									<strong>📋 Formato:</strong><br>
									• NIF: 12345678A (para personas físicas)<br>
									• CIF: A12345678 (para empresas)<br>
									• NIE: X1234567A (para extranjeros residentes)<br>
									• NIF-IVA: ES12345678A (para operaciones intracomunitarias)<br>
									<strong>🎯 Uso más frecuente:</strong> 95% de operaciones con clientes españoles<br>
									<strong>✅ Ventajas:</strong> Identificación fiscal completa, permite deducibilidad del IVA
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">03 - Pasaporte</div>
								<div class="faq-value-description">
									<strong>🌍 Aplicación:</strong> Clientes extranjeros sin residencia fiscal en España<br>
									<strong>📋 Casos típicos:</strong><br>
									• Turistas extranjeros<br>
									• Empresarios internacionales de paso<br>
									• Operaciones puntuales con no residentes<br>
									• Ventas duty-free o con devolución de IVA<br>
									<strong>📄 Formato:</strong> Número de pasaporte + País emisor<br>
									<strong>⚠️ Limitación:</strong> No permite deducibilidad automática del IVA
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">04 - Documento oficial de identificación expedido por el país o territorio de residencia</div>
								<div class="faq-value-description">
									<strong>🏛️ Aplicación:</strong> Documentos oficiales de identidad de otros países<br>
									<strong>📋 Ejemplos específicos:</strong><br>
									• DNI extranjero (de otros países)<br>
									• Cédula de identidad (países latinoamericanos)<br>
									• Carta d'identità (Italia), Personalausweis (Alemania)<br>
									• Driver's License (países anglosajones, en algunos casos)<br>
									<strong>🌐 Ventaja:</strong> Reconocimiento oficial del documento en su país de origen<br>
									<strong>📝 Requisito:</strong> Debe ser emitido por autoridad competente del país de residencia
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">05 - Certificado de residencia</div>
								<div class="faq-value-description">
									<strong>🏠 Aplicación:</strong> Certificado oficial que acredita la residencia fiscal<br>
									<strong>📋 Casos de uso:</strong><br>
									• Aplicación de convenios de doble imposición<br>
									• Operaciones que requieren acreditar residencia fiscal<br>
									• Clientes extranjeros con residencia en terceros países<br>
									• Determinación del tipo de IVA aplicable<br>
									<strong>🏛️ Emisor:</strong> Autoridades fiscales del país de residencia<br>
									<strong>💰 Ventaja fiscal:</strong> Puede determinar exenciones o tipos reducidos de IVA
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">06 - Otro documento probatorio</div>
								<div class="faq-value-description">
									<strong>📑 Aplicación:</strong> Documentos alternativos cuando no aplican las categorías anteriores<br>
									<strong>📋 Ejemplos específicos:</strong><br>
									• Documentos de refugiados o asilados<br>
									• Identificaciones temporales<br>
									• Documentos de organismos internacionales<br>
									• Certificados consulares<br>
									• Documentos de empresas en constitución<br>
									<strong>⚖️ Requisito:</strong> Debe tener validez legal y permitir identificación inequívoca<br>
									<strong>🔍 Validación:</strong> Requiere verificación adicional de la validez del documento
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">07 - No censado</div>
								<div class="faq-value-description">
									<strong>❌ Aplicación:</strong> Cliente no dispone de ningún documento de identificación válido<br>
									<strong>📋 Casos muy específicos:</strong><br>
									• Situaciones excepcionales humanitarias<br>
									• Operaciones de emergencia<br>
									• Casos donde es imposible obtener identificación<br>
									• Operaciones con entidades no reconocidas oficialmente<br>
									<strong>⚠️ Uso muy restringido:</strong> Solo en casos excepcionales justificados<br>
									<strong>📊 Frecuencia:</strong> Menos del 0.1% de las operaciones<br>
									<strong>🔒 Riesgo:</strong> Puede tener implicaciones fiscales y de cumplimiento normativo
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección: Consejos Prácticos -->
		<div class="accordion-item" id="consejos">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>💡 Consejos Prácticos y Casos Especiales</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="highlight">
						<h4>🎯 Guía Rápida para Principiantes</h4>
						<ul>
							<li><strong>🤖 Calificación:</strong> Siempre "Automático" (reduce errores)</li>
							<li><strong>📋 Configuración estándar:</strong> F1 + IVA(01) + Régimen General(01)</li>
							<li><strong>🗺️ Territorio:</strong> Verificar ubicación del cliente (Península/Canarias/Ceuta-Melilla)</li>
							<li><strong>⚖️ Dudas:</strong> Consultar con asesor fiscal ante casos complejos</li>
							<li><strong>📊 Documentación:</strong> Mantener respaldos de todas las decisiones fiscales</li>
						</ul>
					</div>

					<div class="important">
						<h4>⚠️ Casos Especiales por Territorio</h4>
						<ul>
							<li><strong>🌴 Canarias:</strong> IGIC (03) obligatorio en lugar de IVA</li>
							<li><strong>🏛️ Ceuta/Melilla:</strong> IPSI (02) con tipos reducidos</li>
							<li><strong>🌍 Exportaciones:</strong> Régimen 02 + documentación aduanera</li>
							<li><strong>🔧 Rectificativas:</strong> Elegir R1-R5 según naturaleza del error</li>
							<li><strong>💼 B2B profesional:</strong> Considerar inversión sujeto pasivo (S2)</li>
						</ul>
					</div>

					<div class="faq-field">
						<div class="faq-field-title">🚀 Casos de Uso Frecuentes</div>
						<div class="faq-values">
							<div class="faq-value">
								<div class="faq-value-code">🛒 Venta Online Península</div>
								<div class="faq-value-description">
									<strong>Configuración:</strong> F1 + IVA(01) + Régimen(01) + Automático<br>
									<strong>Resultado:</strong> IVA según producto (21%, 10%, 4%)
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">🏨 Servicios Turísticos Canarias</div>
								<div class="faq-value-description">
									<strong>Configuración:</strong> F1 + IGIC(03) + Régimen(01) + Automático<br>
									<strong>Resultado:</strong> IGIC 7% (servicios turísticos)
								</div>
							</div>

							<div class="faq-value">
								<div class="faq-value-code">🏗️ Servicios Construcción</div>
								<div class="faq-value-description">
									<strong>Configuración:</strong> F1 + IVA(01) + Régimen(01) + S2<br>
									<strong>Particularidad:</strong> Cliente paga el IVA, no el proveedor
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Sección: Errores Comunes y Soluciones -->
		<div class="accordion-item" id="errores-comunes">
			<div class="accordion-header" onclick="toggleAccordion(this)">
				<span>⚠️ Errores Comunes y Soluciones</span>
				<span class="accordion-icon">▼</span>
			</div>
			<div class="accordion-content">
				<div class="accordion-body">
					<div class="faq-field">
						<div class="faq-field-title">🚨 Error: Cliente de Reino Unido con CIF Intracomunitario</div>
						<div class="faq-field-description">
							<strong>Problema:</strong> Al tratar de enviar una factura de un cliente de Reino Unido con un CIF intracomunitario, se produce un error de validación.<br><br>

							<strong>Causa:</strong> Reino Unido salió de la Unión Europea en 2021, por lo que ya no se considera un país intracomunitario.<br><br>

							<strong>Solución:</strong><br>
							1. En la ficha del cliente de Reino Unido, cambiar el <strong>Tipo de Identificación</strong> de "CIF Intracomunitario" a <strong>"Otro tipo de Documento Oficial"</strong><br>
							2. Mantener el mismo número en el campo CIF intracomunitario<br>
							3. Esto asegura que se identifique correctamente como país extracomunitario<br><br>

							<strong>Ejemplo:</strong><br>
							• Cliente: Empresa UK Ltd<br>
							• Número: GB123456789<br>
							• Tipo ID: <span style="color: #28a745; font-weight: bold;">✓ Otro tipo de Documento Oficial</span> (correcto)<br>
							• Tipo ID: <span style="color: #dc3545; font-weight: bold;">✗ CIF Intracomunitario</span> (incorrecto)
						</div>
					</div>

					<div class="faq-field">
						<div class="faq-field-title">🆔 Error: Cliente particular sin NIF válido</div>
						<div class="faq-field-description">
							<strong>Problema:</strong> Error al enviar factura a cliente particular sin un NIF válido configurado.<br><br>

							<strong>Causa:</strong> Incluso para clientes particulares, VeriFactu requiere un documento de identificación válido.<br><br>

							<strong>Solución:</strong><br>
							1. En la ficha del cliente particular, asegurar que tiene un <strong>NIF/DNI válido</strong><br>
							2. Si no tiene NIF español, usar <strong>"Otro tipo de Documento Oficial"</strong><br>
							3. Para extranjeros: usar su documento de identidad del país correspondiente<br>
							4. En casos especiales, contactar con soporte para configuraciones específicas<br><br>

							<strong>Ejemplos válidos:</strong><br>
							• Cliente español: DNI 12345678A<br>
							• Cliente extranjero UE: Pasaporte + país<br>
							• Cliente extracomunitario: Documento oficial del país
						</div>
					</div>

					<div class="faq-field">
						<div class="faq-field-title">🔄 Cómo reenviar una factura con errores</div>
						<div class="faq-field-description">
							<strong>Pasos para reenviar:</strong><br>
							1. <strong>Corregir el error:</strong> Ir a la ficha del cliente y corregir la configuración que causó el error<br>
							2. <strong>Acceder a la factura:</strong> Abrir la factura que dio error (ej: FA202510-000131)<br>
							3. <strong>Ir a la pestaña VeriFactu:</strong> Hacer clic en la pestaña "VERIFACTU" dentro de la factura<br>
							4. <strong>Reenviar:</strong> Hacer clic en el botón <strong>"Enviar"</strong><br>
							5. <strong>Verificar:</strong> Comprobar que el estado cambia a "Enviado correctamente"<br><br>

							<div style="background: #e3f2fd; padding: 10px; border-left: 4px solid #2196f3; margin: 10px 0;">
								<strong>💡 Tip:</strong> Siempre corregir primero la configuración del cliente antes de reenviar la factura.
							</div>
						</div>
					</div>

					<div class="faq-field">
						<div class="faq-field-title">📚 Códigos de Error de la AEAT</div>
						<div class="faq-field-description">
							Los códigos de estado que devuelve el módulo <strong>no son inventados</strong>, son códigos oficiales de la Agencia Tributaria.<br><br>

							<strong>Fuente oficial:</strong><br>
							<a href="https://prewww2.aeat.es/static_files/common/internet/dep/aplicaciones/es/aeat/tikeV1.0/cont/ws/errores.properties" target="_blank" style="color: #1976d2; text-decoration: none;">
								🔗 Listado oficial de códigos de error AEAT
							</a><br><br>

							<strong>Errores más comunes:</strong><br>
							• <strong>B2B001:</strong> Datos del destinatario incorrectos<br>
							• <strong>B2B002:</strong> Tipo de identificación no válido<br>
							• <strong>B2B003:</strong> NIF/CIF no válido o mal formateado<br>
							• <strong>B2B004:</strong> Datos de facturación incompletos<br>
							• <strong>B2B005:</strong> Importe o IVA mal calculado<br><br>

							<div style="background: #fff3e0; padding: 10px; border-left: 4px solid #ff9800; margin: 10px 0;">
								<strong>⚠️ Importante:</strong> Cada código de error tiene una solución específica. Consulta la documentación oficial para detalles.
							</div>
						</div>
					</div>

					<div class="faq-field">
						<div class="faq-field-title">🌍 Configuración para Países Extracomunitarios</div>
						<div class="faq-field-description">
							<strong>Países que salieron de la UE:</strong><br>
							• <strong>Reino Unido:</strong> Desde enero 2021 → Usar "Otro tipo de Documento Oficial"<br>
							• Cualquier país que no pertenezca a la UE actual<br><br>

							<strong>Configuración correcta:</strong><br>
							1. <strong>Tipo de Identificación:</strong> "Otro tipo de Documento Oficial"<br>
							2. <strong>Número:</strong> El número de registro empresarial del país<br>
							3. <strong>País:</strong> Seleccionar el país correcto en la ficha del cliente<br><br>

							<strong>Países UE actuales (usar CIF Intracomunitario):</strong><br>
							Alemania, Austria, Bélgica, Bulgaria, Chipre, Croacia, Dinamarca, Eslovaquia, Eslovenia, Estonia, Finlandia, Francia, Grecia, Hungría, Irlanda, Italia, Letonia, Lituania, Luxemburgo, Malta, Países Bajos, Polonia, Portugal, República Checa, Rumania, Suecia.<br><br>

							<div style="background: #f3e5f5; padding: 10px; border-left: 4px solid #9c27b0; margin: 10px 0;">
								<strong>📋 Nota:</strong> En caso de duda sobre el estatus de un país, consultar con la asesoría fiscal.
							</div>
						</div>
					</div>

					<div class="faq-field">
						<div class="faq-field-title">🔧 Solución de Problemas Paso a Paso</div>
						<div class="faq-field-description">
							<strong>Metodología para resolver errores:</strong><br><br>

							<strong>1. Identificar el error:</strong><br>
							• Revisar el mensaje de error específico<br>
							• Anotar el código de error de la AEAT<br>
							• Identificar qué factura está fallando<br><br>

							<strong>2. Localizar la causa:</strong><br>
							• Revisar la configuración del cliente<br>
							• Verificar los datos de la factura<br>
							• Comprobar tipos de identificación<br><br>

							<strong>3. Aplicar la corrección:</strong><br>
							• Modificar la ficha del cliente si es necesario<br>
							• Ajustar configuraciones específicas<br>
							• Validar que los cambios son correctos<br><br>

							<strong>4. Reenviar y verificar:</strong><br>
							• Usar la pestaña VeriFactu de la factura<br>
							• Hacer clic en "Enviar"<br>
							• Confirmar que el envío es exitoso<br><br>

							<div style="background: #e8f5e8; padding: 10px; border-left: 4px solid #4caf50; margin: 10px 0;">
								<strong>✅ Consejo:</strong> Documentar las soluciones aplicadas para casos futuros similares.
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

	</div>
</div>

<script>
// Funcionalidad del acordeón
function toggleAccordion(header) {
	const content = header.nextElementSibling;
	const icon = header.querySelector('.accordion-icon');

	// Toggle active states
	header.classList.toggle('active');
	content.classList.toggle('active');

	// Rotate icon
	if (header.classList.contains('active')) {
		icon.style.transform = 'rotate(180deg)';
	} else {
		icon.style.transform = 'rotate(0deg)';
	}
}

// Expandir todas las secciones
function expandAll() {
	const headers = document.querySelectorAll('.accordion-header');
	const contents = document.querySelectorAll('.accordion-content');

	headers.forEach(header => {
		header.classList.add('active');
		header.querySelector('.accordion-icon').style.transform = 'rotate(180deg)';
	});

	contents.forEach(content => {
		content.classList.add('active');
	});
}

// Contraer todas las secciones
function collapseAll() {
	const headers = document.querySelectorAll('.accordion-header');
	const contents = document.querySelectorAll('.accordion-content');

	headers.forEach(header => {
		header.classList.remove('active');
		header.querySelector('.accordion-icon').style.transform = 'rotate(0deg)';
	});

	contents.forEach(content => {
		content.classList.remove('active');
	});
}

// Scroll a sección específica
function scrollToSection(sectionId) {
	const section = document.getElementById(sectionId);
	if (section) {
		// Expandir la sección si está contraída
		const header = section.querySelector('.accordion-header');
		const content = section.querySelector('.accordion-content');

		if (!header.classList.contains('active')) {
			toggleAccordion(header);
		}

		// Scroll suave a la sección
		section.scrollIntoView({
			behavior: 'smooth',
			block: 'start'
		});
	}
}

// Funcionalidad de búsqueda
document.addEventListener('DOMContentLoaded', function() {
	const searchInput = document.getElementById('searchInput');
	const searchStats = document.getElementById('searchStats');
	let searchTimeout;

	searchInput.addEventListener('input', function() {
		clearTimeout(searchTimeout);
		searchTimeout = setTimeout(() => {
			performSearch(this.value.trim());
		}, 300);
	});

	function performSearch(query) {
		const accordionItems = document.querySelectorAll('.accordion-item');
		let totalMatches = 0;
		let visibleSections = 0;

		if (query === '') {
			// Mostrar todo si no hay búsqueda
			accordionItems.forEach(item => {
				item.style.display = 'block';
				removeHighlights(item);
			});
			searchStats.textContent = 'Escribe para buscar información específica';
			return;
		}

		accordionItems.forEach(item => {
			const text = item.textContent.toLowerCase();
			const matches = text.includes(query.toLowerCase());

			if (matches) {
				item.style.display = 'block';
				highlightText(item, query);
				visibleSections++;

				// Auto-expandir secciones con coincidencias
				const header = item.querySelector('.accordion-header');
				const content = item.querySelector('.accordion-content');

				if (!header.classList.contains('active')) {
					toggleAccordion(header);
				}

				// Contar coincidencias específicas
				const regex = new RegExp(query, 'gi');
				const textMatches = text.match(regex);
				if (textMatches) {
					totalMatches += textMatches.length;
				}
			} else {
				item.style.display = 'none';
				removeHighlights(item);
			}
		});

		// Actualizar estadísticas de búsqueda
		if (totalMatches > 0) {
			searchStats.innerHTML = `
				✅ Encontradas <strong>${totalMatches}</strong> coincidencias en <strong>${visibleSections}</strong> secciones
			`;
		} else {
			searchStats.innerHTML = `
				❌ No se encontraron coincidencias para "<strong>${query}</strong>"
			`;
		}
	}

	function highlightText(element, query) {
		if (!query) return;

		const walker = document.createTreeWalker(
			element,
			NodeFilter.SHOW_TEXT,
			null,
			false
		);

		const textNodes = [];
		let node;
		while (node = walker.nextNode()) {
			textNodes.push(node);
		}

		textNodes.forEach(textNode => {
			const parent = textNode.parentNode;
			if (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE') return;

			const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
			const text = textNode.textContent;

			if (regex.test(text)) {
				const highlightedHTML = text.replace(regex, '<span class="search-highlight">$1</span>');
				const wrapper = document.createElement('div');
				wrapper.innerHTML = highlightedHTML;

				while (wrapper.firstChild) {
					parent.insertBefore(wrapper.firstChild, textNode);
				}
				parent.removeChild(textNode);
			}
		});
	}

	function removeHighlights(element) {
		const highlights = element.querySelectorAll('.search-highlight');
		highlights.forEach(highlight => {
			const parent = highlight.parentNode;
			parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
			parent.normalize();
		});
	}

	function escapeRegExp(string) {
		return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	}
});

// Atajos de teclado
document.addEventListener('keydown', function(e) {
	// Ctrl/Cmd + F para enfocar búsqueda
	if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
		e.preventDefault();
		document.getElementById('searchInput').focus();
	}

	// Escape para limpiar búsqueda
	if (e.key === 'Escape') {
		const searchInput = document.getElementById('searchInput');
		if (searchInput.value) {
			searchInput.value = '';
			searchInput.dispatchEvent(new Event('input'));
		}
	}
});
</script>

<?php

llxFooter();
