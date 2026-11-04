# Encadenamiento y huella

Cada registro de facturación que el módulo envía a la AEAT lleva una **huella** SHA-256 calculada sobre sus datos esenciales y sobre la huella del registro anterior. Esa dependencia convierte los registros de un obligado tributario en una cadena estrictamente secuencial (Art. 13 de la Orden HAC/1177/2024): alterar o suprimir un eslabón rompe la verificación de todos los siguientes. Esta página describe cómo calcula la huella la biblioteca interna (`lib/newfenix/src/Invoice.php`), cómo localiza el módulo el registro anterior (`lib/functions/functions.hash.php`) y cómo protege la sección crítica frente a validaciones simultáneas.

## Qué es la huella

La huella es el resultado de `hash('sha256', …)` sobre una cadena de texto en UTF-8, expresada en hexadecimal y **mayúsculas** (64 caracteres). El tipo de huella se declara en el registro como `TipoHuella = '01'` (constante `HASH_TYPE_SHA256`) y la versión del esquema como `IDVersion = '1.0'`.

La calcula `Invoice::generateHash()`. En el flujo normal no se llama directamente: `Manager::sendInvoice()` invoca `Invoice::getData(true)`, que primero ejecuta `validate()` y después `generateHash()` si el registro aún no tiene huella. La cadena de entrada se construye con `sprintf` exactamente así:

```php
'IDEmisorFactura=%s&NumSerieFactura=%s&FechaExpedicionFactura=%s&TipoFactura=%s&CuotaTotal=%s&ImporteTotal=%s&Huella=%s&FechaHoraHusoGenRegistro=%s'
```

| Campo | De dónde sale | Formato |
|---|---|---|
| `IDEmisorFactura` | NIF del obligado (`VERIFACTU_HOLDER_NIF`) | Texto |
| `NumSerieFactura` | `Facture::ref` (referencia definitiva) | Texto |
| `FechaExpedicionFactura` | `date('d-m-Y', $facture->date)` | `dd-mm-aaaa` |
| `TipoFactura` | `verifactu_factura_tipo` (`F1`, `F2`, `R1`…) | Código |
| `CuotaTotal` | Suma de `CuotaRepercutida` más `CuotaRecargoEquivalencia` de todos los detalles de desglose | `number_format(…, 2, '.', '')` |
| `ImporteTotal` | Suma de bases más `CuotaTotal` | `number_format(…, 2, '.', '')` |
| `Huella` | Huella del registro anterior, o cadena vacía si es el primero | 64 hex en mayúsculas o `''` |
| `FechaHoraHusoGenRegistro` | Marca de tiempo generada al construir el objeto: `date('Y-m-d\TH:i:sP')` | ISO 8601 con desplazamiento horario |

Los totales los recalcula `computeTotals()` a partir de los detalles añadidos con `addDesglose()`, no de los totales de Dolibarr; por eso el desglose enviado y la huella son coherentes entre sí (véase [[Desglose de impuestos|Desglose-de-impuestos]]). La marca de tiempo usa la zona horaria de PHP en el servidor; la biblioteca dispone de `generateFingerprint()` con `Europe/Madrid`, pero el flujo del módulo pasa por `getData()` y, por tanto, por `generateHash()`.

Ejemplo de cadena de entrada para un primer registro:

```text
IDEmisorFactura=B12345678&NumSerieFactura=FA2609-0001&FechaExpedicionFactura=19-09-2026&TipoFactura=F1&CuotaTotal=21.00&ImporteTotal=121.00&Huella=&FechaHoraHusoGenRegistro=2026-09-19T10:15:30+02:00
```

## Primer registro y registro anterior

El bloque `Encadenamiento` del registro admite dos formas, y `Invoice::validate()` exige que exista una de ellas:

| Método | Contenido del registro | Cuándo lo usa el módulo |
|---|---|---|
| `setAsFirstInChain()` | `Encadenamiento.PrimerRegistro = 'S'` | `getLastInvoiceHash()` devuelve `null` (cadena vacía para esa entidad y entorno) |
| `setChainLink($nif, $numero, $fecha, $huella)` | `Encadenamiento.RegistroAnterior = {IDEmisorFactura, NumSerieFactura, FechaExpedicionFactura, Huella}` | Hay un registro anterior con huella |

`setChainLink()` rechaza una huella vacía (`InvalidArgumentException`) y normaliza la recibida con `strtoupper(trim())`. `validate()` comprueba además que la huella anterior y la propia cumplen `^[0-9A-F]{64}$`. El test `tests/InvoiceValidationTest.php` cubre estos casos: un registro sin encadenamiento o con una huella anterior mal formada se rechaza localmente, antes de llegar al servicio.

Las anulaciones también se encadenan: `handleInvoiceCancellation()` (`lib/functions/functions.cancellation.php`) llama a `getLastInvoiceHash()` y, desde la 2.2.0, marca la anulación como primer registro cuando no hay registro anterior, porque el esquema oficial declara `Encadenamiento` como elección entre `PrimerRegistro` y `RegistroAnterior` también para `RegistroFacturacionAnulacionType`. Véase [[Anulación de facturas|Anulacion-de-facturas]].

