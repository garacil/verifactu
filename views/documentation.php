<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
 * Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * Based on original code from verifactu module by Alberto SuperAdmin (easysoft.es)
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
 *	\file       verifactu/views/documentation.php
 *	\ingroup    verifactu
 *	\brief      Página de documentación del módulo VeriFactu
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
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

// Load translation files required by the page
$langs->loadLangs(array("verifactu@verifactu"));

$action = GETPOST('action', 'aZ09');
$file = GETPOST('file', 'alpha');
$preview = GETPOST('preview', 'int');


// Security check
// if (! $user->rights->verifactu->myobject->read) {
// 	accessforbidden();
// }
$socid = GETPOST('socid', 'int');
if (isset($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$max = 5;
$now = dol_now();


/*
 * Actions
 */

// Generate PDF preview
if ($action == 'preview' && !empty($file)) {
	$documentationDir = dol_buildpath('/verifactu/documentation', 0);
	$filePath = $documentationDir . '/' . $file;

	// Security: validate file is within documentation directory
	$realDocDir = realpath($documentationDir);
	$realFilePath = realpath($filePath);

	if ($realFilePath && strpos($realFilePath, $realDocDir) === 0 && file_exists($realFilePath)) {
		$thumbnail = generatePdfThumbnail($realFilePath);
		if ($thumbnail) {
			// Send correct headers for image
			header('Content-Type: text/plain');
			echo $thumbnail;
			exit;
		}
	}

	http_response_code(404);
	echo 'Error: File not found or invalid';
	exit;
}

/**
 * Generate high quality PDF thumbnail
 *
 * @param string $pdfPath Full path to PDF file
 * @param int $width Thumbnail width
 * @param int $height Thumbnail height
 * @return string|null Base64 data URI of image or null if fails
 */
function generatePdfThumbnail($pdfPath, $width = 600, $height = 800)
{
	try {
		// Verify file exists
		if (!file_exists($pdfPath)) {
			dol_syslog("PDF thumbnail: File not found - " . $pdfPath, LOG_WARNING);
			return null;
		}


		// Use Imagick (required) - HIGH QUALITY
		if (!extension_loaded('imagick')) {
			dol_syslog("PDF thumbnail: Imagick extension not loaded", LOG_WARNING);
			return null;
		}

		$imagick = new Imagick();

		// DPI alto para máxima calidad (300 DPI = calidad de impresión)
		$imagick->setResolution(300, 300);

		// CRÍTICO: Establecer fondo blanco ANTES de leer el PDF
		$imagick->setBackgroundColor(new ImagickPixel('white'));

		// Leer solo la primera página del PDF
		$imagick->readImage($pdfPath . '[0]');

		// Aplanar la imagen para eliminar transparencias (fondo blanco)
		$imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
		$imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);

		// Comprimir con JPEG de alta calidad (mejor que PNG para documentos)
		$imagick->setImageFormat('jpeg');
		$imagick->setImageCompressionQuality(92); // 92% calidad (muy alta, poco peso)

		// Aplicar filtro Lanczos para redimensionado de máxima calidad
		$imagick->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, true);

		// Sharpen para mejorar nitidez después del resize
		$imagick->unsharpMaskImage(0, 0.5, 1, 0.05);

		// Convertir a base64
		$imageBlob = $imagick->getImageBlob();
		$base64 = base64_encode($imageBlob);

		$imagick->clear();
		$imagick->destroy();

		dol_syslog("PDF thumbnail: Successfully generated for " . basename($pdfPath), LOG_DEBUG);

		return 'data:image/jpeg;base64,' . $base64;
	} catch (Exception $e) {
		// Log error pero no fallar la request completa
		dol_syslog("Error generating PDF thumbnail: " . $e->getMessage(), LOG_WARNING);
		return null;
	}
}

/**
 * Scan documentation directory and organize files by categories
 *
 * @return array Array organized by categories with files
 */
function getDocumentationFiles()
{
	$documentationDir = dol_buildpath('/verifactu/documentation', 0);

	if (!is_dir($documentationDir)) {
		return array();
	}

	// Recursively scan all files
	$allFiles = dol_dir_list(
		$documentationDir,
		"files",
		1, // recursive
		'\.(pdf|xlsx|xls|docx|doc)$', // only these types
		array('\.meta$', '^\.'), // exclude meta and hidden files
		'name',
		SORT_ASC,
		1 // mode 1 to get date and size
	);

	// Organize by folders
	$categories = array();

	foreach ($allFiles as $file) {
		$relativePath = str_replace($documentationDir . '/', '', $file['fullname']);
		$pathParts = explode('/', $relativePath);

		// Category is the first folder
		$category = isset($pathParts[0]) && count($pathParts) > 1 ? $pathParts[0] : 'General';

		if (!isset($categories[$category])) {
			$categories[$category] = array();
		}

		// Determine file type and icon
		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		$fileInfo = array(
			'name' => $file['name'],
			'fullname' => $file['fullname'],
			'relativepath' => $relativePath,
			'size' => $file['size'],
			'date' => $file['date'],
			'extension' => $extension,
			'icon' => getFileIcon($extension),
			'color' => getFileColor($extension),
			'canPreview' => ($extension === 'pdf' && extension_loaded('imagick'))
		);

		$categories[$category][] = $fileInfo;
	}

	return $categories;
}

