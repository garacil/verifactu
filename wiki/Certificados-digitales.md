# Certificados digitales

El módulo se autentica ante los servicios de la AEAT con el certificado digital del obligado tributario (o de su representante) mediante una conexión TLS con certificado de cliente. Esta página explica qué formatos admite, cómo subir y gestionar el certificado desde la administración, dónde se guarda, cómo se convierte a PEM, qué se valida antes de cada envío, qué pasa con los certificados de la FNMT cifrados con algoritmos antiguos bajo OpenSSL 3 y cómo interpretar los errores más habituales. Todo el código de certificados vive en `lib/functions/functions.certificates.php`, `admin/managecertificates.php` y `admin/uploadcertificates.php`.

## Formatos admitidos

| Formato | Extensiones | Dónde se admite | Observaciones |
|---|---|---|---|
| PKCS#12 | `.pfx`, `.p12` | Pestaña «Gestionar Certificados» y `admin/uploadcertificates.php` | Contenedor con certificado y clave privada protegido por contraseña. Es el formato que entregan la FNMT y la mayoría de autoridades |
| PEM | `.pem` | `admin/uploadcertificates.php` y desplegable de la configuración | Debe contener certificado y clave privada en el mismo fichero (`openssl_pkey_get_private()` se aplica sobre el mismo contenido que `openssl_x509_read()`) |

El desplegable «Certificado digital» de la configuración lista los ficheros de la carpeta de certificados que cumplan `\.(pfx|p12|pem)$`.

## Requisitos del certificado

- Válido y no revocado, emitido por una autoridad reconocida (el README cita la FNMT «o equivalente»).
- El README pide certificado de persona jurídica; desde la 2.2.0 el registro de cambios admite expresamente que un certificado de representante o de colaborador social firme por varios obligados tributarios (por eso no se bloquea su reutilización entre entidades).
- Dentro de su periodo de validez X.509: `validateCertificateAndKey()` rechaza con `VERIFACTU_CERT_ERROR_EXPIRED` cualquier certificado cuya fecha actual esté fuera de `validFrom`/`validTo`.
- Clave privada que corresponda al certificado (`openssl_x509_check_private_key()`).
- Tipo coherente con `VERIFACTU_CERT_TYPE`: `normal` sirve con certificados personales y de empresa; `sello` solo con certificados de sello electrónico.

## Subir el certificado desde «Gestionar Certificados»

Es la vía documentada en el README (**Configuración > Módulos > VeriFactu > Certificados**) y la única enlazada desde las pestañas de administración. Fichero: `admin/managecertificates.php`, acción `upload_certificate`, solo para administradores.

1. Selecciona el fichero `.pfx` o `.p12` (el campo acepta `.pfx,.p12`; otra extensión responde «Error: Solo se permiten archivos .pfx o .p12»).
2. Escribe la contraseña del contenedor.
3. Pulsa **Subir**.

Lo que ocurre a continuación, en orden:

1. Se crea la carpeta de certificados si no existe (`dol_mkdir`).
2. El fichero se guarda con su nombre original en `documents/verifactu/certificates/`.
3. `deleteExistingPemFiles()` borra los PEM que pudieran quedar de un certificado anterior con el mismo nombre base: `<nombre>_bundle.pem`, `<nombre>_bundle_protegido.pem`, `<nombre>_cert.pem` y `<nombre>_key.pem`. Así el módulo no sigue usando un PEM generado a partir de un certificado caducado (corrección de la 1.0.4).
4. `extractAndSendPublicKey()` intenta abrir el contenedor:
   - primero con `openssl_pkcs12_read()` (PHP nativo, sin procesos);
   - si falla, con el binario `openssl` externo (`extraerCertificadoPublicoConOpenSSL()` → `runOpensslPkcs12()`), que reintenta con `-legacy` cuando procede.
5. Con el certificado público extraído se comprueba que es X.509 válido y se construye la tabla informativa (`generateCertificateDisplayInfo()`).
6. Se extrae la clave privada en PEM (nativa o externa) y se guarda en `VERIFACTU_CERTIFICATE_PRIVATE_KEY`.
7. Si todo va bien, se fijan `VERIFACTU_CERTIFICATE` (nombre del fichero) y `VERIFACTU_CERTIFICATE_KEY` (la contraseña) y se muestra «Certificado configurado automáticamente: <fichero>».
8. Si falla, el fichero se borra (`@unlink`) y se muestra el error clasificado.