## Dónde se guarda la huella

Al transmitir, `Manager::recordAuditInfo()` conserva la huella y la marca de tiempo del registro enviado. Si la respuesta se acepta, `processInvoiceSendResponse()` las recupera con `getLastGeneratedHash()` y `getLastGeneratedTimestamp()` y las persiste, con conexión independiente, en los campos adicionales de la factura:

| Campo adicional | Etiqueta | Contenido |
|---|---|---|
| `verifactu_huella` | Huella digital | Huella SHA-256 del registro |
| `verifactu_fecha_hora_generacion` | Fecha/hora de huella | `FechaHoraHusoGenRegistro` usado en el cálculo |
| `verifactu_entorno` | Entorno (pruebas/producción) | `test` o `production`, según `Manager::getEnvironment()` |
| `verifactu_payload` | Datos XML | JSON con el registro completo enviado |

Si la AEAT rechaza el registro, la huella **no** se guarda (y en el camino de excepción se vacía expresamente): solo entran en la cadena los registros que la AEAT aceptó. Las respuestas parciales con alguna línea rechazada tampoco se guardan como enviadas desde la 2.0.0 (véase [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]).

## Una cadena por entidad y por entorno

`getLastInvoiceHash()` localiza el registro anterior con esta consulta:

```sql
SELECT fe.verifactu_huella AS hash, f.ref AS invoice_number, f.datef AS invoice_date
  FROM llx_facture f
  INNER JOIN llx_facture_extrafields fe ON f.rowid = fe.fk_object
 WHERE f.entity = {entidad activa}
   AND fe.verifactu_huella IS NOT NULL AND fe.verifactu_huella != ''
   AND fe.verifactu_entorno = '{entorno actual}'
   AND f.fk_statut > 0
 ORDER BY f.rowid DESC
 LIMIT 1
```

De ahí se deducen las reglas de la cadena:

- **Una entidad, una cadena.** El filtro es `f.entity = $conf->entity`, la entidad activa, y no `getEntity('invoice')`: una factura compartida desde otra entidad de Multiempresa nunca entra en la cadena de esta. El test `tests/EntityIsolationTest.php` comprueba que ningún fichero del módulo vuelve a ampliar la consulta con `getEntity()`. Véase [[Multiempresa|Multiempresa]].
- **Un entorno, una cadena.** El filtro `verifactu_entorno = getEnvironment()` separa la cadena de pruebas de la de producción; al pasar a producción la primera factura abre una cadena nueva con `PrimerRegistro = 'S'`. Véase [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]].
- **Solo facturas validadas con huella.** Un borrador o una factura con estado «Error» no pueden ser registro anterior.
- **El orden es el de inserción (`rowid`)**, no el de fecha ni el de la serie de numeración. Todas las series de una entidad comparten la cadena; de ahí la regla cronológica de [[Fechas y numeración|Fechas-y-numeracion]].

La función devuelve un array con las claves `hash`, `numero`/`number` y `fecha`/`date`, o `null` si no hay registro anterior.

## Por qué la fecha se formatea en PHP

Hasta la 2.0.0 la consulta formateaba la fecha con `DATE_FORMAT(f.datef, '%d-%m-%Y')`. Esa función solo existe en MySQL y `convertSQLFromMysql()` de Dolibarr no la traduce, de modo que en PostgreSQL la consulta fallaba. El fallo era silencioso: la función solo miraba si había filas, así que un error de consulta y una cadena vacía eran indistinguibles y **cada factura se encadenaba como primer registro**.

Desde la 2.1.0:

- La fecha se convierte con `$db->jdate($obj->invoice_date)` y se formatea con `date('d-m-Y', …)`, el mismo formato que usa `handleInvoiceCreationOrSubsanation()` al construir `FechaExpedicionFactura`, de modo que ambos extremos de la cadena llevan la misma cadena de fecha.
- Un error de lectura **lanza una excepción** con el texto `VERIFACTU_CHAIN_READ_FAILED`: «No se pudo leer la cadena VeriFactu para obtener el registro anterior, por lo que la factura no se ha enviado. Consulte el registro de eventos de Dolibarr para ver el error de base de datos.» En la validación esa excepción sigue el camino de error y la factura vuelve a borrador; es preferible no enviar a enviar con la cadena rota.
- Una cadena realmente vacía sigue devolviendo `null`, de modo que la primera factura abre la cadena con normalidad.

`tests/ChainIntegrityTest.php` verifica que la consulta no contiene `DATE_FORMAT`, `MONTH(` ni `YEAR(`, que la fecha sale como `dd-mm-aaaa`, que un fallo de consulta lanza excepción y que ningún otro fichero del módulo usa funciones exclusivas de MySQL en sus consultas.

> **Aviso:** si tu instalación usa PostgreSQL y envió registros con versiones anteriores a la 2.1.0, conviene revisar en la AEAT los registros ya remitidos: es posible que se transmitieran como primer registro de cadena.

## Bloqueo de la sección crítica

