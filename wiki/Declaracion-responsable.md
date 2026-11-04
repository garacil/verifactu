# Declaración responsable

La declaración responsable es el documento con el que el productor de un sistema informático de facturación declara, bajo su responsabilidad, que el sistema cumple los requisitos del Real Decreto 1007/2023 y de la Orden HAC/1177/2024. El módulo la mantiene en un único fichero de configuración, `conf/declaracion_responsable.conf.php`, del que se alimentan tanto la página que la muestra y exporta como la identidad del sistema informático que viaja en cada registro de facturación. Esta página describe ese fichero campo por campo, las funciones auxiliares que lo acompañan, la identidad transmitida a la AEAT y sus límites, la visualización y la exportación, y qué debe personalizar quien redistribuya o modifique el módulo.

## Qué es y qué normativa cita el módulo

El fichero de configuración cita en su cabecera el marco legal que desarrolla:

| Norma | Cómo la usa el módulo |
|---|---|
| Real Decreto 1007/2023, de 5 de diciembre | Reglamento de los sistemas de facturación; los seis requisitos de su Art. 8.1 (integridad, conservación, accesibilidad, legibilidad, trazabilidad, inalterabilidad) se declaran en la sección `cumplimiento` |
| Real Decreto 254/2025 | «Modificaciones y plazos» |
| Orden HAC/1177/2024, de 17 de octubre | Especificaciones técnicas. Cada campo del fichero indica el apartado del **artículo 15** que lo exige (15.1.a-c productor, 15.2.a-g sistema, 15.3 declaración, 15.4 fecha y lugar); los algoritmos de huella y firma remiten al Art. 14 |
| Artículo 29.2.j) de la Ley 58/2003, General Tributaria | Fundamento de la obligación; el texto de la declaración lo cita |

El código aplica de forma directa una exigencia del Art. 15.2: la identidad del sistema declarada debe ser exactamente la transmitida a la AEAT en cada registro. Por eso, desde la 2.0.0, `getSystemConfig()` lee la identidad del propio fichero de declaración (ver más abajo). El README añade en la 2.1.0 la razón de que los datos del productor no sean configurables desde la administración: en multiempresa lo que identifica al obligado tributario (`VERIFACTU_HOLDER_NIF`, `VERIFACTU_HOLDER_COMPANY_NAME`) ya es configuración por entidad, mientras que el productor del software es quien suscribe la declaración, y «quien redistribuya el módulo bajo su nombre edita `conf/declaracion_responsable.conf.php`, que existe para eso».

> **Nota:** esta wiki describe lo que el módulo declara y transmite. La decisión de qué debe contener la declaración de un productor concreto y cuándo debe suscribirse corresponde a ese productor y a su asesoramiento; ver [[Marco normativo|Marco-normativo]].

## El fichero `conf/declaracion_responsable.conf.php`

Es un fichero PHP que define la constante `VERIFACTU_DECLARACION_RESPONSABLE`, rellena el array global `$declaracionResponsable` en ocho secciones y declara cinco funciones auxiliares. Lo incluyen con `require_once` la página de la declaración, el exportador JSON y `getDeclaredSystemIdentity()`.

### Sección 1: `productor` (Art. 15.1)

| Clave | Valor actual | Obligatorio | Observaciones |
|---|---|---|---|
| `razon_social` | `7Kas Servicios de Internet SL` | Sí (15.1.a) | |
| `nif` | `B98515273` | Sí (15.1.b) | Si el productor no tiene NIF español se usa `nif_extranjero` |
| `nif_extranjero.tipo_identificacion`, `.numero`, `.pais_emision` | vacíos | Solo sin NIF español | `pais_emision` en ISO 3166-1 alpha-2 |
| `direccion.tipo_via` … `.codigo_pais` | `Calle`, `Columbretes`, `38`, piso y puerta vacíos, `12560`, `Benicasim`, `Castellón`, `España`, `ES` | Sí (15.1.c); la validación exige `localidad` | |
| `contacto.email`, `.telefono`, `.web` | `info@7kas.es`, vacío, `https://7kas.es` | Recomendado | «para comunicaciones de la AEAT» |

