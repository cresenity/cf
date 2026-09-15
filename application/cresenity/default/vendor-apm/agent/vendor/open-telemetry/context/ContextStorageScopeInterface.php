<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\Context;

use ArrayAccess;
/**
 * @psalm-suppress MissingTemplateParam
 */
interface ContextStorageScopeInterface extends ScopeInterface, ArrayAccess
{
    /**
     * Returns the context associated with this scope.
     *
     * @return ContextInterface associated context
     */
    public function context(): ContextInterface;
    /**
     * @param string $offset
     */
    #[\Override]
    public function offsetSet($offset, $value): void;
}
