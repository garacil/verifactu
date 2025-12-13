# VeriFactu Responsible Declaration Configuration

## Introduction

This document describes how to configure and use the Responsible Declaration configuration file for the VeriFactu module for Dolibarr.

The Responsible Declaration is a legal requirement established by Spanish regulations for all producers of invoicing IT systems.

## Legal Framework

The configuration file complies with the following regulations:

| Regulation | Description |
|------------|-------------|
| **RD 1007/2023** | VeriFactu Regulation - Requirements for invoicing systems |
| **RD 254/2025** | Modifications and implementation deadlines |
| **Order HAC/1177/2024** | Technical and functional specifications |
| **Art. 29.2.j) Law 58/2003** | General Tax Law |

## File Location

```
verifactu/
└── conf/
    └── declaracion_responsable.conf.php
```

## File Structure

The configuration file is organized into 8 main sections:

### 1. Producer Data (`$declaracionResponsable['productor']`)

Data of the person or entity that develops the IT system.

```php
$declaracionResponsable['productor'] = array(
    'razon_social' => 'Producer name',
    'nif' => 'Spanish NIF',
    'nif_extranjero' => array(
        'tipo_identificacion' => '',
        'numero' => '',
        'pais_emision' => '',
    ),
    'direccion' => array(
        'tipo_via' => 'Street',
        'nombre_via' => '',
        'numero' => '',
        'codigo_postal' => '',
        'localidad' => '',
        'provincia' => '',
        'pais' => 'Spain',
    ),
    'contacto' => array(
        'email' => '',
        'telefono' => '',
        'web' => '',
    ),
);
```

**Required fields:**
- `razon_social`: Full name or company name
- `nif` or `nif_extranjero`: Tax identification
- `direccion.localidad`: Producer's locality

### 2. System Data (`$declaracionResponsable['sistema']`)

Information about the invoicing IT system.

```php
$declaracionResponsable['sistema'] = array(
    'nombre' => 'VeriFactu for Dolibarr ERP/CRM',
    'id_sistema_informatico' => 'VERIFACTU-DOLIBARR-OSS',
    'version' => '1.0.2',
    'fecha_version' => '2025-07-16',
    'tipo_licencia' => 'GPL-3.0-or-later',
);
```

**Required fields:**
- `nombre`: Commercial name of the system
- `id_sistema_informatico`: Unique identifier code (max. 30 characters)
- `version`: System version

### 3. Components (`$declaracionResponsable['componentes']`)

Description of the hardware and software that make up the system.

```php
$declaracionResponsable['componentes'] = array(
    'software' => array(
        array(
            'nombre' => 'Dolibarr ERP/CRM',
            'descripcion' => 'Open source ERP/CRM system',
            'version_minima' => '10.0',
            'tipo' => 'plataforma_base',
        ),
        // ... more components
    ),
    'hardware' => array(
        'arquitectura' => 'x86_64 / ARM64',
        'memoria_minima' => '512 MB',
    ),
    'funcionalidades' => array(
        'generacion_registros' => true,
        'encadenamiento_hash' => true,
        'firma_electronica' => true,
        // ... more features
    ),
);
```

### 4. Technical Specifications (`$declaracionResponsable['especificaciones_tecnicas']`)

VeriFactu system technical configuration.

```php
$declaracionResponsable['especificaciones_tecnicas'] = array(
    'modalidad_funcionamiento' => 'verifactu', // 'verifactu' or 'no_verifactu'
    'exclusivo_verifactu' => false,
    'multiusuario' => true,
    'multiempresa' => true,
    'tipo_firma' => array(
        'formato' => 'XAdES',
        'tipo' => 'Enveloped',
        'algoritmo_firma' => 'RSA-SHA256',
        'algoritmo_hash' => 'SHA-256',
    ),
    'algoritmo_huella' => 'SHA-256',
);
```

### 5. Integrity (`$declaracionResponsable['integridad']`)

Configuration for module integrity hash calculation.

```php
$declaracionResponsable['integridad'] = array(
    'algoritmo' => 'SHA-256',
    'hash_modulo' => '',  // Calculated automatically
    'fecha_calculo' => '',
    'ficheros_verificados' => array(
        'core/modules/modVerifactu.class.php',
        'class/verifactu.class.php',
        // ... critical files
    ),
);
```

### 6. Compliance (`$declaracionResponsable['cumplimiento']`)

Regulatory compliance declaration.

```php
$declaracionResponsable['cumplimiento'] = array(
    'declaracion' => 'Responsible declaration text...',
    'requisitos_cumplidos' => array(
        'integridad' => true,
        'conservacion' => true,
        'accesibilidad' => true,
        'legibilidad' => true,
        'trazabilidad' => true,
        'inalterabilidad' => true,
    ),
    'referencia_legal' => array(
        'ley' => 'Law 58/2003',
        'real_decreto' => 'Royal Decree 1007/2023',
        'orden_ministerial' => 'Order HAC/1177/2024',
    ),
);
```