### Sección 2: `sistema` (Art. 15.2)

| Clave | Valor actual | Obligatorio | Observaciones |
|---|---|---|---|
| `nombre` | `VeriFactu para Dolibarr ERP/CRM` | Sí (15.2.a) | Nombre comercial; es el que muestra la página de la declaración |
| `nombre_sistema_informatico` | `Dolibarr Verifactu Module` | Sí (15.2.a) | Valor exacto de `NombreSistemaInformatico` en cada registro; máximo 30 caracteres (`sf:TextMax30Type`) |
| `id_sistema_informatico` | `DV` | Sí (15.2.b) | `IdSistemaInformatico`; máximo **2 caracteres** (`sf:TextMax2Type`). El comentario del fichero advierte de que un identificador más largo es rechazado por el servicio web |
| `version` | `2.2.2` | Sí (15.2.c) | Debe coincidir con `$this->verifactu_version` de `modVerifactu.class.php`; es el campo `Version` transmitido; máximo 50 caracteres |
| `fecha_version` | `2026-09-19` | | |
| `numero_instalacion` | vacío | | Lo rellena `generarIdInstalacion()` en tiempo de ejecución |
| `tipo_licencia` | `GPL-3.0-or-later` | | |
| `repositorio` | `https://github.com/garacil/verifactu` | | |

### Sección 3: `componentes` (Art. 15.2.d)

- `software`: lista de componentes con `nombre`, `descripcion`, `version_minima` y `tipo`: Dolibarr ERP/CRM (10.0, `plataforma_base`), PHP (7.4, `runtime`), OpenSSL (1.1.0, `dependencia`), SOAP Extension (`dependencia`) y chillerlan/php-qrcode (4.0, `dependencia`).
- `hardware`: `arquitectura` `x86_64 / ARM64`, `memoria_minima` `512 MB`, `almacenamiento_minimo` `100 MB`, `descripcion` «Servidor web compatible con PHP 7.4+».
- `funcionalidades`: ocho indicadores a `true`: `generacion_registros`, `encadenamiento_hash`, `firma_electronica`, `generacion_qr`, `envio_aeat`, `consulta_aeat`, `conservacion_registros`, `exportacion_datos`.

### Sección 4: `especificaciones_tecnicas` (Anexos y Art. 14)

| Clave | Valor actual | Observaciones |
|---|---|---|
| `modalidad_funcionamiento` | `verifactu` | Art. 15.2.e; alternativa documentada `no_verifactu` |
| `exclusivo_verifactu` | `false` | |
| `multiusuario` | `true` | Art. 15.2.f |
| `multiempresa` | `true` | |
| `tipo_firma` | `formato` XAdES, `tipo` Enveloped, `nivel` B-Level, `certificado` «Certificado cualificado de firma electrónica», `algoritmo_firma` RSA-SHA256, `algoritmo_hash` SHA-256 | Art. 15.2.g, con remisión al Art. 14 |
| `algoritmo_huella` | `SHA-256` | Art. 14 |
| `codificacion` | `UTF-8` | |
| `version_esquema_xml` | `1.0` | Coincide con `IDVersion` de los registros |
| `endpoints_aeat.produccion` | `https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.wsdl` | Valor declarado en el fichero |
| `endpoints_aeat.pruebas` | `https://www7.aeat.es/wlpl/TIKE-CONT/ws/SuministroLR.wsdl` | Valor declarado en el fichero |

