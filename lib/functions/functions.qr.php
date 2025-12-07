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
 * \file    verifactu/lib/functions/functions.qr.php
 * \ingroup verifactu
 * \brief   QR code generation functions for VeriFactu
 */

use Sietekas\Verifactu\QRGenerator;

// Note: Libraries are loaded by verifactu.lib.php which includes this file

/**
 * Generates QR image as HTML img tag
 *
 * @param Facture $facture Invoice object
 * @param int $size QR size in pixels
 * @param int $margin QR margin in pixels
 * @return string HTML with the QR image
 */
function getQrImage(Facture $facture, $size = 300, $margin = 0)
{
	global $conf;

	$url = QRGenerator::generateVerifiableUrl(
		$conf->global->VERIFACTU_HOLDER_NIF,
		$facture->ref,
		date('d-m-Y', $facture->date),
		($facture->total_ttc + abs($facture->total_localtax2)),
		(getEnvironment() === "test"),
	);
	$image = QRGenerator::generateBase64QR($url, $size, $margin);
	$style = 'width:' . $size . 'px; height:' . $size . 'px;';
	if ($margin > 0) {
		$style .= ' margin:' . $margin . 'px;';
	}
	return '<img src="' . $image . '" id="' . (function_exists('newToken') ? newToken() : $_SESSION['newtoken']) . '" class="verifactu-qr-code" style="' . $style . '" />';
}

/**
 * Generates QR in base64 format
 *
 * @param Facture $facture Invoice object
 * @param int $size QR size in pixels
 * @param int $margin QR margin in pixels
 * @return string QR in base64
 */
function getQrBase64(Facture $facture, $size = 300, $margin = 0)
{
	global $conf;

	$url = QRGenerator::generateVerifiableUrl(
		$conf->global->VERIFACTU_HOLDER_NIF,
		$facture->ref,
		date('d-m-Y', $facture->date),
		$facture->total_ttc,
		(getEnvironment() === "test"),
	);
	return QRGenerator::generateBase64QR($url, $size, $margin);
}

/**
 * Generates AEAT verification URL
 *
 * @param Facture $facture Invoice object
 * @return string Verification URL
 */
function getQRUrl(Facture $facture)
{
	global $conf;

	return QRGenerator::generateVerifiableUrl(
		$conf->global->VERIFACTU_HOLDER_NIF,
		$facture->ref,
		date('d-m-Y', $facture->date),
		$facture->total_ttc,
		(getEnvironment() === "test"),
	);
}

/**
 * Checks if an invoice was successfully sent to VeriFactu
 *
 * @param Facture $facture Invoice object
 * @return bool True if the invoice was sent successfully
 */
function isInvoiceSentToVerifactu(Facture $facture)
{
	global $langs;
	$langs->load("verifactu@verifactu");
	$facture->fetch_optionals();
	if (!empty($facture->array_options['options_verifactu_csv_factura']) && strpos($facture->array_options['options_verifactu_estado'], 'attr-status="' . ($langs->trans('VERIFACTU_STATUS_SEND')) . '"') !== false) {
		return true;
	}
	return false;
}

// Backward compatibility alias
function isFactureSendToVerifactu(Facture $facture)
{
	return isInvoiceSentToVerifactu($facture);
}
