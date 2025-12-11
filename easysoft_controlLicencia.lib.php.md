# Análisis de Violaciones de Privacidad y Normativa Legal

## Archivo Analizado
**Fichero:** `verifactu_easysoft.1.4.3/lib/controlLicencia.lib.php`
**Módulo:** VeriFactu de Easysoft (versión 1.4.3)
**Fecha de análisis:** 11 de diciembre de 2025

---

## Resumen Ejecutivo

El fichero `controlLicencia.lib.php` y el módulo VeriFactu de Easysoft contienen **múltiples violaciones graves** de la normativa europea y española de protección de datos, así como prácticas que vulneran la privacidad de los usuarios sin su conocimiento ni consentimiento.

---

## 1. Violaciones del Reglamento General de Protección de Datos (RGPD/GDPR)

### 1.1. Recopilación de Datos sin Consentimiento (Art. 6 y 7 RGPD)

El módulo recopila y transmite datos personales y empresariales **sin solicitar consentimiento explícito** del usuario:

**Datos recopilados en `getDetailedEnvironmentInfo()` (líneas 448-591):**

```php
// Información de la empresa (datos personales/empresariales)
$companyInfo = array(
    'name' => $conf->global->MAIN_INFO_SOCIETE_NOM,      // Razón social
    'address' => $conf->global->MAIN_INFO_SOCIETE_ADDRESS, // Dirección física
    'zip' => $conf->global->MAIN_INFO_SOCIETE_ZIP,       // Código postal
    'city' => $conf->global->MAIN_INFO_SOCIETE_TOWN,     // Ciudad
    'country' => $conf->global->MAIN_INFO_SOCIETE_COUNTRY, // País
    'phone' => $conf->global->MAIN_INFO_SOCIETE_TEL,     // Teléfono
    'email' => $conf->global->MAIN_INFO_SOCIETE_MAIL     // Email corporativo
);
```

**Violación:** El RGPD exige consentimiento libre, específico, informado e inequívoco (Art. 7). No existe ningún mecanismo de consentimiento previo a la transmisión de estos datos.

### 1.2. Recopilación Excesiva de Datos - Violación del Principio de Minimización (Art. 5.1.c RGPD)

El módulo recopila datos que **exceden lo necesario** para la verificación de licencia:

**Datos del cliente/usuario (líneas 482-491):**
```php
$clientInfo = array(
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'client_ip' => $_SERVER['REMOTE_ADDR'],           // IP del usuario
    'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'],
    'real_ip' => $_SERVER['HTTP_X_REAL_IP'],
    'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
    'referer' => $_SERVER['HTTP_REFERER']
);
```

**Datos del servidor (líneas 455-479):**
```php
$serverIp = $_SERVER['SERVER_ADDR'];     // IP privada del servidor
$publicIp = file_get_contents('https://api.ipify.org'); // IP pública
```

**Datos del sistema (líneas 495-506):**
```php
$systemInfo = array(
    'os' => php_uname(),                    // Sistema operativo completo
    'php_version' => phpversion(),
    'document_root' => $_SERVER['DOCUMENT_ROOT'], // Ruta del servidor
    'script_name' => $_SERVER['SCRIPT_NAME'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    // ...
);
```

**Violación:** Para verificar una licencia solo se necesitaría el número de serie y el dominio. La recopilación de IPs, rutas del servidor, información del sistema operativo, datos de la empresa, etc., viola el principio de minimización de datos.

### 1.3. Falta de Transparencia (Art. 13 y 14 RGPD)

No existe información clara al usuario sobre:
- Qué datos se recopilan
- Para qué finalidad
- Quién es el responsable del tratamiento
- Cuánto tiempo se conservan
- Derechos del interesado

**Violación:** El RGPD obliga a informar de manera clara y accesible antes de la recogida de datos.

### 1.4. Transferencia de Datos a Terceros sin Base Legal (Art. 44-49 RGPD)

Los datos se envían a servidores externos (`sdl.easysoft.es`) sin:
- Verificar si el destino cumple con el RGPD
- Informar al usuario de la transferencia
- Obtener consentimiento para la transferencia

**Endpoints identificados:**
- `https://sdl.easysoft.es/getVerifactuDeclaracionResponsable` (línea 305)
- `https://sdl.easysoft.es/ping` (línea 601)
- `https://sdl.easysoft.es/storeVerifactuPublicPEM` (managecertificates.php:559)
- `https://sdl.easysoft.es/downloadModule` (managedownload.php:140)
- `https://sdl.easysoft.es/getLastModuleVersion` (modVerifactu.class.php:81)

---

## 2. Violaciones de la Ley Orgánica de Protección de Datos (LOPDGDD) - España

### 2.1. Incumplimiento del Deber de Información (Art. 11 LOPDGDD)

La LOPDGDD refuerza el deber de información del RGPD, exigiendo que sea:
- Concisa
- Transparente
- Inteligible
- De fácil acceso

**Violación:** El módulo no proporciona ninguna información al usuario sobre el tratamiento de datos.

### 2.2. Tratamiento de Datos de Contacto Empresarial (Art. 19 LOPDGDD)

Aunque el Art. 19 permite el tratamiento de datos de contacto empresarial para relaciones comerciales, esto **no exime** de:
- Informar del tratamiento
- Respetar el derecho de oposición
- Aplicar el principio de minimización

