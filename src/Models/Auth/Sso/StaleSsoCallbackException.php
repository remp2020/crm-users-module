<?php

namespace Crm\UsersModule\Models\Auth\Sso;

use Nette\Utils\Json;

/**
 * Thrown when an SSO callback cannot be verified because the flow it belongs to is no longer in progress.
 */
class StaleSsoCallbackException extends SsoException
{
    /** State was issued by us and its flow already finished successfully. */
    public const REASON_REPLAYED = 'replayed';

    /** State was issued by us, but its flow never finished and the cookies are already gone. */
    public const REASON_EXPIRED = 'expired';

    /** State is not (or no longer) known to us; may also be a forged callback. */
    public const REASON_UNKNOWN_STATE = 'unknown_state';

    public function __construct(
        string $message,
        private readonly string $reason,
        private readonly array $context = [],
    ) {
        parent::__construct(sprintf('%s (reason: %s, context: %s)', $message, $reason, Json::encode($context)));
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Expected reasons are a normal part of the browser's lifecycle and don't need to be reported as errors.
     */
    public function isExpected(): bool
    {
        return in_array($this->reason, [self::REASON_REPLAYED, self::REASON_EXPIRED], true);
    }

    /**
     * The flow this callback belongs to already finished successfully, so the sign-in the user asked for is done
     * and the callback carries no work left to do.
     */
    public function isAlreadyCompleted(): bool
    {
        return $this->reason === self::REASON_REPLAYED;
    }
}
