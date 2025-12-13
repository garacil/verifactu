# Privacy Violations and Legal Compliance Analysis

## Analyzed File
**File:** `verifactu_easysoft.1.4.3/lib/controlLicencia.lib.php`
**Module:** VeriFactu by Easysoft (version 1.4.3)
**Analysis date:** December 11, 2025

---

## Executive Summary

The `controlLicencia.lib.php` file and the VeriFactu module by Easysoft contain **multiple serious violations** of European and Spanish data protection regulations, as well as practices that violate user privacy without their knowledge or consent.

---

## 1. General Data Protection Regulation (GDPR) Violations

### 1.1. Data Collection without Consent (Art. 6 and 7 GDPR)

The module collects and transmits personal and business data **without requesting explicit consent** from the user:

**Data collected in `getDetailedEnvironmentInfo()` (lines 448-591):**

```php
// Company information (personal/business data)
$companyInfo = array(
    'name' => $conf->global->MAIN_INFO_SOCIETE_NOM,      // Company name
    'address' => $conf->global->MAIN_INFO_SOCIETE_ADDRESS, // Physical address
    'zip' => $conf->global->MAIN_INFO_SOCIETE_ZIP,       // Postal code
    'city' => $conf->global->MAIN_INFO_SOCIETE_TOWN,     // City
    'country' => $conf->global->MAIN_INFO_SOCIETE_COUNTRY, // Country
    'phone' => $conf->global->MAIN_INFO_SOCIETE_TEL,     // Phone
    'email' => $conf->global->MAIN_INFO_SOCIETE_MAIL     // Corporate email
);
```

**Violation:** GDPR requires free, specific, informed and unambiguous consent (Art. 7). There is no consent mechanism prior to the transmission of this data.

### 1.2. Excessive Data Collection - Violation of the Minimization Principle (Art. 5.1.c GDPR)

The module collects data that **exceeds what is necessary** for license verification:

**Client/user data (lines 482-491):**
```php
$clientInfo = array(
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'client_ip' => $_SERVER['REMOTE_ADDR'],           // User's IP
    'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'],
    'real_ip' => $_SERVER['HTTP_X_REAL_IP'],
    'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
    'referer' => $_SERVER['HTTP_REFERER']
);
```

**Server data (lines 455-479):**
```php
$serverIp = $_SERVER['SERVER_ADDR'];     // Private server IP
$publicIp = file_get_contents('https://api.ipify.org'); // Public IP
```

**System data (lines 495-506):**
```php
$systemInfo = array(
    'os' => php_uname(),                    // Full operating system
    'php_version' => phpversion(),
    'document_root' => $_SERVER['DOCUMENT_ROOT'], // Server path
    'script_name' => $_SERVER['SCRIPT_NAME'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    // ...
);
```

**Violation:** To verify a license, only the serial number and domain would be needed. Collecting IPs, server paths, operating system information, company data, etc., violates the data minimization principle.

### 1.3. Lack of Transparency (Art. 13 and 14 GDPR)

There is no clear information to the user about:
- What data is collected
- For what purpose
- Who is the data controller
- How long data is retained
- Data subject rights

**Violation:** GDPR requires clear and accessible information before data collection.

### 1.4. Transfer of Data to Third Parties without Legal Basis (Art. 44-49 GDPR)

Data is sent to external servers (`sdl.easysoft.es`) without:
- Verifying if the destination complies with GDPR
- Informing the user of the transfer
- Obtaining consent for the transfer

**Identified endpoints:**
- `https://sdl.easysoft.es/getVerifactuDeclaracionResponsable` (line 305)
- `https://sdl.easysoft.es/ping` (line 601)
- `https://sdl.easysoft.es/storeVerifactuPublicPEM` (managecertificates.php:559)
- `https://sdl.easysoft.es/downloadModule` (managedownload.php:140)
- `https://sdl.easysoft.es/getLastModuleVersion` (modVerifactu.class.php:81)

---

## 2. Spanish Organic Law on Data Protection (LOPDGDD) Violations

### 2.1. Breach of Duty to Inform (Art. 11 LOPDGDD)

The LOPDGDD reinforces the GDPR's duty to inform, requiring it to be:
- Concise
- Transparent
- Intelligible
- Easily accessible

**Violation:** The module does not provide any information to the user about data processing.

### 2.2. Processing of Business Contact Data (Art. 19 LOPDGDD)

