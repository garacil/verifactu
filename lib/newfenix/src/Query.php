<?php

/**
 * OpenAEAT Billing Library - Query Class
 *
 * This class constructs search requests for querying invoices in the Spanish
 * Tax Agency (AEAT) VeriFactu system. Supports filtering by period, counterparty,
 * date ranges, specific invoice identification, and cryptographic fingerprint.
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

/**
 * AEAT Invoice Search Request Builder
 */
class Query
{
    /**
     * API version identifier
     */
    private const PROTOCOL_VERSION = '1.0';

    /**
     * Date format used by AEAT
     */
    private const DATE_PATTERN = 'd-m-Y';

    /**
     * Obligated party identification
     */
    private array $obligatedParty = [
        'taxId' => '',
        'legalName' => '',
    ];

    /**
     * Target invoice identification
     */
    private array $targetInvoice = [
        'serial' => null,
        'issueDate' => null,
    ];

    /**
     * Third party filter
     */
    private array $thirdParty = [
        'taxId' => null,
        'legalName' => null,
    ];

    /**
     * Search criteria collection
     */
    private array $criteria = [];

    /**
     * Extended parameters
     */
    private array $extensions = [];

    /**
     * Initializes a new search request.
     *
     * @param string|null $invoiceSerial Target invoice serial number
     * @param string|null $invoiceDate Target invoice date (DD-MM-YYYY)
     * @param string $obligatedTaxId Obligated party tax ID
     * @param string $obligatedName Obligated party legal name
     * @param string|null $counterpartyTaxId Counterparty tax ID filter
     * @param string|null $counterpartyName Counterparty name filter
     */
    public function __construct(
        ?string $invoiceSerial = null,
        ?string $invoiceDate = null,
        string $obligatedTaxId = '',
        string $obligatedName = '',
        ?string $counterpartyTaxId = null,
        ?string $counterpartyName = null
    ) {
        $this->obligatedParty['taxId'] = $obligatedTaxId;
        $this->obligatedParty['legalName'] = $obligatedName;
        $this->targetInvoice['serial'] = $invoiceSerial;
        $this->targetInvoice['issueDate'] = $invoiceDate;
        $this->thirdParty['taxId'] = $counterpartyTaxId;
        $this->thirdParty['legalName'] = $counterpartyName;

        if ($invoiceDate !== null) {
            $this->validateDateFormat($invoiceDate);
        }
    }

    /**
     * Validates date string against expected format.
     *
     * @param string $dateString Date to validate
     * @throws \InvalidArgumentException On invalid format
     */
    private function validateDateFormat(string $dateString): void
    {
        $parsed = \DateTime::createFromFormat(self::DATE_PATTERN, $dateString);
        if ($parsed === false) {
            throw new \InvalidArgumentException(
                sprintf('Date must be in %s format, received: %s', self::DATE_PATTERN, $dateString)
            );
        }
    }

    /**
     * Extracts year and month from a date string.
     *
     * @param string $dateString Date in DD-MM-YYYY format
     * @return array ['year' => string, 'month' => string]
     */
    private function extractPeriodFromDate(string $dateString): array
    {
        $parsed = \DateTime::createFromFormat(self::DATE_PATTERN, $dateString);
        return [
            'year' => $parsed->format('Y'),
            'month' => $parsed->format('m'),
        ];
    }

    /**
     * Defines the obligated issuer for the search.
     *
     * @param string $taxId Tax identification number
     * @param string $name Legal name
     * @return self
     */
    public function setIssuer(string $taxId, string $name): self
    {
        $this->obligatedParty['taxId'] = strtoupper(trim($taxId));
        $this->obligatedParty['legalName'] = $name;
        return $this;
    }

    /**
     * Sets the target invoice to search for.
     *
     * @param string $serial Invoice serial number
     * @param string $date Issue date (DD-MM-YYYY)
     * @return self
     */
    public function setInvoice(string $serial, string $date): self
    {
        $this->validateDateFormat($date);
        $this->targetInvoice['serial'] = $serial;
        $this->targetInvoice['issueDate'] = $date;
        return $this;
    }

    /**
     * Configures the accounting period filter.
     *
     * @param string $fiscalYear Four-digit year
     * @param string $period Two-digit month (01-12)
     * @return self
     */
    public function setFiscalPeriod(string $fiscalYear, string $period): self
    {
        $this->criteria['PeriodoImputacion'] = [
            'Ejercicio' => $fiscalYear,
            'Periodo' => str_pad($period, 2, '0', STR_PAD_LEFT),
        ];
        return $this;
    }

