# VeriFactu - Módulo para Dolibarr

Módulo de Dolibarr para la integración con el sistema VeriFactu de la Agencia Estatal de Administración Tributaria (AEAT), en cumplimiento de la **Ley 11/2021** (Ley Antifraude) y el **Real Decreto 1007/2023** que establece los requisitos técnicos para los sistemas de facturación en España.

## Historia del Proyecto

Este proyecto tiene su origen en el código abierto **verifactu** desarrollado originalmente por **Alberto SuperAdmin (Alberto Luque Rivas)** de **easysoft.es** y distribuido bajo licencia **GPL v3**.

### Desarrollo

Este módulo fue desarrollado a partir del módulo original licenciado bajo **GPL v3 (GNU General Public License version 3)**. Sobre esta base:

- Se reorganizó y modularizó el código para mejorar la mantenibilidad
- Se tradujeron la documentación y los comentarios al inglés
- Se creó una nueva librería interna (OpenAEAT\Billing)

**Este fork mantiene todo el código bajo GPL v3**, respetando la licencia original y los derechos de los autores originales, añadiendo la atribución correspondiente en todos los archivos.

---

## Descripción

VeriFactu es el sistema de verificación de facturas de la AEAT que permite:

- Envío automático de facturas a la AEAT
- Generación de códigos QR de verificación
- Encadenamiento criptográfico de facturas (SHA-256)
- Consulta del estado de facturas enviadas
- Anulación de facturas
- Gestión de facturas rectificativas

## Requisitos

- Dolibarr 13.0 o superior
- PHP 7.4 o superior
- Extensión PHP SOAP habilitada
- Extensión PHP OpenSSL habilitada
- Extensión PHP GD habilitada (para QR)
- Certificado digital válido (FNMT o equivalente)

## Instalación

1. Copiar la carpeta `verifactu` en el directorio `htdocs/custom/` de Dolibarr
2. Ir a **Configuración > Módulos** en Dolibarr
3. Buscar "VeriFactu" en la lista de módulos
4. Activar el módulo

## Configuración

### 1. Configuración General

Ir a **Configuración > Módulos > VeriFactu > Configuración**

- **Entorno**: Seleccionar Pruebas o Producción
- **NIF Emisor**: Número de identificación fiscal de la empresa
- **Nombre/Razón Social**: Nombre de la empresa

### 2. Certificado Digital

Ir a **Configuración > Módulos > VeriFactu > Certificados**

1. Subir el certificado en formato PFX/P12
2. Introducir la contraseña del certificado
3. Verificar que el certificado es válido

El certificado debe ser:
- Certificado de persona jurídica (empresa)
- Emitido por una CA reconocida (FNMT, etc.)
- Válido y no revocado

### 3. Sistema Informático

Configurar los datos del sistema de facturación:

- **NIF Desarrollador**: NIF del desarrollador del software
- **Nombre del Sistema**: Nombre del sistema de facturación
- **ID del Sistema**: Identificador único del sistema
- **Versión**: Versión del software

### 4. Esquemas WSDL/XSD

El módulo descarga y cachea localmente los esquemas WSDL y XSD necesarios para la comunicación SOAP con la AEAT. Esto evita dependencias de servidores externos (AEAT, W3C) y previene errores de rate-limiting al procesar muchas facturas seguidas.

- Los esquemas se descargan automáticamente la primera vez que se envía una factura
- En **Configuración > Módulos > VeriFactu > Configuración** se muestra el estado de los esquemas
- Usar el botón **Descargar/Actualizar esquemas** para forzar la re-descarga si la AEAT actualiza los esquemas

## Uso

### Envío de Facturas

1. Crear una factura en Dolibarr
2. Validar la factura
3. En la pestaña "VeriFactu" de la factura:
   - Hacer clic en "Enviar a AEAT"
   - Verificar el estado de la respuesta

### Validación Masiva

La validación masiva de facturas procesa cada factura en una transacción independiente:
- Si una factura falla, las demás continúan procesándose normalmente
- Se detectan automáticamente conflictos de fechas con VeriFactu (orden cronológico)
- Si hay conflictos, se muestra un diálogo de confirmación antes de ajustar las fechas
- Las facturas se procesan ordenadas por fecha (de menor a mayor)

### Validación Individual

Al validar una factura individual (PROV), el sistema:
- Ajusta automáticamente la fecha a `max(hoy, última_factura_validada)` para respetar el orden cronológico de VeriFactu
- Muestra un aviso en el diálogo de confirmación si la fecha fue ajustada, indicando la fecha original, la nueva fecha y la referencia de la última factura validada

### Consulta de Facturas

1. Ir a **Facturación > VeriFactu > Consulta AEAT**
2. Seleccionar los filtros de búsqueda:
   - Período de imputación (año/mes)
   - Rango de fechas
   - Contraparte (NIF del cliente)
