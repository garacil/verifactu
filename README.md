# VeriFactu - Modulo para Dolibarr

Modulo de Dolibarr para la integracion con el sistema VeriFactu de la Agencia Estatal de Administracion Tributaria (AEAT), en cumplimiento de la **Ley 11/2021** (Ley Antifraude) y el **Real Decreto 1007/2023** que establece los requisitos tecnicos para los sistemas de facturacion en Espana.

## Historia del Proyecto

Este proyecto tiene su origen en el codigo abierto **verifactu** desarrollado originalmente por **Alberto SuperAdmin (Alberto Luque Rivas)** de **easysoft.es** y distribuido bajo licencia **GPL v3**.

### Desarrollo

Este modulo fue desarrollado a partir del modulo original licenciado bajo **GPL v3 (GNU General Public License version 3)**. Sobre esta base:

- Se reorganizo y modularizo el codigo para mejorar la mantenibilidad
- Se tradujeron la documentacion y los comentarios al ingles
- Se creo una nueva libreria interna (OpenAEAT\Billing)

**Este fork mantiene todo el codigo bajo GPL v3**, respetando la licencia original y los derechos de los autores originales, anadiendo la atribucion correspondiente en todos los archivos.

---

## Descripcion

VeriFactu es el sistema de verificacion de facturas de la AEAT que permite:

- Envio automatico de facturas a la AEAT
- Generacion de codigos QR de verificacion
- Encadenamiento criptografico de facturas (SHA-256)
- Consulta del estado de facturas enviadas
- Anulacion de facturas
- Gestion de facturas rectificativas

## Requisitos

- Dolibarr 13.0 o superior
- PHP 7.4 o superior
- Extension PHP SOAP habilitada
- Extension PHP OpenSSL habilitada
- Extension PHP GD habilitada (para QR)
- Certificado digital valido (FNMT o equivalente)

## Instalacion

1. Copiar la carpeta `verifactu` en el directorio `htdocs/custom/` de Dolibarr
2. Ir a **Configuracion > Modulos** en Dolibarr
3. Buscar "VeriFactu" en la lista de modulos
4. Activar el modulo

## Configuracion

### 1. Configuracion General

Ir a **Configuracion > Modulos > VeriFactu > Configuracion**

- **Entorno**: Seleccionar Pruebas o Produccion
- **NIF Emisor**: Numero de identificacion fiscal de la empresa
- **Nombre/Razon Social**: Nombre de la empresa

### 2. Certificado Digital

Ir a **Configuracion > Modulos > VeriFactu > Certificados**

1. Subir el certificado en formato PFX/P12
2. Introducir la contrasena del certificado
3. Verificar que el certificado es valido

El certificado debe ser:
- Certificado de persona juridica (empresa)
- Emitido por una CA reconocida (FNMT, etc.)
- Valido y no revocado

### 3. Sistema Informatico

Configurar los datos del sistema de facturacion:

- **NIF Desarrollador**: NIF del desarrollador del software
- **Nombre del Sistema**: Nombre del sistema de facturacion
- **ID del Sistema**: Identificador unico del sistema
- **Version**: Version del software

### 4. Esquemas WSDL/XSD

El modulo descarga y cachea localmente los esquemas WSDL y XSD necesarios para la comunicacion SOAP con la AEAT. Esto evita dependencias de servidores externos (AEAT, W3C) y previene errores de rate-limiting al procesar muchas facturas seguidas.

- Los esquemas se descargan automaticamente la primera vez que se envia una factura
- En **Configuracion > Modulos > VeriFactu > Configuracion** se muestra el estado de los esquemas
- Usar el boton **Descargar/Actualizar esquemas** para forzar la re-descarga si la AEAT actualiza los esquemas

## Uso

### Envio de Facturas

1. Crear una factura en Dolibarr
2. Validar la factura
3. En la pestana "VeriFactu" de la factura:
   - Hacer clic en "Enviar a AEAT"
   - Verificar el estado de la respuesta

### Validacion Masiva

La validacion masiva de facturas procesa cada factura en una transaccion independiente:
- Si una factura falla, las demas continuan procesandose normalmente
- Se detectan automaticamente conflictos de fechas con VeriFactu (orden cronologico)
- Si hay conflictos, se muestra un dialogo de confirmacion antes de ajustar las fechas
- Las facturas se procesan ordenadas por fecha (de menor a mayor)

### Validacion Individual

