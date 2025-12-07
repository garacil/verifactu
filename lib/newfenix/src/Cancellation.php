<?php

/**
 * OpenAEAT Billing Library - Cancellation Class
 *
 * This class manages invoice cancellation records for the Spanish Tax Agency
 * (AEAT) VeriFactu system. Supports standard cancellations, corrections for
 * previously rejected invoices, and cancellations without prior registration.
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

/**
 * VeriFactu Cancellation Record Builder
 */
class Cancellation
{
    /**
     * Protocol version identifier
     */
    private const SCHEMA_VERSION = '1.0';

    /**
     * Cryptographic algorithm identifier
     */
    private const HASH_ALGORITHM = 'sha256';

    /**
     * Hash type code for AEAT
     */
    private const HASH_TYPE_CODE = '01';

    /**
     * Timestamp format pattern
     */
    private const TIMESTAMP_PATTERN = 'Y-m-d\TH:i:sP';

    /**
     * Default timezone for timestamp generation
     */
    private const DEFAULT_ZONE = 'Europe/Madrid';

    /**
     * Field separator for hash input
     */
    private const HASH_DELIMITER = '&';

    /**
     * Emitter identity information
     */
    private array $emitterIdentity = [
        'taxId' => '',
        'legalName' => '',
    ];

    /**
     * Target document identification
     */
    private array $targetDocument = [
        'emitterTaxId' => '',
        'serialNumber' => '',
        'issueDate' => '',
    ];

    /**
     * Registration payload data
     */
    private array $registrationPayload = [];

    /**
     * Operational state flags
     */
    private array $operationalFlags = [
        'previousRejection' => false,
        'withoutPriorRecord' => false,
    ];

    /**
     * Initializes a new cancellation record.
     *
     * @param string $invoiceNumberToCancel The invoice number to cancel
     * @param string $invoiceDateToCancel The invoice date to cancel (format: dd-mm-yyyy)
     * @param string $issuerNIFToCancel NIF of the issuer of the invoice to cancel
     * @param string $issuerNIF The VAT number of the current issuer (submitting the cancellation)
     * @param string $issuerName The name of the current issuer
     */
    public function __construct(
        string $invoiceNumberToCancel = '',
        string $invoiceDateToCancel = '',
        string $issuerNIFToCancel = '',
        string $issuerNIF = '',
        string $issuerName = ''
    ) {
        $this->emitterIdentity['taxId'] = $issuerNIF;
        $this->emitterIdentity['legalName'] = $issuerName;

        $this->targetDocument['emitterTaxId'] = $issuerNIFToCancel;
        $this->targetDocument['serialNumber'] = $invoiceNumberToCancel;
        $this->targetDocument['issueDate'] = $invoiceDateToCancel;

        $this->initializePayload();
    }

    /**
     * Initializes the base registration payload.
     */
    private function initializePayload(): void
    {
        $this->registrationPayload = [
            'IDVersion' => self::SCHEMA_VERSION,
            'IDFactura' => [
                'IDEmisorFacturaAnulada' => $this->targetDocument['emitterTaxId'],
                'NumSerieFacturaAnulada' => $this->targetDocument['serialNumber'],
                'FechaExpedicionFacturaAnulada' => $this->targetDocument['issueDate'],
            ],
            'FechaHoraHusoGenRegistro' => $this->resolveCurrentTimestamp(),
            'TipoHuella' => self::HASH_TYPE_CODE,
        ];
    }

    /**
     * Resolves the current timestamp in ISO 8601 format.
     *
     * @param string $zone Timezone identifier
     * @return string Formatted timestamp
     */
    private function resolveCurrentTimestamp(string $zone = self::DEFAULT_ZONE): string
    {
        return (new \DateTime('now', new \DateTimeZone($zone)))->format(self::TIMESTAMP_PATTERN);
    }

    /**
     * Computes the SHA-256 cryptographic hash.
     *
     * @param string $input Data to hash
     * @return string Uppercase hex digest
     */
    private function computeCryptographicHash(string $input): string
    {
        $normalized = mb_convert_encoding($input, 'UTF-8');
        return strtoupper(hash(self::HASH_ALGORITHM, $normalized));
    }

