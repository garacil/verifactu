<?php
/* Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
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

// This tpl file is included into the init part of pages, so before action.
// So no output must be done.

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
	print "Error, template page can't be called as URL";
	exit;
}

if (empty($extrafieldsobjectkey) && is_object($object)) {
	$extrafieldsobjectkey = $object->table_element;
}

// VeriFactu fields shown by default (others are hidden)
$verifactuDefaultVisible = array(
	'verifactu_estado',
	'verifactu_csv_factura'
);

// Loop to show all columns of extrafields from $obj, $extrafields and $db
if (!empty($extrafieldsobjectkey)) {	// $extrafieldsobject is the $object->table_element like 'societe', 'socpeople', ...
	if (isset($extrafields->attributes[$extrafieldsobjectkey]['label']) && is_array($extrafields->attributes[$extrafieldsobjectkey]['label']) && count($extrafields->attributes[$extrafieldsobjectkey]['label']) > 0) {
		if (empty($extrafieldsobjectprefix)) {
			$extrafieldsobjectprefix = 'ef.';
		}

		foreach ($extrafields->attributes[$extrafieldsobjectkey]['label'] as $key => $val) {
			// Only process VERIFACTU extrafields
			if (strpos($key, 'verifactu_') !== 0) continue;
			if (!empty($extrafields->attributes[$extrafieldsobjectkey]['list'][$key])) {
				// Only show essential fields by default
				$isChecked = in_array($key, $verifactuDefaultVisible) ? 1 : 0;

				$arrayfields[$extrafieldsobjectprefix . $key] = array(
					'label'    => $extrafields->attributes[$extrafieldsobjectkey]['label'][$key],
					'type'     => $extrafields->attributes[$extrafieldsobjectkey]['type'][$key],
					'checked'  => $isChecked,
					'position' => $extrafields->attributes[$extrafieldsobjectkey]['pos'][$key],
					'enabled'  => (abs((int) dol_eval($extrafields->attributes[$extrafieldsobjectkey]['list'][$key], 1)) != 3 && dol_eval($extrafields->attributes[$extrafieldsobjectkey]['perms'][$key], 1, 1, '1')),
					'langfile' => $extrafields->attributes[$extrafieldsobjectkey]['langfile'][$key],
					'help'     => $extrafields->attributes[$extrafieldsobjectkey]['help'][$key],
				);
			}
		}
	}
}
