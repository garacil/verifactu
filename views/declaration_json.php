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
 *	\file       verifactu/views/declaration_json.php
 *	\ingroup    verifactu
 *	\brief      JSON export endpoint for responsible declaration
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
	http_response_code(500);
	header('Content-Type: application/json');
	echo json_encode(['error' => 'Include of main fails']);
	exit;
}

// Security check - require login
if (empty($user->id)) {
	http_response_code(401);
	header('Content-Type: application/json');
	echo json_encode(['error' => 'Authentication required']);
	exit;
}

// Check permissions
if (!$user->admin && empty($user->rights->verifactu->manage)) {
	http_response_code(403);
	header('Content-Type: application/json');
	echo json_encode(['error' => 'Permission denied']);
	exit;
}

// Load configuration file
$confFile = dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);
if (!file_exists($confFile)) {
	http_response_code(404);
	header('Content-Type: application/json');
	echo json_encode(['error' => 'Configuration file not found']);
	exit;
}

require_once $confFile;

// Get declaration data with hash calculation
$declaracion = obtenerDeclaracionResponsable(true);

// Add export metadata
$declaracion['export_metadata'] = array(
	'export_date' => date('Y-m-d H:i:s'),
	'export_user' => $user->login,
	'export_format' => 'JSON',
	'dolibarr_version' => DOL_VERSION,
	'php_version' => PHP_VERSION,
);

// Set headers for JSON download
$filename = 'declaracion_responsable_verifactu_' . date('Ymd_His') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output JSON
echo json_encode($declaracion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
