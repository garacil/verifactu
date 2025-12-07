<?php

/**
 * OpenAEAT Billing Library - Invoice Class
 *
 * This class handles VeriFactu invoice creation, validation, and submission
 * for the Spanish Tax Agency (AEAT) electronic invoicing system.
 *
 * Supports all invoice types defined in RD 1619/2012:
 * - Standard invoices (F1)
 * - Simplified invoices (F2)
 * - Substitution invoices (F3)
 * - Credit notes / Rectifying invoices (R1-R5)
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

/**
 * VeriFactu Invoice Document Builder
 */
class Invoice
{
    /**
     * VAT qualification codes (AEAT L9)
     */
    public const QUAL_TAXABLE = 'S1';
    public const QUAL_TAXABLE_REVERSE = 'S2';
    public const QUAL_NOT_SUBJECT = 'N1';
    public const QUAL_NOT_SUBJECT_LOCATION = 'N2';

    /**
     * Exemption reason codes (AEAT L10)
     */
    public const EXEMPT_ARTICLE_20 = 'E1';
    public const EXEMPT_ARTICLE_21 = 'E2';
    public const EXEMPT_ARTICLE_22 = 'E3';
    public const EXEMPT_ARTICLE_23_24 = 'E4';
    public const EXEMPT_ARTICLE_25 = 'E5';
    public const EXEMPT_OTHER = 'E6';

    /**
     * Document type codes (RD 1619/2012)
     */
    public const TYPE_STANDARD = 'F1';
    public const TYPE_SIMPLIFIED = 'F2';
    public const TYPE_SUBSTITUTION = 'F3';
    public const TYPE_CREDIT_NOTE_LEGAL = 'R1';
    public const TYPE_CREDIT_NOTE_80_3 = 'R2';
    public const TYPE_CREDIT_NOTE_80_4 = 'R3';
    public const TYPE_CREDIT_NOTE_OTHER = 'R4';
    public const TYPE_CREDIT_NOTE_SIMPLIFIED = 'R5';

    /**
     * Tax system codes (AEAT L1)
     */
    public const TAX_VAT = '01';
    public const TAX_IPSI = '02';
    public const TAX_IGIC = '03';
    public const TAX_OTHER = '05';

    /**
     * Foreign identification type codes
     */
    public const ID_TYPE_EU_VAT = '02';
    public const ID_TYPE_PASSPORT = '03';
    public const ID_TYPE_OFFICIAL_DOC = '04';
    public const ID_TYPE_RESIDENCE_CERT = '05';
    public const ID_TYPE_OTHER_DOC = '06';
    public const ID_TYPE_NOT_REGISTERED = '07';

    /**
     * Rectification method codes
     */
    public const RECT_SUBSTITUTION = 'S';
    public const RECT_DIFFERENCES = 'I';

    /**
     * VAT regime codes
     */
    public const REGIME_GENERAL = '01';
    public const REGIME_EXPORT = '02';
    public const REGIME_USED_GOODS = '03';
    public const REGIME_INVESTMENT_GOLD = '04';
    public const REGIME_TRAVEL_AGENCIES = '05';
    public const REGIME_VAT_GROUP = '06';
    public const REGIME_CASH_ACCOUNTING = '07';
    public const REGIME_IGIC_IPSI = '08';
    public const REGIME_TRAVEL_INTERMEDIARIES = '09';
    public const REGIME_THIRD_PARTY_COLLECTIONS = '10';
    public const REGIME_BUSINESS_RENTAL = '11';
    public const REGIME_CONSTRUCTION_CERT = '14';
    public const REGIME_SUCCESSIVE_TRACT = '15';
    public const REGIME_OSS_IOSS = '17';
    public const REGIME_EQUIVALENCE_SURCHARGE = '18';
    public const REGIME_AGRICULTURE = '19';
    public const REGIME_SIMPLIFIED = '20';

    /**
     * Protocol version
     */
    private const SCHEMA_VERSION = '1.0';

    /**
     * Hash algorithm identifier
     */
    private const HASH_TYPE_SHA256 = '01';

    /**
     * Issuer identification
     */
    private array $issuerData = [
        'taxId' => '',
        'legalName' => '',
    ];

    /**
     * Document identification
     */
    private array $documentId = [
        'serial' => '',
        'issueDate' => '',
    ];

    /**
     * Document payload
     */
    private array $payload = [];

    /**
     * Recipient collection
     */
    private array $recipientList = [];

    /**
     * Tax breakdown entries
     */
    private array $taxEntries = [];

    /**
     * Aggregated tax groups
     */
    private array $taxGroups = [];

    /**
     * Submission header data
     */
    private array $headerData = [];

    /**
     * Document status flags
     */
    private array $statusFlags = [
        'isAmendment' => false,
        'isPriorRejection' => false,
    ];