3. Hacer clic en "Consultar"

### Anulación de Facturas

1. Acceder a la factura enviada
2. En la pestaña "VeriFactu":
   - Hacer clic en "Anular en AEAT"
   - Seleccionar el tipo de anulación
   - Confirmar la operación

### Código QR

El código QR se genera automáticamente al enviar la factura y se muestra en:
- La vista de la factura
- El PDF de la factura (si está configurado)
- El ticket de TPV (si está habilitado)

## Tipos de Factura Soportados

| Código | Descripción |
|--------|-------------|
| F1 | Factura completa |
| F2 | Factura simplificada |
| F3 | Factura sustitutiva de simplificadas |
| R1 | Rectificativa (error fundado en derecho) |
| R2 | Rectificativa (Art. 80.3) |
| R3 | Rectificativa (Art. 80.4) |
| R4 | Rectificativa (otras) |
| R5 | Rectificativa en factura simplificada |

## Idiomas Soportados

- Español (es_ES)
- Catalán (ca_ES)
- Euskera (eu_ES)
- Gallego (gl_ES)
- Inglés (en_US)

## Declaración Responsable

El módulo incluye un sistema completo de **Declaración Responsable** en cumplimiento con la normativa española vigente:

- **RD 1007/2023** - Reglamento VeriFactu
- **RD 254/2025** - Modificaciones y plazos
- **Orden HAC/1177/2024** - Especificaciones técnicas
- **Art. 29.2.j) Ley 58/2003** - Ley General Tributaria

### Archivo de Configuración

La configuración se encuentra en `conf/declaracion_responsable.conf.php` e incluye:

| Sección | Contenido |
|---------|-----------|
| **Datos del Productor** | NIF, razón social, dirección, contacto |
| **Datos del Sistema** | Nombre, IdSistemaInformatico, versión |
| **Componentes** | Software y hardware requeridos |
| **Especificaciones Técnicas** | Tipo de firma (XAdES), algoritmo hash (SHA-256) |
| **Integridad** | Hash del módulo calculado dinámicamente |
| **Cumplimiento** | Declaración según Art. 29.2.j) LGT |
| **Suscripción** | Fecha, lugar y firmante |

### Funciones Disponibles

```php
// Obtener configuración completa con hash calculado
$declaracion = obtenerDeclaracionResponsable(true);

// Validar campos obligatorios
$errores = validarDeclaracionResponsable();

// Calcular hash SHA-256 del módulo
$hash = calcularHashModuloVerifactu();

// Exportar en formato JSON
$json = exportarDeclaracionJSON();
```

### Visualización

La declaración responsable está disponible en:
- **Menú:** VeriFactu > Declaración Responsable
- **Exportación JSON:** `/verifactu/views/declaration_json.php`

Para más detalles, ver `docs/responsible_declaration.md`.

## Estructura del Módulo

```
verifactu/
├── admin/                  # Páginas de administración
│   ├── setup.php           # Configuración general
│   ├── managecertificates.php  # Gestión de certificados
│   └── uploadcertificates.php  # Subida de certificados
├── class/                  # Clases PHP
│   ├── actions_verifactu.class.php  # Hooks y acciones
│   ├── api_verifactu.class.php      # API REST
│   └── verifactu.utils.php          # Utilidades
├── conf/                   # Configuración
│   └── declaracion_responsable.conf.php  # Declaración responsable
├── core/
│   ├── modules/            # Descriptor del módulo
│   └── triggers/           # Triggers automáticos
│       ├── interface_900_modVerifactu_BillRestrictions.class.php
│       └── interface_999_modVerifactu_VerifactuTriggers.class.php
├── docs/                   # Documentación
│   └── responsible_declaration.md  # Doc. declaración responsable
├── lib/
│   ├── newfenix/           # Librería OpenAEAT Billing
│   │   ├── src/            # Clases principales
│   │   │   ├── SchemaManager.php  # Gestión de esquemas WSDL/XSD
│   │   │   ├── SoapClient.php     # Transporte SOAP
│   │   │   ├── Config.php         # Configuración
│   │   │   ├── Manager.php        # Orquestador
│   │   │   ├── Invoice.php        # Modelo de factura
│   │   │   ├── Cancellation.php   # Modelo de anulación
│   │   │   └── Query.php          # Modelo de consulta
│   │   └── vendor/         # Dependencias (QRCode)
│   └── functions/          # Funciones del módulo
│       ├── functions.submission.php    # Envío de facturas
│       ├── functions.query.php         # Consultas AEAT
│       ├── functions.cancellation.php  # Anulación
│       ├── functions.certificates.php  # Certificados
│       ├── functions.compatibility.php # Funciones de compatibilidad
│       ├── functions.qr.php            # Generación QR
│       └── functions.response.php      # Respuestas AEAT
├── tests/                  # Tests
│   ├── MassValidateInterceptionTest.php  # Tests validación masiva
│   └── SchemaManagerTest.php             # Tests gestión de esquemas
├── views/                  # Vistas y páginas
│   ├── list.facture.php    # Lista de facturas
│   ├── query.facture.php   # Consulta AEAT
│   ├── tabVERIFACTU.facture.php  # Pestaña VeriFactu
│   ├── declaration.php     # Declaración responsable
│   ├── declaration_json.php  # Exportación JSON declaración
│   ├── faq.php             # Ayuda y FAQ
│   ├── pos.facture.php     # Ticket TPV
│   └── documentation.php   # Documentación
├── langs/                  # Archivos de idioma
├── css/                    # Estilos CSS
├── js/                     # JavaScript
└── README.md               # Este archivo
```

