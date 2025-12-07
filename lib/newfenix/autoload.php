<?php

/**
 * OpenAEAT Billing Library - Autoloader
 *
 * This file provides autoloading functionality for the OpenAEAT Billing library
 * and backward compatibility with the legacy Sietekas\Verifactu namespace.
 *
 * The autoloader:
 * - Loads all source classes via require_once to ensure immediate availability
 * - Creates class aliases for backward compatibility with legacy code
 * - Registers a prepending autoloader that blocks loading of old class files
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

// =============================================================================
// EAGER LOADING - Load all source files first to ensure classes are available
// before any autoloading mechanisms try to load them
// =============================================================================

require_once __DIR__ . '/src/BillingException.php';
require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/SoapClient.php';
require_once __DIR__ . '/src/Invoice.php';
require_once __DIR__ . '/src/Cancellation.php';
require_once __DIR__ . '/src/Query.php';
require_once __DIR__ . '/src/Manager.php';
require_once __DIR__ . '/src/QRGenerator.php';

// =============================================================================
// BACKWARD COMPATIBILITY - Class aliases for legacy Sietekas\Verifactu namespace
// These aliases allow existing code using the old namespace to work seamlessly
// =============================================================================

class_alias('OpenAEAT\\Billing\\Invoice', 'Sietekas\\Verifactu\\VerifactuInvoice');
class_alias('OpenAEAT\\Billing\\Cancellation', 'Sietekas\\Verifactu\\VerifactuInvoiceCancel');
class_alias('OpenAEAT\\Billing\\Query', 'Sietekas\\Verifactu\\VerifactuInvoiceQuery');
class_alias('OpenAEAT\\Billing\\Manager', 'Sietekas\\Verifactu\\VerifactuManager');
class_alias('OpenAEAT\\Billing\\QRGenerator', 'Sietekas\\Verifactu\\QRGenerator');

// =============================================================================
// PREPENDING AUTOLOADER - Blocks loading of old Sietekas\Verifactu class files
// This prevents Composer's autoloader from loading conflicting class definitions
// =============================================================================

spl_autoload_register(function ($class) {
    // List of legacy classes that are now provided via aliases above
    // These MUST NOT be autoloaded from old file locations
    $blockedClasses = [
        'Sietekas\\Verifactu\\VerifactuInvoice',
        'Sietekas\\Verifactu\\VerifactuInvoiceCancel',
        'Sietekas\\Verifactu\\VerifactuInvoiceQuery',
        'Sietekas\\Verifactu\\VerifactuManager',
        'Sietekas\\Verifactu\\QRGenerator',
    ];

    if (in_array($class, $blockedClasses, true)) {
        // Class is already available via alias - return true to stop autoload chain
        return true;
    }

    // Handle OpenAEAT\Billing namespace for any classes not yet loaded
    $prefix = 'OpenAEAT\\Billing\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Class not in our namespace - let other autoloaders handle it
        return false;
    }

    // Build file path from class name
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
        return true;
    }

    return false;
}, true, true); // prepend=true, throw=true - runs before other autoloaders