    /**
     * Constructs a new invoice document.
     *
     * @param string $serial Document serial number
     * @param string $issueDate Issue date (DD-MM-YYYY)
     * @param string $issuerTaxId Issuer tax identification
     * @param string $issuerName Issuer legal name
     */
    public function __construct(
        string $serial = '',
        string $issueDate = '',
        string $issuerTaxId = '',
        string $issuerName = ''
    ) {
        $this->issuerData['taxId'] = $issuerTaxId;
        $this->issuerData['legalName'] = $issuerName;
        $this->documentId['serial'] = $serial;
        $this->documentId['issueDate'] = $issueDate;

        $this->initializePayload($serial, $issueDate, $issuerTaxId, $issuerName);
    }

    /**
     * Initializes the document payload structure.
     */
    private function initializePayload(string $serial, string $date, string $taxId, string $name): void
    {
        $this->payload = [
            'IDVersion' => self::SCHEMA_VERSION,
            'IDFactura' => [
                'NumSerieFactura' => $serial,
                'FechaExpedicionFactura' => $date,
            ],
            'TipoFactura' => self::TYPE_STANDARD,
            'FechaHoraHusoGenRegistro' => $this->generateTimestamp(),
            'TipoHuella' => self::HASH_TYPE_SHA256,
        ];

        if (!empty($taxId)) {
            $this->payload['IDFactura']['IDEmisorFactura'] = $taxId;
            $this->payload['NombreRazonEmisor'] = $name;
        }
    }

    /**
     * Generates current timestamp in ISO 8601 format.
     *
     * @return string Formatted timestamp
     */
    private function generateTimestamp(): string
    {
        return date('Y-m-d\TH:i:sP');
    }

    /**
     * Creates an amendment document.
     *
     * @param string $serial Document serial
     * @param string $date Issue date
     * @param string $taxId Issuer tax ID
     * @param string $name Issuer name
     * @param bool $priorRejection Whether correcting a rejection
     * @return self
     */
    public static function createSubsanacion(
        string $serial,
        string $date,
        string $taxId,
        string $name,
        bool $priorRejection = false
    ): self {
        $instance = new self($serial, $date, $taxId, $name);
        $instance->setAsCorrection(true);
        if ($priorRejection) {
            $instance->setAsPreviousRejection(true);
        }
        return $instance;
    }

    /**
     * Creates a credit note document.
     *
     * @param string $serial Document serial
     * @param string $date Issue date
     * @param string $taxId Issuer tax ID
     * @param string $name Issuer name
     * @param string $creditType Credit note type code
     * @return self
     */
    public static function createRectificativa(
        string $serial,
        string $date,
        string $taxId,
        string $name,
        string $creditType = self::TYPE_CREDIT_NOTE_LEGAL
    ): self {
        $instance = new self($serial, $date, $taxId, $name);
        $instance->setType($creditType);
        return $instance;
    }

    /**
     * Creates a simplified invoice document.
     *
     * @param string $serial Document serial
     * @param string $date Issue date
     * @param string $taxId Issuer tax ID
     * @param string $name Issuer name
     * @return self
     */
    public static function createSimplificada(
        string $serial,
        string $date,
        string $taxId,
        string $name
    ): self {
        $instance = new self($serial, $date, $taxId, $name);
        $instance->setType(self::TYPE_SIMPLIFIED);
        return $instance;
    }

    /**
     * Returns available document types with descriptions.
     *
     * @return array Type code => description mapping
     */
    public static function getTiposFactura(): array
    {
        return [
            self::TYPE_STANDARD => 'Invoice (art. 6, 7.2 and 7.3 of RD 1619/2012)',
            self::TYPE_SIMPLIFIED => 'Simplified Invoice',
            self::TYPE_SUBSTITUTION => 'Invoice issued as substitution of simplified invoices',
            self::TYPE_CREDIT_NOTE_LEGAL => 'Credit Note (Legal error)',
            self::TYPE_CREDIT_NOTE_80_3 => 'Credit Note (Art. 80.3)',
            self::TYPE_CREDIT_NOTE_80_4 => 'Credit Note (Art. 80.4)',
            self::TYPE_CREDIT_NOTE_OTHER => 'Credit Note (Other causes)',
            self::TYPE_CREDIT_NOTE_SIMPLIFIED => 'Credit Note for simplified invoices',
        ];
    }

    /**
     * Returns current timestamp (compatibility method).
     *
     * @return string ISO 8601 timestamp
     */
    public function getCurrentDateTimeISO8601(): string
    {
        return $this->generateTimestamp();
    }

