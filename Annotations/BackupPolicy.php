<?php

namespace Mabe\BackupBundle\Annotations;

/**
 * @Annotation
 * @Target("CLASS")
 */
final class BackupPolicy
{
    const GROUPS = 'GROUPS';
    const ALL = 'ALL';

    public $policy;

    public function __construct(array $values)
    {
        $this->policy = strtoupper($values['value']);

        if ($this->policy !== self::ALL && $this->policy !== self::GROUPS) {
            throw new \RuntimeException('Available backup policies are "all" or "groups".');
        }
    }
}