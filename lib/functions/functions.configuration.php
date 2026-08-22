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
 * \file    verifactu/lib/functions/functions.configuration.php
 * \ingroup verifactu
 * \brief   VeriFactu environment and system configuration functions
 */

/**
 * Gets the VeriFactu environment based on current date and configuration
 *
 * Until 31/12/2026 23:59:59, 'test' environment is used
 * From 01/01/2027 00:00:00, 'production' environment is used
 * If VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT is enabled, always uses 'production'
 *
 * @return string 'test' or 'production'
 */
function getEnvironment()
{
	global $conf;

	// If production environment is forced, use production
	if (!empty($conf->global->VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT)) {
		return 'production';
	}

	// Official Spanish peninsular timezone
	$tz = new DateTimeZone('Europe/Madrid');

	// Current date and time in peninsular time
	$currentDate = new DateTime('now', $tz);

	// Transition date to production (peninsular time)
	if ($conf->global->VERIFACTU_COMPANY_TYPE === 'autonomo') {
		$transitionDate = new DateTime('2027-07-01 00:00:00', $tz);
	} else {
		$transitionDate = new DateTime('2027-01-01 00:00:00', $tz);
	}

	// If current date is before January 1, 2027 (peninsular time), use test
	if ($currentDate < $transitionDate) {
		return 'test';
	}

	// From January 1, 2027 (peninsular time), use production
	return 'production';
}

/**
 * Gets the current domain to identify the installation
 *
 * @return string Server domain
 */
function getVerifactuDomain()
{
	if (!empty($_SERVER['HTTP_HOST'])) {
		return $_SERVER['HTTP_HOST'];
	}
	if (!empty($_SERVER['SERVER_NAME'])) {
		return $_SERVER['SERVER_NAME'];
	}
	return 'localhost';
}

/**
 * Calculates integrity checksums for the module
 *
 * @param string $moduleDirectory Module directory path
 * @return string|false Integrity hash or false on error
 */
function calculateVerifactuIntegrityChecksums($moduleDirectory)
{
	if (!is_dir($moduleDirectory)) {
		return false;
	}

	$files = [];
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($moduleDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
		RecursiveIteratorIterator::LEAVES_ONLY
	);

	foreach ($iterator as $file) {
		if ($file->isFile() && $file->getExtension() === 'php') {
			$relativePath = str_replace($moduleDirectory . '/', '', $file->getPathname());
			$files[$relativePath] = md5_file($file->getPathname());
		}
	}

	ksort($files);
	return hash('sha256', json_encode($files));
}

/**
 * Gets the identity of this billing system as declared in the responsible declaration
 *
 * Art. 15.2 of Orden HAC/1177/2024 requires the software identity declared in the
 * "declaracion responsable" to be exactly the one transmitted to AEAT in every
 * billing record. Reading both from the same source removes any chance of drift.
 *
 * The values must fit the official AEAT schema (SuministroInformacion.xsd,
 * SistemaInformatico block):
 *   - NombreSistemaInformatico : sf:TextMax30Type  (max 30 chars)
 *   - IdSistemaInformatico     : sf:TextMax2Type   (max  2 chars)
 *   - Version                  : sf:TextMax50Type  (max 50 chars)
 *
 * @return array{name: string, id: string, version: string} Declared system identity
 */
function getDeclaredSystemIdentity()
{
	// Defaults kept in sync with conf/declaracion_responsable.conf.php so the
	// function stays usable even if the declaration file cannot be loaded.
	$identity = [
		'name' => 'Dolibarr Verifactu Module',
		'id' => 'DV',
		'version' => '1.0.5',
	];

	// Bind the global before including: the declaration file assigns
	// $declaracionResponsable at file scope, which inside a function would
	// otherwise land in the local scope and be lost.
	global $declaracionResponsable;

	$declarationFile = dirname(__DIR__, 2) . '/conf/declaracion_responsable.conf.php';
	if (is_readable($declarationFile)) {
		require_once $declarationFile;

		if (!empty($declaracionResponsable['sistema'])) {
			$system = $declaracionResponsable['sistema'];

			if (!empty($system['nombre_sistema_informatico'])) {
				$identity['name'] = $system['nombre_sistema_informatico'];
			}
			if (!empty($system['id_sistema_informatico'])) {
				$identity['id'] = $system['id_sistema_informatico'];
			}
			if (!empty($system['version'])) {
				$identity['version'] = $system['version'];
			}
		}
	}

	// Enforce the AEAT schema limits: an oversized value is rejected by the
	// webservice, so truncate rather than let the submission fail.
	$identity['name'] = substr($identity['name'], 0, 30);
	$identity['id'] = substr($identity['id'], 0, 2);
	$identity['version'] = substr($identity['version'], 0, 50);

	return $identity;
}

