<?php

namespace Wexample\SymfonyMonitoring\Class;

/**
 * What an incident is about: a remote, a heartbeat, or anything the application
 * names — a type and a key, both stable.
 */
final readonly class IncidentSource
{
    public const string TYPE_REMOTE = 'remote';

    public const string TYPE_HEARTBEAT = 'heartbeat';

    public function __construct(
        public string $type,
        public string $key,
    ) {
    }

    public static function remote(string $key): self
    {
        return new self(self::TYPE_REMOTE, $key);
    }

    public static function heartbeat(string $key): self
    {
        return new self(self::TYPE_HEARTBEAT, $key);
    }

    /**
     * What makes two reports the same incident: this source, the code, and what
     * tells two of that code apart — the file a misdated report is in.
     */
    public function fingerprint(
        string $code,
        ?string $discriminator = null
    ): string {
        return hash('sha256', implode("\0", [$this->type, $this->key, $code, $discriminator ?? '']));
    }
}
