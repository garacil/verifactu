<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2025 Alberto SuperAdmin <aluquerivasdev@gmail.com>
 * Copyright (C) 2025 Germán Luis Aracil Boned <garacilb@gmail.com>
 *
 * Based on original code from verifactu module by Alberto SuperAdmin (easysoft.es)
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

use Luracast\Restler\RestException;

dol_include_once('/verifactu/lib/verifactu.lib.php');

/**
 * \file    verifactu/class/api_verifactu.class.php
 * \ingroup verifactu
 * \brief   File for API management of verifactu.
 */

/**
 * API class for verifactu integrity
 *
 * @access public
 */
class VerifactuApi extends DolibarrApi
{
	/**
	 * Constructor
	 *
	 * @url     GET /
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * Get integrity hash of VERIFACTU module
	 * Public endpoint that returns the same information as integrity.php
	 *
	 * @return array Hash integrity information
	 *
	 * @url GET integrity
	 * @access public
	 * @class DolibarrApiAccess {@requires none}
	 */
	public function getIntegrity()
	{
		try {
			dol_include_once('/verifactu/core/modules/modVerifactu.class.php');
			$verifactuModule = new ModVerifactu($this->db);
			// Get VERIFACTU module directory
			$moduleDirectory = dol_buildpath('verifactu');

			// Calculate integrity hash using controlLicencia.lib.php function
			$integrityHash = calculateVerifactuIntegrityChecksums($moduleDirectory);

			if ($integrityHash === false) {
				// Error calculating hash
				throw new RestException(500, 'Could not calculate the integrity hash for VERIFACTU module');
			}

			// Successful response with same structure as integrity.php
			return [
				'status' => 'success',
				'message' => 'Integrity hash calculated successfully',
				'hash' => $integrityHash,
				'module' => 'verifactu',
				'version' => $verifactuModule->version,
				'timestamp' => time(),
				'directory' => 'verifactu',
				'domain' => getVerifactuDomain(),
			];
		} catch (Exception $e) {
			// Error en la ejecución
			throw new RestException(500, 'Error en el cálculo de integridad: ' . $e->getMessage());
		}
	}

	/**
	 * Update verifactu environment to production
	 *
	 * @return array response message
	 *
	 * @url POST toProduction
	 * @access public
	 */
	public function toProduction()
	{
		global $conf;
		try {
			dol_include_once('/core/lib/admin.lib.php');
			$res = dolibarr_set_const($this->db, 'VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT', 1, 'chaine', 0, '', $conf->entity);

			if ($res < 0) {
				throw new RestException(500, 'Error al actualizar el entorno de verifactu: ' . $this->db->error);
			}

			return [
				'status' => 'success',
				'message' => 'Entorno de verifactu actualizado a producción correctamente',
			];
		} catch (Exception $e) {
			// Error en la ejecución
			throw new RestException(500, 'Error en la actualización del entorno: ' . $e->getMessage());
		}
	}
	/**
	 * Update verifactu environment to production
	 *
	 * @return array response message
	 *
	 * @url POST toTest
	 * @access public
	 */
	public function toTest()
	{
		global $conf;
		try {
			dol_include_once('/core/lib/admin.lib.php');
			$res = dolibarr_del_const($this->db, 'VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT', $conf->entity);

			if ($res < 0) {
				throw new RestException(500, 'Error al actualizar el entorno de verifactu: ' . $this->db->error);
			}

			return [
				'status' => 'success',
				'message' => 'Entorno de verifactu actualizado a test correctamente',
			];
		} catch (Exception $e) {
			// Error en la ejecución
			throw new RestException(500, 'Error en la actualización del entorno: ' . $e->getMessage());
		}
	}

}