## API REST

El módulo expone una API REST para integración externa:

```
GET  /api/index.php/verifactu/integrity
```

## Resolución de Problemas

### Error SOAP 4118

Este error indica un problema con la estructura del mensaje SOAP. Verificar:
- Formato de fecha (DD-MM-AAAA)
- Datos del emisor y destinatario
- Configuración del certificado

### Error SOAP por rate-limiting de W3C

Si aparecen errores al crear el SoapClient al procesar muchas facturas seguidas, es porque PHP intenta descargar `xmldsig-core-schema.xsd` desde w3.org y este servidor aplica rate-limiting. Solución:
- Ir a **Configuración > Módulos > VeriFactu > Configuración**
- Pulsar el botón **Descargar/Actualizar esquemas** para cachear los WSDL/XSD localmente
- Los esquemas se descargan automáticamente en el primer uso, pero se puede forzar desde aquí

### Error de Certificado

Si el certificado no es reconocido:
- Verificar que el certificado no ha expirado
- Comprobar que la contraseña es correcta
- Asegurarse de que el certificado es de persona jurídica

### Pantalla en Blanco

Si aparece una pantalla en blanco al ver facturas:
- Revisar los logs de PHP en busca de errores
- Verificar que todas las extensiones PHP están instaladas

### Facturas con Retención IRPF

El módulo calcula correctamente el `ImporteTotal` para VeriFactu excluyendo la retención IRPF:
- `ImporteTotal` = Base Imponible + IVA + Recargo de Equivalencia (sin deducir IRPF)
- El código QR refleja este importe correcto
- Las consultas a AEAT comparan con el importe correcto

## Información del Módulo

- **Versión**: 1.0.4
- **Autor**: Germán Luis Aracil Boned
- **Email**: garacilb@gmail.com
- **Licencia**: GPL-3.0-or-later
- **Dedicado a**: Mi compañero y amigo Ildefonso González Rodríguez

## Registro de Cambios

### v1.0.4 (2026-03-04)

#### Correcciones

- **Retención IRPF en VeriFactu**: Corregido el cálculo de `ImporteTotal` en el código QR y en las consultas a AEAT. Las facturas con retención IRPF ahora envían correctamente `ImporteTotal = Base + IVA + Recargo` sin deducir el IRPF. Creada función helper `getVerifactuImporteTotal()` para centralizar el cálculo.

- **Validación masiva con transacciones independientes**: Reescrito completamente el sistema de validación masiva de facturas. Cada factura se procesa en su propia transacción de base de datos, de modo que si una falla, las demás continúan procesándose. Se intercepta la acción estándar de Dolibarr `massvalidation` con procesamiento individual por factura.

- **Orden cronológico de fechas en validación masiva**: Antes de validar masivamente, el sistema detecta si alguna factura tiene fecha anterior a la última factura validada en VeriFactu. Si hay conflicto, muestra un diálogo de confirmación y ajusta las fechas automáticamente. Las facturas se procesan ordenadas por fecha ascendente y la fecha mínima se va actualizando conforme se procesan.

- **Orden cronológico de fechas en validación individual**: Al validar una factura individual (PROV), la fecha se ajusta a `max(hoy, última_factura_validada)` en vez de solo a `hoy`. Si la fecha se ajustó, se muestra un aviso en el diálogo de confirmación.

- **Envío masivo desde lista de facturas**: Corregido el problema por el que al confirmar el envío masivo desde la lista de facturas no se transmitían los IDs seleccionados (el diálogo `formconfirm` no soporta arrays). Se usa ahora un campo oculto `verifactu_toselect` con IDs separados por comas. Las facturas ya enviadas se filtran automáticamente.

- **Archivos PEM al renovar certificado**: Al subir un nuevo certificado P12/PFX, los archivos PEM antiguos no se eliminaban, provocando que el sistema siguiera usando el certificado anterior (potencialmente expirado). Añadida función `deleteExistingPemFiles()` que limpia los PEM antes de procesar el nuevo certificado. Añadida visualización de la fecha de expiración del certificado con indicadores de color (expirado/próximo a expirar/válido).

