# Multiempresa

El módulo VeriFactu para Dolibarr funciona con el módulo Multicompany tratando **cada entidad como un obligado tributario distinto**, con su propia configuración, su propio certificado, su propia cadena de huellas y su propia cola de reintentos. Esta página explica cómo se consigue ese aislamiento en el código (versión 2.2.2), las comprobaciones que evitan configuraciones incompatibles (NIF repetido, numeración compartida), cómo se identifica cada instalación ante la AEAT, cómo activar y configurar el módulo entidad por entidad y qué limitaciones quedan.

## Principio: una entidad, un obligado tributario

Desde la 2.0.0 (aislamiento entre entidades, PR #34) y la 2.2.0 (unicidad de NIF y aviso de numeración) el módulo parte de esta regla: dos entidades de Multicompany con VeriFactu activado son dos obligados tributarios, cada uno con su cadena. En consecuencia:

- Ninguna consulta del módulo usa `getEntity('invoice')`, que incluiría las entidades con las que se comparten facturas; todas se acotan a `$conf->entity`.
- La cadena de huellas se lee, se bloquea y se escribe por entidad y por entorno.
- Un mismo NIF no puede configurarse en dos entidades con VeriFactu activo.
- Compartir la numeración de facturas entre entidades se advierte como incompatible.
- Un mismo certificado sí puede usarse en varias entidades (representante o colaborador social).

## Activación y configuración por entidad

Multicompany activa los módulos externos por entidad: la constante `MAIN_MODULE_VERIFACTU` se crea con la entidad activa. Para cada entidad que deba usar VeriFactu:

1. Cambia a la entidad desde el selector de Multicompany.
2. Activa el módulo en **Inicio > Configuración > Módulos**. `init()` crea las constantes de `$this->const`, el permiso, los menús, la tarea programada y las carpetas para **esa** entidad, y pone la insignia «No Enviada» solo en las facturas de esa entidad:

   ```sql
   UPDATE llx_facture_extrafields SET verifactu_estado = '<insignia>'
   WHERE verifactu_estado IS NULL
     AND fk_object IN (SELECT rowid FROM llx_facture WHERE entity = <entidad>);
   ```

   El valor por defecto «país obligatorio» de `DefaultValues` también se registra con `entity = $conf->entity`.
3. Sube el certificado en **Gestionar Certificados**. La carpeta es `documents/<id_entidad>/verifactu/certificates/` (para la entidad 1, `documents/verifactu/certificates/`), porque Dolibarr redefine la raíz de datos por entidad: `$conf->verifactu->multidir_output[$conf->entity]`.
4. Rellena la **Configuración básica** con la razón social y el NIF **de esa entidad**. `VERIFACTU_HOLDER_COMPANY_NAME` y `VERIFACTU_HOLDER_NIF` se guardan con `dolibarr_set_const(..., $conf->entity)` y Dolibarr carga `llx_const` con `WHERE entity IN (0, n)`, de modo que cada entidad ve solo los suyos.
5. Elige el entorno. `VERIFACTU_FORCE_PRODUCTION_ENVIRONMENT` y `VERIFACTU_COMPANY_TYPE` son también por entidad: una entidad puede estar en producción y otra en pruebas.
6. Descarga los esquemas: la caché está en `documents/<id_entidad>/verifactu/schemas/`, así que hay que pulsar el botón (o hacer el primer envío) en cada entidad.

Los campos adicionales son globales (la definición en `llx_extrafields` la comparte toda la instalación), pero su contenido pertenece a cada factura y cada tercero.

> **Nota:** el registro de cambios de la 2.1.0 explica por qué los datos del obligado tributario ya son configuración por entidad y no se ha parametrizado la declaración responsable: lo que quedaría por parametrizar son los datos del **productor del software**, que es quien suscribe la declaración y que se editan en `conf/declaracion_responsable.conf.php` (véase [[Declaración responsable|Declaracion-responsable]]).

## Aislamiento de las consultas

Todas las consultas del módulo que leen facturas filtran por `f.entity = ((int) $conf->entity)`. `tests/EntityIsolationTest.php` comprueba que estos ficheros no usan `getEntity('invoice')` y sí filtran por la entidad activa:

| Fichero | Consulta | Qué protege |
|---|---|---|
| `lib/functions/functions.hash.php` | `getLastInvoiceHash()` | El registro anterior de la cadena nunca viene de otra entidad |
| `class/verifactu.utils.php` | `getInvoicesWithConnectionErrors()` | La cola de reintentos solo procesa la entidad activa, con su configuración y certificado |
| `core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php` | `companyModify()` | El bloqueo del NIF del tercero cuenta solo facturas de la entidad |
| `views/list.facture.php` | Lista «Facturas emitidas» | Comentario en el código: «VeriFactu data is per legal entity, never shared across entities» |
| `views/query.facture.php` | Búsqueda de la vista de consulta | Consulta a la AEAT acotada a la entidad |
| `verifactuindex.php` | Las siete consultas del panel | Estadísticas por entidad |
| `integrity.php` | Recuento de facturas | Ya no cuenta las de todas las entidades (`WHERE 1=1`) |
| `lib/functions/functions.configuration.php` | `hasVerifactuFiscalRecords($entity)` | El bloqueo del NIF se evalúa por entidad |

El registro de cambios de la 2.0.0 resume el motivo: con `getEntity('invoice')`, en una instalación que comparte facturas «en la cadena fiscal eso encadena registros de otro obligado tributario». Además, el test recuerda que `getEntity()` devuelve una lista separada por comas, lo que rompía la consulta `f.entity = ...` en cuanto se activaba la compartición.

## Cadena por entidad y por entorno

`getLastInvoiceHash()` busca la última factura validada de la entidad activa con huella no vacía y `verifactu_entorno` igual al entorno actual:

```sql
SELECT fe.verifactu_huella, f.ref, f.datef
FROM llx_facture f
INNER JOIN llx_facture_extrafields fe ON f.rowid = fe.fk_object
WHERE f.entity = <entidad>
  AND fe.verifactu_huella IS NOT NULL AND fe.verifactu_huella != ''
  AND fe.verifactu_entorno = '<test|production>'
  AND f.fk_statut > 0
ORDER BY f.rowid DESC
LIMIT 1
```

La sección crítica (leer la huella anterior, enviar, guardar la nueva) se serializa con un bloqueo cuyo nombre incluye entidad y entorno, `verifactu_chain_<entidad>_<entorno>` (`getVerifactuChainLockName()`): dos entidades, o las cadenas de pruebas y producción de una misma entidad, nunca se esperan entre sí. En MySQL es `GET_LOCK()`; en PostgreSQL, `pg_try_advisory_lock(1007, <clave>)`, donde la clave es el `crc32` del nombre convertido a entero con signo (`getVerifactuChainLockKey()`). El tiempo de espera es `VERIFACTU_CHAIN_LOCK_TIMEOUT` (30 segundos), también configurable por entidad. Véase [[Encadenamiento y huella|Encadenamiento-y-huella]].

## Un NIF por entidad

### Qué se comprueba

Al guardar la configuración (`admin/setup.php`, `action=update`), antes de que `FormSetup` escriba nada, se llama a `getVerifactuEntityWithSameTaxId($nifEnviado, $conf->entity)` (`lib/functions/functions.configuration.php`). La función:

1. Devuelve `null` si el NIF está vacío o Multicompany no está activo (`isModEnabled('multicompany')`).
2. Normaliza el NIF con `normalizeVerifactuTaxIdentifier()`: `trim`, mayúsculas y eliminación de todo lo que no sea letra o dígito, de modo que ` b-12345678 ` y `B12345678` se consideran iguales.
3. Consulta **solo tablas del núcleo**: busca en `llx_const` las filas `VERIFACTU_HOLDER_NIF` de otras entidades cuyo `MAIN_MODULE_VERIFACTU` valga `1`:

   ```sql
   SELECT taxpayer.entity, taxpayer.value
   FROM llx_const AS taxpayer
   INNER JOIN llx_const AS module ON module.entity = taxpayer.entity
     AND module.name = 'MAIN_MODULE_VERIFACTU' AND module.value = '1'
   WHERE taxpayer.name = 'VERIFACTU_HOLDER_NIF'
     AND taxpayer.entity <> <entidad actual>
   ```

   (con `$db->decrypt()` sobre nombre y valor). No lee `llx_entity` ni ninguna estructura interna de Multicompany, que cambia entre versiones.
4. Compara normalizado y devuelve el id de la primera entidad en conflicto.

Si hay conflicto, la pantalla muestra el error `VERIFACTU_TAX_IDENTITY_ALREADY_USED`: «El NIF del obligado tributario ya está configurado en la entidad VeriFactu %s. Cada entidad con VeriFactu activado es un obligado tributario distinto, con su propia cadena de registros.» y no se guarda ningún campo.

### Diseño: falla en abierto y fuera del envío

- Si la consulta falla, la función escribe el error en el registro y devuelve `null`: un error de base de datos **no bloquea** la pantalla de configuración. Lo que protege una cadena ya existente es el bloqueo del NIF de la 2.0.0 (`hasVerifactuFiscalRecords()`), que falla en cerrado.
- Nada de esto se ejecuta en el camino del envío. `tests/MultiEntityAndCancellationTest.php` comprueba que `functions.submission.php` no llama a `getVerifactuEntityWithSameTaxId()`: allí un `false` revierte la factura a borrador, y un fallo transitorio de base de datos habría desvalidado facturas ya emitidas.
- Las entidades con VeriFactu **desactivado** no cuentan, aunque conserven un `VERIFACTU_HOLDER_NIF` antiguo en `llx_const`.

### Lo que no se comprueba

La 2.2.0 descartó la unicidad de la huella del certificado entre entidades que proponía el mismo PR #34: un certificado de representante o de colaborador social puede firmar legítimamente por varios obligados tributarios. Tampoco se comprueban las opciones de compartición de Multicompany más allá de la numeración de facturas.

## Numeración de facturas compartida

`isVerifactuInvoiceNumberingShared()` devuelve `true` cuando Multicompany está activo y tanto `MULTICOMPANY_SHARINGS_ENABLED` como `MULTICOMPANY_INVOICENUMBER_SHARING_ENABLED` están informadas. `admin/setup.php` la evalúa en **cada carga** de la página y, si procede, muestra el aviso `VERIFACTU_SHARED_INVOICE_NUMBERING_WARNING`:

> Multicompany está compartiendo la numeración de facturas entre entidades. Dos obligados tributarios tomarían entonces su serie del mismo contador, algo que VeriFactu no puede conciliar: cada obligado debe mantener su propia numeración.

Es un aviso, no un bloqueo: el módulo no puede corregir la configuración de Multicompany. Se lee de las constantes de configuración del propio módulo Multicompany, no de sus tablas. Para resolverlo, desactiva la compartición de numeración de facturas en la configuración de Multicompany antes de emitir facturas con VeriFactu en más de una entidad. Véase [[Fechas y numeración|Fechas-y-numeracion]].

## Número de instalación por entidad

`getSystemConfig()` transmite en cada registro:

```
NumeroInstalacion = <dolibarr_main_instance_unique_id>_<id_entidad>   (recortado a 100 caracteres)
```

`$dolibarr_main_instance_unique_id` es el identificador único de la instalación definido en `conf/conf.php`, común a todas las entidades; el sufijo `_<entidad>` distingue cada obligado dentro de la misma instalación de Dolibarr. Los demás campos del bloque `SistemaInformatico` (`NombreSistemaInformatico = Dolibarr Verifactu Module`, `IdSistemaInformatico = DV`, `Version = 2.2.2`, `TipoUsoPosibleSoloVerifactu = S`, `TipoUsoPosibleMultiOT = N`, `IndicadorMultiplesOT = N`) son los mismos para todas las entidades.

La página de declaración responsable calcula un identificador de instalación distinto, `generarIdInstalacion()`, a partir del id del sistema, la versión, el nombre y NIF de la empresa y el nombre del servidor; ese valor es informativo y no es el que viaja a la AEAT.

## Bloqueo del NIF por entidad

`hasVerifactuFiscalRecords($entity)` busca una factura **de esa entidad** con `verifactu_huella` no vacía. Si existe, `admin/setup.php` rechaza cualquier cambio de `VERIFACTU_HOLDER_NIF` con `VERIFACTU_TAX_IDENTITY_LOCKED`. Cada entidad se bloquea por separado: que la entidad 1 tenga registros no impide corregir el NIF de la entidad 2 mientras esta no haya generado ninguna huella. Si la consulta falla, la función devuelve `true` (bloqueado), porque «la identidad fiscal no debe cambiar mientras el estado de la cadena no pueda verificarse».

## Reintentos, panel y restricciones por entidad

- **Cola de reintentos**: la tarea programada se registra por entidad al activar el módulo y `getInvoicesWithConnectionErrors()` solo lee facturas de `$conf->entity`. Cada entidad reenvía con su propio certificado y su propio `VERIFACTU_NEXT_SEND_AT` (`registerAEATWaitTime()` guarda la constante con `$conf->entity`).
- **Panel principal** (`verifactuindex.php`): todas las estadísticas se filtran por entidad.
- **Bloqueo del NIF del tercero**: `companyModify()` impide cambiar el CIF/NIF o el NIF intracomunitario de un tercero que tenga facturas enviadas **en la entidad activa**. Con terceros compartidos entre entidades, el mismo tercero podría modificarse desde una entidad en la que no tenga facturas VeriFactu; el trigger solo ve las de la entidad desde la que se edita.
- **Restricciones sobre facturas enviadas**: se evalúan sobre la factura concreta, que pertenece a una entidad; no dependen de la compartición.

## Limitaciones

- **Sin vista consolidada.** No hay ninguna pantalla que muestre las facturas VeriFactu de todas las entidades; hay que cambiar de entidad.
- **Sin comprobación del certificado por entidad.** El módulo no verifica que el certificado subido en una entidad corresponda al NIF configurado en ella; véase [[Certificados digitales|Certificados-digitales]].
- **Numeración compartida solo se avisa**, no se impide.
- **Facturas compartidas.** Si Multicompany comparte facturas entre entidades, una factura emitida en la entidad A y visible desde la B no entra en la cadena ni en las listas de B; sigue siendo un registro de A.
- **Declaración responsable única.** Los datos del productor y la identidad del sistema son los mismos para toda la instalación (`conf/declaracion_responsable.conf.php`).
- **Campos adicionales globales.** Desactivar el módulo en una entidad no elimina los campos `verifactu_*`, que son comunes a la instalación; tampoco borra las constantes de esa entidad.
- **Bloqueo de cadena por motor.** En motores distintos de MySQL/MariaDB y PostgreSQL no hay bloqueo de aplicación, sea cual sea el número de entidades.

## Véase también

- [[Configuración|Configuracion]]
- [[Certificados digitales|Certificados-digitales]]
- [[Encadenamiento y huella|Encadenamiento-y-huella]]
- [[Fechas y numeración|Fechas-y-numeracion]]
- [[Reintentos y envíos pendientes|Reintentos-y-envios-pendientes]]
- [[Declaración responsable|Declaracion-responsable]]
- [[Suite de pruebas|Suite-de-pruebas]]
- [[Instalación|Instalacion]]
