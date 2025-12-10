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
 * Until 31/12/2025 23:59:59, 'test' environment is used
 * From 01/01/2026 00:00:00, 'production' environment is used
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
		$transitionDate = new DateTime('2026-07-01 00:00:00', $tz);
	} else {
		$transitionDate = new DateTime('2026-01-01 00:00:00', $tz);
	}

	// If current date is before January 1, 2026 (peninsular time), use test
	if ($currentDate < $transitionDate) {
		return 'test';
	}

	// From January 1, 2026 (peninsular time), use production
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

	return [
		'NombreRazon' => $issuerName,
		'NIF' => $issuerNif,
		'NombreSistemaInformatico' => 'Dolibarr Verifactu Module',
		'IdSistemaInformatico' => 'DV',
		'Version' => (defined('DOL_VERSION') ? DOL_VERSION : '1.0.0'),
		'NumeroInstalacion' => $installationNumber,
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