    /**
     * Assembles the hash input string per AEAT specification.
     *
     * @return string Concatenated field values
     */
    private function assembleHashInput(): string
    {
        $components = [
            'IDEmisorFacturaAnulada=' . $this->registrationPayload['IDFactura']['IDEmisorFacturaAnulada'],
            'NumSerieFacturaAnulada=' . $this->registrationPayload['IDFactura']['NumSerieFacturaAnulada'],
            'FechaExpedicionFacturaAnulada=' . $this->registrationPayload['IDFactura']['FechaExpedicionFacturaAnulada'],
            'Huella=' . ($this->registrationPayload['Encadenamiento']['RegistroAnterior']['Huella'] ?? ''),
            'FechaHoraHusoGenRegistro=' . $this->registrationPayload['FechaHoraHusoGenRegistro'],
        ];

        return implode(self::HASH_DELIMITER, $components);
    }

    /**
     * Verifies all required fields are present.
     *
     * @throws \InvalidArgumentException On missing fields
     */
    private function verifyRequiredFields(): void
    {
        $mandatoryFields = ['IDVersion', 'IDFactura', 'FechaHoraHusoGenRegistro', 'TipoHuella'];

        foreach ($mandatoryFields as $field) {
            if (!isset($this->registrationPayload[$field]) || $this->registrationPayload[$field] === '') {
                throw new \InvalidArgumentException("Required field missing: {$field}");
            }
        }

        $invoiceFields = ['IDEmisorFacturaAnulada', 'NumSerieFacturaAnulada', 'FechaExpedicionFacturaAnulada'];
        foreach ($invoiceFields as $field) {
            if (!isset($this->registrationPayload['IDFactura'][$field])) {
                throw new \InvalidArgumentException("IDFactura incomplete");
            }
        }
    }

    /**
     * Verifies chain linkage is properly configured.
     *
     * @throws \InvalidArgumentException If chain not configured
     */
    private function verifyChainConfiguration(): void
    {
        $hasPreviousRecord = isset($this->registrationPayload['Encadenamiento']['RegistroAnterior']);
        $isFirstRecord = isset($this->registrationPayload['Encadenamiento']['PrimerRegistro']);

        if (!$hasPreviousRecord && !$isFirstRecord) {
            throw new \InvalidArgumentException("Must have chain link to previous record or be marked as first in chain");
        }
    }

    /**
     * Synchronizes internal state with payload.
     */
    private function synchronizeDocumentIdentification(): void
    {
        $this->registrationPayload['IDFactura']['IDEmisorFacturaAnulada'] = $this->targetDocument['emitterTaxId'];
        $this->registrationPayload['IDFactura']['NumSerieFacturaAnulada'] = $this->targetDocument['serialNumber'];
        $this->registrationPayload['IDFactura']['FechaExpedicionFacturaAnulada'] = $this->targetDocument['issueDate'];
    }

    /**
     * Constructs the header block for registration.
     *
     * @return array Header structure
     */
    private function constructHeaderBlock(): array
    {
        return [
            'IDVersion' => self::SCHEMA_VERSION,
            'ObligadoEmision' => [
                'NombreRazon' => $this->emitterIdentity['legalName'],
                'NIF' => $this->emitterIdentity['taxId'],
            ],
        ];
    }

    // =========================================================================
    // STATIC FACTORY METHODS
    // =========================================================================

    /**
     * Creates a normal (standard) cancellation.
     *
     * Use this for cancelling invoices that were previously accepted by AEAT.
     *
     * @param string $invoiceNumberToCancel Invoice number to cancel
     * @param string $invoiceDateToCancel Invoice date to cancel
     * @param string $issuerNIFToCancel NIF of the original invoice issuer
     * @param string $issuerNIF NIF of the entity submitting the cancellation
     * @param string $issuerName Name of the entity submitting the cancellation
     * @return self
     */
    public static function createNormal(
        string $invoiceNumberToCancel,
        string $invoiceDateToCancel,
        string $issuerNIFToCancel,
        string $issuerNIF,
        string $issuerName
    ): self {
        return new self($invoiceNumberToCancel, $invoiceDateToCancel, $issuerNIFToCancel, $issuerNIF, $issuerName);
    }