#### Nuevas Funcionalidades

- **Caché local de esquemas WSDL/XSD**: Nueva clase `SchemaManager` que descarga los 7 archivos de esquema (1 WSDL + 5 XSD de AEAT + 1 XSD de W3C) localmente y reescribe las referencias `schemaLocation` para eliminar dependencias externas. Esto previene errores de rate-limiting de w3.org al procesar muchas facturas. Los esquemas se descargan automáticamente en el primer uso y pueden actualizarse desde la página de configuración.

#### Archivos Modificados
- `class/actions_verifactu.class.php` - Validación masiva e individual con transacciones independientes y control de fechas
- `lib/functions/functions.compatibility.php` - Nueva función `getVerifactuImporteTotal()`
- `lib/functions/functions.qr.php` - Corrección importes QR con IRPF
- `lib/functions/functions.query.php` - Corrección comparación importes AEAT
- `lib/functions/functions.submission.php` - Integración SchemaManager
- `lib/newfenix/src/SchemaManager.php` - Nueva clase gestión de esquemas
- `lib/newfenix/src/SoapClient.php` - Uso de WSDL local con fallback remoto
- `lib/newfenix/src/Config.php` - Propiedad `localWsdlPath`
- `lib/newfenix/src/Manager.php` - Integración `setSchemasDir()`
- `admin/setup.php` - Sección estado de esquemas con botón de descarga + expiración de certificado
- `admin/managecertificates.php` - Limpieza de PEM al subir nuevo certificado
- `admin/uploadcertificates.php` - Limpieza de PEM al subir nuevo certificado
- `lib/functions/functions.certificates.php` - Nueva función `deleteExistingPemFiles()`
- `views/list.facture.php` - Corrección transmisión IDs en envío masivo
- Todos los archivos de idioma (es_ES, en_US, ca_ES, eu_ES, gl_ES)

#### Tests
- `tests/MassValidateInterceptionTest.php` - 52 tests para validación masiva, transacciones, fechas
- `tests/SchemaManagerTest.php` - 19 tests para gestión de esquemas

### v1.0.3 (2025-12-20)

#### Cambios
- **Fechas obligatorias VeriFactu actualizadas**: Ajustadas las fechas de transición automática a producción de 2026 a 2027 según el anuncio del Gobierno:
  - Sociedades: 1 de enero de 2027 (antes 1 de enero de 2026)
  - Autónomos: 1 de julio de 2027 (antes 1 de julio de 2026)
- **Documentación del proyecto simplificada**: Simplificada la sección de historia del proyecto en el README manteniendo la atribución GPL v3 al autor original
- **Declaración responsable actualizada**: Configurados los datos del productor con la información de 7Kas Servicios de Internet SL (CIF B98515273, Benicasim, Castellón)

#### Archivos Actualizados
- `lib/functions/functions.configuration.php` - Lógica de cambio de entorno
- `admin/setup.php` - Fechas de visualización de estado
- `conf/declaracion_responsable.conf.php` - Datos del productor 7Kas Servicios de Internet SL
- Todos los archivos de idioma (es_ES, en_US, ca_ES, eu_ES, gl_ES)

### v1.0.2 (2025-12-12)

#### Nuevas Funcionalidades
- **Declaración Responsable configurable**: Añadido archivo de configuración `conf/declaracion_responsable.conf.php` que cumple con la normativa española (RD 1007/2023, Orden HAC/1177/2024):
  - Datos del productor (NIF, dirección, contacto)
  - Datos del sistema informático (IdSistemaInformatico, versión)
  - Especificaciones técnicas (firma XAdES, hash SHA-256)
  - Hash de integridad del módulo calculado dinámicamente
  - Declaración de cumplimiento con referencias legales
- **Exportación JSON**: Nuevo endpoint `/views/declaration_json.php` para exportar la declaración responsable en formato JSON
- **Documentación**: Añadido `docs/responsible_declaration.md` con guía de configuración completa

#### Mejoras
- Actualizada la página de Declaración Responsable para usar los datos del archivo de configuración
- Validación automática de campos obligatorios con mensajes de aviso
- Visualización del hash de integridad del módulo en la declaración

### v1.0.1 (2025-12-06)

#### Correcciones
- **Gestión de errores en validación masiva de facturas**: Corregido el problema por el que facturas con errores de VeriFactu cancelaban todo el proceso de validación por lotes. Ahora, cuando una factura falla al enviar a VeriFactu:
  - La factura permanece como borrador con referencia PROV en lugar de ser validada y luego revertida
  - Las demás facturas del lote continúan procesándose normalmente
  - Mejorada la gestión de conexiones PostgreSQL para evitar errores "connection already closed"
  - Los mensajes de error se muestran correctamente al usuario