La tabla informativa muestra: **Nombre** (`CN` del sujeto), **Documento** (`serialNumber` del sujeto, donde las autoridades españolas suelen incluir el NIF), **Organización**, **Emisor**, **País**, **Válido desde/hasta**, **Número de serie**, **Algoritmo de firma**, **Clave pública** (tipo y bits), huellas **SHA1** y **SHA256** y los **usos permitidos** (Digital Signature, Non Repudiation, Key Encipherment), con una insignia «Valid»/«Invalid» según las fechas.

> **Nota:** la cabecera de esta página describe la extracción como «Extracción de clave pública y registro en servidor» y la información de la pantalla habla de una clave pública remota. En la 2.2.2 no se envía nada a ningún servidor externo: el certificado se procesa y se guarda solo en la instalación.

## Página alternativa `uploadcertificates.php`

`admin/uploadcertificates.php` no está enlazada en las pestañas, pero es accesible por URL para administradores. Usa el formulario estándar de adjuntos de Dolibarr (`FormFile::form_attach_new_file()`) con `accept=".p12,.pfx,.pem"` y permite subir varios ficheros a la vez. Para cada fichero subido:

1. Fija temporalmente `VERIFACTU_CERTIFICATE` al nombre del fichero.
2. Borra los PEM anteriores del mismo nombre base.
3. Llama a `prepareCertificateForVerifactu()`, que convierte a PEM usando la contraseña que ya haya en `VERIFACTU_CERTIFICATE_KEY`.
4. Muestra «Certificado '%s' convertido a PEM correctamente» o «Certificado '%s' validado (ya era PEM)»; si falla, «Error procesando certificado '%s': %s» y restaura el valor anterior de `VERIFACTU_CERTIFICATE`.

Debajo lista los ficheros de la carpeta de certificados con opción de **eliminar** (`action=remove_file`). Es la única pantalla del módulo desde la que se pueden borrar certificados y PEM antiguos. Como no pide la contraseña, es útil sobre todo para subir un `.pem` ya preparado o para limpiar ficheros.

## Dónde se guardan

| Elemento | Ubicación |
|---|---|
| Carpeta de certificados | `documents/verifactu/certificates/` (`$conf->verifactu->multidir_output[$conf->entity] . "/certificates"`) |
| Con Multicompany, entidad distinta de 1 | `documents/<id_entidad>/verifactu/certificates/` |
| PEM convertido | `<nombre_base>_bundle_protegido.pem` en la misma carpeta, permisos `0600` |
| Ficheros temporales de la conversión externa | `<nombre_base>_cert.pem` y `<nombre_base>_key.pem`, eliminados al terminar |
| Nombre del fichero en uso | Constante `VERIFACTU_CERTIFICATE` |
| Contraseña o clave del PEM | Constante `VERIFACTU_CERTIFICATE_KEY` |
| Clave privada extraída | Constante `VERIFACTU_CERTIFICATE_PRIVATE_KEY` |

La carpeta `certificates` se declara en `$this->dirs` del descriptor y la crea la activación; `managecertificates.php` la crea también bajo demanda.

## Conversión a PEM y cambio de clave

Antes de cada conexión, `getCertificateOptions()` llama a `prepareCertificateForVerifactu()` → `prepareCertificateLocalForVerifactu()`:

1. Comprueba que hay certificado configurado y que el fichero existe.
2. Si el fichero **no** termina en `.pem` y la clave no está vacía, llama a `prepareLocalCertificate($certPath, $dir, $password, true, $privateKey)` con `$privateKey = $dolibarr_main_instance_unique_id` (o la propia contraseña si esa variable de `conf.php` estuviera vacía).
3. `prepareLocalCertificate()`:
   - si ya existe `<nombre>_bundle_protegido.pem`, lo devuelve sin hacer nada más;
   - si no, intenta la vía nativa (`extractPkcs12WithPhp()`), reexporta la clave privada protegida con `$privateKey` (`openssl_pkey_export()`) y escribe certificado + clave en el PEM con `chmod 0600`;
   - si la vía nativa no puede (contenedor *legacy*) y `proc_open` está disponible, usa el binario: `openssl pkcs12 -in <p12> -clcerts -nokeys -out <cert.pem>` y `openssl pkcs12 -in <p12> -nocerts -passout env:VERIFACTU_P12_PASSOUT -out <key.pem>`, ambos con `-password env:VERIFACTU_P12_PASS` y reintento `-legacy`, y combina los dos ficheros;
   - si no hay `proc_open`, lanza una `RuntimeException` con el motivo real.