**Violación:** Se recopilan datos que exceden los de contacto empresarial (IPs, rutas de servidor, información técnica del sistema).

---

## 3. Violaciones de la Ley de Servicios de la Sociedad de la Información (LSSI)

### 3.1. Comunicaciones Comerciales no Solicitadas (Art. 21 LSSI)

La función de "ping" (línea 599, nombrada críticamente `_____()` para ofuscar su propósito) envía información periódica sin consentimiento:

```php
function _____($module = 'verifactu')
{
    $baseUrl = "https://sdl.easysoft.es/ping";
    // Envía TODA la información del entorno
    $environmentData = getDetailedEnvironmentInfo();
    // ...
}
```

**Violación:** Constituye una comunicación electrónica no solicitada con fines comerciales (tracking de instalaciones).

---

## 4. Violaciones de Privacidad y Prácticas Cuestionables

### 4.1. Envío de Certificados Digitales a Servidores Externos

En `managecertificates.php` (líneas 545-559):
```php
$payload = json_encode([
    // ...
    "public_pem" => $publicPem,  // ¡CERTIFICADO PÚBLICO!
]);
$url = "https://sdl.easysoft.es/storeVerifactuPublicPEM";
```

**Riesgo crítico:** Aunque sea la parte pública del certificado, enviar certificados digitales a terceros sin consentimiento explícito representa un riesgo de seguridad y una violación de la confianza del usuario.

### 4.2. Ofuscación de Funciones Maliciosas

La función de ping está nombrada como `_____()` (cinco guiones bajos) para ocultar su propósito:

```php
function _____($module = 'verifactu')
{
    $baseUrl = "https://sdl.easysoft.es/ping";
    // ...
}
```

**Práctica cuestionable:** El uso de nombres ofuscados sugiere intención de ocultar la funcionalidad de tracking/telemetría.

### 4.3. Obtención de IP Pública sin Consentimiento

```php
$publicIp = @file_get_contents('https://api.ipify.org?format=text');
if (!$publicIp || !filter_var($publicIp, FILTER_VALIDATE_IP)) {
    $publicIp = @file_get_contents('https://httpbin.org/ip');
}
```

**Violación:** Se contacta con servicios externos para obtener la IP pública del servidor, exponiendo información de red sensible.

### 4.4. Recopilación de Información de Base de Datos

```php
$dbInfo = array(
    'type' => $db->type,
    'version' => $db->getVersion(),
    'db_name' => $conf->db->name,  // Nombre de la base de datos
    // ...
);
```

**Riesgo:** El nombre de la base de datos puede revelar información sensible sobre la infraestructura.

---

## 5. Posibles Sanciones

### 5.1. Bajo el RGPD

- **Infracciones graves (Art. 83.4):** Hasta 10.000.000€ o 2% del volumen de negocio
- **Infracciones muy graves (Art. 83.5):** Hasta 20.000.000€ o 4% del volumen de negocio

### 5.2. Bajo la LOPDGDD

- **Infracciones leves:** Hasta 40.000€
- **Infracciones graves:** Hasta 300.000€
- **Infracciones muy graves:** Hasta 20.000.000€

### 5.3. Bajo la LSSI

- **Infracciones graves:** Hasta 150.000€

---

## 6. Resumen de Datos Transmitidos sin Consentimiento

| Categoría | Datos Específicos | Nivel de Riesgo |
|-----------|-------------------|-----------------|
| **Empresa** | Nombre, dirección, teléfono, email | ALTO |
| **Red** | IP privada, IP pública, dominio | ALTO |
| **Sistema** | OS, PHP version, rutas del servidor | MEDIO |
| **Usuario** | IP cliente, User-Agent, idioma | ALTO |
| **Base de datos** | Tipo, versión, nombre | MEDIO |
| **Certificados** | PEM público | CRÍTICO |
| **Licencia** | Serial, hash de integridad | BAJO |

---

## 7. Recomendaciones

1. **Eliminar** la recopilación de datos innecesarios para la verificación de licencia
2. **Implementar** un mecanismo de consentimiento explícito antes de cualquier transmisión
3. **Informar** claramente al usuario de qué datos se recopilan y para qué
4. **Eliminar** la función ofuscada `_____()` de telemetría
5. **No transmitir** certificados digitales a servidores externos
6. **Limitar** los datos de verificación de licencia al serial y dominio únicamente
7. **Proporcionar** una política de privacidad clara y accesible

---

## 8. Conclusión

El módulo VeriFactu de Easysoft presenta **violaciones graves y sistemáticas** de la normativa de protección de datos europea y española. La recopilación masiva de información técnica y empresarial, combinada con su transmisión a servidores externos sin consentimiento ni información al usuario, constituye una práctica que podría ser considerada **spyware corporativo**.

El uso de nombres de función ofuscados (`_____`) para ocultar funcionalidades de telemetría agrava la situación, sugiriendo intencionalidad en la ocultación de estas prácticas.

Se recomienda encarecidamente **no utilizar este módulo** en su versión actual y reportar estas prácticas a la Agencia Española de Protección de Datos (AEPD) si se ha visto afectado por este tratamiento de datos.

---

**Documento generado:** 11 de diciembre de 2025
**Análisis realizado sobre:** verifactu_easysoft v1.4.3