    /**
     * Sets issuer information.
     *
     * @param string $taxId Tax identification number
     * @param string $name Legal name
     * @return self
     */
    public function setIssuer(string $taxId, string $name): self
    {
        $this->issuerData['taxId'] = $taxId;
        $this->issuerData['legalName'] = $name;
        $this->payload['IDFactura']['IDEmisorFactura'] = $taxId;
        $this->payload['NombreRazonEmisor'] = $name;
        return $this;
    }

    /**
     * Sets document type.
     *
     * @param string $type Type code constant
     * @return self
     */
    public function setType(string $type): self
    {
        $this->payload['TipoFactura'] = $type;
        return $this;
    }

    /**
     * Sets operation description.
     *
     * @param string $text Description text
     * @return self
     */
    public function setDescription(string $text): self
    {
        $this->payload['DescripcionOperacion'] = $text;
        return $this;
    }

    /**
     * Sets external reference identifier.
     *
     * @param string $ref Reference string
     * @return self
     */
    public function setExternalReference(string $ref): self
    {
        $this->payload['RefExterna'] = $ref;
        return $this;
    }

    /**
     * Sets rectification method type.
     *
     * @param string $method RECT_SUBSTITUTION or RECT_DIFFERENCES
     * @return self
     */
    public function setRectificationType(string $method): self
    {
        $this->payload['TipoRectificativa'] = $method;
        return $this;
    }

    /**
     * Sets incidence flag for voluntary submissions.
     *
     * @param string $flag 'S' or 'N'
     * @return self
     */
    public function setIncidence(string $flag): self
    {
        if (!in_array($flag, ['S', 'N'], true)) {
            throw new \InvalidArgumentException('Incidence must be "S" or "N"');
        }
        $this->headerData['RemisionVoluntaria']['Incidencia'] = $flag;
        return $this;
    }

    /**
     * Returns current incidence flag.
     *
     * @return string Flag value
     */
    public function getIncidence(): string
    {
        return $this->headerData['RemisionVoluntaria']['Incidencia'] ?? 'N';
    }

    /**
     * Adds a Spanish recipient.
     *
     * @param string $taxId Recipient NIF
     * @param string $name Recipient name
     * @return self
     */
    public function addRecipient(string $taxId, string $name): self
    {
        $this->recipientList[] = [
            'NombreRazon' => $name,
            'NIF' => $taxId,
        ];
        $this->syncRecipients();
        return $this;
    }

    /**
     * Adds a foreign recipient.
     *
     * @param string $name Recipient name
     * @param string $idType Identification type code
     * @param string $idNumber Identification number
     * @param string|null $countryCode ISO country code
     * @return self
     */
    public function addForeignRecipient(
        string $name,
        string $idType,
        string $idNumber,
        ?string $countryCode = null
    ): self {
        if (empty($countryCode) || strtoupper($countryCode) === 'ES') {
            return $this->addRecipient($idNumber, $name);
        }

        $this->recipientList[] = [
            'NombreRazon' => $name,
            'IDOtro' => [
                'CodigoPais' => strtoupper($countryCode),
                'IDType' => $idType,
                'ID' => $idNumber,
            ],
        ];
        $this->syncRecipients();
        return $this;
    }

    /**
     * Synchronizes recipient list with payload.
     */
    private function syncRecipients(): void
    {
        $this->payload['Destinatarios'] = ['IDDestinatario' => $this->recipientList];
    }

    /**
     * Returns current recipients.
     *
     * @return array Recipient data
     */
    public function getRecipients(): array
    {
        return $this->payload['Destinatarios'] ?? [];
    }

    /**
     * Sets multiple recipients at once.
     *
     * @param array $recipients Recipient data array
     * @return self
     */
    public function setRecipients(array $recipients): self
    {
        $this->recipientList = [];
        foreach ($recipients as $recipient) {
            if (isset($recipient['NIF'], $recipient['NombreRazon'])) {
                $this->recipientList[] = [
                    'NombreRazon' => $recipient['NombreRazon'],
                    'NIF' => $recipient['NIF'],
                ];
            }
        }
        $this->syncRecipients();
        return $this;
    }