Al validar una factura individual (PROV), el sistema:
- Ajusta automaticamente la fecha a `max(hoy, ultima_factura_validada)` para respetar el orden cronologico de VeriFactu
- Muestra un aviso en el dialogo de confirmacion si la fecha fue ajustada, indicando la fecha original, la nueva fecha y la referencia de la ultima factura validada

### Consulta de Facturas

1. Ir a **Facturacion > VeriFactu > Consulta AEAT**
2. Seleccionar los filtros de busqueda:
   - Periodo de imputacion (ano/mes)
   - Rango de fechas
   - Contraparte (NIF del cliente)
3. Hacer clic en "Consultar"

### Anulacion de Facturas

1. Acceder a la factura enviada
2. En la pestana "VeriFactu":
   - Hacer clic en "Anular en AEAT"
   - Seleccionar el tipo de anulacion
   - Confirmar la operacion

### Codigo QR

El codigo QR se genera automaticamente al enviar la factura y se muestra en:
- La vista de la factura
- El PDF de la factura (si esta configurado)
- El ticket de TPV (si esta habilitado)

## Tipos de Factura Soportados

| Codigo | Descripcion |
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

- Espanol (es_ES)
- Catalan (ca_ES)
- Euskera (eu_ES)
- Gallego (gl_ES)
- Ingles (en_US)

## Declaracion Responsable

El modulo incluye un sistema completo de **Declaracion Responsable** en cumplimiento con la normativa espanola vigente:

- **RD 1007/2023** - Reglamento VeriFactu
- **RD 254/2025** - Modificaciones y plazos
- **Orden HAC/1177/2024** - Especificaciones tecnicas
- **Art. 29.2.j) Ley 58/2003** - Ley General Tributaria

### Archivo de Configuracion

La configuracion se encuentra en `conf/declaracion_responsable.conf.php` e incluye:

| Seccion | Contenido |
|---------|-----------|
| **Datos del Productor** | NIF, razon social, direccion, contacto |
| **Datos del Sistema** | Nombre, IdSistemaInformatico, version |
| **Componentes** | Software y hardware requeridos |
| **Especificaciones Tecnicas** | Tipo de firma (XAdES), algoritmo hash (SHA-256) |
| **Integridad** | Hash del modulo calculado dinamicamente |
| **Cumplimiento** | Declaracion segun Art. 29.2.j) LGT |
| **Suscripcion** | Fecha, lugar y firmante |

### Funciones Disponibles

```php
// Obtener configuracion completa con hash calculado
$declaracion = obtenerDeclaracionResponsable(true);

// Validar campos obligatorios
$errores = validarDeclaracionResponsable();

// Calcular hash SHA-256 del modulo
$hash = calcularHashModuloVerifactu();

// Exportar en formato JSON
$json = exportarDeclaracionJSON();
```

### Visualizacion

La declaracion responsable esta disponible en:
- **Menu:** VeriFactu > Declaracion Responsable
- **Exportacion JSON:** `/verifactu/views/declaration_json.php`

Para mas detalles, ver `docs/responsible_declaration.md`.

## Estructura del Modulo

```
verifactu/
├── admin/                  # Paginas de administracion
│   ├── setup.php           # Configuracion general
│   ├── managecertificates.php  # Gestion de certificados
│   └── uploadcertificates.php  # Subida de certificados
├── class/                  # Clases PHP
│   ├── actions_verifactu.class.php  # Hooks y acciones
│   ├── api_verifactu.class.php      # API REST
│   └── verifactu.utils.php          # Utilidades
├── conf/                   # Configuracion
│   └── declaracion_responsable.conf.php  # Declaracion responsable
├── core/
│   ├── modules/            # Descriptor del modulo
│   └── triggers/           # Triggers automaticos
│       ├── interface_900_modVerifactu_BillRestrictions.class.php
│       └── interface_999_modVerifactu_VerifactuTriggers.class.php
├── docs/                   # Documentacion
│   └── responsible_declaration.md  # Doc. declaracion responsable
├── lib/
│   ├── newfenix/           # Libreria OpenAEAT Billing
│   │   ├── src/            # Clases principales
│   │   │   ├── SchemaManager.php  # Gestion de esquemas WSDL/XSD
│   │   │   ├── SoapClient.php     # Transporte SOAP
│   │   │   ├── Config.php         # Configuracion
│   │   │   ├── Manager.php        # Orquestador
│   │   │   ├── Invoice.php        # Modelo de factura
│   │   │   ├── Cancellation.php   # Modelo de anulacion
│   │   │   └── Query.php          # Modelo de consulta
│   │   └── vendor/         # Dependencias (QRCode)
│   └── functions/          # Funciones del modulo
│       ├── functions.submission.php    # Envio de facturas
│       ├── functions.query.php         # Consultas AEAT
│       ├── functions.cancellation.php  # Anulacion
│       ├── functions.certificates.php  # Certificados
│       ├── functions.compatibility.php # Funciones de compatibilidad
│       ├── functions.qr.php            # Generacion QR
│       └── functions.response.php      # Respuestas AEAT
├── tests/                  # Tests
│   ├── MassValidateInterceptionTest.php  # Tests validacion masiva
│   └── SchemaManagerTest.php             # Tests gestion de esquemas
├── views/                  # Vistas y paginas
│   ├── list.facture.php    # Lista de facturas
│   ├── query.facture.php   # Consulta AEAT
│   ├── tabVERIFACTU.facture.php  # Pestana VeriFactu
│   ├── declaration.php     # Declaracion responsable
│   ├── declaration_json.php  # Exportacion JSON declaracion
│   ├── faq.php             # Ayuda y FAQ
│   ├── pos.facture.php     # Ticket TPV
│   └── documentation.php   # Documentacion
├── langs/                  # Archivos de idioma
├── css/                    # Estilos CSS
├── js/                     # JavaScript
└── README.md               # Este archivo
```

