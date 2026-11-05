<?php

namespace IPE\Extension;

/**
 * An extension that the resolver decided not to install.
 */
final class Skipped implements \JsonSerializable
{
    /**
     * @public
     * @var string
     */
    const REASON_ALREADY_INSTALLED = 'already-installed';

    /**
     * @public
     * @var string
     */
    const REASON_UNKNOWN = 'unknown';

    /**
     * @public
     * @var string
     */
    const REASON_UNSUPPORTED = 'unsupported';

    /**
     * @public
     * @var string
     */
    const REASON_CONFLICT = 'conflict';

    /**
     * @public
     * @var string
     */
    const REASON_REQUIRES_SKIPPED = 'requires-skipped';

    /**
     * @var non-empty-string
     */
    private $name;

    /**
     * @var non-empty-string
     */
    private $reason;

    /**
     * @var non-empty-string
     */
    private $message;

    /**
     * @param non-empty-string $name the handle or the name of the extension (or the directory containing it, if it's unknown)
     * @param non-empty-string $reason one of the REASON_* constants
     * @param non-empty-string $message
     */
    public function __construct($name, $reason, $message)
    {
        $this->name = $name;
        $this->reason = $reason;
        $this->message = $message;
    }

    /**
     * Get the handle or the name of the extension (or the directory containing it, if it's unknown).
     *
     * @return non-empty-string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return non-empty-string one of the REASON_* constants
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * @return non-empty-string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * {@inheritdoc}
     *
     * @see \JsonSerializable::jsonSerialize()
     *
     * @return array{name: non-empty-string, reason: non-empty-string, message: non-empty-string}
     */
    #[\ReturnTypeWillChange()]
    public function jsonSerialize()
    {
        return [
            'name' => $this->name,
            'reason' => $this->reason,
            'message' => $this->message,
        ];
    }
}
