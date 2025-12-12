# Configuración de la Declaración Responsable VeriFactu

## Introducción

Este documento describe cómo configurar y utilizar el fichero de configuración de la Declaración Responsable para el módulo VeriFactu de Dolibarr.

La Declaración Responsable es un requisito legal establecido por la normativa española para todos los productores de sistemas informáticos de facturación.

## Marco Legal

El fichero de configuración cumple con la siguiente normativa:

| Normativa | Descripción |
|-----------|-------------|
| **RD 1007/2023** | Reglamento VeriFactu - Requisitos para sistemas de facturación |
| **RD 254/2025** | Modificaciones y plazos de implementación |
| **Orden HAC/1177/2024** | Especificaciones técnicas y funcionales |
| **Art. 29.2.j) Ley 58/2003** | Ley General Tributaria |

## Ubicación del Fichero

```
verifactu/
└── conf/
    └── declaracion_responsable.conf.php
```

## Estructura del Fichero

El fichero de configuración está organizado en 8 secciones principales:

### 1. Datos del Productor (`$declaracionResponsable['productor']`)

Datos de la persona o entidad que desarrolla el sistema informático.

```php
$declaracionResponsable['productor'] = array(
    'razon_social' => 'Nombre del productor',
    'nif' => 'NIF español',
    'nif_extranjero' => array(
        'tipo_identificacion' => '',
        'numero' => '',
        'pais_emision' => '',
    ),
    'direccion' => array(
        'tipo_via' => 'Calle',
        'nombre_via' => '',
        'numero' => '',
        'codigo_postal' => '',
        'localidad' => '',
        'provincia' => '',
        'pais' => 'España',
    ),
    'contacto' => array(
        'email' => '',
        'telefono' => '',
        'web' => '',
    ),
);
```

**Campos obligatorios:**
- `razon_social`: Nombre completo o razón social
- `nif` o `nif_extranjero`: Identificación fiscal
- `direccion.localidad`: Localidad del productor

### 2. Datos del Sistema (`$declaracionResponsable['sistema']`)

Información del sistema informático de facturación.

```php
$declaracionResponsable['sistema'] = array(
    'nombre' => 'VeriFactu para Dolibarr ERP/CRM',
    'id_sistema_informatico' => 'VERIFACTU-DOLIBARR-OSS',
    'version' => '1.0.2',
    'fecha_version' => '2025-07-16',
    'tipo_licencia' => 'GPL-3.0-or-later',
);
```

**Campos obligatorios:**
- `nombre`: Nombre comercial del sistema
- `id_sistema_informatico`: Código único identificador (máx. 30 caracteres)
- `version`: Versión del sistema

### 3. Componentes (`$declaracionResponsable['componentes']`)

Descripción del hardware y software que compone el sistema.

```php
$declaracionResponsable['componentes'] = array(
    'software' => array(
        array(
            'nombre' => 'Dolibarr ERP/CRM',
            'descripcion' => 'Sistema ERP/CRM de código abierto',
            'version_minima' => '10.0',
            'tipo' => 'plataforma_base',
        ),
        // ... más componentes
    ),
    'hardware' => array(
        'arquitectura' => 'x86_64 / ARM64',
        'memoria_minima' => '512 MB',
    ),
    'funcionalidades' => array(
        'generacion_registros' => true,
        'encadenamiento_hash' => true,
        'firma_electronica' => true,
        // ... más funcionalidades
    ),
);
```

### 4. Especificaciones Técnicas (`$declaracionResponsable['especificaciones_tecnicas']`)

Configuración técnica del sistema VeriFactu.

```php
$declaracionResponsable['especificaciones_tecnicas'] = array(
    'modalidad_funcionamiento' => 'verifactu', // 'verifactu' o 'no_verifactu'
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

### 5. Integridad (`$declaracionResponsable['integridad']`)

Configuración para el cálculo del hash de integridad del módulo.

```php
$declaracionResponsable['integridad'] = array(
    'algoritmo' => 'SHA-256',
    'hash_modulo' => '',  // Se calcula automáticamente
    'fecha_calculo' => '',
    'ficheros_verificados' => array(
        'core/modules/modVerifactu.class.php',
        'class/verifactu.class.php',
        // ... ficheros críticos
    ),
);
```

### 6. Cumplimiento (`$declaracionResponsable['cumplimiento']`)

Declaración de cumplimiento normativo.

```php
$declaracionResponsable['cumplimiento'] = array(
    'declaracion' => 'Texto de la declaración responsable...',
    'requisitos_cumplidos' => array(
        'integridad' => true,
        'conservacion' => true,
        'accesibilidad' => true,
        'legibilidad' => true,
        'trazabilidad' => true,
        'inalterabilidad' => true,
    ),
    'referencia_legal' => array(
        'ley' => 'Ley 58/2003',
        'real_decreto' => 'Real Decreto 1007/2023',
        'orden_ministerial' => 'Orden HAC/1177/2024',
    ),
);
```

### 7. Suscripción (`$declaracionResponsable['suscripcion']`)

Fecha y lugar de la declaración.

```php
$declaracionResponsable['suscripcion'] = array(
    'fecha' => '2025-12-12',  // Formato YYYY-MM-DD
    'lugar' => array(
        'localidad' => 'Madrid',
        'provincia' => 'Madrid',
        'pais' => 'España',
    ),
    'firmante' => array(
        'nombre' => 'Nombre del firmante',
        'cargo' => 'Desarrollador / Productor',
    ),
);
```

### 8. Metadatos (`$declaracionResponsable['metadata']`)

Información sobre el fichero de configuración.

```php
$declaracionResponsable['metadata'] = array(
    'version_config' => '1.0.0',
    'fecha_creacion' => '2025-12-12',
    'ultima_modificacion' => '2025-12-12',
    'autor' => 'Nombre del autor',
);
```

## Uso del Fichero

### Cargar la Configuración

```php
// Incluir el fichero de configuración
$confFile = dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);
require_once $confFile;