    /**
     * Adds a tax breakdown entry.
     *
     * @param string|null $qualification VAT qualification code
     * @param float $baseAmount Tax base or non-subject amount
     * @param string|null $exemptionCause Exemption reason code
     * @param string $taxSystem Tax system code
     * @param string|null $regime VAT regime code
     * @param float|null $rate Tax rate percentage
     * @param float|null $taxAmount Calculated tax
     * @param float|null $costBase Cost base amount
     * @param float|null $surchargeRate Equivalence surcharge rate
     * @param float|null $surchargeAmount Equivalence surcharge amount
     * @return self
     */
    public function addDesglose(
        ?string $qualification,
        float $baseAmount,
        ?string $exemptionCause = null,
        string $taxSystem = self::TAX_VAT,
        ?string $regime = null,
        ?float $rate = null,
        ?float $taxAmount = null,
        ?float $costBase = null,
        ?float $surchargeRate = null,
        ?float $surchargeAmount = null
    ): self {
        if ($baseAmount == 0 && ($rate == 0 || $rate === null)) {
            return $this;
        }

        $groupKey = $this->buildGroupKey($rate, $surchargeRate, $regime);

        if (!isset($this->taxGroups[$groupKey])) {
            $this->taxGroups[$groupKey] = $this->createTaxGroup(
                $qualification,
                $taxSystem,
                $regime,
                $exemptionCause,
                $rate,
                $taxAmount,
                $costBase,
                $surchargeRate,
                $surchargeAmount
            );
        }

        $this->aggregateTaxGroup($groupKey, $baseAmount, $costBase, $taxAmount, $surchargeAmount);
        $this->rebuildTaxEntries();
        $this->payload['Desglose'] = ['DetalleDesglose' => $this->taxEntries];

        return $this;
    }

    /**
     * Builds aggregation key for tax groups.
     */
    private function buildGroupKey(?float $rate, ?float $surcharge, ?string $regime): string
    {
        $rateRounded = $rate !== null ? round($rate, 1) : 0;
        $surchargeRounded = $surcharge !== null ? round($surcharge, 1) : 0;
        return sprintf('%s_%s_%s', $rateRounded, $surchargeRounded, $regime ?? 'NONE');
    }

    /**
     * Creates a new tax group structure.
     */
    private function createTaxGroup(
        ?string $qual,
        string $tax,
        ?string $regime,
        ?string $exempt,
        ?float $rate,
        ?float $amount,
        ?float $cost,
        ?float $surRate,
        ?float $surAmount
    ): array {
        $group = [
            'CalificacionOperacion' => $qual,
            'BaseImponibleOimporteNoSujeto' => 0,
            'Impuesto' => $tax,
        ];

        if (!empty($regime)) {
            $group['ClaveRegimen'] = $regime;
        }

        if (!empty($exempt)) {
            $group['OperacionExenta'] = $exempt;
        } else {
            if ($rate !== null) {
                $group['TipoImpositivo'] = round($rate, 2);
            }
            if ($amount !== null) {
                $group['CuotaRepercutida'] = 0;
            }
            if ($surRate !== null) {
                $group['TipoRecargoEquivalencia'] = round($surRate, 2);
            }
            if ($surAmount !== null) {
                $group['CuotaRecargoEquivalencia'] = 0;
            }
        }

        if ($cost !== null) {
            $group['BaseImponibleACoste'] = 0;
        }

        return $group;
    }

    /**
     * Aggregates amounts into a tax group.
     */
    private function aggregateTaxGroup(
        string $key,
        float $base,
        ?float $cost,
        ?float $tax,
        ?float $surcharge
    ): void {
        $this->taxGroups[$key]['BaseImponibleOimporteNoSujeto'] += $base;

        if ($cost !== null && isset($this->taxGroups[$key]['BaseImponibleACoste'])) {
            $this->taxGroups[$key]['BaseImponibleACoste'] += $cost;
        }

        if ($tax !== null && isset($this->taxGroups[$key]['CuotaRepercutida'])) {
            $this->taxGroups[$key]['CuotaRepercutida'] += $tax;
            $this->taxGroups[$key]['CuotaRepercutida'] = round(
                $this->taxGroups[$key]['CuotaRepercutida'],
                2
            );
        }

        if ($surcharge !== null && isset($this->taxGroups[$key]['CuotaRecargoEquivalencia'])) {
            $this->taxGroups[$key]['CuotaRecargoEquivalencia'] += $surcharge;
            $this->taxGroups[$key]['CuotaRecargoEquivalencia'] = round(
                $this->taxGroups[$key]['CuotaRecargoEquivalencia'],
                2
            );
        }
    }

    /**
     * Rebuilds tax entries from aggregated groups.
     */
    private function rebuildTaxEntries(): void
    {
        $this->taxEntries = [];
        foreach ($this->taxGroups as $group) {
            $entry = $group;
            $entry['BaseImponibleOimporteNoSujeto'] = round($group['BaseImponibleOimporteNoSujeto'], 2);

            if (isset($group['CuotaRepercutida'])) {
                $entry['CuotaRepercutida'] = round($group['CuotaRepercutida'], 2);
            }
            if (isset($group['BaseImponibleACoste'])) {
                $entry['BaseImponibleACoste'] = round($group['BaseImponibleACoste'], 2);
            }
            if (isset($group['CuotaRecargoEquivalencia'])) {
                $entry['CuotaRecargoEquivalencia'] = round($group['CuotaRecargoEquivalencia'], 2);
            }

            $this->taxEntries[] = $entry;
        }
        $this->computeTotals();
    }