    /**
     * Adds counterparty filter to the search.
     *
     * @param string $taxId Counterparty tax ID
     * @param string|null $name Counterparty name (optional)
     * @return self
     */
    public function setCounterparty(string $taxId, ?string $name = null): self
    {
        $party = ['NIF' => $taxId];
        if ($name !== null) {
            $party['NombreRazon'] = $name;
        }
        $this->criteria['Contraparte'] = $party;
        return $this;
    }

    /**
     * Restricts search to a date interval.
     *
     * @param string $startDate Range start (DD-MM-YYYY)
     * @param string $endDate Range end (DD-MM-YYYY)
     * @return self
     */
    public function setDateRange(string $startDate, string $endDate): self
    {
        $this->validateDateFormat($startDate);
        $this->validateDateFormat($endDate);

        $this->criteria['FechaExpedicionFactura'] = [
            'RangoFechaExpedicion' => [
                'Desde' => $startDate,
                'Hasta' => $endDate,
            ],
        ];
        return $this;
    }

    /**
     * Searches for a specific invoice by its identifiers.
     *
     * @param string $serial Invoice serial number
     * @param string $date Issue date (DD-MM-YYYY)
     * @param string|null $issuerTaxId Issuer tax ID (uses default if omitted)
     * @return self
     */
    public function setSpecificInvoice(string $serial, string $date, ?string $issuerTaxId = null): self
    {
        $this->validateDateFormat($date);

        $identification = [
            'NumSerieFacturaEmisor' => $serial,
            'FechaExpedicionFacturaEmisor' => $date,
        ];

        if ($issuerTaxId !== null) {
            $identification['NIF'] = $issuerTaxId;
        }

        $this->criteria['IDFactura'] = $identification;
        return $this;
    }

    /**
     * Searches by cryptographic hash.
     *
     * @param string $hash SHA-256 fingerprint
     * @return self
     */
    public function setFingerprint(string $hash): self
    {
        $this->criteria['Huella'] = strtoupper($hash);
        return $this;
    }

    /**
     * Merges custom criteria into the search.
     *
     * @param array $customCriteria Additional search criteria
     * @return self
     */
    public function addCustomFilters(array $customCriteria): self
    {
        $this->criteria = array_merge($this->criteria, $customCriteria);
        return $this;
    }

    /**
     * Configures extended request parameters.
     *
     * @param array $params Extension parameters
     * @return self
     */
    public function setAdditionalOptions(array $params): self
    {
        $this->extensions = $params;
        return $this;
    }

    /**
     * Removes all search criteria.
     *
     * @return self
     */
    public function clearFilters(): self
    {
        $this->criteria = [];
        return $this;
    }

    /**
     * Automatically resolves the accounting period from available data.
     */
    private function resolvePeriodAutomatically(): void
    {
        if (!empty($this->criteria['PeriodoImputacion'])) {
            return;
        }

        $dateSource = null;

        if ($this->targetInvoice['issueDate'] !== null) {
            $dateSource = $this->targetInvoice['issueDate'];
        } elseif (isset($this->criteria['IDFactura']['FechaExpedicionFacturaEmisor'])) {
            $dateSource = $this->criteria['IDFactura']['FechaExpedicionFacturaEmisor'];
        }

        if ($dateSource !== null) {
            $period = $this->extractPeriodFromDate($dateSource);
            $this->criteria['PeriodoImputacion'] = [
                'Ejercicio' => $period['year'],
                'Periodo' => $period['month'],
            ];
        }
    }

    /**
     * Incorporates constructor-level invoice filter if not overridden.
     *
     * @param array $filterBlock Reference to filter block
     */
    private function applyTargetInvoiceFilter(array &$filterBlock): void
    {
        if ($this->targetInvoice['serial'] === null || $this->targetInvoice['issueDate'] === null) {
            return;
        }

        if (isset($filterBlock['IDFactura'])) {
            return;
        }

        $filterBlock['IDFactura'] = [
            'NumSerieFacturaEmisor' => $this->targetInvoice['serial'],
            'FechaExpedicionFacturaEmisor' => $this->targetInvoice['issueDate'],
            'NIF' => $this->obligatedParty['taxId'],
        ];
    }

