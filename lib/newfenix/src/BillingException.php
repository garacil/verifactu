<?php

/**
 * OpenAEAT Billing Library - BillingException Class
 *
 * This class provides a specialized exception type for billing operations
 * in the AEAT VeriFactu system. Supports contextual error information,
 * error categorization, and convenient factory methods for common errors.
 *
 * @package    OpenAEAT\Billing
 * @author     German Luis Aracil Boned <garacilb@gmail.com>
 * @copyright  2025 German Luis Aracil Boned
 * @license    GPL-3.0-or-later
 */

declare(strict_types=1);

namespace OpenAEAT\Billing;

/**
 * Billing Operations Exception Handler
 */
class BillingException extends \Exception
{
    /**
     * Error category identifiers
     */
    public const CATEGORY_VALIDATION = 'validation';
    public const CATEGORY_COMMUNICATION = 'communication';
    public const CATEGORY_AUTHENTICATION = 'authentication';
    public const CATEGORY_PROCESSING = 'processing';
    public const CATEGORY_CONFIGURATION = 'configuration';

    /**
     * Standard HTTP status codes for service errors
     */
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_UNAUTHORIZED = 401;
    private const HTTP_FORBIDDEN = 403;
    private const HTTP_NOT_FOUND = 404;
    private const HTTP_SERVICE_UNAVAILABLE = 503;

    /**
     * Error details storage
     */
    private array $errorDetails = [
        'context' => [],
        'category' => self::CATEGORY_PROCESSING,
        'timestamp' => null,
    ];

    /**
     * Creates a new billing exception.
     *
     * @param string $message Error description
     * @param int $code Error code
     * @param \Throwable|null $previous Previous exception for chaining
     * @param array $context Additional contextual data
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);
        $this->errorDetails['context'] = $context;
        $this->errorDetails['timestamp'] = $this->captureTimestamp();
    }

    /**
     * Captures the current timestamp.
     *
     * @return string ISO 8601 timestamp
     */
    private function captureTimestamp(): string
    {
        return (new \DateTime())->format('c');
    }

    /**
     * Assigns the error category.
     *
     * @param string $category Category identifier
     * @return self
     */
    private function assignCategory(string $category): self
    {
        $this->errorDetails['category'] = $category;
        return $this;
    }

    /**
     * Returns the contextual data.
     *
     * @return array Context array
     */
    public function getContext(): array
    {
        return $this->errorDetails['context'];
    }

    /**
     * Returns the error category.
     *
     * @return string Category identifier
     */
    public function getCategory(): string
    {
        return $this->errorDetails['category'];
    }

    /**
     * Returns the error timestamp.
     *
     * @return string|null ISO 8601 timestamp
     */
    public function getTimestamp(): ?string
    {
        return $this->errorDetails['timestamp'];
    }

    /**
     * Checks if context contains specific key.
     *
     * @param string $key Context key to check
     * @return bool True if key exists
     */
    public function hasContextKey(string $key): bool
    {
        return array_key_exists($key, $this->errorDetails['context']);
    }

    /**
     * Retrieves a specific context value.
     *
     * @param string $key Context key
     * @param mixed $default Default value if not found
     * @return mixed Context value or default
     */
    public function getContextValue(string $key, $default = null)
    {
        return $this->errorDetails['context'][$key] ?? $default;
    }

    /**
     * Adds additional context data.
     *
     * @param string $key Context key
     * @param mixed $value Context value
     * @return self
     */
    public function addContext(string $key, $value): self
    {
        $this->errorDetails['context'][$key] = $value;
        return $this;
    }

    /**
     * Checks if this is a validation error.
     *
     * @return bool True if validation category
     */
    public function isValidationError(): bool
    {
        return $this->errorDetails['category'] === self::CATEGORY_VALIDATION;
    }

    /**
     * Checks if this is a communication error.
     *
     * @return bool True if communication category
     */
    public function isCommunicationError(): bool
    {
        return $this->errorDetails['category'] === self::CATEGORY_COMMUNICATION;
    }

    /**
     * Serializes the exception to an array.
     *
     * @return array Exception data
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'category' => $this->errorDetails['category'],
            'context' => $this->errorDetails['context'],
            'timestamp' => $this->errorDetails['timestamp'],
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Returns the complete error details.
     *
     * @return array Error details array
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    // =========================================================================
    // STATIC FACTORY METHODS
    // =========================================================================

    /**
     * Creates an exception for invalid tax identification.
     *
     * @param string $taxId The invalid tax ID
     * @return self
     */
    public static function invalidTaxId(string $taxId): self
    {
        $instance = new self(
            "Invalid tax ID: {$taxId}",
            self::HTTP_BAD_REQUEST,
            null,
            ['taxId' => $taxId]
        );
        return $instance->assignCategory(self::CATEGORY_VALIDATION);
    }