    /**
     * Adds a simplified tax line.
     *
     * @param string $qualification VAT qualification
     * @param float $base Tax base
     * @param float $rate Tax rate
     * @param float|null $tax Tax amount
     * @param string $regime VAT regime
     * @param string|null $exemptionCause Exemption cause
     * @param float|null $surchargeRate Surcharge rate
     * @param float|null $surchargeAmount Surcharge amount
     * @return self
     */
    public function addTaxLine(
        string $qualification,
        float $base,
        float $rate = 0.0,
        ?float $tax = null,
        string $regime = '01',
        ?string $exemptionCause = null,
        ?float $surchargeRate = null,
        ?float $surchargeAmount = null
    ): self {
        return $this->addDesglose(
            $qualification,
            $base,
            $exemptionCause,
            self::TAX_VAT,
            $regime,
            $rate,
            $tax,
            null,
            $surchargeRate,
            $surchargeAmount
        );
    }

    /**
     * Marks document as first in chain.
     *
     * @return self
     */
    public function setAsFirstInChain(): self
    {
        $this->payload['Encadenamiento'] = ['PrimerRegistro' => 'S'];
        return $this;
    }

    /**
     * Sets chain link to previous document.
     *
     * @param string $prevTaxId Previous issuer tax ID
     * @param string $prevSerial Previous serial number
     * @param string $prevDate Previous issue date
     * @param string $prevHash Previous document hash
     * @return self
     */
    public function setChainLink(
        string $prevTaxId,
        string $prevSerial,
        string $prevDate,
        string $prevHash
    ): self {
        if (empty($prevHash)) {
            throw new \InvalidArgumentException('The previous fingerprint cannot be empty.');
        }
        $this->payload['Encadenamiento']['RegistroAnterior'] = [
            'IDEmisorFactura' => $prevTaxId,
            'NumSerieFactura' => $prevSerial,
            'FechaExpedicionFactura' => $prevDate,
            'Huella' => $prevHash,
        ];
        return $this;
    }

    /**
     * Marks document as an amendment.
     *
     * @param bool $isAmendment Amendment flag
     * @return self
     */
    public function setAsCorrection(bool $isAmendment = true): self
    {
        $this->statusFlags['isAmendment'] = $isAmendment;
        if ($isAmendment) {
            $this->payload['Subsanacion'] = 'S';
        } else {
            unset($this->payload['Subsanacion']);
        }
        return $this;
    }

    /**
     * Marks document as correction of prior rejection.
     *
     * @param bool $isPrior Prior rejection flag
     * @return self
     */
    public function setAsPreviousRejection(bool $isPrior = true): self
    {
        $this->statusFlags['isPriorRejection'] = $isPrior;
        if ($isPrior) {
            $this->payload['RechazoPrevio'] = 'X';
            $this->setAsCorrection(true);
        } else {
            unset($this->payload['RechazoPrevio']);
        }
        return $this;
    }

    /**
     * Sets billing system information.
     *
     * @param array $config System configuration
     * @return self
     */
    public function setSystemInfo(array $config): self
    {
        $this->payload['SistemaInformatico'] = $config;
        return $this;
    }

    /**
     * Sets billing system identity with standard fields.
     *
     * @param string $devTaxId Developer tax ID
     * @param string $softName Software name
     * @param string $softVersion Software version
     * @param string $softId Software identifier
     * @return self
     */
    public function setSystemIdentity(
        string $devTaxId,
        string $softName,
        string $softVersion,
        string $softId
    ): self {
        $this->payload['SistemaInformatico'] = [
            'NombreRazon' => $softName,
            'NIF' => strtoupper(trim($devTaxId)),
            'NombreSistemaInformatico' => $softName,
            'IdSistemaInformatico' => $softId,
            'Version' => $softVersion,
            'NumeroInstalacion' => '1',
            'TipoUsoPosibleSoloVerifactu' => 'S',
            'TipoUsoPosibleMultiOT' => 'N',
            'IndicadorMultiplesOT' => 'N',
        ];
        return $this;
    }

    /**
     * Sets tax breakdown directly.
     *
     * @param array $breakdown Breakdown entries
     * @return self
     */
    public function setDesglose(array $breakdown): self
    {
        $this->taxEntries = $breakdown;
        $this->payload['Desglose'] = ['DetalleDesglose' => $this->taxEntries];
        $this->computeTotals();
        return $this;
    }

    /**
     * Sets additional information fields.
     *
     * @param array $fields Field => value pairs
     * @return self
     */
    public function setInformacionAdicional(array $fields): self
    {
        foreach ($fields as $field => $value) {
            $this->payload[$field] = $value;
        }
        return $this;
    }

