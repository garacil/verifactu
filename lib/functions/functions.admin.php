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
 * \file    verifactu/lib/functions/functions.admin.php
 * \ingroup verifactu
 * \brief   Administration and UI functions for VeriFactu
 */

/**
 * Prepares the admin pages header tabs
 *
 * @return array Array of tab definitions
 */
function verifactuAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("verifactu@verifactu");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/verifactu/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	// Certificate management page
	$head[$h][0] = dol_buildpath("/verifactu/admin/managecertificates.php", 1);
	$head[$h][1] = $langs->trans("ManageCertificates");
	$head[$h][2] = 'manage_certificates';
	$h++;

	$head[$h][0] = dol_buildpath("/verifactu/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	$head[$h][0] = dol_buildpath("/verifactu/admin/copying.php", 1);
	$head[$h][1] = $langs->trans("VerifactuCopying");
	$head[$h][2] = 'copying';
	$h++;

	// Show more tabs from modules
	complete_head_from_modules($conf, $langs, null, $head, $h, 'verifactu@verifactu');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'verifactu@verifactu', 'remove');

	return $head;
}
