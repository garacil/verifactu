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
 * Returns the name identifying the chain lock of the active entity.
 *
 * The chain is per legal entity and per environment, so two entities, or the
 * test and production chains of one entity, never wait for each other.
 *
 * @return string Lock name
 */
function getVerifactuChainLockName()
{
	global $conf;

	return 'verifactu_chain_' . ((int) $conf->entity) . '_' . getEnvironment();
}

/**
 * Tracks whether this request owns the chain lock.
 *
 * @param  bool|null $set New state, or null to read the current one
 * @return bool           True when this request owns the lock
 */
function verifactuChainLockHeld($set = null)
{
	static $held = false;

	if ($set !== null) {
		$held = (bool) $set;
	}

	return $held;
}

/**
 * Returns the two 32 bit keys of the PostgreSQL advisory lock.
 *
 * The first one identifies the module, the second one this entity and
 * environment.
 *
 * @param  string $lockName Lock name
 * @return int              Second key, signed as PostgreSQL expects it
 */
function getVerifactuChainLockKey($lockName)
{
	$key = crc32($lockName);

	return ($key > 0x7FFFFFFF ? $key - 0x100000000 : $key);
}

/**
 * Serializes the critical section of the chain.
 *
 * Reading the previous fingerprint, submitting and storing the new one must not
 * interleave with another validation: two concurrent validations would read the
 * same previous record and chain both invoices onto it, breaking the sequential
 * chain required by Art. 13 of Orden HAC/1177/2024.
 *
 * The lock lives in the database session, so it is released on its own if the
 * process dies. MySQL uses GET_LOCK() and PostgreSQL an advisory lock; any
 * other driver has no application lock and the caller proceeds without one.
 *
 * @param  int|null $timeoutSeconds Seconds to wait, VERIFACTU_CHAIN_LOCK_TIMEOUT by default
 * @return bool                     True when the caller owns the chain
 */