4. **Actualiza la configuración**: `VERIFACTU_CERTIFICATE` pasa a ser el nombre del PEM y `VERIFACTU_CERTIFICATE_KEY` pasa a ser `$privateKey`.

> **Importante:** tras el primer envío (o la primera visita a `uploadcertificates.php`) el desplegable de la configuración mostrará `<nombre>_bundle_protegido.pem` y el campo «Clave del certificado» ya no contendrá la contraseña del P12, sino la clave con la que se protegió el PEM (por defecto `$dolibarr_main_instance_unique_id` de `conf.php`). Es el comportamiento previsto. Si cambias `dolibarr_main_instance_unique_id` en `conf.php`, el PEM existente seguirá abriéndose con la clave guardada en `VERIFACTU_CERTIFICATE_KEY`.

Si el fichero configurado ya es `.pem`, se usa tal cual con `VERIFACTU_CERTIFICATE_KEY` como frase de paso.

## Extracción: vía nativa y binario externo

| Paso | Función | Requiere | Notas |
|---|---|---|---|
| 1. Lectura nativa | `extractPkcs12WithPhp()` → `openssl_pkcs12_read()` | Extensión `openssl` | No lanza procesos. Devuelve certificado, clave, certificados adicionales y, en caso de fallo, los indicadores `legacy_cipher` y `wrong_password` |
| 2. Binario externo | `runOpensslPkcs12()` → `runOpensslCommand()` | `proc_open` disponible y binario `openssl` | Argumentos en array (sin shell en Linux), contraseña en la variable de entorno `VERIFACTU_P12_PASS` (`-password env:VERIFACTU_P12_PASS`), entorno heredado de `getenv()` para conservar `PATH` |
| 3. Reintento *legacy* | `runOpensslPkcs12()` | Binario OpenSSL 3 o superior (`opensslCliSupportsLegacyFlag()`) | Solo si el primer intento falló con un error que `isLegacyCipherOpensslError()` reconoce (`0308010C`, «digital envelope routines», `rc2-40-cbc`, `pbe-sha1-3des`...) |

`isPhpFunctionAvailable('proc_open')` comprueba `function_exists()` y la lista `disable_functions`. En Windows, `getOpensslBinary()` usa la ruta `C:\laragon\bin\apache\httpd-2.4.54-win64-VS16\bin\openssl.exe` y `proc_open` recibe la orden como cadena escapada.

## Validación antes de cada envío

`validateCertificateAndKey($certPath, $passphrase)` se ejecuta en `getCertificateOptions()` y otra vez en `execVERIFACTUCall()`:

1. El fichero existe y se puede leer.
2. `openssl_x509_read()` lo interpreta como X.509 («Error reading X.509 certificate - invalid format» si no).
3. La fecha actual (`dol_now()`) está dentro de `validFrom_time_t`/`validTo_time_t`; si no, `VERIFACTU_CERT_ERROR_EXPIRED` («Error: el certificado está fuera de su periodo de validez...»).
4. `openssl_pkey_get_private($contenido, $passphrase)` abre la clave («Error reading private key. Incorrect passphrase or key not found» si no).
5. `openssl_x509_check_private_key()` confirma que clave y certificado son pareja («Private key does not match certificate»).

Las opciones que se pasan al `SoapClient` son `local_cert` (ruta del PEM), `passphrase`, `trace`, `exceptions`, `cache_wsdl = WSDL_CACHE_NONE` y un contexto SSL con `verify_peer` y `verify_peer_name` desactivados (véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]).

La pantalla de configuración muestra además la **caducidad**: «Válido» con los días restantes, «Caduca pronto» si faltan menos de 30 días o «Caducado». Detalle en [[Configuración|Configuracion]].

## Certificados FNMT con cifrado *legacy* y OpenSSL 3

### Síntoma

Al subir un `.p12` de la FNMT el módulo responde que la contraseña no es correcta aunque lo sea. La causa suele ser el cifrado del contenedor (`RC2-40-CBC`, `PBE-SHA1-3DES`), que OpenSSL 3 (Debian 12, imagen oficial `dolibarr/dolibarr`) desactiva salvo que se active el proveedor *legacy*. La lectura nativa falla con `error:0308010C: digital envelope routines::unsupported`.

