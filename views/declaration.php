<?php
/* Copyright (C) 2025 7kas Servicios de Internet SL
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
 *	\file       verifactu/views/declaration.php
 *	\ingroup    verifactu
 *	\brief      Responsible declaration page for VeriFactu module
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Load translation files required by the page
$langs->loadLangs(array("verifactu@verifactu"));

// Security check
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$socid = $user->socid;
}

/*
 * View
 */

llxHeader("", $langs->trans("VERIFACTU_DECLARATION_MENU"));

print load_fiche_titre($langs->trans("VERIFACTU_DECLARATION_MENU"), '', 'bill');

print '<div class="fichecenter">';
?>

<style>
.declaracion-container {
	max-width: 900px;
	margin: 30px auto;
	padding: 40px;
	background: linear-gradient(145deg, #ffffff 0%, #f8f9fc 100%);
	border-radius: 16px;
	box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
	border: 1px solid #e8eaed;
}

.declaracion-header {
	text-align: center;
	margin-bottom: 40px;
	padding-bottom: 30px;
	border-bottom: 2px solid #e8eaed;
}

.declaracion-header h2 {
	color: #1a73e8;
	font-size: 28px;
	font-weight: 600;
	margin: 0 0 10px 0;
}

.declaracion-header .subtitulo {
	color: #5f6368;
	font-size: 14px;
	text-transform: uppercase;
	letter-spacing: 1px;
}

.declaracion-body {
	padding: 20px 0;
}

.declaracion-intro {
	font-size: 16px;
	color: #3c4043;
	line-height: 1.8;
	margin-bottom: 30px;
	text-align: justify;
}

.empresa-info {
	background: #f8f9fa;
	border-radius: 12px;
	padding: 30px;
	margin: 30px 0;
	border-left: 4px solid #1a73e8;
}

.empresa-info h3 {
	color: #1a73e8;
	font-size: 20px;
	margin: 0 0 20px 0;
	font-weight: 600;
}

.empresa-datos {
	display: grid;
	gap: 15px;
}

.empresa-dato {
	display: flex;
	align-items: flex-start;
	gap: 12px;
}

.empresa-dato .icono {
	width: 24px;
	height: 24px;
	background: #1a73e8;
	color: white;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 12px;
	flex-shrink: 0;
	margin-top: 2px;
}

.empresa-dato .contenido {
	flex: 1;
}

.empresa-dato .etiqueta {
	font-size: 12px;
	color: #5f6368;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	margin-bottom: 2px;
}

.empresa-dato .valor {
	font-size: 16px;
	color: #202124;
	font-weight: 500;
}

.declaracion-legal {
	margin-top: 30px;
	padding: 20px;
	background: #e8f5e9;
	border-radius: 8px;
	border: 1px solid #c8e6c9;
}

.declaracion-legal p {
	margin: 0;
	color: #2e7d32;
	font-size: 14px;
	line-height: 1.6;
}

.declaracion-footer {
	margin-top: 40px;
	padding-top: 30px;
	border-top: 2px solid #e8eaed;
	text-align: center;
}

.normativa-badge {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
	color: white;
	padding: 12px 24px;
	border-radius: 30px;
	font-size: 13px;
	font-weight: 500;
}

.normativa-badge::before {
	content: '✓';
	font-weight: bold;
}

.btn-imprimir {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin-top: 30px;
	padding: 12px 28px;
	background: linear-gradient(135deg, #5f6368 0%, #3c4043 100%);
	color: white;
	border: none;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	transition: all 0.3s ease;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.btn-imprimir:hover {
	background: linear-gradient(135deg, #3c4043 0%, #202124 100%);
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

.btn-imprimir .btn-icon {
	font-size: 18px;
}

@media print {
	body * {
		visibility: hidden;
	}
	.declaracion-container, .declaracion-container * {
		visibility: visible;
	}
	.declaracion-container {
		position: absolute;
		left: 0;
		top: 0;
		width: 100%;
		box-shadow: none;
		border: none;
	}
	.btn-imprimir {
		display: none;
	}
}
</style>

<div class="declaracion-container">
	<div class="declaracion-header">
		<h2>Declaraci&oacute;n Responsable</h2>
		<div class="subtitulo">Sistema VeriFactu - Cumplimiento RD 1007/2023</div>
	</div>

	<div class="declaracion-body">
		<p class="declaracion-intro">
			En cumplimiento con el Real Decreto 1007/2023, de 5 de diciembre, y la Orden HAC/1177/2024,
			por el que se establece el sistema VeriFactu para la verificaci&oacute;n de facturas,
			la siguiente entidad act&uacute;a como desarrollador y proveedor del presente m&oacute;dulo
			de facturaci&oacute;n electr&oacute;nica:
		</p>

		<div class="empresa-info">
			<h3>Datos del Desarrollador</h3>
			<div class="empresa-datos">
				<div class="empresa-dato">
					<div class="icono">&#x1F3E2;</div>
					<div class="contenido">
						<div class="etiqueta">Raz&oacute;n Social</div>
						<div class="valor">7kas Servicios de Internet SL</div>
					</div>
				</div>
				<div class="empresa-dato">
					<div class="icono">&#x1F4CD;</div>
					<div class="contenido">
						<div class="etiqueta">Direcci&oacute;n</div>
						<div class="valor">Calle Columbretes, 38<br>12560 Benicasim (Castell&oacute;n)</div>
					</div>
				</div>
				<div class="empresa-dato">
					<div class="icono">&#x2709;</div>
					<div class="contenido">
						<div class="etiqueta">Email de Contacto</div>
						<div class="valor">soporte@7kas.com</div>
					</div>
				</div>
				<div class="empresa-dato">
					<div class="icono">&#x1F4CB;</div>
					<div class="contenido">
						<div class="etiqueta">CIF</div>
						<div class="valor">B98515273</div>
					</div>
				</div>
			</div>
		</div>

		<div class="declaracion-legal">
			<p>
				<strong>Declaraci&oacute;n de conformidad:</strong> Este m&oacute;dulo ha sido desarrollado
				conforme a las especificaciones t&eacute;cnicas establecidas por la Agencia Estatal de
				Administraci&oacute;n Tributaria (AEAT) para sistemas de emisi&oacute;n de facturas verificables,
				garantizando la integridad, autenticidad e inalterabilidad de los registros de facturaci&oacute;n.
			</p>
		</div>
	</div>

	<div class="declaracion-footer">
		<div class="normativa-badge">
			Conforme al RD 1007/2023 y Orden HAC/1177/2024
		</div>
		<br>
		<button class="btn-imprimir" onclick="window.print()">
			<span class="btn-icon">&#x1F5A8;</span>
			Imprimir Declaraci&oacute;n
		</button>
	</div>
</div>

<?php
print '</div>';

// End of page
llxFooter();
$db->close();