/**
 * Get icon for file type
 */
function getFileIcon($extension)
{
	$icons = array(
		'pdf' => 'fa-file-pdf',
		'xlsx' => 'fa-file-excel',
		'xls' => 'fa-file-excel',
		'docx' => 'fa-file-word',
		'doc' => 'fa-file-word'
	);

	return isset($icons[$extension]) ? $icons[$extension] : 'fa-file';
}

/**
 * Get color for file type
 */
function getFileColor($extension)
{
	$colors = array(
		'pdf' => '#d32f2f',
		'xlsx' => '#217346',
		'xls' => '#217346',
		'docx' => '#2b579a',
		'doc' => '#2b579a'
	);

	return isset($colors[$extension]) ? $colors[$extension] : '#666';
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

// Get organized documentation files
$documentationCategories = getDocumentationFiles();

llxHeader("", $langs->trans("VERIFACTU_GUIDES_MENU"));

print load_fiche_titre($langs->trans("VERIFACTU_GUIDES_MENU"), '', 'help');

// Welcome message
print '<div class="documentation-header">';
print '<div class="info-banner">';
print '<i class="fa fa-info-circle"></i> ';
print '<span>' . $langs->trans("VERIFACTU_DOCUMENTATION_INTRO") . '</span>';
print '</div>';
print '</div>';

// Check if there are documents
if (empty($documentationCategories)) {
	print '<div class="opacitymedium center" style="padding: 40px;">';
	print '<i class="fa fa-folder-open fa-3x" style="color: #ccc; margin-bottom: 15px;"></i><br>';
	print $langs->trans("VERIFACTU_NO_DOCUMENTATION");
	print '</div>';
} else {
	// Show documents by categories
	foreach ($documentationCategories as $categoryName => $files) {
		print '<div class="documentation-category">';
		print '<h2 class="category-title">';
		print '<i class="fa fa-folder-open"></i> ';
		print dol_escape_htmltag($categoryName);
		print ' <span class="badge badge-info">' . count($files) . '</span>';
		print '</h2>';

		print '<div class="documents-grid">';

		foreach ($files as $file) {
			// Direct access to documentation file
			$downloadUrl = dol_buildpath('/verifactu/documentation/' . $file['relativepath'], 1);

			print '<div class="document-card">';

			// Preview para PDF si está disponible
			if ($file['canPreview']) {
				print '<div class="document-preview" data-file="' . dol_escape_htmltag($file['relativepath']) . '">';
				print '<div class="preview-loading"><i class="fa fa-spinner fa-spin"></i> Generando vista previa...</div>';
				print '</div>';
			} else {
				print '<div class="document-icon" style="background: linear-gradient(135deg, ' . $file['color'] . ' 0%, ' . adjustBrightness($file['color'], -20) . ' 100%);">';
				print '<i class="fa ' . $file['icon'] . ' fa-3x"></i>';
				print '</div>';
			}

			print '<div class="document-info">';
			print '<h3 class="document-title">' . dol_escape_htmltag($file['name']) . '</h3>';

			print '<div class="document-meta">';
			print '<span class="meta-item"><i class="fa fa-calendar"></i> ' . dol_print_date($file['date'], '%d/%m/%Y') . '</span>';
			print '<span class="meta-item"><i class="fa fa-hdd-o"></i> ' . dol_print_size($file['size']) . '</span>';
			print '</div>';

			print '<div class="document-actions">';
			print '<a href="' . $downloadUrl . '" class="btn-download" target="_blank">';
			print '<i class="fa fa-download"></i> ' . $langs->trans("View");
			print '</a>';
			print '</div>';

			print '</div>'; // document-info
			print '</div>'; // document-card
		}

		print '</div>'; // documents-grid
		print '</div>'; // documentation-category
	}
}

// JavaScript to load PDF previews
print '<script>
document.addEventListener("DOMContentLoaded", function() {
	const previewContainers = document.querySelectorAll(".document-preview");

	previewContainers.forEach(function(container) {
		const file = container.getAttribute("data-file");
		const url = "' . $_SERVER['PHP_SELF'] . '?action=preview&file=" + encodeURIComponent(file);

		// Load preview with AJAX
		fetch(url)
			.then(response => {
				if (!response.ok) throw new Error("Preview not available");
				return response.text();
			})
			.then(dataUri => {
				container.innerHTML = \'<img src="\' + dataUri + \'" alt="PDF Preview" class="pdf-thumbnail" />\';
			})
			.catch(error => {
				// If fails, show standard icon
				container.innerHTML = \'<div class="document-icon" style="background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);"><i class="fa fa-file-pdf fa-3x"></i></div>\';
			});
	});
});
</script>';

llxFooter();
$db->close();

/**
 * Ajustar brillo de un color hexadecimal
 */
function adjustBrightness($hex, $percent)
{
	$hex = str_replace('#', '', $hex);
	$r = hexdec(substr($hex, 0, 2));
	$g = hexdec(substr($hex, 2, 2));
	$b = hexdec(substr($hex, 4, 2));

	$r = max(0, min(255, $r + $percent));
	$g = max(0, min(255, $g + $percent));
	$b = max(0, min(255, $b + $percent));

	return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}