    /**
     * Incorporates constructor-level counterparty filter if not overridden.
     *
     * @param array $filterBlock Reference to filter block
     */
    private function applyThirdPartyFilter(array &$filterBlock): void
    {
        if ($this->thirdParty['taxId'] === null) {
            return;
        }

        if (isset($filterBlock['Contraparte'])) {
            return;
        }

        $party = ['NIF' => $this->thirdParty['taxId']];
        if ($this->thirdParty['legalName'] !== null) {
            $party['NombreRazon'] = $this->thirdParty['legalName'];
        }
        $filterBlock['Contraparte'] = $party;
    }

    /**
     * Builds the complete request structure for AEAT submission.
     *
     * @return array Structured request data
     */
    public function getData(): array
    {
        $this->resolvePeriodAutomatically();

        $filterBlock = $this->criteria;
        $this->applyTargetInvoiceFilter($filterBlock);
        $this->applyThirdPartyFilter($filterBlock);

        $request = [
            'ConsultaFactuSistemaFacturacion' => [
                'Cabecera' => [
                    'IDVersion' => self::PROTOCOL_VERSION,
                    'ObligadoEmision' => [
                        'NombreRazon' => $this->obligatedParty['legalName'],
                        'NIF' => $this->obligatedParty['taxId'],
                    ],
                ],
                'FiltroConsulta' => $filterBlock,
            ],
        ];

        if (!empty($this->extensions)) {
            $request['ConsultaFactuSistemaFacturacion'] = array_merge_recursive(
                $request['ConsultaFactuSistemaFacturacion'],
                $this->extensions
            );
        }

        return $request;
    }

    /**
     * Returns the current search criteria.
     *
     * @return array Active criteria
     */
    public function getFilters(): array
    {
        return $this->criteria;
    }

    /**
     * Returns the target invoice serial number.
     *
     * @return string|null Serial number or null
     */
    public function getInvoiceNumber(): ?string
    {
        return $this->targetInvoice['serial'];
    }

    /**
     * Returns the target invoice date.
     *
     * @return string|null Date string or null
     */
    public function getInvoiceDate(): ?string
    {
        return $this->targetInvoice['issueDate'];
    }

    /**
     * Returns the obligated party tax ID.
     *
     * @return string Tax ID
     */
    public function getIssuerNif(): string
    {
        return $this->obligatedParty['taxId'];
    }

    /**
     * Returns the obligated party legal name.
     *
     * @return string Legal name
     */
    public function getIssuerName(): string
    {
        return $this->obligatedParty['legalName'];
    }

    /**
     * Returns the counterparty tax ID filter.
     *
     * @return string|null Tax ID or null
     */
    public function getRecipientNif(): ?string
    {
        return $this->thirdParty['taxId'];
    }

    /**
     * Returns the counterparty name filter.
     *
     * @return string|null Name or null
     */
    public function getRecipientName(): ?string
    {
        return $this->thirdParty['legalName'];
    }

    /**
     * Returns the extended parameters.
     *
     * @return array Extension parameters
     */
    public function getAdditionalOptions(): array
    {
        return $this->extensions;
    }

    /**
     * Creates a period-only search request.
     *
     * @param string $taxId Obligated party tax ID
     * @param string $name Obligated party name
     * @param string $year Fiscal year
     * @param string $month Fiscal month
     * @return self Configured instance
     */
    public static function forPeriod(string $taxId, string $name, string $year, string $month): self
    {
        $instance = new self(null, null, $taxId, $name);
        $instance->setFiscalPeriod($year, $month);
        return $instance;
    }

    /**
     * Creates a single invoice search request.
     *
     * @param string $taxId Obligated party tax ID
     * @param string $name Obligated party name
     * @param string $serial Invoice serial
     * @param string $date Invoice date
     * @return self Configured instance
     */
    public static function forInvoice(string $taxId, string $name, string $serial, string $date): self
    {
        return new self($serial, $date, $taxId, $name);
    }

    /**
     * Creates a counterparty search request.
     *
     * @param string $obligatedTaxId Obligated party tax ID
     * @param string $obligatedName Obligated party name
     * @param string $counterpartyTaxId Counterparty tax ID
     * @param string $year Fiscal year
     * @param string $month Fiscal month
     * @return self Configured instance
     */
    public static function forCounterparty(
        string $obligatedTaxId,
        string $obligatedName,
        string $counterpartyTaxId,
        string $year,
        string $month
    ): self {
        $instance = new self(null, null, $obligatedTaxId, $obligatedName, $counterpartyTaxId);
        $instance->setFiscalPeriod($year, $month);
        return $instance;
    }
}
