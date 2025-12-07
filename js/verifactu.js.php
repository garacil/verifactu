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
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}


/**
 * \file    verifactu/js/verifactu.js.php
 * \ingroup verifactu
 * \brief   JavaScript file for module Verifactu.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/../main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/../main.inc.php";
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

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=3600, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}
$langs->load("verifactu@verifactu");
?>

/* Javascript library of module Verifactu */


$(document).ready(function () {
// Find the text field by its name and replace it with an options selector
var estadoActual =$('input[name="search_options_verifactu_estado"]').val()
console.log(estadoActual);
console.log('<?php echo $langs->trans('VERIFACTU_STATUS_NOT_SEND') ?>');
var $select = $('<select>', {
	name: 'search_options_verifactu_estado',
	id: 'search_options_verifactu_estado'
	}).addClass('minwidth150').append(
	$('<option>', {
		value: '',
		text: '<?php echo $langs->trans('VERIFACTU_ALL_STATUS') ?>',
		selected: estadoActual != '<?php echo $langs->trans('VERIFACTU_STATUS_NOT_SEND') ?>' && estadoActual != '<?php echo $langs->trans('VERIFACTU_STATUS_SEND') ?>' && estadoActual != '<?php echo $langs->trans('VERIFACTU_STATUS_ERROR') ?>'
		}),
		$('<option>', {
		value: '<?php echo $langs->trans('VERIFACTU_STATUS_NOT_SEND') ?>',
		text: '<?php echo $langs->trans('VERIFACTU_STATUS_NOT_SEND') ?>',
		selected: estadoActual == '<?php echo $langs->trans('VERIFACTU_STATUS_NOT_SEND') ?>'
		}),
		$('<option>', {
		value: '<?php echo $langs->trans('VERIFACTU_STATUS_SEND') ?>',
		text: '<?php echo $langs->trans('VERIFACTU_STATUS_SEND') ?>',
		selected: estadoActual == '<?php echo $langs->trans('VERIFACTU_STATUS_SEND') ?>'
		}),
		$('<option>', {
		value: '<?php echo $langs->trans('VERIFACTU_STATUS_ERROR') ?>',
		text: '<?php echo $langs->trans('VERIFACTU_STATUS_ERROR') ?>',
		selected: estadoActual == '<?php echo $langs->trans('VERIFACTU_STATUS_ERROR') ?>'
		})
		);

		// Replace the text field with the options selector
		$('input[name="search_options_verifactu_estado"]').replaceWith($select);

		// Initialize Select2 on the new selection field
		$select.select2();

});

// TAKEPOS VALIDATE logic

// Create improved loading overlay
function createVerifactuLoadingOverlay() {
	if ($('#verifactu-loading-overlay').length === 0) {
		var overlay = $('<div>', {
			id: 'verifactu-loading-overlay',
			css: {
				position: 'fixed',
				top: 0,
				left: 0,
				width: '100%',
				height: '100%',
				backgroundColor: 'rgba(0, 0, 0, 0.85)',
				zIndex: 999999,
				display: 'none',
				justifyContent: 'center',
				alignItems: 'center',
				flexDirection: 'column',
				backdropFilter: 'blur(5px)'
			}
		}).append(
			$('<div>', {
				css: {
					backgroundColor: '#ffffff',
					padding: '50px 60px',
					borderRadius: '20px',
					textAlign: 'center',
					boxShadow: '0 20px 60px rgba(0, 0, 0, 0.3)',
					minWidth: '350px',
					border: '3px solid #3498db'
				}
			}).append(
				// Verifactu logo or icon
				$('<div>', {
					css: {
						fontSize: '48px',
						marginBottom: '20px',
						color: '#3498db'
					}
				}).html('&#128195;'), // Document icon
				// Spinner
				$('<div>', {
					css: {
						border: '6px solid #f3f3f3',
						borderTop: '6px solid #3498db',
						borderRadius: '50%',
						width: '80px',
						height: '80px',
						animation: 'spin 1s linear infinite',
						margin: '0 auto 25px'
					}
				}),
				// Main text
				$('<h2>', {
					text: 'Sending to Verifactu...',
					css: {
						margin: '0 0 15px 0',
						fontSize: '24px',
						color: '#2c3e50',
						fontWeight: 'bold',
						fontFamily: 'Arial, sans-serif'
					}
				}),
				// Secondary text
				$('<p>', {
					text: 'Please wait while your invoice is being processed',
					css: {
						margin: 0,
						fontSize: '14px',
						color: '#7f8c8d',
						fontFamily: 'Arial, sans-serif'
					}
				}),
				// Animated dots
				$('<div>', {
					id: 'verifactu-dots',
					css: {
						marginTop: '20px',
						fontSize: '24px',
						color: '#3498db',
						height: '30px',
						fontWeight: 'bold'
					}
				})
			)
		);

		// Add CSS animations
		if ($('#verifactu-animations').length === 0) {
			$('<style>', {
				id: 'verifactu-animations',
				text: `
					@keyframes spin {
						0% { transform: rotate(0deg); }
						100% { transform: rotate(360deg); }
					}
					@keyframes pulse {
						0%, 100% { opacity: 1; }
						50% { opacity: 0.5; }
					}
				`
			}).appendTo('head');
		}

		$('body').append(overlay);

		// Animate the ellipsis dots
		var dots = 0;
		setInterval(function() {
			if ($('#verifactu-loading-overlay').is(':visible')) {
				dots = (dots + 1) % 4;
				$('#verifactu-dots').text('.'.repeat(dots));
			}
		}, 500);
	}
}

// Show overlay
function showVerifactuLoading() {
	createVerifactuLoadingOverlay();
	$('#verifactu-loading-overlay').css('display', 'flex').fadeIn(300);
}

// Hide overlay
function hideVerifactuLoading() {
	$('#verifactu-loading-overlay').fadeOut(300);
}

// Intercept the Validate() function using simple polling
(function() {
	var isIntercepted = false;

	function interceptValidate() {
		if (typeof window.Validate === 'function' && !isIntercepted) {
			console.log('[Verifactu] Función Validate() detectada, interceptando...');

			var originalValidate = window.Validate;
			isIntercepted = true;

			window.Validate = function() {
				console.log('[Verifactu] Validate() interceptada - Mostrando overlay...');
				showVerifactuLoading();

				try {
					return originalValidate.apply(this, arguments);
				} catch (error) {
					console.error('[Verifactu] Error:', error);
					setTimeout(hideVerifactuLoading, 2000);
					throw error;
				}
			};

			// Copy properties
			for (var prop in originalValidate) {
				if (originalValidate.hasOwnProperty(prop)) {
					window.Validate[prop] = originalValidate[prop];
				}
			}
		}
	}

	// Try to intercept every 100ms for 5 seconds
	var attempts = 0;
	var checkInterval = setInterval(function() {
		if (isIntercepted || attempts++ >= 50) {
			clearInterval(checkInterval);
		} else {
			interceptValidate();
		}
	}, 100);
})();

