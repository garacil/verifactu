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
 * \file    verifactu/lib/verifactu.lib.php
 * \ingroup verifactu
 * \brief   Library files with common functions for Verifactu
 *
 * This file acts as the main entry point and loads all function modules
 * organized by specific purpose.
 *
 * Module structure:
 * - functions.compatibility.php   : Backward compatibility with older Dolibarr versions
 * - functions.configuration.php   : Environment and system configuration
 * - functions.admin.php           : Administration and UI functions
 * - functions.certificates.php    : Digital certificate management
 * - functions.hash.php            : Chaining and SHA-256 fingerprints
 * - functions.qr.php              : QR code generation
 * - functions.response.php        : AEAT response processing
 * - functions.submission.php      : Invoice submission (Creation/Modification)
 * - functions.cancellation.php    : Invoice cancellation
 * - functions.query.php           : AEAT queries
 * - functions.utilities.php       : General utilities (XML, connection, etc.)
 */

// =============================================================================
// EXTERNAL DEPENDENCIES
// =============================================================================

// Internal VeriFactu library autoload (newfenix - OpenAEAT Billing)
dol_include_once('/verifactu/lib/newfenix/autoload.php');

// Vendor autoload for dependencies (chillerlan QRCode)
dol_include_once('/verifactu/lib/newfenix/vendor/autoload.php');

// Dolibarr core libraries
dol_include_once('/core/lib/files.lib.php');
dol_include_once('/core/lib/admin.lib.php');

// =============================================================================
// FUNCTION MODULES (ordered by dependencies)
// English file names with backward compatibility aliases
// =============================================================================

// 1. Base modules (no internal dependencies)
dol_include_once('/verifactu/lib/functions/functions.compatibility.php');
dol_include_once('/verifactu/lib/functions/functions.utilities.php');

// 2. Configuration modules
dol_include_once('/verifactu/lib/functions/functions.configuration.php');
dol_include_once('/verifactu/lib/functions/functions.admin.php');

// 3. Certificate modules (depends on configuration)
dol_include_once('/verifactu/lib/functions/functions.certificates.php');

// 4. Processing modules (depend on configuration)
dol_include_once('/verifactu/lib/functions/functions.hash.php');
dol_include_once('/verifactu/lib/functions/functions.qr.php');
dol_include_once('/verifactu/lib/functions/functions.response.php');

// 5. AEAT operation modules (depend on various previous modules)
dol_include_once('/verifactu/lib/functions/functions.submission.php');
dol_include_once('/verifactu/lib/functions/functions.cancellation.php');
dol_include_once('/verifactu/lib/functions/functions.query.php');

// =============================================================================
// USE STATEMENTS FOR LIBRARY CLASSES
// =============================================================================

use Sietekas\Verifactu\VerifactuInvoice;
use Sietekas\Verifactu\VerifactuInvoiceCancel;
use Sietekas\Verifactu\VerifactuManager;
use Sietekas\Verifactu\VerifactuInvoiceQuery;
use Sietekas\Verifactu\QRGenerator;

// =============================================================================
// AVAILABLE FUNCTIONS DOCUMENTATION
// =============================================================================

/*
 * COMPATIBILITY FUNCTIONS (functions.compatibility.php):
 * - getInvoiceTotalHT($invoice)     : Gets total HT compatible with Dolibarr v13+
 * - getInvoiceTotalTVA($invoice)    : Gets total VAT compatible with Dolibarr v13+
 *
 * CONFIGURATION FUNCTIONS (functions.configuration.php):
 * - getEnvironment()                          : Gets environment (test/production)
 * - getVerifactuDomain()                      : Gets installation domain
 * - calculateVerifactuIntegrityChecksums($dir): Calculates integrity checksums
 * - getSystemConfig()                         : Gets system configuration for AEAT
 * - getVerifactuParams($facture)              : Gets VeriFactu parameters for invoice
 *
 * ADMINISTRATION FUNCTIONS (functions.admin.php):
 * - verifactuAdminPrepareHead()     : Prepares admin panel tabs
 *
 * CERTIFICATE FUNCTIONS (functions.certificates.php):
 * - getCertificateOptions()                   : Gets configured certificate options
 * - prepareCertificateForVerifactu()          : Prepares certificate for use
 * - prepareCertificateLocalForVerifactu()     : Prepares local certificate
 * - validateCertificateAndKey($path, $pass)   : Validates certificate and private key
 * - processPrivateKeyFormat($content)         : Processes private key format
 * - prepareLocalCertificate(...)              : Converts PFX/P12 to PEM
 * - extractPrivateKeyWithOpenSSL(...)         : Extracts private key with external OpenSSL
 * - extractPublicCertWithOpenSSL(...)         : Extracts public certificate with external OpenSSL
 * - generateCertificateDisplayInfo(...)       : Generates HTML with certificate info
 *
 * HASH/CHAINING FUNCTIONS (functions.hash.php):
 * - getLastInvoiceHash()            : Gets last invoice hash for chaining
 *
 * QR FUNCTIONS (functions.qr.php):
 * - getQrImage($facture, $size, $margin)      : Generates QR image as HTML img
 * - getQrBase64($facture, $size, $margin)     : Generates QR in base64
 * - getQRUrl($facture)                        : Generates AEAT verification URL
 * - isInvoiceSentToVerifactu($facture)        : Checks if invoice was sent
 *
 * RESPONSE FUNCTIONS (functions.response.php):
 * - saveVerifactuErrorData($facture, $data)   : Saves errors with independent connection
 * - buildVerifactuStatusBadge($status, $class): Generates status HTML badge
 * - extractAEATResponseData($response)        : Extracts AEAT response data
 * - buildVerifactuOutputSummary($response)    : Builds output summary
 *
 * SUBMISSION FUNCTIONS (functions.submission.php):
 * - execVERIFACTUCall($facture, $action)      : Main submission function
 * - handleInvoiceCreationOrCorrection(...)    : Handles Creation/Correction
 * - processInvoiceSendResponse(...)           : Processes submission response
 *
 * CANCELLATION FUNCTIONS (functions.cancellation.php):
 * - handleInvoiceCancellation(...)            : Handles invoice cancellation
 * - createCancellationByType(...)             : Creates cancellation object by type
 * - processCancellationResponse(...)          : Processes cancellation response
 * - processCancellationSuccess(...)           : Processes successful response
 * - processCancellationError(...)             : Processes error response
 *
 * QUERY FUNCTIONS (functions.query.php):
 * - execVERIFACTUQuery($filter)               : Executes AEAT query
 * - buildQueryFromFilter($filter, ...)        : Builds query object
 * - buildQueryForSpecificInvoice(...)         : Builds query for specific invoice
 * - applyPostFilters($response, $filter)      : Applies post-query filters
 * - applyInvoiceIdFilter($response, $filter)  : Applies IDFactura filter
 * - applyCounterpartyFilter($response, $filter): Applies Counterparty filter
 *
 * UTILITY FUNCTIONS (functions.utilities.php):
 * - prettyXML($xmlStr)              : Formats XML for reading
 * - checkInternetConnection($timeout): Checks Internet connection
 * - checkHttpConnection($url, $timeout): Checks HTTP connection
 * - checkDnsResolution($domain, $timeout): Checks DNS resolution
 * - checkPingConnection($host, $timeout): Checks ping connection
 * - isWindowsWithoutPing()          : Detects Windows without ping available
 */
