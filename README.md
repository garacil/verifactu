# VeriFactu - Module for Dolibarr

Dolibarr module for integration with the VeriFactu system of the Spanish Tax Agency (AEAT).

## Project History

This project originates from the open source **verifactu** code originally developed by **Alberto SuperAdmin (Alberto Luque Rivas)** from **easysoft.es** and distributed under the **GPL v3** license.

### Motivation

The development of this fork arose from the following needs:

1. **VeriFactu invoicing without problematic dependencies**: It was necessary to be able to invoice in compliance with VeriFactu requirements without having to use the RD10072023 module from the same provider, which creates a dependency that forces it to be installed for VeriFactu to accept activation even though there is no real dependency to do so.

2. **Privacy and data control**: It was detected that the original RD10072023 and Verifactu modules (see files rd10072023.md and easysoft_controlLicencia.lib.php.md), send sensitive information to the module provider **without the user's knowledge or explicit consent**. This practice raises serious concerns about the privacy of business and tax data.

3. **Provider independence**: A solution was sought that did not require external licensing systems or remote validations that compromise user autonomy.

### Development

This module was developed from the **open source part** of the purchased module, which was licensed under **GPL v3 (GNU General Public License version 3)**. On this basis:

- The code was reorganized and modularized to improve maintainability
- Dependencies on external licensing systems were removed
- Documentation and comments were translated to English
- A new internal library was created (Sietekas\Verifactu)
- Functionalities that sent data to external servers without consent were removed as the RD10072023 module is no longer needed

### Note on GPL License and Proprietary Code

During the analysis of the original `verifactu_easysoft` code, it was detected that **there are files without license headers** within the project that contain functional code integrated with the rest of the GPL code:

- `lib/functions/funciones.utilidades.php`
- `lib/functions/funciones.certificados.php`
- `lib/functions/funciones.conectorCertificados.php`

**We suspect this could constitute a violation of the GPL license**, since:

1. **GPL v3 does not allow mixing proprietary code with GPL code** in the same project distributed as a unit. Unlike permissive licenses like MIT or BSD, the GPL has a "copyleft" effect that requires all derivative work to maintain the same license.

2. According to GPL v3, section 5: *"You must license the entire work, as a whole, under this License to anyone who comes into possession of a copy."*

3. If the original provider intends these files without headers to be proprietary code while the rest of the project is GPL, they would be **violating the terms of the GPL** they themselves chose for the project.

4. Alternatively, if it is simply an oversight and those files are also GPL (just missing the header), then there is no legal problem, but it is poor documentation practice.

**This fork maintains all code under GPL v3**, respecting the original license and the rights of the original authors, adding the corresponding attribution in all files.

---

## Description

VeriFactu is the AEAT invoice verification system that allows:

- Automatic sending of invoices to AEAT
- Generation of verification QR codes
- Cryptographic chaining of invoices (SHA-256)
- Query of sent invoice status
- Invoice cancellation
- Management of corrective invoices

## Requirements

- Dolibarr 13.0 or higher
- PHP 7.4 or higher
- PHP SOAP extension enabled
- PHP OpenSSL extension enabled
- PHP GD extension enabled (for QR)
- Valid digital certificate (FNMT or equivalent)

## Installation

1. Copy the `verifactu` folder to Dolibarr's `htdocs/custom/` directory
2. Go to **Setup > Modules** in Dolibarr
3. Search for "VeriFactu" in the module list
4. Activate the module

## Configuration

### 1. General Configuration

Go to **Setup > Modules > VeriFactu > Configuration**

- **Environment**: Select Test or Production
- **Issuer NIF**: Company's tax ID number
- **Name/Company Name**: Company name

### 2. Digital Certificate

Go to **Setup > Modules > VeriFactu > Certificates**

1. Upload the certificate in PFX/P12 format
2. Enter the certificate password
3. Verify that the certificate is valid

The certificate must be:
- Legal entity certificate (company)
- Issued by a recognized CA (FNMT, etc.)
- Valid and not revoked

### 3. IT System

Configure the invoicing system data:

- **Developer NIF**: Software developer's tax ID
- **System Name**: Name of the invoicing system
- **System ID**: Unique system identifier
- **Version**: Software version

## Usage

### Sending Invoices

1. Create an invoice in Dolibarr
2. Validate the invoice
3. In the "VeriFactu" tab of the invoice:
   - Click on "Send to AEAT"
   - Verify the response status

### Invoice Query

1. Go to **Invoicing > VeriFactu > AEAT Query**
2. Select the search filters:
   - Imputation period (year/month)
   - Date range
   - Counterparty (customer's NIF)
3. Click "Query"

### Invoice Cancellation

1. Access the sent invoice
2. In the "VeriFactu" tab:
   - Click on "Cancel in AEAT"
   - Select the cancellation type
   - Confirm the operation

### QR Code

The QR code is automatically generated when sending the invoice and is displayed in:
- The invoice view
- The invoice PDF (if configured)
- The POS receipt (if enabled)

## Supported Invoice Types

| Code | Description |
|------|-------------|
| F1 | Full invoice |
| F2 | Simplified invoice |
| F3 | Substitute invoice for simplified ones |
| R1 | Corrective (error based on law) |
| R2 | Corrective (Art. 80.3) |
| R3 | Corrective (Art. 80.4) |
| R4 | Corrective (other) |
| R5 | Corrective in simplified invoice |

## Supported Languages

- Spanish (es_ES)
- Catalan (ca_ES)
- Basque (eu_ES)
- Galician (gl_ES)
- English (en_US)

## Responsible Declaration

The module includes a complete **Responsible Declaration** system in compliance with current Spanish regulations:

- **RD 1007/2023** - VeriFactu Regulation
- **RD 254/2025** - Modifications and deadlines
- **Order HAC/1177/2024** - Technical specifications
- **Art. 29.2.j) Law 58/2003** - General Tax Law

