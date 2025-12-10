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
 * \file    verifactu/lib/functions/functions.compatibility.php
 * \ingroup verifactu
 * \brief   Compatibility functions for older Dolibarr versions
 */

/**
 * Gets the total HT (net amount) from an invoice (compatible with Dolibarr v13 and earlier)
 *
 * @param object $invoice Invoice object
 * @return float Total HT (net amount before tax)
 */
function getInvoiceTotalHT($invoice)
{
	if (versioncompare(explode('.', DOL_VERSION), array(14)) < 0) {
		// Dolibarr v13 and earlier: use 'total'
		return isset($invoice->total) ? $invoice->total : 0;
	}
	// Dolibarr v14+: use 'total_ht'
	return isset($invoice->total_ht) ? $invoice->total_ht : 0;
}

/**
 * Gets the total VAT amount from an invoice (compatible with Dolibarr v13 and earlier)
 *
 * @param object $invoice Invoice object
 * @return float Total VAT amount
 */
function getInvoiceTotalTVA($invoice)
{
	if (versioncompare(explode('.', DOL_VERSION), array(14)) < 0) {
		// Dolibarr v13 and earlier: use 'tva'
		return isset($invoice->tva) ? $invoice->tva : 0;
	}
	// Dolibarr v14+: use 'total_tva'
	return isset($invoice->total_tva) ? $invoice->total_tva : 0;
}