> **Nota:** las direcciones que el módulo usa realmente para conectar están en `lib/newfenix/src/Config.php` (servicio `VerifactuSOAP` en `www1`/`www10` y `prewww1`/`prewww10`) y el WSDL que descarga es `SistemaFacturacion.wsdl` de `prewww2.aeat.es`; no coinciden con las URL declaradas en `endpoints_aeat`, que la página de la declaración no muestra. Ver [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]. Del mismo modo, el módulo no contiene código de firma XAdES: la comunicación se autentica con el certificado del obligado en la capa TLS y la integridad de los registros se apoya en la huella encadenada.

### Sección 5: `integridad`

| Clave | Contenido |
|---|---|
| `algoritmo` | `SHA-256` |
| `hash_modulo` | Vacío en el fichero; lo calcula `calcularHashModuloVerifactu()` |
| `fecha_calculo` | Fecha del último cálculo, en tiempo de ejecución |
| `ficheros_verificados` | Los 13 ficheros cuyo contenido se concatena para el hash (lista completa en [[Integridad del módulo|Integridad-del-modulo]]) |
| `ficheros_no_encontrados` | Se rellena en el cálculo; «debe estar siempre vacío: un fichero ausente queda fuera del hash y por tanto sin verificar» |

### Sección 6: `cumplimiento` (Art. 15.3)

- `declaracion`: el texto de la declaración («El productor del sistema informático de facturación declara, bajo su responsabilidad, que el presente sistema cumple con los requisitos establecidos en el Real Decreto 1007/2023 … y en la Orden HAC/1177/2024 …»). Es el párrafo introductorio de la página.
- `requisitos_cumplidos`: `integridad`, `conservacion`, `accesibilidad`, `legibilidad`, `trazabilidad`, `inalterabilidad`, todos `true`, con referencia al Art. 8.1 a) a f) del RD 1007/2023.
- `referencia_legal`: `ley` (Ley 58/2003), `articulo` (`29.2.j)`), `real_decreto`, `orden_ministerial`.

### Sección 7: `suscripcion` (Art. 15.4)

| Clave | Valor actual | Obligatorio |
|---|---|---|
| `fecha` | `2025-12-19` (formato `AAAA-MM-DD`) | Sí |
| `lugar.localidad`, `.provincia`, `.pais`, `.codigo_pais` | `Benicasim`, `Castellón`, `España`, `ES` | Sí (localidad) |
| `firmante.nombre`, `.cargo`, `.en_representacion_de` | `Germán Luis Aracil Boned`, `Desarrollador / Productor`, `7Kas Servicios de Internet SL` | |

### Sección 8: `metadata`

`version_config` `1.0.0`, `fecha_creacion` `2025-12-12`, `ultima_modificacion` `2025-12-19`, `autor` `7Kas Servicios de Internet SL` y `notas`. Describen el propio fichero de configuración, no el módulo.

## Funciones auxiliares

Definidas al final del mismo fichero:

| Función | Qué hace |
|---|---|
| `calcularHashModuloVerifactu($basePath = '')` | Concatena el contenido de `ficheros_verificados` (relativos a la raíz del módulo, o a `$basePath`), calcula SHA-256 y actualiza `integridad.hash_modulo`, `integridad.fecha_calculo` e `integridad.ficheros_no_encontrados`. Si falta algún fichero lo registra además con `dol_syslog()` a nivel `LOG_WARNING` (desde la 2.0.0; antes se omitía en silencio) |
| `generarIdInstalacion()` | 32 primeros caracteres del SHA-256 de `id_sistema_informatico|version|MAIN_INFO_SOCIETE_NOM|MAIN_INFO_SIREN|php_uname('n')` |
| `obtenerDeclaracionResponsable($calcularHash = true)` | Devuelve el array completo tras calcular el hash (si se pide) y rellenar `sistema.numero_instalacion` |
| `validarDeclaracionResponsable()` | Devuelve la lista de errores: faltan razón social, NIF (o identificación extranjera), localidad del productor, nombre del sistema, `id_sistema_informatico`, versión, fecha o localidad de suscripción. Vacía si todo es correcto |
| `exportarDeclaracionJSON()` | `json_encode()` de `obtenerDeclaracionResponsable(true)` con `JSON_PRETTY_PRINT` y `JSON_UNESCAPED_UNICODE` |

