<?php

namespace QistassPay;

/**
 * Thrown when Qistass Pay responds successfully (HTTP-wise) but with a
 * non-success status — e.g. merchant_not_found, invalid_signature — or when
 * the response is missing fields the SDK needs.
 */
class QistassPayException extends \Exception
{
    /** Machine-readable status string from Qistass Pay, e.g. "merchant_not_found". */
    public string $status;

    /** The full decoded response body, for callers who need more detail. */
    public array $response;

    public function __construct(string $status, string $message, array $response)
    {
        parent::__construct("Qistass Pay error [{$status}]: {$message}");
        $this->status = $status;
        $this->response = $response;
    }
}
