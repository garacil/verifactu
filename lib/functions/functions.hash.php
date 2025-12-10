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
 * \file    verifactu/lib/functions/functions.hash.php
 * \ingroup verifactu
 * \brief   Chaining and fingerprint functions for VeriFactu
 */

/**
 * Gets the hash of the last invoice for chaining
 * Simply searches for the last invoice with a verifactu fingerprint that was sent
 *
 * @return array|null Array with hash, number and date of the last invoice or null if none exists
 */
function getLastInvoiceHash()
{
	global $db, $conf;

	$environment = getEnvironment();
	// Simple query: get the last invoice with verifactu fingerprint
	$sql = "SELECT fe.verifactu_huella as hash, f.ref as invoice_number, ";
	$sql .= "DATE_FORMAT(f.datef, '%d-%m-%Y') as invoice_date"; // Format dd-mm-yyyy for Verifactu
	$sql .= " FROM " . MAIN_DB_PREFIX . "facture f";
	$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
	$sql .= " WHERE f.entity = " . getEntity('invoice');
	$sql .= " AND fe.verifactu_huella IS NOT NULL AND fe.verifactu_huella != '' AND fe.verifactu_entorno = '" . $db->escape($environment) . "'";
	$sql .= " AND f.fk_statut > 0"; // Only validated invoices
	$sql .= " ORDER BY f.rowid DESC LIMIT 1";

	dol_syslog("VERIFACTU: getLastInvoiceHash SQL: " . $sql, LOG_DEBUG);

	$resql = $db->query($sql);
	if ($resql && $db->num_rows($resql) > 0) {
		$obj = $db->fetch_object($resql);
		return [
			'hash' => $obj->hash,
			'numero' => $obj->invoice_number,  // Backward compatibility
			'number' => $obj->invoice_number,  // English key
			'fecha' => $obj->invoice_date,      // Backward compatibility
			'date' => $obj->invoice_date        // English key
		];
	}

	dol_syslog("VERIFACTU: No previous invoice with fingerprint found for chaining", LOG_INFO);
	return null;
}
