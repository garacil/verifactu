<?php
/* Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
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

// Load configuration file
$confFile = dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);
if (file_exists($confFile)) {
	require_once $confFile;
	$declaracion = obtenerDeclaracionResponsable(true);
	$erroresConfig = validarDeclaracionResponsable();
} else {
	$declaracion = null;
	$erroresConfig = array('El fichero de configuración no existe');
}

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

// Show configuration errors if any
if (!empty($erroresConfig)) {
	print '<div class="warning">';
	print '<strong>Advertencia:</strong> La configuración de la declaración responsable está incompleta:<br>';
	print '<ul>';
	foreach ($erroresConfig as $error) {
		print '<li>' . htmlspecialchars($error) . '</li>';
	}
	print '</ul>';
	print '<p>Por favor, complete el fichero <code>conf/declaracion_responsable.conf.php</code></p>';
	print '</div>';
}
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

.info-section {
	background: #f8f9fa;
	border-radius: 12px;
	padding: 30px;
	margin: 30px 0;
	border-left: 4px solid #1a73e8;
}

.info-section.sistema {
	border-left-color: #34a853;
}

.info-section.tecnico {
	border-left-color: #ea4335;
}

.info-section.integridad {
	border-left-color: #fbbc04;
}

.info-section h3 {
	color: #1a73e8;
	font-size: 20px;
	margin: 0 0 20px 0;
	font-weight: 600;
}

.info-section.sistema h3 {
	color: #34a853;
}

.info-section.tecnico h3 {
	color: #ea4335;
}

.info-section.integridad h3 {
	color: #b06000;
}

.info-datos {
	display: grid;
	gap: 15px;
}

.info-dato {
	display: flex;
	align-items: flex-start;
	gap: 12px;
}

.info-dato .icono {
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

.info-section.sistema .info-dato .icono {
	background: #34a853;
}

.info-section.tecnico .info-dato .icono {
	background: #ea4335;
}

.info-section.integridad .info-dato .icono {
	background: #fbbc04;
	color: #333;
}

.info-dato .contenido {
	flex: 1;
}

.info-dato .etiqueta {
	font-size: 12px;
	color: #5f6368;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	margin-bottom: 2px;
}

.info-dato .valor {
	font-size: 16px;
	color: #202124;
	font-weight: 500;
}

.info-dato .valor.hash {
	font-family: monospace;
	font-size: 12px;
	word-break: break-all;
	background: #e8eaed;
	padding: 8px;
	border-radius: 4px;
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

.requisitos-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 10px;
	margin-top: 15px;
}

.requisito-badge {
	display: flex;
	align-items: center;
	gap: 6px;
	background: #e8f5e9;
	padding: 8px 12px;
	border-radius: 6px;
	font-size: 13px;
	color: #2e7d32;
}

.requisito-badge .check {
	color: #2e7d32;
	font-weight: bold;
}

.declaracion-footer {
	margin-top: 40px;
	padding-top: 30px;
	border-top: 2px solid #e8eaed;
	text-align: center;
}

.normativa-badges {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	justify-content: center;
	margin-bottom: 20px;
}

.normativa-badge {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
	color: white;
	padding: 10px 20px;
	border-radius: 30px;
	font-size: 12px;
	font-weight: 500;
}

.normativa-badge::before {
	content: '\2713';
	font-weight: bold;
}

.btn-actions {
	display: flex;
	gap: 15px;
	justify-content: center;
	margin-top: 30px;
}

.btn-action {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 12px 28px;
	border: none;
	border-radius: 8px;
	font-size: 14px;
	font-weight: 500;
	cursor: pointer;
	transition: all 0.3s ease;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
	text-decoration: none;
}

.btn-action.print {
	background: linear-gradient(135deg, #5f6368 0%, #3c4043 100%);
	color: white;
}

.btn-action.print:hover {
	background: linear-gradient(135deg, #3c4043 0%, #202124 100%);
	transform: translateY(-2px);
	box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

.btn-action.json {
	background: linear-gradient(135deg, #34a853 0%, #2e7d32 100%);
	color: white;
}

.btn-action.json:hover {
	background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
	transform: translateY(-2px);
}

.btn-action .btn-icon {
	font-size: 18px;
}

.suscripcion-info {
	background: #fff3e0;
	border-radius: 8px;
	padding: 20px;
	margin-top: 20px;
	border: 1px solid #ffe0b2;
}

.suscripcion-info h4 {
	color: #e65100;
	margin: 0 0 15px 0;
	font-size: 16px;
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
	.btn-actions {
		display: none;
	}
}

@media (max-width: 768px) {
	.requisitos-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}
</style>

<div class="declaracion-container">
	<div class="declaracion-header">
		<h2>Declaraci&oacute;n Responsable</h2>
		<div class="subtitulo">Sistema VeriFactu - Cumplimiento Normativo</div>
	</div>

	<div class="declaracion-body">
		<p class="declaracion-intro">
			<?php echo htmlspecialchars($declaracion['cumplimiento']['declaracion'] ?? ''); ?>
		</p>

		<!-- Datos del Productor -->
		<div class="info-section productor">
			<h3>Datos del Productor</h3>
			<div class="info-datos">
				<div class="info-dato">
					<div class="icono">&#x1F3E2;</div>
					<div class="contenido">
						<div class="etiqueta">Raz&oacute;n Social</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['productor']['razon_social'] ?? 'No configurado'); ?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F4CB;</div>
					<div class="contenido">
						<div class="etiqueta">NIF</div>
						<div class="valor"><?php
							$nif = $declaracion['productor']['nif'] ?? '';
							if (empty($nif) && !empty($declaracion['productor']['nif_extranjero']['numero'])) {
								$nif = $declaracion['productor']['nif_extranjero']['numero'] . ' (' . $declaracion['productor']['nif_extranjero']['pais_emision'] . ')';
							}
							echo htmlspecialchars($nif ?: 'No configurado');
						?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F4CD;</div>
					<div class="contenido">
						<div class="etiqueta">Direcci&oacute;n</div>
						<div class="valor"><?php
							$dir = $declaracion['productor']['direccion'] ?? array();
							$direccion = array_filter(array(
								trim(($dir['tipo_via'] ?? '') . ' ' . ($dir['nombre_via'] ?? '') . ' ' . ($dir['numero'] ?? '')),
								trim(($dir['piso'] ?? '') . ' ' . ($dir['puerta'] ?? '')),
								$dir['codigo_postal'] ?? '',
								$dir['localidad'] ?? '',
								$dir['provincia'] ?? '',
							));
							echo htmlspecialchars(implode(', ', $direccion) ?: 'No configurado');
						?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x2709;</div>
					<div class="contenido">
						<div class="etiqueta">Email de Contacto</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['productor']['contacto']['email'] ?? 'No configurado'); ?></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Datos del Sistema -->
		<div class="info-section sistema">
			<h3>Datos del Sistema Inform&aacute;tico</h3>
			<div class="info-datos">
				<div class="info-dato">
					<div class="icono">&#x1F4BB;</div>
					<div class="contenido">
						<div class="etiqueta">Nombre del Sistema</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['sistema']['nombre'] ?? ''); ?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F511;</div>
					<div class="contenido">
						<div class="etiqueta">IdSistemaInform&aacute;tico</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['sistema']['id_sistema_informatico'] ?? ''); ?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F4C8;</div>
					<div class="contenido">
						<div class="etiqueta">Versi&oacute;n</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['sistema']['version'] ?? ''); ?> (<?php echo htmlspecialchars($declaracion['sistema']['fecha_version'] ?? ''); ?>)</div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F512;</div>
					<div class="contenido">
						<div class="etiqueta">N&uacute;mero de Instalaci&oacute;n</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['sistema']['numero_instalacion'] ?? ''); ?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F4DD;</div>
					<div class="contenido">
						<div class="etiqueta">Licencia</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['sistema']['tipo_licencia'] ?? ''); ?></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Especificaciones Técnicas -->
		<div class="info-section tecnico">
			<h3>Especificaciones T&eacute;cnicas</h3>
			<div class="info-datos">
				<div class="info-dato">
					<div class="icono">&#x2699;</div>
					<div class="contenido">
						<div class="etiqueta">Modalidad de Funcionamiento</div>
						<div class="valor"><?php
							$modalidad = $declaracion['especificaciones_tecnicas']['modalidad_funcionamiento'] ?? '';
							echo $modalidad === 'verifactu' ? 'VERI*FACTU (envío inmediato a AEAT)' : 'NO VERI*FACTU';
						?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F58A;</div>
					<div class="contenido">
						<div class="etiqueta">Tipo de Firma Electr&oacute;nica</div>
						<div class="valor"><?php
							$firma = $declaracion['especificaciones_tecnicas']['tipo_firma'] ?? array();
							echo htmlspecialchars(($firma['formato'] ?? '') . ' ' . ($firma['tipo'] ?? '') . ' con certificado cualificado');
						?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F510;</div>
					<div class="contenido">
						<div class="etiqueta">Algoritmo de Huella</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['especificaciones_tecnicas']['algoritmo_huella'] ?? 'SHA-256'); ?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F465;</div>
					<div class="contenido">
						<div class="etiqueta">Caracter&iacute;sticas</div>
						<div class="valor"><?php
							$caracteristicas = array();
							if ($declaracion['especificaciones_tecnicas']['multiusuario'] ?? false) $caracteristicas[] = 'Multi-usuario';
							if ($declaracion['especificaciones_tecnicas']['multiempresa'] ?? false) $caracteristicas[] = 'Multi-empresa';
							echo htmlspecialchars(implode(', ', $caracteristicas) ?: 'Estándar');
						?></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Hash de Integridad -->
		<div class="info-section integridad">
			<h3>Integridad del Sistema</h3>
			<div class="info-datos">
				<div class="info-dato">
					<div class="icono">&#x1F50F;</div>
					<div class="contenido">
						<div class="etiqueta">Hash del M&oacute;dulo (<?php echo htmlspecialchars($declaracion['integridad']['algoritmo'] ?? 'SHA-256'); ?>)</div>
						<div class="valor hash"><?php echo htmlspecialchars($declaracion['integridad']['hash_modulo'] ?? 'No calculado'); ?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="icono">&#x1F4C5;</div>
					<div class="contenido">
						<div class="etiqueta">Fecha de C&aacute;lculo</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['integridad']['fecha_calculo'] ?? 'No disponible'); ?></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Requisitos Cumplidos -->
		<div class="declaracion-legal">
			<p><strong>Requisitos t&eacute;cnicos cumplidos seg&uacute;n Art. 8 RD 1007/2023:</strong></p>
			<div class="requisitos-grid">
				<?php
				$requisitos = array(
					'integridad' => 'Integridad',
					'conservacion' => 'Conservación',
					'accesibilidad' => 'Accesibilidad',
					'legibilidad' => 'Legibilidad',
					'trazabilidad' => 'Trazabilidad',
					'inalterabilidad' => 'Inalterabilidad',
				);
				foreach ($requisitos as $key => $label) {
					$cumplido = $declaracion['cumplimiento']['requisitos_cumplidos'][$key] ?? false;
					if ($cumplido) {
						echo '<div class="requisito-badge"><span class="check">&#x2713;</span> ' . $label . '</div>';
					}
				}
				?>
			</div>
		</div>

		<!-- Suscripción -->
		<div class="suscripcion-info">
			<h4>Suscripci&oacute;n de la Declaraci&oacute;n</h4>
			<div class="info-datos">
				<div class="info-dato">
					<div class="contenido">
						<div class="etiqueta">Fecha</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['suscripcion']['fecha'] ?? ''); ?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="contenido">
						<div class="etiqueta">Lugar</div>
						<div class="valor"><?php
							$lugar = $declaracion['suscripcion']['lugar'] ?? array();
							echo htmlspecialchars(($lugar['localidad'] ?? '') . ', ' . ($lugar['pais'] ?? 'España'));
						?></div>
					</div>
				</div>
				<div class="info-dato">
					<div class="contenido">
						<div class="etiqueta">Firmante</div>
						<div class="valor"><?php echo htmlspecialchars($declaracion['suscripcion']['firmante']['nombre'] ?? ''); ?></div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="declaracion-footer">
		<div class="normativa-badges">
			<span class="normativa-badge">RD 1007/2023</span>
			<span class="normativa-badge">Orden HAC/1177/2024</span>
			<span class="normativa-badge">Art. 29.2.j) LGT</span>
		</div>

		<div class="btn-actions">
			<button class="btn-action print" onclick="window.print()">
				<span class="btn-icon">&#x1F5A8;</span>
				Imprimir Declaraci&oacute;n
			</button>
			<a class="btn-action json" href="<?php echo dol_buildpath('/verifactu/views/declaration_json.php', 1); ?>" target="_blank">
				<span class="btn-icon">&#x1F4BE;</span>
				Exportar JSON
			</a>
		</div>
	</div>
</div>

<?php
print '</div>';

// End of page
llxFooter();
$db->close();