Uso mínimo desde código PHP (es el que documenta el README):

```php
require_once dol_buildpath('/verifactu/conf/declaracion_responsable.conf.php', 0);

$declaracion = obtenerDeclaracionResponsable(true);   // array completo con hash calculado
$errores     = validarDeclaracionResponsable();       // array de mensajes, vacío si está completa
$hash        = calcularHashModuloVerifactu();         // SHA-256 de los ficheros verificados
$json        = exportarDeclaracionJSON();             // cadena JSON
```

## Identidad del sistema informático transmitida

Cada registro de alta y de anulación lleva el bloque `SistemaInformatico`. Lo construye `getSystemConfig()` (`lib/functions/functions.configuration.php`), que a su vez obtiene la identidad de `getDeclaredSystemIdentity()`:

| Campo transmitido | Origen | Límite del esquema (`SuministroInformacion.xsd`) |
|---|---|---|
| `NombreRazon` | `VERIFACTU_HOLDER_COMPANY_NAME` (razón social configurada del obligado) | |
| `NIF` | `VERIFACTU_HOLDER_NIF` (NIF configurado del obligado) | |
| `NombreSistemaInformatico` | `sistema.nombre_sistema_informatico` | 30 caracteres (`sf:TextMax30Type`) |
| `IdSistemaInformatico` | `sistema.id_sistema_informatico` | 2 caracteres (`sf:TextMax2Type`) |
| `Version` | `sistema.version` | 50 caracteres (`sf:TextMax50Type`) |
| `NumeroInstalacion` | `$dolibarr_main_instance_unique_id . '_' . $conf->entity` | 100 caracteres (`substr(…, 0, 100)`) |
| `TipoUsoPosibleSoloVerifactu` | `S` (fijo) | |
| `TipoUsoPosibleMultiOT` | `N` (fijo) | |
| `IndicadorMultiplesOT` | `N` (fijo) | |

> **Nota:** `NombreRazon` y `NIF` de este bloque se toman de la configuración del obligado tributario de la entidad, no de la sección `productor` del fichero de declaración. Y el «Número de instalación» que muestra la página de la declaración (`generarIdInstalacion()`) no es el `NumeroInstalacion` que se transmite (identificador único de la instancia de Dolibarr más el número de entidad).

### `getDeclaredSystemIdentity()` y por qué declaración y transmisión salen de la misma fuente

Hasta la 1.0.4 la declaración anunciaba `IdSistemaInformatico = VERIFACTU-DOLIBARR-OSS` y el nombre `VeriFactu para Dolibarr ERP/CRM`, mientras que a la AEAT se enviaban `DV` y `Dolibarr Verifactu Module`, y en `Version` viajaba `DOL_VERSION` (la versión de Dolibarr) en lugar de la del módulo. La revisión de conformidad de la issue #30 (puntos 1 y 6) señaló que el Art. 15.2 exige que coincidan, y la 2.0.0 lo resolvió con una sola fuente de verdad:

1. `getDeclaredSystemIdentity()` parte de unos valores por defecto internos (`Dolibarr Verifactu Module`, `DV`, `2.2.2`) para seguir funcionando si el fichero no se puede leer.
2. Declara `global $declaracionResponsable` **antes** de incluir el fichero, porque este asigna el array en el ámbito del fichero y, dentro de una función, se perdería.
3. Incluye `conf/declaracion_responsable.conf.php` con `require_once` y, si `sistema` existe, toma de él `nombre_sistema_informatico`, `id_sistema_informatico` y `version`.
4. Aplica los límites 30/2/50 con `mb_substr()` (cuenta caracteres, como el esquema, y no parte un carácter multibyte). Desde la 2.1.0 el recorte **no es silencioso**: registra con `dol_syslog()` a nivel `LOG_ERR` que el valor transmitido ya no coincide con el declarado y pide corregir la declaración.