    /**
     * Sets generation timestamp.
     *
     * @param string $timestamp ISO 8601 timestamp
     * @return self
     */
    public function setGenerationTimestamp(string $timestamp): self
    {
        $this->payload['FechaHoraHusoGenRegistro'] = $timestamp;
        return $this;
    }

    /**
     * Sets fingerprint type code.
     *
     * @param string $type Type code
     * @return self
     */
    public function setFingerprintType(string $type): self
    {
        $this->payload['TipoHuella'] = $type;
        return $this;
    }

    /**
     * Sets timestamp for hash generation.
     *
     * @param string $timestamp Timestamp or empty for current
     * @return self
     */
    public function setTimestampForHash(string $timestamp = ''): self
    {
        $this->payload['FechaHoraHusoGenRegistro'] = empty($timestamp)
            ? $this->generateTimestamp()
            : $timestamp;
        return $this;
    }

    /**
     * Sets document identification.
     *
     * @param string $serial Serial number
     * @param string $date Issue date
     * @return self
     */
    public function setInvoiceId(string $serial, string $date): self
    {
        $this->payload['IDFactura']['NumSerieFactura'] = trim($serial);
        $this->payload['IDFactura']['FechaExpedicionFactura'] = $date;
        $this->documentId['serial'] = trim($serial);
        $this->documentId['issueDate'] = $date;
        return $this;
    }

    /**
     * Adds a rectified invoice reference.
     *
     * @param string $taxId Original issuer tax ID
     * @param string $serial Original serial number
     * @param string $date Original issue date
     * @param float $baseRect Rectified base amount
     * @param float $taxRect Rectified tax amount
     * @param float|null $surchargeRect Rectified surcharge amount
     * @return self
     */
    public function addRectifiedInvoice(
        string $taxId,
        string $serial,
        string $date,
        float $baseRect,
        float $taxRect,
        ?float $surchargeRect = null
    ): self {
        if (!isset($this->payload['FacturasRectificadas'])) {
            $this->payload['FacturasRectificadas'] = [];
        }

        $this->payload['FacturasRectificadas'][] = [
            'IDEmisorFactura' => $taxId,
            'NumSerieFactura' => $serial,
            'FechaExpedicionFactura' => $date,
        ];

        if (!isset($this->payload['ImporteRectificacion'])) {
            $this->payload['ImporteRectificacion'] = [
                'BaseRectificada' => 0.00,
                'CuotaRectificada' => 0.00,
                'CuotaRecargoRectificado' => 0.00,
            ];
        }

        $this->payload['ImporteRectificacion']['BaseRectificada'] += $baseRect;
        $this->payload['ImporteRectificacion']['CuotaRectificada'] += $taxRect;

        if ($surchargeRect !== null && $surchargeRect > 0) {
            $this->payload['ImporteRectificacion']['CuotaRecargoRectificado'] += $surchargeRect;
        }

        $this->formatRectificationAmounts();
        return $this;
    }

    /**
     * Formats rectification amounts.
     */
    private function formatRectificationAmounts(): void
    {
        $rect = &$this->payload['ImporteRectificacion'];
        $rect['BaseRectificada'] = number_format((float) $rect['BaseRectificada'], 2, '.', '');
        $rect['CuotaRectificada'] = number_format((float) $rect['CuotaRectificada'], 2, '.', '');
        $rect['CuotaRecargoRectificado'] = number_format((float) $rect['CuotaRecargoRectificado'], 2, '.', '');
    }

    /**
     * Computes and updates document totals.
     */
    private function computeTotals(): void
    {
        $totalBase = 0;
        $totalTax = 0;
        $totalSurcharge = 0;

        foreach ($this->taxEntries as $entry) {
            $totalBase += (float) ($entry['BaseImponibleOimporteNoSujeto'] ?? 0);
            $totalTax += (float) ($entry['CuotaRepercutida'] ?? 0);
            $totalSurcharge += (float) ($entry['CuotaRecargoEquivalencia'] ?? 0);
        }

        $combinedTax = $totalTax + $totalSurcharge;
        $grandTotal = $totalBase + $combinedTax;

        $this->payload['CuotaTotal'] = number_format($combinedTax, 2, '.', '');
        $this->payload['ImporteTotal'] = number_format($grandTotal, 2, '.', '');
    }

    /**
     * Calculates and returns document totals.
     *
     * @return array Totals breakdown
     */
    public function calculateTotals(): array
    {
        $this->computeTotals();

        $totalBase = 0.0;
        $totalTax = 0.0;
        $totalSurcharge = 0.0;

        foreach ($this->taxEntries as $entry) {
            $totalBase += (float) ($entry['BaseImponibleOimporteNoSujeto'] ?? 0);
            $totalTax += (float) ($entry['CuotaRepercutida'] ?? 0);
            $totalSurcharge += (float) ($entry['CuotaRecargoEquivalencia'] ?? 0);
        }

        return [
            'base' => $totalBase,
            'tax' => $totalTax,
            'surcharge' => $totalSurcharge,
            'total' => $totalBase + $totalTax + $totalSurcharge,
        ];
    }

