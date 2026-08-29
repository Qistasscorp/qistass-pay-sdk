<?php

namespace QistassPay;

/**
 * Thrown when the HTTP request to Qistass Pay itself fails at the transport
 * level (timeout, DNS, TLS, non-JSON body) — as opposed to QistassPayException,
 * which means Qistass Pay answered but rejected the request.
 */
class QistassPayNetworkException extends \RuntimeException
{
}