function acquireVerifactuChainLock($timeoutSeconds = null)
{
	global $db, $conf;

	if (verifactuChainLockHeld()) {
		// Already owned by this request. Taking it twice would require two
		// releases and could leave the lock behind.
		return true;
	}

	if ($timeoutSeconds === null) {
		$timeoutSeconds = (int) ($conf->global->VERIFACTU_CHAIN_LOCK_TIMEOUT ?? 30);
	}
	$timeoutSeconds = max(1, (int) $timeoutSeconds);
	$lockName = getVerifactuChainLockName();

	if ($db->type === 'mysqli') {
		$resql = $db->query("SELECT GET_LOCK('" . $db->escape($lockName) . "', " . $timeoutSeconds . ") as obtained");
		if (!$resql) {
			dol_syslog("VERIFACTU: could not request the chain lock: " . $db->lasterror(), LOG_ERR);
			return false;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		verifactuChainLockHeld(!empty($obj) && (int) $obj->obtained === 1);
	} elseif ($db->type === 'pgsql') {
		$key = getVerifactuChainLockKey($lockName);
		$deadline = microtime(true) + $timeoutSeconds;
		do {
			$resql = $db->query("SELECT pg_try_advisory_lock(1007, " . ((int) $key) . ") as obtained");
			if (!$resql) {
				dol_syslog("VERIFACTU: could not request the chain lock: " . $db->lasterror(), LOG_ERR);
				return false;
			}
			$obj = $db->fetch_object($resql);
			$db->free($resql);
			verifactuChainLockHeld(!empty($obj) && in_array($obj->obtained, array(true, 't', '1', 1), true));
			if (verifactuChainLockHeld()) {
				break;
			}
			usleep(200000);
		} while (microtime(true) < $deadline);
	} else {
		// SQLite and any other driver serialize writes on their own and offer
		// no application lock, so the caller continues without one.
		dol_syslog("VERIFACTU: driver " . $db->type . " has no application lock, the chain is not serialized", LOG_WARNING);
		return true;
	}

	if (!verifactuChainLockHeld()) {
		dol_syslog("VERIFACTU: chain lock " . $lockName . " still busy after " . $timeoutSeconds . "s", LOG_WARNING);
	}

	return verifactuChainLockHeld();
}

/**
 * Releases the chain lock taken by acquireVerifactuChainLock().
 *
 * @return void
 */
function releaseVerifactuChainLock()
{
	global $db;

	if (!verifactuChainLockHeld()) {
		return;
	}

	$lockName = getVerifactuChainLockName();

	if ($db->type === 'mysqli') {
		$resql = $db->query("SELECT RELEASE_LOCK('" . $db->escape($lockName) . "')");
	} elseif ($db->type === 'pgsql') {
		$resql = $db->query("SELECT pg_advisory_unlock(1007, " . ((int) getVerifactuChainLockKey($lockName)) . ")");
	} else {
		$resql = false;
	}

	if ($resql) {
		$db->free($resql);
	}

	verifactuChainLockHeld(false);
}

/**
 * Gets the hash of the last invoice for chaining
 * Simply searches for the last invoice with a verifactu fingerprint that was sent
 *
 * The date is formatted in PHP, not in SQL: DATE_FORMAT() only exists in MySQL
 * and Dolibarr does not translate it for PostgreSQL, so the query used to fail
 * there. A failed query returned no previous record and every invoice was then
 * chained as the first one of the chain, which breaks Art. 13 of Orden
 * HAC/1177/2024. For that reason a read error is now an exception instead of a
 * silent "there is no previous record".
 *
 * @return array|null Array with hash, number and date of the last invoice or null if none exists
 * @throws Exception  When the chain cannot be read
 */
function getLastInvoiceHash()
{
	global $db, $conf, $langs;

	$environment = getEnvironment();
	// Simple query: get the last invoice with verifactu fingerprint
	$sql = "SELECT fe.verifactu_huella as hash, f.ref as invoice_number, f.datef as invoice_date";
	$sql .= " FROM " . MAIN_DB_PREFIX . "facture f";
	$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "facture_extrafields fe ON f.rowid = fe.fk_object";
	// The VeriFactu chain belongs to a single legal entity: a shared invoice
	// from another entity must never enter it.
	$sql .= " WHERE f.entity = " . ((int) $conf->entity);
	$sql .= " AND fe.verifactu_huella IS NOT NULL AND fe.verifactu_huella != '' AND fe.verifactu_entorno = '" . $db->escape($environment) . "'";
	$sql .= " AND f.fk_statut > 0"; // Only validated invoices
	$sql .= " ORDER BY f.rowid DESC";
	$sql .= $db->plimit(1);

	dol_syslog("VERIFACTU: getLastInvoiceHash SQL: " . $sql, LOG_DEBUG);

	$resql = $db->query($sql);
	if (!$resql) {
		// Never fall through to "no previous record": that would chain this
		// invoice as the first one and silently break the chain.
		$error = $db->lasterror();
		dol_syslog("VERIFACTU: could not read the chain to find the previous record: " . $error, LOG_ERR);
		throw new Exception(is_object($langs) ? $langs->trans('VERIFACTU_CHAIN_READ_FAILED') : 'Could not read the VeriFactu chain: ' . $error);
	}

	if ($db->num_rows($resql) > 0) {
		$obj = $db->fetch_object($resql);
		// Same formatting used when the record was submitted, so both sides of
		// the chain carry the identical date string.
		$previousDate = $db->jdate($obj->invoice_date);
		$formattedDate = ($previousDate ? date('d-m-Y', $previousDate) : '');
		$db->free($resql);

		return [
			'hash' => $obj->hash,
			'numero' => $obj->invoice_number,  // Backward compatibility
			'number' => $obj->invoice_number,  // English key
			'fecha' => $formattedDate,          // Backward compatibility
			'date' => $formattedDate            // English key
		];
	}
	$db->free($resql);

	dol_syslog("VERIFACTU: No previous invoice with fingerprint found for chaining", LOG_INFO);
	return null;
}