### Qué hace el módulo desde la 2.0.0

- Reintenta con `openssl pkcs12 -legacy` si el binario es OpenSSL 3 o superior.
- Distingue en el mensaje entre contraseña incorrecta (`VERIFACTU_CERT_ERROR_WRONG_PASSWORD`), algoritmo *legacy* no soportado (`VERIFACTU_CERT_ERROR_LEGACY_ALGORITHM`) y fallo genérico (`VERIFACTU_CERT_ERROR_EXTRACTION_FAILED`).

### Si sigue fallando

**Opción A: activar el proveedor *legacy* en el servidor.** Editar `/etc/ssl/openssl.cnf`:

```ini
[provider_sect]
default = default_sect
legacy = legacy_sect

[default_sect]
activate = 1

[legacy_sect]
activate = 1
```

Con el proveedor activo, la propia lectura nativa de PHP suele funcionar y ya no hace falta el binario.

**Opción B: reexportar el certificado con un algoritmo moderno** en un ordenador donde sí se abra:

```bash
openssl pkcs12 -legacy -in certificado_fnmt.p12 -nodes -out temporal.pem -password pass:TU_CLAVE
openssl pkcs12 -export -in temporal.pem -out certificado_moderno.p12 -password pass:TU_CLAVE
rm temporal.pem
```

Después sube `certificado_moderno.p12` desde «Gestionar Certificados». Es la única salida cuando el alojamiento no permite `proc_open` ni tocar `openssl.cnf`:

| Certificado | Servidor normal | Alojamiento compartido sin `proc_open` |
|---|---|---|
| Cifrado moderno | Funciona | Funciona |
| Cifrado *legacy* (FNMT antiguo) | Funciona vía `-legacy` | No es posible: reexportar |

## Tipo de certificado: `normal` o `sello`

`VERIFACTU_CERT_TYPE` se traduce en `OpenAEAT\Billing\Config` a la familia de servidores:

| Valor | Servidor de pruebas | Servidor de producción | Para qué certificados |
|---|---|---|---|
| `normal` | `https://prewww1.aeat.es` | `https://www1.agenciatributaria.gob.es` | Personales y de empresa (recomendado) |
| `sello` | `https://prewww10.aeat.es` | `https://www10.agenciatributaria.gob.es` | Solo certificados de sello electrónico |

El fichero de idioma advierte: «Certificados personales (AC FNMT) pueden tener problemas con tipo 'sello' en consultas» y «Si obtiene errores "no XML document", su certificado personal no es compatible con 'sello'. Use 'normal'».

## Certificado de representante e identidad fiscal

- El módulo **no coteja** el NIF que figura en el certificado (`serialNumber` del sujeto, mostrado como «Documento» en la tabla informativa) con `VERIFACTU_HOLDER_NIF`. La coherencia entre el certificado con el que se firma la conexión y el obligado tributario declarado es responsabilidad de quien configura el módulo.
- `VERIFACTU_HOLDER_REPRESENTATIVE_NIF` («NIF del representante») se guarda desde la configuración, pero en la 2.2.2 ningún fichero del módulo lo lee ni lo transmite.
- Un mismo certificado puede usarse en varias entidades de Multicompany: la 2.2.0 descartó bloquear la reutilización de la huella del certificado porque un representante o colaborador social puede firmar legítimamente por varios obligados.
- Lo que sí está bloqueado es el **NIF del obligado** una vez existe una huella (`VERIFACTU_TAX_IDENTITY_LOCKED`); el mensaje recuerda que «El certificado sí se puede sustituir», que es lo que hace falta al renovarlo.

## Renovar el certificado

1. Sube el nuevo `.p12` desde **Gestionar Certificados** con su contraseña. Si conserva el mismo nombre de fichero que el anterior, `deleteExistingPemFiles()` elimina los PEM antiguos y el nuevo se convierte en el siguiente envío. Si el nombre es distinto, el módulo pasa a usar el nuevo y los ficheros antiguos quedan en la carpeta.
2. Comprueba en **Ajustes** que «Certificado digital» apunta al nuevo fichero y que «Caducidad del certificado» muestra «Válido».
3. Opcionalmente, borra el certificado y los PEM antiguos desde `admin/uploadcertificates.php`.
4. El NIF no cambia y la cadena de huellas continúa; no hace falta reactivar el módulo.