    /**
     * Creates an exception for invalid date format.
     *
     * @param string $date The invalid date string
     * @return self
     */
    public static function invalidDate(string $date): self
    {
        $instance = new self(
            "Invalid date format: {$date}",
            self::HTTP_BAD_REQUEST,
            null,
            ['date' => $date]
        );
        return $instance->assignCategory(self::CATEGORY_VALIDATION);
    }

    /**
     * Creates an exception for SOAP communication errors.
     *
     * @param string $message Error message
     * @param \Throwable|null $previous Previous exception
     * @return self
     */
    public static function soapError(string $message, ?\Throwable $previous = null): self
    {
        $instance = new self("SOAP error: {$message}", 0, $previous);
        return $instance->assignCategory(self::CATEGORY_COMMUNICATION);
    }

    /**
     * Creates an exception for service unavailability.
     *
     * @param string $endpoint The unavailable endpoint
     * @return self
     */
    public static function serviceUnavailable(string $endpoint): self
    {
        $instance = new self(
            "Service unavailable: {$endpoint}",
            self::HTTP_SERVICE_UNAVAILABLE,
            null,
            ['endpoint' => $endpoint]
        );
        return $instance->assignCategory(self::CATEGORY_COMMUNICATION);
    }

    /**
     * Creates an exception for authentication failures.
     *
     * @param string $reason Failure reason
     * @param array $details Additional details
     * @return self
     */
    public static function authenticationFailed(string $reason, array $details = []): self
    {
        $instance = new self(
            "Authentication failed: {$reason}",
            self::HTTP_UNAUTHORIZED,
            null,
            $details
        );
        return $instance->assignCategory(self::CATEGORY_AUTHENTICATION);
    }

    /**
     * Creates an exception for certificate errors.
     *
     * @param string $message Error description
     * @param string|null $certPath Certificate path if available
     * @return self
     */
    public static function certificateError(string $message, ?string $certPath = null): self
    {
        $context = [];
        if ($certPath !== null) {
            $context['certificatePath'] = $certPath;
        }
        $instance = new self(
            "Certificate error: {$message}",
            self::HTTP_FORBIDDEN,
            null,
            $context
        );
        return $instance->assignCategory(self::CATEGORY_AUTHENTICATION);
    }

    /**
     * Creates an exception for missing required field.
     *
     * @param string $fieldName Name of the missing field
     * @return self
     */
    public static function missingRequiredField(string $fieldName): self
    {
        $instance = new self(
            "Missing required field: {$fieldName}",
            self::HTTP_BAD_REQUEST,
            null,
            ['field' => $fieldName]
        );
        return $instance->assignCategory(self::CATEGORY_VALIDATION);
    }

    /**
     * Creates an exception for invalid invoice data.
     *
     * @param string $reason Validation failure reason
     * @param array $invoiceData Related invoice data
     * @return self
     */
    public static function invalidInvoiceData(string $reason, array $invoiceData = []): self
    {
        $instance = new self(
            "Invalid invoice data: {$reason}",
            self::HTTP_BAD_REQUEST,
            null,
            $invoiceData
        );
        return $instance->assignCategory(self::CATEGORY_VALIDATION);
    }

    /**
     * Creates an exception for configuration errors.
     *
     * @param string $message Error description
     * @param array $configInfo Configuration details
     * @return self
     */
    public static function configurationError(string $message, array $configInfo = []): self
    {
        $instance = new self(
            "Configuration error: {$message}",
            0,
            null,
            $configInfo
        );
        return $instance->assignCategory(self::CATEGORY_CONFIGURATION);
    }

    /**
     * Creates an exception for hash generation failures.
     *
     * @param string $reason Failure reason
     * @return self
     */
    public static function hashGenerationFailed(string $reason): self
    {
        $instance = new self(
            "Hash generation failed: {$reason}",
            0,
            null,
            ['operation' => 'hash_generation']
        );
        return $instance->assignCategory(self::CATEGORY_PROCESSING);
    }

    /**
     * Creates an exception for chain linkage errors.
     *
     * @param string $message Error description
     * @return self
     */
    public static function chainLinkageError(string $message): self
    {
        $instance = new self(
            "Chain linkage error: {$message}",
            0,
            null,
            ['operation' => 'chain_linkage']
        );
        return $instance->assignCategory(self::CATEGORY_PROCESSING);
    }

    /**
     * Creates a generic processing exception.
     *
     * @param string $message Error description
     * @param array $context Additional context
     * @param \Throwable|null $previous Previous exception
     * @return self
     */
    public static function processingFailed(string $message, array $context = [], ?\Throwable $previous = null): self
    {
        $instance = new self($message, 0, $previous, $context);
        return $instance->assignCategory(self::CATEGORY_PROCESSING);
    }
}