### 7. Subscription (`$declaracionResponsable['suscripcion']`)

Date and place of the declaration.

```php
$declaracionResponsable['suscripcion'] = array(
    'fecha' => '2025-12-12',  // Format YYYY-MM-DD
    'lugar' => array(
        'localidad' => 'Madrid',
        'provincia' => 'Madrid',
        'pais' => 'Spain',
    ),
    'firmante' => array(
        'nombre' => 'Signatory name',
        'cargo' => 'Developer / Producer',
    ),
);
```

### 8. Metadata (`$declaracionResponsable['metadata']`)

Information about the configuration file.

```php
$declaracionResponsable['metadata'] = array(
    'version_config' => '1.0.0',
    'fecha_creacion' => '2025-12-12',
    'ultima_modificacion' => '2025-12-12',
    'autor' => 'Author name',
);
```

## Using the File

### Loading the Configuration

```php
// Include the configuration file
$confFile = dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);
require_once $confFile;

// Get the complete configuration (calculates hash automatically)
$declaration = obtenerDeclaracionResponsable(true);

// Validate the configuration
$errors = validarDeclaracionResponsable();
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo "Error: " . $error . "\n";
    }
}
```

### Available Functions

| Function | Description |
|----------|-------------|
| `obtenerDeclaracionResponsable($calcularHash)` | Gets the complete configuration. If `$calcularHash` is true, recalculates the integrity hash. |
| `validarDeclaracionResponsable()` | Validates that required fields are complete. Returns array of errors. |
| `calcularHashModuloVerifactu($basePath)` | Calculates the SHA-256 hash of the module's critical files. |
| `generarIdInstalacion()` | Generates a unique identifier for the installation. |
| `exportarDeclaracionJSON()` | Exports the declaration in JSON format. |

### Example: Get Module Hash

```php
require_once dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);

$hash = calcularHashModuloVerifactu();
echo "Module hash: " . $hash;
```

### Example: Export to JSON

```php
require_once dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);

$json = exportarDeclaracionJSON();
file_put_contents('/tmp/declaration.json', $json);
```

## Initial Configuration

To configure the responsible declaration, follow these steps:

1. **Edit the configuration file:**
   ```bash
   nano conf/declaracion_responsable.conf.php
   ```

2. **Complete the required data:**
   - Producer's company name
   - NIF or tax identification
   - Complete address
   - Subscription locality

3. **Verify the configuration:**
   Access the Responsible Declaration page in the VeriFactu menu to see the configured data and any validation errors.

4. **Export the declaration:**
   Use the "Export JSON" button to get a copy of the declaration in JSON format.

## Visualization

The responsible declaration can be viewed at:

**Menu:** VeriFactu > Responsible Declaration

The page shows:
- Producer data
- IT system data
- Technical specifications
- Module integrity hash
- Fulfilled requirements
- Subscription data

## Export

### Available Formats

| Format | Description | Use |
|--------|-------------|-----|
| **Print** | Printable version of the declaration | Physical documentation |
| **JSON** | Structured export | Integration with other systems |

### JSON Endpoint

```
GET /verifactu/views/declaration_json.php
```

Requires authentication and VeriFactu management permissions.

## Integrity Hash

The integrity hash is calculated on the following critical files:

- `core/modules/modVerifactu.class.php`
- `class/verifactu.class.php`
- `class/verifactu.utils.php`
- `lib/verifactu.lib.php`
- `lib/verifactu-types.array.php`
- `core/triggers/interface_99_modVerifactu_VerifactuTriggers.class.php`

The hash is recalculated every time the responsible declaration page is accessed, ensuring that any modification to critical files is detected.

## Frequently Asked Questions

### Who should complete this configuration?

The producer or developer of the IT system. If you are an end user and use this module without modifications, the original producer's data (repository maintainer) applies.

### What happens if I don't complete the NIF?

The responsible declaration page will show a warning indicating that the configuration is incomplete. However, the module will continue to work.

### How is the integrity hash calculated?

The content of all critical files is concatenated and the SHA-256 hash of the result is calculated.

### Can I add more files to the hash calculation?

Yes, you can add files to the `ficheros_verificados` array in the integrity section.

### Should I update the subscription date?

The subscription date should be updated when significant changes are made to the system or when a new version is released.

## References

- [BOE - Order HAC/1177/2024](https://www.boe.es/diario_boe/txt.php?id=BOE-A-2024-22138)
- [AEAT - VeriFactu Systems](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/)
- [AEAT FAQ - Responsible Declaration](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/certificacion-sistemas-informaticos-declaracion-responsable.html)

---

*Last updated: 2025-12-12*