### Configuration File

The configuration is located in `conf/declaracion_responsable.conf.php` and includes:

| Section | Content |
|---------|---------|
| **Producer Data** | NIF, company name, address, contact |
| **System Data** | Name, IdSistemaInformatico, version |
| **Components** | Required software and hardware |
| **Technical Specifications** | Signature type (XAdES), hash algorithm (SHA-256) |
| **Integrity** | Dynamically calculated module hash |
| **Compliance** | Declaration according to Art. 29.2.j) LGT |
| **Subscription** | Date, place and signatory |

### Available Functions

```php
// Get complete configuration with calculated hash
$declaration = obtenerDeclaracionResponsable(true);

// Validate required fields
$errors = validarDeclaracionResponsable();

// Calculate SHA-256 hash of the module
$hash = calcularHashModuloVerifactu();

// Export in JSON format
$json = exportarDeclaracionJSON();
```

### Visualization

The responsible declaration is available at:
- **Menu:** VeriFactu > Responsible Declaration
- **JSON Export:** `/verifactu/views/declaration_json.php`

For more details, see `docs/responsible_declaration.md`.

## Module Structure

```
verifactu/
├── admin/                  # Administration pages
│   ├── setup.php           # General configuration
│   ├── managecertificates.php  # Certificate management
│   └── uploadcertificates.php  # Certificate upload
├── class/                  # PHP classes
│   ├── actions_verifactu.class.php  # Hooks and actions
│   ├── api_verifactu.class.php      # REST API
│   └── verifactu.utils.php          # Utilities
├── conf/                   # Configuration
│   └── declaracion_responsable.conf.php  # Responsible declaration
├── core/
│   ├── modules/            # Module descriptor
│   └── triggers/           # Automatic triggers
│       ├── interface_900_modVerifactu_BillRestrictions.class.php
│       └── interface_999_modVerifactu_VerifactuTriggers.class.php
├── docs/                   # Documentation
│   └── responsible_declaration.md  # Responsible declaration doc
├── lib/
│   ├── newfenix/           # OpenAEAT Billing Library
│   │   ├── src/            # Main classes
│   │   └── vendor/         # Dependencies (QRCode)
│   └── functions/          # Module functions
│       ├── functions.submission.php    # Invoice submission
│       ├── functions.query.php         # AEAT queries
│       ├── functions.cancellation.php  # Cancellation
│       ├── functions.certificates.php  # Certificates
│       ├── functions.qr.php            # QR generation
│       └── functions.response.php      # AEAT responses
├── views/                  # Views and pages
│   ├── list.facture.php    # Invoice list
│   ├── query.facture.php   # AEAT query
│   ├── tabVERIFACTU.facture.php  # VeriFactu tab
│   ├── declaration.php     # Responsible declaration
│   ├── declaration_json.php  # Declaration JSON export
│   ├── faq.php             # Help and FAQ
│   ├── pos.facture.php     # POS receipt
│   └── documentation.php   # Documentation
├── langs/                  # Language files
├── css/                    # CSS styles
├── js/                     # JavaScript
└── README.md               # This file
```

## REST API

The module exposes a REST API for external integration:

```
GET  /api/index.php/verifactu/integrity
```

## Troubleshooting

### SOAP Error 4118

This error indicates a problem with the SOAP message structure. Check:
- Date format (DD-MM-YYYY)
- Issuer and recipient data
- Certificate configuration

### Certificate Error

If the certificate is not recognized:
- Verify that the certificate has not expired
- Check that the password is correct
- Ensure that the certificate is for a legal entity

### Blank Screen

If a blank screen appears when viewing invoices:
- Check PHP logs for errors
- Verify that all PHP extensions are installed

## Module Information

- **Version**: 1.0.2
- **Author**: Germán Luis Aracil Boned
- **Email**: garacilb@gmail.com
- **License**: GPL-3.0-or-later
- **Dedicated to**: My colleague and friend Ildefonso González Rodríguez

## Changelog

### v1.0.2 (2025-12-12)

#### New Features
- **Configurable Responsible Declaration**: Added configuration file `conf/declaracion_responsable.conf.php` that complies with Spanish regulations (RD 1007/2023, Order HAC/1177/2024):
  - Producer data (NIF, address, contact)
  - IT system data (IdSistemaInformatico, version)
  - Technical specifications (XAdES signature, SHA-256 hash)
  - Dynamically calculated module integrity hash
  - Compliance declaration with legal references
- **JSON Export**: New endpoint `/views/declaration_json.php` to export the responsible declaration in JSON format
- **Documentation**: Added `docs/responsible_declaration.md` with complete configuration guide

#### Improvements
- Updated the Responsible Declaration page to use configuration file data
- Automatic validation of required fields with warning messages
- Display of module integrity hash in the declaration

### v1.0.1 (2025-12-06)

#### Bug Fixes
- **Error handling in bulk invoice validation**: Fixed the issue where invoices with VeriFactu errors cancelled the entire batch validation process. Now, when an invoice fails to send to VeriFactu:
  - The invoice remains as draft with PROV reference instead of being validated and then reverted
  - Other invoices in the batch continue processing normally
  - Improved PostgreSQL connection handling to avoid "connection already closed" errors
  - Error messages are displayed correctly to the user