    /**
     * Generates SHA-256 fingerprint.
     *
     * @return self
     */
    public function generateHash(): self
    {
        $this->computeTotals();

        $chainHash = $this->payload['Encadenamiento']['RegistroAnterior']['Huella'] ?? '';

        $hashInput = sprintf(
            'IDEmisorFactura=%s&NumSerieFactura=%s&FechaExpedicionFactura=%s&TipoFactura=%s&CuotaTotal=%s&ImporteTotal=%s&Huella=%s&FechaHoraHusoGenRegistro=%s',
            $this->issuerData['taxId'],
            $this->payload['IDFactura']['NumSerieFactura'],
            $this->payload['IDFactura']['FechaExpedicionFactura'],
            $this->payload['TipoFactura'],
            $this->payload['CuotaTotal'],
            $this->payload['ImporteTotal'],
            $chainHash,
            $this->payload['FechaHoraHusoGenRegistro']
        );

        $this->payload['Huella'] = strtoupper(
            hash('sha256', mb_convert_encoding($hashInput, 'UTF-8'))
        );

        return $this;
    }

    /**
     * Generates fingerprint with current timestamp.
     *
     * @param string|null $tz Timezone identifier
     * @return self
     */
    public function generateFingerprint(?string $tz = 'Europe/Madrid'): self
    {
        $this->payload['FechaHoraHusoGenRegistro'] = (new \DateTime(
            'now',
            new \DateTimeZone($tz)
        ))->format('Y-m-d\TH:i:sP');

        return $this->generateHash();
    }

    /**
     * Validates document completeness.
     *
     * @return bool True if valid
     * @throws \InvalidArgumentException On validation failure
     */
    public function validate(): bool
    {
        $requiredFields = [
            'IDVersion',
            'IDFactura',
            'NombreRazonEmisor',
            'TipoFactura',
            'DescripcionOperacion',
            'FechaHoraHusoGenRegistro',
            'TipoHuella',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($this->payload[$field]) || empty($this->payload[$field])) {
                throw new \InvalidArgumentException("Required field missing: {$field}");
            }
        }

        $idFields = ['NumSerieFactura', 'FechaExpedicionFactura', 'IDEmisorFactura'];
        foreach ($idFields as $field) {
            if (!isset($this->payload['IDFactura'][$field])) {
                throw new \InvalidArgumentException("IDFactura incomplete");
            }
        }

        $simplifiedTypes = [self::TYPE_SIMPLIFIED, self::TYPE_CREDIT_NOTE_SIMPLIFIED];
        if (!in_array($this->payload['TipoFactura'], $simplifiedTypes, true)) {
            if (empty($this->payload['Destinatarios']['IDDestinatario'] ?? [])) {
                throw new \InvalidArgumentException("Must have at least one recipient");
            }
        }

        if (empty($this->payload['Desglose']['DetalleDesglose'] ?? [])) {
            throw new \InvalidArgumentException("Must have at least one tax breakdown detail");
        }