Although Art. 19 allows processing of business contact data for commercial relationships, this **does not exempt** from:
- Informing about the processing
- Respecting the right to object
- Applying the minimization principle

**Violation:** Data collected exceeds business contact data (IPs, server paths, system technical information).

---

## 3. Information Society Services Law (LSSI) Violations

### 3.1. Unsolicited Commercial Communications (Art. 21 LSSI)

The "ping" function (line 599, critically named `_____()` to obfuscate its purpose) periodically sends information without consent:

```php
function _____($module = 'verifactu')
{
    $baseUrl = "https://sdl.easysoft.es/ping";
    // Sends ALL environment information
    $environmentData = getDetailedEnvironmentInfo();
    // ...
}
```

**Violation:** This constitutes unsolicited electronic communication for commercial purposes (installation tracking).

---

## 4. Privacy Violations and Questionable Practices

### 4.1. Sending Digital Certificates to External Servers

In `managecertificates.php` (lines 545-559):
```php
$payload = json_encode([
    // ...
    "public_pem" => $publicPem,  // PUBLIC CERTIFICATE!
]);
$url = "https://sdl.easysoft.es/storeVerifactuPublicPEM";
```

**Critical risk:** Even though it is the public part of the certificate, sending digital certificates to third parties without explicit consent represents a security risk and a breach of user trust.

### 4.2. Obfuscation of Malicious Functions

The ping function is named as `_____()` (five underscores) to hide its purpose:

```php
function _____($module = 'verifactu')
{
    $baseUrl = "https://sdl.easysoft.es/ping";
    // ...
}
```

**Questionable practice:** The use of obfuscated names suggests intent to hide tracking/telemetry functionality.

### 4.3. Obtaining Public IP without Consent

```php
$publicIp = @file_get_contents('https://api.ipify.org?format=text');
if (!$publicIp || !filter_var($publicIp, FILTER_VALIDATE_IP)) {
    $publicIp = @file_get_contents('https://httpbin.org/ip');
}
```

**Violation:** External services are contacted to obtain the server's public IP, exposing sensitive network information.

### 4.4. Collection of Database Information

```php
$dbInfo = array(
    'type' => $db->type,
    'version' => $db->getVersion(),
    'db_name' => $conf->db->name,  // Database name
    // ...
);
```

**Risk:** The database name can reveal sensitive information about the infrastructure.

---

## 5. Potential Sanctions

### 5.1. Under GDPR

- **Serious infringements (Art. 83.4):** Up to €10,000,000 or 2% of turnover
- **Very serious infringements (Art. 83.5):** Up to €20,000,000 or 4% of turnover

### 5.2. Under LOPDGDD

- **Minor infringements:** Up to €40,000
- **Serious infringements:** Up to €300,000
- **Very serious infringements:** Up to €20,000,000

### 5.3. Under LSSI

- **Serious infringements:** Up to €150,000

---

## 6. Summary of Data Transmitted without Consent

| Category | Specific Data | Risk Level |
|----------|---------------|------------|
| **Company** | Name, address, phone, email | HIGH |
| **Network** | Private IP, public IP, domain | HIGH |
| **System** | OS, PHP version, server paths | MEDIUM |
| **User** | Client IP, User-Agent, language | HIGH |
| **Database** | Type, version, name | MEDIUM |
| **Certificates** | Public PEM | CRITICAL |
| **License** | Serial, integrity hash | LOW |

---

## 7. Recommendations

1. **Remove** collection of data unnecessary for license verification
2. **Implement** an explicit consent mechanism before any transmission
3. **Clearly inform** the user what data is collected and for what purpose
4. **Remove** the obfuscated telemetry function `_____()`
5. **Do not transmit** digital certificates to external servers
6. **Limit** license verification data to serial and domain only
7. **Provide** a clear and accessible privacy policy

---

## 8. Conclusion

The VeriFactu module by Easysoft presents **serious and systematic violations** of European and Spanish data protection regulations. The massive collection of technical and business information, combined with its transmission to external servers without consent or user information, constitutes a practice that could be considered **corporate spyware**.

The use of obfuscated function names (`_____`) to hide telemetry functionalities aggravates the situation, suggesting intentionality in hiding these practices.

It is strongly recommended **not to use this module** in its current version and to report these practices to the Spanish Data Protection Agency (AEPD) if you have been affected by this data processing.

---

**Document generated:** December 11, 2025
**Analysis performed on:** verifactu_easysoft v1.4.3