> **Aviso:** si sustituyes el fichero directamente en el disco con el mismo nombre, borra también `<nombre>_bundle_protegido.pem`; de lo contrario `prepareLocalCertificate()` reutilizará el PEM del certificado antiguo.

## Seguridad

- La contraseña nunca se interpola en la línea de órdenes de `openssl`: viaja en la variable de entorno `VERIFACTU_P12_PASS` (y `VERIFACTU_P12_PASSOUT` para la frase de salida), que se limpia al terminar. Esto evita la inyección de órdenes y que la contraseña sea visible en la lista de procesos (cambio de la 2.0.0).
- Los PEM se escriben con permisos `0600`; `tests/CertificateIdentityTest.php` comprueba que ambos puntos de escritura lo hacen.
- `prepareLocalCertificate()` lanza excepciones en lugar de `die()`, y el llamador las convierte en el mensaje de error habitual.
- `VERIFACTU_CERTIFICATE_KEY` y `VERIFACTU_CERTIFICATE_PRIVATE_KEY` se guardan en `llx_const` como cadenas, visibles para administradores en Inicio > Configuración > Otros. Protege el acceso de administrador y las copias de seguridad de la base de datos en consecuencia.
- El `SoapClient` se crea con `verify_peer = false` y `verify_peer_name = false`; la autenticación se apoya en el certificado de cliente.

## Errores habituales

| Mensaje | Origen | Causa y solución |
|---|---|---|
| «Error: Solo se permiten archivos .pfx o .p12» | `managecertificates.php` | Extensión distinta. Para PEM usa `uploadcertificates.php` |
| «Error: la contraseña del certificado no es correcta...» (`VERIFACTU_CERT_ERROR_WRONG_PASSWORD`) | `isWrongPasswordOpensslError()` («mac verify error», «bad decrypt»...) | Contraseña incorrecta |
| «Error: el certificado está cifrado con un algoritmo antiguo (RC2-40-CBC / PBE-SHA1-3DES)...» (`VERIFACTU_CERT_ERROR_LEGACY_ALGORITHM`) | `isLegacyCipherOpensslError()` y sin proveedor *legacy* disponible | Opción A u opción B de la sección anterior |
| «Error: no se pudo extraer el certificado público del archivo...» (`VERIFACTU_CERT_ERROR_EXTRACTION_FAILED`) | Ninguno de los dos métodos funcionó y el error no encaja en los anteriores | Revisa el registro de Dolibarr: las líneas `VERIFACTU DEBUG` incluyen la salida de OpenSSL |
| «proc_open is disabled on this server and PHP could not read the certificate: ...» | `extractPrivateKeyWithOpenSSL()` / `extractPublicCertificateWithOpenSSL()` | Alojamiento compartido y contenedor que PHP no puede abrir; reexporta el certificado |
| «Error: el certificado está fuera de su periodo de validez...» (`VERIFACTU_CERT_ERROR_EXPIRED`) | `validateCertificateAndKey()` | Certificado caducado o aún no vigente; renuévalo o comprueba el reloj del servidor |
| «Private key does not match certificate or passphrase is incorrect» | `getCertificateOptions()` | El PEM no corresponde al certificado o `VERIFACTU_CERTIFICATE_KEY` no es la clave del PEM; borra el PEM y vuelve a subir el P12 |
| «No certificate configured in module settings» | `prepareCertificateLocalForVerifactu()` | `VERIFACTU_CERTIFICATE` vacío |
| «Certificate file not found: ...» | Ídem | El fichero configurado no está en la carpeta de certificados de la entidad |
| «Neither the PHP OpenSSL extension nor proc_open is available on your server» | Ídem | Falta la extensión `openssl` de PHP y además `proc_open` está desactivado |
| «Certificate error: The certificate passphrase is incorrect or the certificate is invalid» | `execVERIFACTUCall()` | Segunda validación antes del envío; mismas causas que las anteriores |
| «no XML document» en consultas | Respuesta del servidor | Certificado personal con tipo `sello`; cambia a `normal` |

Más casos en [[Resolución de problemas|Resolucion-de-problemas]] y [[Códigos de error|Codigos-de-error]].

## Véase también

- [[Requisitos|Requisitos]]
- [[Configuración|Configuracion]]
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]
- [[Multiempresa|Multiempresa]]
- [[Registro y depuración|Registro-y-depuracion]]
- [[Resolución de problemas|Resolucion-de-problemas]]
- [[Suite de pruebas|Suite-de-pruebas]]