Los valores declarados se eligieron para caber en el esquema: el identificador largo anterior habría sido rechazado por el servicio web. `tests/IssueFixesTest.php` comprueba que `IdSistemaInformatico`, `NombreSistemaInformatico` y `Version` transmitidos son idénticos a los declarados y que respetan 2, 30, 50 y 100 caracteres. El flujo de publicación (`.github/workflows/release.yml`) rechaza además crear una versión si el número no coincide en `modVerifactu.class.php` (`verifactu_version`), en el fichero de declaración (`'version'`) y en el valor por defecto de `getDeclaredSystemIdentity()`; ver [[Publicación de versiones|Publicacion-de-versiones]].

## Visualización y exportación

### Página de la declaración (`views/declaration.php`)

Accesible desde el menú **VeriFactu > Declaración responsable** (`VERIFACTU_DECLARATION_MENU`, permiso `verifactu->manage` o administrador). La página incluye el fichero de configuración, llama a `obtenerDeclaracionResponsable(true)` (recalcula el hash en cada visita) y a `validarDeclaracionResponsable()`, y muestra:

- un aviso con la lista de errores si la configuración está incompleta («La configuración de la declaración responsable está incompleta … complete el fichero `conf/declaracion_responsable.conf.php`»);
- el texto de la declaración (`cumplimiento.declaracion`);
- **Datos del productor**: razón social, NIF (o identificación extranjera con país), dirección compuesta y correo de contacto;
- **Datos del sistema informático**: nombre comercial (`sistema.nombre`), `IdSistemaInformatico`, versión con fecha, número de instalación (`generarIdInstalacion()`) y licencia;
- **Especificaciones técnicas**: modalidad («VERI*FACTU (envío inmediato a AEAT)» o «NO VERI*FACTU»), tipo de firma, algoritmo de huella y características (multiusuario, multiempresa);
- **Integridad del sistema**: hash del módulo y fecha de cálculo;
- los requisitos del Art. 8 RD 1007/2023 marcados como cumplidos;
- **Suscripción**: fecha, lugar y firmante;
- insignias RD 1007/2023, Orden HAC/1177/2024 y Art. 29.2.j) LGT, y dos botones: **Imprimir declaración** (`window.print()`, con una hoja de estilos `@media print` que deja solo el documento) y **Exportar JSON**.

Si el fichero de configuración no existe, la página muestra «El fichero de configuración no existe» y campos «No configurado».

### Exportación JSON (`views/declaration_json.php`)

`GET /custom/verifactu/views/declaration_json.php` (la ruta la construye `dol_buildpath()`). Exige sesión iniciada (si no, HTTP 401 `Authentication required`) y el permiso `verifactu->manage` o ser administrador (si no, 403 `Permission denied`); devuelve 404 si falta el fichero de configuración. Añade al array un bloque `export_metadata` con `export_date`, `export_user` (login), `export_format` (`JSON`), `dolibarr_version` y `php_version`, y lo sirve como descarga (`Content-Disposition: attachment`) con el nombre `declaracion_responsable_verifactu_AAAAMMDD_HHMMSS.json`, en JSON legible, sin escapar caracteres Unicode ni barras.

El JSON contiene las ocho secciones completas, incluidos `nombre_sistema_informatico`, `endpoints_aeat`, `ficheros_verificados` y `ficheros_no_encontrados`, que la página HTML no muestra.

## Qué debe personalizar quien redistribuya o modifique el módulo

Los valores actuales identifican al productor original. Si distribuyes el módulo bajo tu nombre, o lo modificas y lo pones en producción como sistema propio, el fichero exige revisar al menos:

