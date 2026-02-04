<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2023 SuperAdmin
 * Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
 * Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * Based on original code from verifactu module by Alberto SuperAdmin (easysoft.es)
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
 * \file    verifactu/admin/uploadcertificates.php
 * \ingroup verifactu
 * \brief   Page for uploading and managing digital certificates.
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once '../lib/verifactu.lib.php';

// Translations
$langs->loadLangs(array("errors", "admin", "verifactu@verifactu"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$upload_dir = $conf->verifactu->multidir_output[$conf->entity];

/*
 * Actions
 */

// None
$permissiontoadd = 1;
if (GETPOST('sendit', 'alpha')) {
	$upload_dir = $conf->verifactu->multidir_output[$conf->entity] . "/certificates";
	include DOL_DOCUMENT_ROOT . '/core/actions_linkedfiles.inc.php';

	// After uploading file, try to automatically convert certificates
	if (!empty($_FILES) && !$error) {
		// Process each uploaded file
		if (is_array($_FILES['userfile']['name'])) {
			$userfilenames = $_FILES['userfile']['name'];
		} else {
			$userfilenames = array($_FILES['userfile']['name']);
		}

		foreach ($userfilenames as $key => $filename) {
			if (!empty($filename)) {
				// Temporarily update configuration with uploaded file
				$oldCertificado = $conf->global->VERIFACTU_CERTIFICATE ?? '';
				dolibarr_set_const($db, 'VERIFACTU_CERTIFICATE', $filename, 'chaine', 0, '', $conf->entity);

				// Delete existing PEM files to force regeneration from new certificate
				$certBaseName = pathinfo($filename, PATHINFO_FILENAME);
				$certificatesDir = $conf->verifactu->multidir_output[$conf->entity] . "/certificates";
				$deletedPemFiles = deleteExistingPemFiles($certificatesDir, $certBaseName);
				if ($deletedPemFiles > 0) {
					dol_syslog("VERIFACTU: Deleted $deletedPemFiles old PEM files before processing new certificate: $filename", LOG_INFO);
				}

				try {
					// Use lib function to prepare certificate
					$certInfo = prepareCertificateForVerifactu();
					if ($certInfo) {
						if ($certInfo['converted']) {
							setEventMessages($langs->trans('VERIFACTU_CERTIFICATE_CONVERTED_SUCCESS', $filename), null, 'mesgs');
						} else {
							setEventMessages($langs->trans('VERIFACTU_CERTIFICATE_VALIDATED_SUCCESS', $filename), null, 'mesgs');
						}
					} else {
						// Specific error already stored in $GLOBALS['verifactu_cert_error']
						if (isset($GLOBALS['verifactu_cert_error'])) {
							setEventMessages($langs->trans('VERIFACTU_CERTIFICATE_ERROR', $filename, $GLOBALS['verifactu_cert_error']), null, 'warnings');
						}
						// Restore previous configuration if failed
						dolibarr_set_const($db, 'VERIFACTU_CERTIFICATE', $oldCertificado, 'chaine', 0, '', $conf->entity);
					}
				} catch (Exception $e) {
					setEventMessages($langs->trans('VERIFACTU_CERTIFICATE_ERROR', $filename, $e->getMessage()), null, 'warnings');
					// Restore previous configuration if failed
					dolibarr_set_const($db, 'VERIFACTU_CERTIFICATE', $oldCertificado, 'chaine', 0, '', $conf->entity);
				}
			}
		}
	}

	$upload_dir = $conf->verifactu->multidir_output[$conf->entity];
}


if ($action == 'remove_file') {
	if (!empty($upload_dir)) {
		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';



		$langs->load("other");
		$filetodelete = GETPOST('file', 'alpha');
		$file = $upload_dir . '/' . $filetodelete;
		$dirthumb = dirname($file) . '/thumbs/'; // Chemin du dossier contenant la vignette (if file is an image)
		$ret = dol_delete_file($file, 0, 0, 0);
		if ($ret) {
			// If it exists, remove thumb.
			$regs = array();
			if (preg_match('/(\.jpg|\.jpeg|\.bmp|\.gif|\.png|\.tiff)$/i', $file, $regs)) {
				$photo_vignette = basename(preg_replace('/' . $regs[0] . '/i', '', $file) . '_small' . $regs[0]);
				if (file_exists(dol_osencode($dirthumb . $photo_vignette))) {
					dol_delete_file($dirthumb . $photo_vignette);
				}

				$photo_vignette = basename(preg_replace('/' . $regs[0] . '/i', '', $file) . '_mini' . $regs[0]);
				if (file_exists(dol_osencode($dirthumb . $photo_vignette))) {
					dol_delete_file($dirthumb . $photo_vignette);
				}
			}

			setEventMessages($langs->trans("FileWasRemoved", $filetodelete), null, 'mesgs');
		} else {
			setEventMessages($langs->trans("ErrorFailToDeleteFile", $filetodelete), null, 'errors');
		}

		// Make a redirect to avoid to keep the remove_file into the url that create side effects
		$urltoredirect = $_SERVER['REQUEST_URI'];
		$urltoredirect = preg_replace('/#builddoc$/', '', $urltoredirect);
		$urltoredirect = preg_replace('/action=remove_file&?/', '', $urltoredirect);

		header('Location: ' . $urltoredirect);
		exit;
	} else {
		setEventMessages('BugFoundVarUploaddirnotDefined', null, 'errors');
	}
}

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "UploadCertificates";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ? $backtopage : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = verifactuAdminPrepareHead();
print dol_get_fiche_head($head, 'upload_certificates', $langs->trans($page_name), 0, 'verifactu@verifactu');

dol_include_once('/verifactu/core/modules/modVerifactu.class.php');
//Crea un formulario para subir documentos con extension p12,pfx y pem

$formfile = new FormFile($db);


$formfile->form_attach_new_file(
	$_SERVER['PHP_SELF'],
	$title = $langs->trans('UploadCertificates'),
	$addcancel = 1,
	$sectionid = 0,
	$perm = 1,
	$size = 100,
	$object = '',
	$options = '<b>' . $langs->trans('ChooseYourCertificates') . '</b>',
	$useajax = 0,
	$savingdocmask = '',
	$linkfiles = 0,
	$htmlname = 'formuserfile',
	$accept = '.p12,.pfx,.pem',
	$sectiondir = '',
	$usewithoutform = 0,
	$capture = 0,
	$disablemulti = 0,
	$nooutput = 0
);

$formfile->show_documents(
	$modulepart = 'verifactu',
	$modulesubdir = 'certificates',
	$filedir = $upload_dir . '/certificates',
	$urlsource = '',
	$genallowed = 0,
	$delallowed = 1,
	$modelselected = '',
	$allowgenifempty = 0,
	$forcenomultilang = 0,
	$iconPDF = 0,
	$notused = 0,
	$noform = 0,
	$param = '',
	$title = '',
	$buttonlabel = '',
	$codelang = ''
);
// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();