## API REST

El modulo expone una API REST para integracion externa:

```
GET  /api/index.php/verifactu/integrity
```

## Resolucion de Problemas

### Error SOAP 4118

Este error indica un problema con la estructura del mensaje SOAP. Verificar:
- Formato de fecha (DD-MM-AAAA)
- Datos del emisor y destinatario
- Configuracion del certificado

### Error SOAP por rate-limiting de W3C

Si aparecen errores al crear el SoapClient al procesar muchas facturas seguidas, es porque PHP intenta descargar `xmldsig-core-schema.xsd` desde w3.org y este servidor aplica rate-limiting. Solucion:
- Ir a **Configuracion > Modulos > VeriFactu > Configuracion**
- Pulsar el boton **Descargar/Actualizar esquemas** para cachear los WSDL/XSD localmente
- Los esquemas se descargan automaticamente en el primer uso, pero se puede forzar desde aqui

### Error de Certificado

Si el certificado no es reconocido:
- Verificar que el certificado no ha expirado
- Comprobar que la contrasena es correcta
- Asegurarse de que el certificado es de persona juridica

### Pantalla en Blanco

Si aparece una pantalla en blanco al ver facturas:
- Revisar los logs de PHP en busca de errores
- Verificar que todas las extensiones PHP estan instaladas

### Facturas con Retencion IRPF

El modulo calcula correctamente el `ImporteTotal` para VeriFactu excluyendo la retencion IRPF:
- `ImporteTotal` = Base Imponible + IVA + Recargo de Equivalencia (sin deducir IRPF)
- El codigo QR refleja este importe correcto
- Las consultas a AEAT comparan con el importe correcto

## Informacion del Modulo

- **Version**: 1.0.4
- **Autor**: German Luis Aracil Boned
- **Email**: garacilb@gmail.com
- **Licencia**: GPL-3.0-or-later
- **Dedicado a**: Mi companero y amigo Ildefonso Gonzalez Rodriguez

## Registro de Cambios

### v1.0.4 (2026-03-04)

#### Correcciones

- **Retencion IRPF en VeriFactu**: Corregido el calculo de `ImporteTotal` en el codigo QR y en las consultas a AEAT. Las facturas con retencion IRPF ahora envian correctamente `ImporteTotal = Base + IVA + Recargo` sin deducir el IRPF. Creada funcion helper `getVerifactuImporteTotal()` para centralizar el calculo.

- **Validacion masiva con transacciones independientes**: Reescrito completamente el sistema de validacion masiva de facturas. Cada factura se procesa en su propia transaccion de base de datos, de modo que si una falla, las demas continuan procesandose. Se intercepla la accion estandar de Dolibarr `massvalidation` con procesamiento individual por factura.

- **Orden cronologico de fechas en validacion masiva**: Antes de validar masivamente, el sistema detecta si alguna factura tiene fecha anterior a la ultima factura validada en VeriFactu. Si hay conflicto, muestra un dialogo de confirmacion y ajusta las fechas automaticamente. Las facturas se procesan ordenadas por fecha ascendente y la fecha minima se va actualizando conforme se procesan.

