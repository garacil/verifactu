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

/**
 * \file    verifactu/lib/dumper.php
 * \ingroup verifactu
 * \brief   Function to display structured data in a readable format
 */

/**
 * Displays structured data (arrays/objects) in readable HTML format
 *
 * @param mixed  $data   Data to display (array, object, etc.)
 * @param bool   $return If true, returns the HTML instead of printing it
 * @param string $title  Optional title for the block
 * @return string|void   HTML if $return is true, nothing if false
 */
function dumper($data, $return = false, $title = '')
{
	$output = '';

	if (!empty($title)) {
		$output .= '<div class="div-table-responsive-no-min">';
		$output .= '<table class="noborder centpercent">';
		$output .= '<tr class="liste_titre"><th>' . htmlspecialchars($title) . '</th></tr>';
		$output .= '<tr class="oddeven"><td>';
	}

	$output .= '<pre style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px; margin: 5px 0; font-size: 12px; overflow-x: auto; max-height: 400px; overflow-y: auto;">';

	if (is_object($data) || is_array($data)) {
		$output .= htmlspecialchars(formatDataRecursive($data, 0));
	} else {
		$output .= htmlspecialchars(print_r($data, true));
	}

	$output .= '</pre>';

	if (!empty($title)) {
		$output .= '</td></tr>';
		$output .= '</table>';
		$output .= '</div>';
	}

	if ($return) {
		return $output;
	} else {
		print $output;
	}
}

/**
 * Formats data recursively for readable display
 *
 * @param mixed $data   Data to format
 * @param int   $indent Indentation level
 * @return string       Formatted text
 */
function formatDataRecursive($data, $indent = 0)
{
	$output = '';
	$indentStr = str_repeat('  ', $indent);

	if (is_object($data)) {
		$data = (array) $data;
	}

	if (is_array($data)) {
		foreach ($data as $key => $value) {
			if (is_object($value) || is_array($value)) {
				$output .= $indentStr . $key . ":\n";
				$output .= formatDataRecursive($value, $indent + 1);
			} else {
				$displayValue = $value;
				if (is_bool($value)) {
					$displayValue = $value ? 'true' : 'false';
				} elseif (is_null($value)) {
					$displayValue = 'null';
				} elseif (is_string($value) && strlen($value) > 200) {
					$displayValue = substr($value, 0, 200) . '...';
				}
				$output .= $indentStr . $key . ": " . $displayValue . "\n";
			}
		}
	} else {
		$output .= $indentStr . $data . "\n";
	}

	return $output;
}