/**
 * Gets the billing system configuration for AEAT
 *
 * @return array System configuration array
 */
function getSystemConfig()
{
	global $conf, $dolibarr_main_instance_unique_id;

	$issuerName = $conf->global->VERIFACTU_HOLDER_COMPANY_NAME ?? '';
	$issuerNif = $conf->global->VERIFACTU_HOLDER_NIF ?? '';
	$installationNumber = $dolibarr_main_instance_unique_id . '_' . $conf->entity;

	$identity = getDeclaredSystemIdentity();

	return [
		'NombreRazon' => $issuerName,
		'NIF' => $issuerNif,
		'NombreSistemaInformatico' => $identity['name'],
		'IdSistemaInformatico' => $identity['id'],
		// The module version, not DOL_VERSION: Art. 15.2.c) refers to the version
		// of the billing system being declared, which is this module.
		'Version' => $identity['version'],
		'NumeroInstalacion' => substr($installationNumber, 0, 100),
		'TipoUsoPosibleSoloVerifactu' => 'S',
		'TipoUsoPosibleMultiOT' => 'N',
		'IndicadorMultiplesOT' => 'N',
	];
}

/**
 * Gets VeriFactu parameters from the invoice using ONLY manually configured values
 *
 * Automatic inference was removed to avoid legal/fiscal issues.
 * Users must configure these values consulting with their tax advisor.
 *
 * @param Facture $facture The invoice to get parameters from
 * @return array Array with the 4 necessary VeriFactu parameters
 */
function getVerifactuParams(Facture $facture)
{
	return [
		// Tax Type: VAT (01), IPSI (02), IGIC (03), Others (05)
		'taxType' => (!empty($facture->array_options['options_verifactu_impuesto']) && $facture->array_options['options_verifactu_impuesto'] != '0')
			? $facture->array_options['options_verifactu_impuesto']
			: '',

		// Regime Key: Only for VAT (01) and IGIC (03), empty for IPSI (02) and Others (05)
		'regimeKey' => (!empty($facture->array_options['options_verifactu_clave_regimen']) && $facture->array_options['options_verifactu_clave_regimen'] != '0')
			? $facture->array_options['options_verifactu_clave_regimen']
			: '',

		// Operation Qualification: S1, S2, N1, N2
		'operationQualification' => (!empty($facture->array_options['options_verifactu_calificacion_operacion']) && $facture->array_options['options_verifactu_calificacion_operacion'] != '0')
			? $facture->array_options['options_verifactu_calificacion_operacion']
			: null,

		// Exempt Operation: E1, E2, E3, E4, E5, E6 or null if not exempt
		'exemptOperation' => (!empty($facture->array_options['options_verifactu_operacion_exenta']) && $facture->array_options['options_verifactu_operacion_exenta'] != '0')
			? $facture->array_options['options_verifactu_operacion_exenta']
			: null,

		// BACKWARD COMPATIBILITY: Keep old Spanish key names for existing code
		'tipoImpuesto' => (!empty($facture->array_options['options_verifactu_impuesto']) && $facture->array_options['options_verifactu_impuesto'] != '0')
			? $facture->array_options['options_verifactu_impuesto']
			: '',
		'claveRegimen' => (!empty($facture->array_options['options_verifactu_clave_regimen']) && $facture->array_options['options_verifactu_clave_regimen'] != '0')
			? $facture->array_options['options_verifactu_clave_regimen']
			: '',
		'calificacionOperacion' => (!empty($facture->array_options['options_verifactu_calificacion_operacion']) && $facture->array_options['options_verifactu_calificacion_operacion'] != '0')
			? $facture->array_options['options_verifactu_calificacion_operacion']
			: null,
		'operacionExenta' => (!empty($facture->array_options['options_verifactu_operacion_exenta']) && $facture->array_options['options_verifactu_operacion_exenta'] != '0')
			? $facture->array_options['options_verifactu_operacion_exenta']
			: null
	];
}