| Qué | Dónde | Cuidado |
|---|---|---|
| Datos del productor | `productor` (razón social, NIF, dirección, contacto) | `validarDeclaracionResponsable()` exige razón social, NIF o identificación extranjera y localidad |
| Identidad del sistema | `sistema.nombre`, `sistema.nombre_sistema_informatico` (≤ 30), `sistema.id_sistema_informatico` (≤ 2), `sistema.version` (≤ 50) | Lo que pongas aquí es lo que se transmite; un valor demasiado largo se recorta y queda registrado como error en el log |
| Versión | `sistema.version` **y** `verifactu_version` en `core/modules/modVerifactu.class.php` **y** el valor por defecto en `getDeclaredSystemIdentity()` | El flujo de publicación exige que los tres coincidan |
| Fecha de versión y repositorio | `sistema.fecha_version`, `sistema.repositorio`, `sistema.tipo_licencia` | La licencia GPL-3.0-or-later se hereda |
| Componentes y especificaciones | `componentes`, `especificaciones_tecnicas` | Ajustar si cambian los requisitos o la modalidad |
| Ficheros verificados | `integridad.ficheros_verificados` | Si renombras o añades ficheros críticos, actualiza la lista; `ficheros_no_encontrados` debe quedar vacío (lo comprueba `tests/IssueFixesTest.php`) |
| Suscripción | `suscripcion.fecha`, `.lugar`, `.firmante` | `docs/responsible_declaration.md` recomienda actualizar la fecha con cada versión o cambio significativo |
| Metadatos | `metadata` | Fecha de modificación y autor del fichero |

Por diseño, nada de esto se edita desde la pantalla de configuración de Dolibarr (README, 2.1.0): el fichero forma parte del código que se distribuye, y su hash de integridad y el de los ficheros que enumera cambian con él. Cualquier cambio de identidad debería coincidir con una nueva versión publicada. Ver [[Desarrollo y contribución|Desarrollo-y-contribucion]].

## Historia de este componente

- **1.0.2**: se crea el fichero de configuración, la exportación JSON y `docs/responsible_declaration.md`; la página pasa a leer del fichero y valida los campos obligatorios.
- **1.0.3**: se configuran los datos del productor actuales.
- **2.0.0**: identidad declarada y transmitida unificadas (`getDeclaredSystemIdentity()`, `getSystemConfig()`), `Version` pasa a ser la del módulo, la lista de ficheros verificados se corrige (referenciaba `interface_99_…` y `class/verifactu.class.php`, inexistentes) y se amplía con los ficheros de hash, envío, anulación, configuración, respuesta y certificados; los ficheros ausentes dejan de omitirse en silencio.
- **2.1.0**: el recorte a los límites del esquema se registra como error y se hace con `mb_substr()`; se documenta por qué los datos del productor no se parametrizan desde la administración.

`docs/responsible_declaration.md` (en inglés) describe la estructura del fichero y sus funciones; algunos de sus ejemplos conservan valores anteriores a la 2.0.0 (identificador `VERIFACTU-DOLIBARR-OSS`, versión 1.0.2, lista de ficheros antigua). El fichero de configuración es la referencia.

## Véase también

- [[Integridad del módulo|Integridad-del-modulo]] — `calcularHashModuloVerifactu()`, la lista de ficheros verificados y cómo cotejar una instalación.
- [[Publicación de versiones|Publicacion-de-versiones]] — la comprobación de la versión en tres sitios antes de etiquetar.
- [[Configuración|Configuracion]] — `VERIFACTU_HOLDER_NIF` y `VERIFACTU_HOLDER_COMPANY_NAME`, que completan el bloque `SistemaInformatico`.
- [[Multiempresa|Multiempresa]] — identidad del obligado por entidad frente a productor único.
- [[Marco normativo|Marco-normativo]] — RD 1007/2023, Orden HAC/1177/2024 y LGT.
- [[Permisos y menús|Permisos-y-menus]] — la entrada de menú y el permiso `manage`.
- [[Historial de versiones|Historial-de-versiones]] — cambios 1.0.2, 1.0.3, 2.0.0 y 2.1.0.