Entre leer la huella anterior y guardar la nueva hay una llamada al servicio de la AEAT que dura segundos. Dos validaciones simultáneas leerían el mismo registro anterior y encadenarían ambas facturas sobre él. Desde la 2.1.0, `execVERIFACTUCall()` serializa ese tramo:

```php
if (!acquireVerifactuChainLock()) {
    throw new Exception($langs->trans('VERIFACTU_CHAIN_LOCK_BUSY'));
}
try {
    // Alta / Mod / Baja: leer registro anterior, enviar, guardar huella
} finally {
    releaseVerifactuChainLock();
}
```

| Aspecto | Detalle |
|---|---|
| Nombre del bloqueo | `verifactu_chain_{entidad}_{entorno}` (`getVerifactuChainLockName()`): dos entidades, o los entornos de prueba y producción de una misma entidad, no se esperan entre sí |
| MySQL / MariaDB | `SELECT GET_LOCK('nombre', {segundos})`; se libera con `RELEASE_LOCK()` |
| PostgreSQL | `SELECT pg_try_advisory_lock(1007, {clave})`, donde la clave es `crc32(nombre)` convertida a entero de 32 bits con signo (`getVerifactuChainLockKey()`); se sondea cada 200 ms hasta agotar el plazo y se libera con `pg_advisory_unlock()` |
| Otros motores (SQLite…) | No hay bloqueo de aplicación; se continúa sin serializar y se anota un aviso en el registro |
| Tiempo de espera | `VERIFACTU_CHAIN_LOCK_TIMEOUT`, en segundos, **30 por defecto**, mínimo 1 |
| Reentrada | Si la misma petición ya posee el bloqueo, `acquireVerifactuChainLock()` devuelve `true` sin volver a tomarlo, para no necesitar dos liberaciones |
| Vida del bloqueo | Reside en la sesión de base de datos: si el proceso PHP muere, el motor lo libera solo |

Cuando el plazo vence sin obtener el bloqueo se muestra `VERIFACTU_CHAIN_LOCK_BUSY`: «Se está encadenando otra factura en este momento y ha vencido el tiempo de espera. Inténtelo de nuevo en unos segundos: la cadena es estrictamente secuencial y no se pueden encadenar dos facturas a la vez.» En la validación, esa excepción devuelve la factura a borrador; basta con repetir la validación.

La constante `VERIFACTU_CHAIN_LOCK_TIMEOUT` no aparece en la pantalla de configuración del módulo; si se quiere un plazo distinto, se crea como constante de Dolibarr desde **Inicio > Configuración > Otras configuraciones**. `tests/ChainIntegrityTest.php` comprueba la concesión, la reentrada, la liberación, el uso de `GET_LOCK` y `pg_advisory_unlock`, el comportamiento con un motor sin bloqueo y que la clave cabe en el entero con signo que espera PostgreSQL.

## Integridad de la cadena

- **No modifiques la base de datos a mano.** `verifactu_huella`, `verifactu_fecha_hora_generacion`, `verifactu_entorno`, `verifactu_csv_factura` y la referencia o fecha de una factura con huella forman parte de lo que la AEAT ya tiene registrado. Cambiar una huella almacenada haría que la siguiente factura se encadenase sobre un valor que la AEAT no conoce; borrar la huella de una factura la sacaría de la búsqueda del registro anterior y la siguiente se encadenaría con la anterior a ella.
- **No desvalides ni edites facturas con huella.** El módulo lo bloquea (véase [[Restricciones sobre facturas enviadas|Restricciones-sobre-facturas-enviadas]]); el camino correcto es una rectificativa o una anulación, que son nuevos eslabones de la misma cadena.
- **Copias de seguridad y restauraciones.** Restaurar una copia anterior al último envío deja en la AEAT registros que la base de datos ya no conoce; la siguiente validación se encadenaría con un registro anterior distinto del real. Tras una restauración conviene contrastar el último registro con la [[Consulta a la AEAT|Consulta-a-la-AEAT]] antes de emitir.
- **Registros en la AEAT que Dolibarr no reconoce.** La consulta previa de `execVERIFACTUCall()` y el tratamiento del error 3000 detectan facturas ya registradas; si los importes coinciden, se adopta la huella que devuelve la AEAT (`DatosRegistroFacturacion.Huella`) para que la cadena local coincida con la remota.
- **Verificación de integridad del módulo.** La huella de los ficheros del módulo (`calcularHashModuloVerifactu()`) es un mecanismo distinto, orientado a la declaración responsable; véase [[Integridad del módulo|Integridad-del-modulo]].

## Véase también

- [[Flujo de validación y envío|Flujo-de-validacion-y-envio]]
- [[Fechas y numeración|Fechas-y-numeracion]]
- [[Respuestas de la AEAT|Respuestas-de-la-AEAT]]
- [[Multiempresa|Multiempresa]]
- [[Conexión SOAP y entornos|Conexion-SOAP-y-entornos]]
- [[Anulación de facturas|Anulacion-de-facturas]]
- [[Biblioteca newfenix|Biblioteca-newfenix]]
- [[Suite de pruebas|Suite-de-pruebas]]
- [[Marco normativo|Marco-normativo]]