    /**
     * Creates a cancellation for a previously rejected invoice.
     *
     * Use this when AEAT rejected the original invoice submission and you
     * need to cancel it before resubmitting a corrected version.
     *
     * @param string $invoiceNumberToCancel Invoice number to cancel
     * @param string $invoiceDateToCancel Invoice date to cancel
     * @param string $issuerNIFToCancel NIF of the original invoice issuer
     * @param string $issuerNIF NIF of the entity submitting the cancellation
     * @param string $issuerName Name of the entity submitting the cancellation
     * @return self
     */
    public static function createForPreviousRejection(
        string $invoiceNumberToCancel,
        string $invoiceDateToCancel,
        string $issuerNIFToCancel,
        string $issuerNIF,
        string $issuerName
    ): self {
        $instance = new self($invoiceNumberToCancel, $invoiceDateToCancel, $issuerNIFToCancel, $issuerNIF, $issuerName);
        $instance->setAsPreviousRejection(true);
        return $instance;
    }

    /**
     * Creates a cancellation without previous AEAT record.
     *
     * Use this when cancelling an invoice that was never successfully
     * registered with AEAT (e.g., communication failure during submission).
     *
     * @param string $invoiceNumberToCancel Invoice number to cancel
     * @param string $invoiceDateToCancel Invoice date to cancel
     * @param string $issuerNIFToCancel NIF of the original invoice issuer
     * @param string $issuerNIF NIF of the entity submitting the cancellation
     * @param string $issuerName Name of the entity submitting the cancellation
     * @return self
     */
    public static function createWithoutPreviousRecord(
        string $invoiceNumberToCancel,
        string $invoiceDateToCancel,
        string $issuerNIFToCancel,
        string $issuerNIF,
        string $issuerName
    ): self {
        $instance = new self($invoiceNumberToCancel, $invoiceDateToCancel, $issuerNIFToCancel, $issuerNIF, $issuerName);
        $instance->setWithoutPreviousRecord(true);
        return $instance;
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Returns the current date and time in ISO 8601 format with timezone.
     *
     * @return string ISO 8601 formatted datetime
     */
    public function getCurrentDateTimeISO8601(): string
    {
        return date(self::TIMESTAMP_PATTERN);
    }

    // =========================================================================
    // SETTER METHODS
    // =========================================================================

    /**
     * Sets the cancelled invoice identification.
     *
     * @param string $nif NIF of the original invoice issuer
     * @param string $number Invoice serial number to cancel
     * @param string $date Invoice date to cancel
     * @return self
     */
    public function setInvoice(string $nif, string $number, string $date): self
    {
        $this->targetDocument['emitterTaxId'] = strtoupper(trim($nif));
        $this->targetDocument['serialNumber'] = trim($number);
        $this->targetDocument['issueDate'] = $date;
        $this->synchronizeDocumentIdentification();
        return $this;
    }

    /**
     * Marks this as a cancellation for a previously rejected invoice.
     *
     * Note: Setting this to true will automatically unset the "without previous record" flag.
     *
     * @param bool $value True if correcting a rejected invoice
     * @return self
     */
    public function setAsPreviousRejection(bool $value = true): self
    {
        $this->operationalFlags['previousRejection'] = $value;

        if ($value) {
            $this->registrationPayload['RechazoPrevio'] = 'S';
            $this->operationalFlags['withoutPriorRecord'] = false;
            unset($this->registrationPayload['SinRegistroPrevio']);
        } else {
            unset($this->registrationPayload['RechazoPrevio']);
        }

        return $this;
    }

    /**
     * Marks this as a cancellation without previous AEAT record.
     *
     * Note: Setting this to true will automatically unset the "previous rejection" flag.
     *
     * @param bool $value True if no prior AEAT record exists
     * @return self
     */
    public function setWithoutPreviousRecord(bool $value = true): self
    {
        $this->operationalFlags['withoutPriorRecord'] = $value;

        if ($value) {
            $this->registrationPayload['SinRegistroPrevio'] = 'S';
            $this->operationalFlags['previousRejection'] = false;
            unset($this->registrationPayload['RechazoPrevio']);
        } else {
            unset($this->registrationPayload['SinRegistroPrevio']);
        }

        return $this;
    }

    /**
     * Sets the chain link to a previous record.
     *
     * VeriFactu requires records (including cancellations) to be cryptographically
     * chained. Each record must reference the fingerprint of the previous record.
     *
     * @param string $previousNif Previous record issuer NIF
     * @param string $previousNumber Previous record invoice number
     * @param string $previousDate Previous record date
     * @param string $previousFingerprint Previous record SHA-256 fingerprint
     * @return self
     * @throws \InvalidArgumentException If previousFingerprint is empty
     */
    public function setChainLink(string $previousNif, string $previousNumber, string $previousDate, string $previousFingerprint): self
    {
        if (empty($previousFingerprint)) {
            throw new \InvalidArgumentException('The previous fingerprint cannot be empty.');
        }

        $this->registrationPayload['Encadenamiento']['RegistroAnterior'] = [
            'IDEmisorFactura' => $previousNif,
            'NumSerieFactura' => $previousNumber,
            'FechaExpedicionFactura' => $previousDate,
            'Huella' => $previousFingerprint,
        ];

        return $this;
    }

    /**
     * Sets this cancellation as the first in the chain (no previous record).
     *
     * @return self
     */
    public function setAsFirstInChain(): self
    {
        $this->registrationPayload['Encadenamiento'] = ['PrimerRegistro' => 'S'];
        return $this;
    }

    /**
     * Sets the billing system information required by AEAT.
     *
     * @param array $systemConfig System configuration array
     * @return self
     */
    public function setSystemInfo(array $systemConfig): self
    {
        $this->registrationPayload['SistemaInformatico'] = $systemConfig;
        return $this;
    }

    /**
     * Sets the billing system identity with standard fields.
     *
     * @param string $developerNif Software developer's NIF
     * @param string $softwareName Software name
     * @param string $softwareVersion Software version
     * @param string $softwareId Software identifier
     * @return self
     */
    public function setSystemIdentity(string $developerNif, string $softwareName, string $softwareVersion, string $softwareId): self
    {
        $this->registrationPayload['SistemaInformatico'] = [
            'NombreRazon' => $softwareName,
            'NIF' => strtoupper(trim($developerNif)),
            'NombreSistemaInformatico' => $softwareName,
            'IdSistemaInformatico' => $softwareId,
            'Version' => $softwareVersion,
            'NumeroInstalacion' => '1',
            'TipoUsoPosibleSoloVerifactu' => 'S',
            'TipoUsoPosibleMultiOT' => 'N',
            'IndicadorMultiplesOT' => 'N',
        ];
        return $this;
    }

    /**
     * Sets the generation timestamp.
     *
     * @param string $timestamp ISO 8601 formatted datetime
     * @return self
     */
    public function setGenerationTimestamp(string $timestamp): self
    {
        $this->registrationPayload['FechaHoraHusoGenRegistro'] = $timestamp;
        return $this;
    }

    /**
     * Sets the fingerprint type (always '01' for SHA-256).
     *
     * @param string $type Fingerprint type code
     * @return self
     */
    public function setFingerprintType(string $type): self
    {
        $this->registrationPayload['TipoHuella'] = $type;
        return $this;
    }

    /**
     * Marks this cancellation as a correction/amendment.
     *
     * @param bool $value True to mark as correction
     * @return self
     */
    public function setAsCorrection(bool $value = true): self
    {
        if ($value) {
            $this->registrationPayload['Subsanacion'] = 'S';
        } else {
            unset($this->registrationPayload['Subsanacion']);
        }
        return $this;
    }

    // =========================================================================
    // HASH GENERATION
    // =========================================================================

    /**
     * Generates the SHA-256 fingerprint (huella) for the cancellation.
     *
     * The fingerprint is calculated using a specific concatenation of fields
     * as required by AEAT VeriFactu specification for cancellations.
     *
     * @return self
     */
    public function generateHash(): self
    {
        $inputString = $this->assembleHashInput();
        $this->registrationPayload['Huella'] = $this->computeCryptographicHash($inputString);
        return $this;
    }

    /**
     * Generates the fingerprint with current timestamp.
     *
     * @param string|null $timezone Timezone for timestamp (default: Europe/Madrid)
     * @return self
     */
    public function generateFingerprint(?string $timezone = 'Europe/Madrid'): self
    {
        $zone = $timezone ?? self::DEFAULT_ZONE;
        $this->registrationPayload['FechaHoraHusoGenRegistro'] = $this->resolveCurrentTimestamp($zone);
        return $this->generateHash();
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    /**
     * Validates the cancellation has all required fields.
     *
     * @return bool True if valid
     * @throws \InvalidArgumentException If validation fails
     */
    public function validate(): bool
    {
        $this->verifyRequiredFields();
        $this->verifyChainConfiguration();
        return true;
    }

    // =========================================================================
    // GETTER METHODS
    // =========================================================================

    /**
     * Gets the complete cancellation data for AEAT submission.
     *
     * @param bool $forRegistration True to include header wrapper for registration
     * @return array Cancellation data structure
     */
    public function getData(bool $forRegistration = true): array
    {
        $this->validate();

        if (empty($this->registrationPayload['Huella'])) {
            $this->generateHash();
        }

        if ($forRegistration) {
            return [
                'Cabecera' => $this->constructHeaderBlock(),
                'RegistroFactura' => [
                    'RegistroAnulacion' => $this->registrationPayload
                ]
            ];
        }

        return $this->registrationPayload;
    }

    /**
     * Gets the SHA-256 fingerprint.
     *
     * @return string|null
     */
    public function getFingerprint(): ?string
    {
        return $this->registrationPayload['Huella'] ?? null;
    }

    /**
     * Gets the generation timestamp.
     *
     * @return string|null
     */
    public function getGenerationTimestamp(): ?string
    {
        return $this->registrationPayload['FechaHoraHusoGenRegistro'] ?? null;
    }

    /**
     * Returns the target document serial number.
     *
     * @return string Serial number
     */
    public function getInvoiceNumber(): string
    {
        return $this->targetDocument['serialNumber'];
    }

    /**
     * Returns the target document issue date.
     *
     * @return string Issue date
     */
    public function getInvoiceDate(): string
    {
        return $this->targetDocument['issueDate'];
    }

    /**
     * Returns the emitter tax identification.
     *
     * @return string Tax ID
     */
    public function getEmitterTaxId(): string
    {
        return $this->emitterIdentity['taxId'];
    }

    /**
     * Returns the emitter legal name.
     *
     * @return string Legal name
     */
    public function getEmitterName(): string
    {
        return $this->emitterIdentity['legalName'];
    }

    /**
     * Returns the operational flags state.
     *
     * @return array Flags array
     */
    public function getOperationalFlags(): array
    {
        return $this->operationalFlags;
    }

    /**
     * Checks if marked as previous rejection.
     *
     * @return bool True if previous rejection
     */
    public function isPreviousRejection(): bool
    {
        return $this->operationalFlags['previousRejection'];
    }

    /**
     * Checks if marked as without prior record.
     *
     * @return bool True if without prior record
     */
    public function isWithoutPriorRecord(): bool
    {
        return $this->operationalFlags['withoutPriorRecord'];
    }

    /**
     * Returns the raw registration payload.
     *
     * @return array Payload data
     */
    public function getRawPayload(): array
    {
        return $this->registrationPayload;
    }

    /**
     * Resets the cancellation to initial state.
     *
     * @return self
     */
    public function reset(): self
    {
        $this->operationalFlags = [
            'previousRejection' => false,
            'withoutPriorRecord' => false,
        ];
        $this->initializePayload();
        return $this;
    }
}
