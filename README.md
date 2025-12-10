# VeriFactu - Módulo para Dolibarr

Módulo de Dolibarr para la integración con el sistema VeriFactu de la Agencia Tributaria Española (AEAT).

## Historia del Proyecto

Este proyecto tiene su origen en la parte de código abierto **verifactu** desarrollado originalmente por **Alberto SuperAdmin (Alberto Luque Rivas)** de **easysoft.es** y distribuido bajo licencia **GPL v3**.

### Motivación

El desarrollo de este fork surgió por las siguientes necesidades:

1. **Facturación con VeriFactu sin dependencias problemáticas**: Se necesitaba poder facturar cumpliendo con los requisitos de VeriFactu sin tener que utilizar el módulo RD10072023 del mismo proveedor, el cual crea una dependencia que obliga a ser instalado para que VeriFactu acepte ser activado aun no existiendo ninguna dependencia real para hacerlo.

2. **Privacidad y control de datos**: Se detectó que el módulo original RD10072023, se envía información sensible al proveedor del módulo **sin conocimiento ni consentimiento explícito del usuario**. Esta práctica plantea serias preocupaciones sobre la privacidad de los datos empresariales y fiscales.

3. **Independencia del proveedor**: Se buscaba una solución que no requiriera sistemas de licenciamiento externos ni validaciones remotas que comprometan la autonomía del usuario.

### Desarrollo

Este módulo fue desarrollado partiendo de la **parte de código abierto** del módulo adquirido, el cual estaba licenciado bajo **GPL v3 (GNU General Public License versión 3)**. Sobre esta base:

- Se reorganizó y modularizó el código para mejorar su mantenibilidad
- Se eliminaron las dependencias de sistemas externos de licenciamiento
- Se tradujo la documentación y comentarios al inglés
- Se creó una nueva librería interna (Sietekas\Verifactu)
- Se eliminaron las funcionalidades que enviaban datos a servidores externos sin consentimiento al no precisar usar más el módulo RD10072023

### Nota sobre la Licencia GPL y el Código Propietario

Durante el análisis del código original de `verifactu_easysoft`, se detectó que **existen archivos sin header de licencia** dentro del proyecto que contienen código funcional integrado con el resto del código GPL:

- `lib/functions/funciones.utilidades.php`
- `lib/functions/funciones.certificados.php`
- `lib/functions/funciones.conectorCertificados.php`

**Sospechamos que esto podría constituir una violación de la licencia GPL**, ya que:

1. La **GPL v3 no permite mezclar código propietario con código GPL** en un mismo proyecto que se distribuye como una unidad. A diferencia de licencias permisivas como MIT o BSD, la GPL tiene un efecto "copyleft" que requiere que todo el trabajo derivado mantenga la misma licencia.

2. Según la GPL v3, sección 5: *"You must license the entire work, as a whole, under this License to anyone who comes into possession of a copy."*

3. Si el proveedor original pretende que estos archivos sin header sean código propietario mientras el resto del proyecto es GPL, estaría **violando los términos de la GPL** que él mismo eligió para el proyecto.

4. Alternativamente, si es simplemente un descuido y esos archivos también son GPL (solo falta el header), entonces no hay problema legal, pero sí una mala práctica de documentación.

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
- **Dedicado**: Dedicado a mi compañero y amigo Ildefonso González Rodríguez

## Registro de Cambios

### v1.0.1 (2025-12-06)

#### Corrección de Errores
- **Gestión de errores en validación masiva de facturas**: Corregido el problema donde las facturas con errores de VeriFactu cancelaban todo el proceso de validación por lotes. Ahora, cuando una factura falla en el envío a VeriFactu:
  - La factura permanece como borrador con referencia PROV en lugar de ser validada y luego revertida
  - Las demás facturas del lote continúan procesándose normalmente
  - Mejorado el manejo de conexiones PostgreSQL para evitar errores de "conexión ya cerrada"
  - Los mensajes de error se muestran correctamente al usuario