- **Orden cronologico de fechas en validacion individual**: Al validar una factura individual (PROV), la fecha se ajusta a `max(hoy, ultima_factura_validada)` en vez de solo a `hoy`. Si la fecha se ajusto, se muestra un aviso en el dialogo de confirmacion.

#### Nuevas Funcionalidades

- **Cache local de esquemas WSDL/XSD**: Nueva clase `SchemaManager` que descarga los 7 archivos de esquema (1 WSDL + 5 XSD de AEAT + 1 XSD de W3C) localmente y reescribe las referencias `schemaLocation` para eliminar dependencias externas. Esto previene errores de rate-limiting de w3.org al procesar muchas facturas. Los esquemas se descargan automaticamente en el primer uso y pueden actualizarse desde la pagina de configuracion.

#### Archivos Modificados
- `class/actions_verifactu.class.php` - Validacion masiva e individual con transacciones independientes y control de fechas
- `lib/functions/functions.compatibility.php` - Nueva funcion `getVerifactuImporteTotal()`
- `lib/functions/functions.qr.php` - Correccion importes QR con IRPF
- `lib/functions/functions.query.php` - Correccion comparacion importes AEAT
- `lib/functions/functions.submission.php` - Integracion SchemaManager
- `lib/newfenix/src/SchemaManager.php` - Nueva clase gestion de esquemas
- `lib/newfenix/src/SoapClient.php` - Uso de WSDL local con fallback remoto
- `lib/newfenix/src/Config.php` - Propiedad `localWsdlPath`
- `lib/newfenix/src/Manager.php` - Integracion `setSchemasDir()`
- `admin/setup.php` - Seccion estado de esquemas con boton de descarga
- Todos los archivos de idioma (es_ES, en_US, ca_ES, eu_ES, gl_ES)

#### Tests
- `tests/MassValidateInterceptionTest.php` - 52 tests para validacion masiva, transacciones, fechas
- `tests/SchemaManagerTest.php` - 19 tests para gestion de esquemas

### v1.0.3 (2025-12-20)

#### Cambios
- **Fechas obligatorias VeriFactu actualizadas**: Ajustadas las fechas de transicion automatica a produccion de 2026 a 2027 segun el anuncio del Gobierno:
  - Sociedades: 1 de enero de 2027 (antes 1 de enero de 2026)
  - Autonomos: 1 de julio de 2027 (antes 1 de julio de 2026)
- **Documentacion del proyecto simplificada**: Simplificada la seccion de historia del proyecto en el README manteniendo la atribucion GPL v3 al autor original

#### Archivos Actualizados
- `lib/functions/functions.configuration.php` - Logica de cambio de entorno
- `admin/setup.php` - Fechas de visualizacion de estado
- Todos los archivos de idioma (es_ES, en_US, ca_ES, eu_ES, gl_ES)

### v1.0.2 (2025-12-12)

#### Nuevas Funcionalidades
- **Declaracion Responsable configurable**: Anadido archivo de configuracion `conf/declaracion_responsable.conf.php` que cumple con la normativa espanola (RD 1007/2023, Orden HAC/1177/2024):
  - Datos del productor (NIF, direccion, contacto)
  - Datos del sistema informatico (IdSistemaInformatico, version)
  - Especificaciones tecnicas (firma XAdES, hash SHA-256)
  - Hash de integridad del modulo calculado dinamicamente
  - Declaracion de cumplimiento con referencias legales
- **Exportacion JSON**: Nuevo endpoint `/views/declaration_json.php` para exportar la declaracion responsable en formato JSON
- **Documentacion**: Anadido `docs/responsible_declaration.md` con guia de configuracion completa

#### Mejoras
- Actualizada la pagina de Declaracion Responsable para usar los datos del archivo de configuracion
- Validacion automatica de campos obligatorios con mensajes de aviso
- Visualizacion del hash de integridad del modulo en la declaracion

### v1.0.1 (2025-12-06)

#### Correcciones
- **Gestion de errores en validacion masiva de facturas**: Corregido el problema por el que facturas con errores de VeriFactu cancelaban todo el proceso de validacion por lotes. Ahora, cuando una factura falla al enviar a VeriFactu:
  - La factura permanece como borrador con referencia PROV en lugar de ser validada y luego revertida
  - Las demas facturas del lote continuan procesandose normalmente
  - Mejorada la gestion de conexiones PostgreSQL para evitar errores "connection already closed"
  - Los mensajes de error se muestran correctamente al usuario