// Obtener la configuración completa (calcula el hash automáticamente)
$declaracion = obtenerDeclaracionResponsable(true);

// Validar la configuración
$errores = validarDeclaracionResponsable();
if (!empty($errores)) {
    foreach ($errores as $error) {
        echo "Error: " . $error . "\n";
    }
}
```

### Funciones Disponibles

| Función | Descripción |
|---------|-------------|
| `obtenerDeclaracionResponsable($calcularHash)` | Obtiene la configuración completa. Si `$calcularHash` es true, recalcula el hash de integridad. |
| `validarDeclaracionResponsable()` | Valida que los campos obligatorios estén completos. Devuelve array de errores. |
| `calcularHashModuloVerifactu($basePath)` | Calcula el hash SHA-256 de los ficheros críticos del módulo. |
| `generarIdInstalacion()` | Genera un identificador único para la instalación. |
| `exportarDeclaracionJSON()` | Exporta la declaración en formato JSON. |

### Ejemplo: Obtener Hash del Módulo

```php
require_once dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);

$hash = calcularHashModuloVerifactu();
echo "Hash del módulo: " . $hash;
```

### Ejemplo: Exportar a JSON

```php
require_once dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);

$json = exportarDeclaracionJSON();
file_put_contents('/tmp/declaracion.json', $json);
```

## Configuración Inicial

Para configurar la declaración responsable, siga estos pasos:

1. **Editar el fichero de configuración:**
   ```bash
   nano conf/declaracion_responsable.conf.php
   ```

2. **Completar los datos obligatorios:**
   - Razón social del productor
   - NIF o identificación fiscal
   - Dirección completa
   - Localidad de suscripción

3. **Verificar la configuración:**
   Acceda a la página de Declaración Responsable en el menú de VeriFactu para ver los datos configurados y cualquier error de validación.

4. **Exportar la declaración:**
   Use el botón "Exportar JSON" para obtener una copia de la declaración en formato JSON.

## Visualización

La declaración responsable se puede visualizar en:

**Menú:** VeriFactu > Declaración Responsable

La página muestra:
- Datos del productor
- Datos del sistema informático
- Especificaciones técnicas
- Hash de integridad del módulo
- Requisitos cumplidos
- Datos de suscripción

## Exportación

### Formatos Disponibles

| Formato | Descripción | Uso |
|---------|-------------|-----|
| **Imprimir** | Versión imprimible de la declaración | Documentación física |
| **JSON** | Exportación estructurada | Integración con otros sistemas |

### Endpoint JSON

```
GET /verifactu/views/declaration_json.php
```

Requiere autenticación y permisos de gestión de VeriFactu.

## Hash de Integridad

El hash de integridad se calcula sobre los siguientes ficheros críticos:

- `core/modules/modVerifactu.class.php`
- `class/verifactu.class.php`
- `class/verifactu.utils.php`
- `lib/verifactu.lib.php`
- `lib/verifactu-types.array.php`
- `core/triggers/interface_99_modVerifactu_VerifactuTriggers.class.php`

El hash se recalcula cada vez que se accede a la página de declaración responsable, garantizando que cualquier modificación en los ficheros críticos sea detectada.

## Preguntas Frecuentes

### ¿Quién debe completar esta configuración?

El productor o desarrollador del sistema informático. Si usted es usuario final y utiliza este módulo sin modificaciones, los datos del productor original (mantenedor del repositorio) son los que aplican.

### ¿Qué pasa si no completo el NIF?

La página de declaración responsable mostrará una advertencia indicando que la configuración está incompleta. Sin embargo, el módulo seguirá funcionando.

### ¿Cómo se calcula el hash de integridad?

Se concatena el contenido de todos los ficheros críticos y se calcula el hash SHA-256 del resultado.

### ¿Puedo añadir más ficheros al cálculo del hash?

Sí, puede añadir ficheros al array `ficheros_verificados` en la sección de integridad.

### ¿Debo actualizar la fecha de suscripción?

La fecha de suscripción debe actualizarse cuando se realicen cambios significativos en el sistema o cuando se publique una nueva versión.

## Referencias

- [BOE - Orden HAC/1177/2024](https://www.boe.es/diario_boe/txt.php?id=BOE-A-2024-22138)
- [AEAT - Sistemas VeriFactu](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/)
- [FAQ AEAT - Declaración Responsable](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/certificacion-sistemas-informaticos-declaracion-responsable.html)

---

*Última actualización: 2025-12-12*