        return true;
    }

    /**
     * Returns complete document data for submission.
     *
     * @param bool $forRegistration Include registration wrapper
     * @return array Document structure
     */
    public function getData(bool $forRegistration = true): array
    {
        $this->validate();

        if (empty($this->payload['Huella'])) {
            $this->generateHash();
        }

        if (!$forRegistration) {
            return $this->payload;
        }

        $header = [
            'IDVersion' => self::SCHEMA_VERSION,
            'ObligadoEmision' => [
                'NombreRazon' => $this->issuerData['legalName'],
                'NIF' => $this->issuerData['taxId'],
            ],
        ];

        if (!empty($this->headerData)) {
            $header = array_merge($header, $this->headerData);
        }

        return [
            'Cabecera' => $header,
            'RegistroFactura' => ['RegistroAlta' => $this->payload],
        ];
    }

    /**
     * Returns document serial number.
     */
    public function getInvoiceNumber(): string
    {
        return $this->payload['IDFactura']['NumSerieFactura'] ?? '';
    }

    /**
     * Returns total amount including taxes.
     */
    public function getTotalAmount(): float
    {
        return (float) ($this->payload['ImporteTotal'] ?? 0.0);
    }

    /**
     * Returns document issue date.
     */
    public function getInvoiceDate(): string
    {
        return $this->payload['IDFactura']['FechaExpedicionFactura'] ?? '';
    }

    /**
     * Returns issuer tax ID.
     */
    public function getIssuerNif(): string
    {
        return $this->issuerData['taxId'];
    }

    /**
     * Returns issuer legal name.
     */
    public function getIssuerName(): string
    {
        return $this->issuerData['legalName'];
    }

    /**
     * Returns document type code.
     */
    public function getInvoiceType(): string
    {
        return $this->payload['TipoFactura'] ?? '';
    }

    /**
     * Returns total tax amount.
     */
    public function getTotalTax(): float
    {
        return (float) ($this->payload['CuotaTotal'] ?? 0.0);
    }

    /**
     * Returns SHA-256 fingerprint.
     */
    public function getFingerprint(): ?string
    {
        return $this->payload['Huella'] ?? null;
    }

    /**
     * Returns operation description.
     */
    public function getDescription(): string
    {
        return $this->payload['DescripcionOperacion'] ?? '';
    }

    /**
     * Returns generation timestamp.
     */
    public function getGenerationTimestamp(): ?string
    {
        return $this->payload['FechaHoraHusoGenRegistro'] ?? null;
    }

    /**
     * Returns rectified invoice list.
     */
    public function getRectifiedInvoices(): array
    {
        return $this->payload['FacturasRectificadas'] ?? [];
    }

    /**
     * Returns rectification amounts.
     */
    public function getRectificationAmounts(): array
    {
        return $this->payload['ImporteRectificacion'] ?? [];
    }

    /**
     * Checks if document has rectified invoices.
     */
    public function hasRectifiedInvoices(): bool
    {
        return !empty($this->payload['FacturasRectificadas']);
    }

    /**
     * Returns rectification type if credit note.
     */
    public function getRectificationType(): ?string
    {
        $type = $this->getInvoiceType();
        $creditTypes = [
            self::TYPE_CREDIT_NOTE_LEGAL,
            self::TYPE_CREDIT_NOTE_80_3,
            self::TYPE_CREDIT_NOTE_80_4,
            self::TYPE_CREDIT_NOTE_OTHER,
            self::TYPE_CREDIT_NOTE_SIMPLIFIED,
        ];
        return in_array($type, $creditTypes, true) ? $type : null;
    }

    /**
     * Converts document to JSON.
     *
     * @return string JSON representation
     */
    public function toJSON(): string
    {
        $this->validate();
        return json_encode($this->payload) ?: '{}';
    }

    /**
     * Adds standard VAT breakdown.
     */
    public function addDesgloseIVA(
        float $base,
        float $rate,
        ?string $regime = self::REGIME_GENERAL,
        ?float $tax = null
    ): self {
        return $this->addDesglose(self::QUAL_TAXABLE, $base, null, self::TAX_VAT, $regime, $rate, null);
    }

    /**
     * Adds VAT breakdown with equivalence surcharge.
     */
    public function addDesgloseIVAConRecargo(
        float $base,
        float $rate,
        float $surchargeRate,
        ?string $regime = self::REGIME_EQUIVALENCE_SURCHARGE
    ): self {
        return $this->addDesglose(
            self::QUAL_TAXABLE,
            $base,
            null,
            self::TAX_VAT,
            $regime,
            $rate,
            null,
            null,
            $surchargeRate,
            null
        );
    }

    /**
     * Adds exempt operation breakdown.
     */
    public function addDesgloseExento(
        float $base,
        string $exemptionCause,
        ?string $regime = self::REGIME_GENERAL
    ): self {
        return $this->addDesglose($exemptionCause, $base, $exemptionCause, self::TAX_VAT, $regime);
    }

    /**
     * Adds non-subject operation breakdown.
     */
    public function addDesgloseNoSujeto(float $amount, ?string $regime = self::REGIME_EXPORT): self
    {
        return $this->addDesglose(self::QUAL_NOT_SUBJECT, $amount, null, self::TAX_VAT, $regime);
    }

    /**
     * Adds IGIC (Canary Islands) breakdown.
     */
    public function addDesgloseIGIC(
        float $base,
        float $rate,
        ?string $regime = self::REGIME_IGIC_IPSI
    ): self {
        return $this->addDesglose(self::QUAL_TAXABLE, $base, null, self::TAX_IGIC, $regime, $rate, null);
    }

    /**
     * Adds IPSI (Ceuta/Melilla) breakdown.
     */
    public function addDesgloseIPSI(
        float $base,
        float $rate,
        ?string $regime = self::REGIME_IGIC_IPSI
    ): self {
        return $this->addDesglose(self::QUAL_TAXABLE, $base, null, self::TAX_IPSI, $regime, $rate, null);
    }

    /**
     * Adds other taxes breakdown.
     */
    public function addDesgloseOtros(float $base, float $rate, ?string $regime = '01'): self
    {
        return $this->addDesglose(self::QUAL_TAXABLE, $base, null, self::TAX_OTHER, $regime, $rate, null);
    }
}
