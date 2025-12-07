# VeriFactu - Módulo para Dolibarr

Módulo de Dolibarr para la integración con el sistema VeriFactu de la Agencia Tributaria Española (AEAT).

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
2. Acceder a **Configuración > Módulos** en Dolibarr
3. Buscar "VeriFactu" en la lista de módulos
4. Activar el módulo

## Configuración

### 1. Configuración General

Acceder a **Configuración > Módulos > VeriFactu > Configuración**

- **Entorno**: Seleccionar Pruebas o Producción
- **NIF Emisor**: NIF de la empresa emisora
- **Nombre/Razón Social**: Nombre de la empresa

### 2. Certificado Digital

Acceder a **Configuración > Módulos > VeriFactu > Certificados**

1. Subir el certificado en formato PFX/P12
2. Introducir la contraseña del certificado
3. Verificar que el certificado es válido

El certificado debe ser:
- Certificado de persona jurídica (empresa)
- Emitido por una CA reconocida (FNMT, etc.)
- Vigente y no revocado

### 3. Sistema Informático

Configurar los datos del sistema de facturación:

- **NIF Desarrollador**: NIF del desarrollador del software
- **Nombre Sistema**: Nombre del sistema de facturación
- **ID Sistema**: Identificador único del sistema
- **Versión**: Versión del software

## Uso

### Envío de Facturas

1. Crear una factura en Dolibarr
2. Validar la factura
3. En la pestaña "VeriFactu" de la factura:
   - Click en "Enviar a AEAT"
   - Verificar el estado de la respuesta

### Consulta de Facturas

1. Acceder a **Facturación > VeriFactu > Consulta AEAT**
2. Seleccionar los filtros de búsqueda:
   - Período de imputación (año/mes)
   - Rango de fechas
   - Contraparte (NIF del cliente)
3. Click en "Consultar"

### Anulación de Facturas

1. Acceder a la factura enviada
2. En la pestaña "VeriFactu":
   - Click en "Anular en AEAT"
   - Seleccionar el tipo de anulación
   - Confirmar la operación

### Código QR

El código QR se genera automáticamente al enviar la factura y se muestra en:
- La vista de la factura
- El PDF de la factura (si está configurado)
- El ticket del TPV (si está habilitado)

## Tipos de Factura Soportados

| Código | Descripción |
|--------|-------------|
| F1 | Factura completa |
| F2 | Factura simplificada |
| F3 | Factura sustitutiva de simplificadas |
| R1 | Rectificativa (error fundado en derecho) |
| R2 | Rectificativa (Art. 80.3) |
| R3 | Rectificativa (Art. 80.4) |
| R4 | Rectificativa (resto) |
| R5 | Rectificativa en factura simplificada |

## Idiomas Soportados

- Español (es_ES)
- Catalán (ca_ES)
- Euskera (eu_ES)
- Gallego (gl_ES)
- Inglés (en_US)

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
├── core/
│   ├── modules/            # Descriptor del módulo
│   └── triggers/           # Triggers automáticos
│       ├── interface_900_modVerifactu_BillRestrictions.class.php
│       └── interface_999_modVerifactu_VerifactuTriggers.class.php
├── lib/
│   ├── newfenix/           # Librería OpenAEAT Billing
│   │   ├── src/            # Clases principales
│   │   └── vendor/         # Dependencias (QRCode)
│   └── functions/          # Funciones del módulo
│       ├── functions.submission.php    # Envío de facturas
│       ├── functions.query.php         # Consultas AEAT
│       ├── functions.cancellation.php  # Anulación
│       ├── functions.certificates.php  # Certificados
│       ├── functions.qr.php            # Generación QR
│       └── functions.response.php      # Respuestas AEAT
├── views/                  # Vistas y páginas
│   ├── list.facture.php    # Listado de facturas
│   ├── query.facture.php   # Consulta AEAT
│   ├── tabVERIFACTU.facture.php  # Pestaña VeriFactu
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

## Solución de Problemas

### Error SOAP 4118

Este error indica un problema con la estructura del mensaje SOAP. Verificar:
- El formato de las fechas (DD-MM-YYYY)
- Los datos del emisor y destinatario
- La configuración del certificado

### Error de Certificado

Si el certificado no es reconocido:
- Verificar que el certificado no ha expirado
- Comprobar que la contraseña es correcta
- Asegurar que el certificado es de persona jurídica

### Pantalla en Blanco

Si aparece una pantalla en blanco al ver facturas:
- Revisar los logs de PHP en busca de errores
- Verificar que todas las extensiones PHP están instaladas

## Información del Módulo

- **Versión**: 1.4.2
- **Autor**: Germán Luis Aracil Boned
- **Email**: garacilb@gmail.com
- **Licencia**: GPL-3.0-or-later

## Registro de Cambios

### v1.0.1 (2025-12-06)

#### Corrección de Errores
- **Gestión de errores en validación masiva de facturas**: Corregido el problema donde las facturas con errores de VeriFactu cancelaban todo el proceso de validación por lotes. Ahora, cuando una factura falla en el envío a VeriFactu:
  - La factura permanece como borrador con referencia PROV en lugar de ser validada y luego revertida
  - Las demás facturas del lote continúan procesándose normalmente
  - Mejorado el manejo de conexiones PostgreSQL para evitar errores de "conexión ya cerrada"
  - Los mensajes de error se muestran correctamente al usuario
