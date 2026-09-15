<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Queue;

use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Agent;
use CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Tracing\Tracer;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Incubating\Attributes\MessagingIncubatingAttributes;
/**
 * Traces one queue/worker job boundary (SPEC §30-31) - a long-running worker
 * (a daemon loop, `queue:work`, a Swoole/RoadRunner-style process) never
 * restarts PHP between jobs the way a normal request does, so nothing may
 * assume "one process = one trace".
 *
 * No special context-reset code is needed here beyond what `Tracer::trace()`
 * already does: it activates a span's scope, runs the callback, then ends
 * the span and detaches the scope in a `finally` block regardless of outcome
 * (`ContextManager::runInSpan()`) - called once per job, that already leaves
 * nothing active for the next iteration to accidentally inherit. This class
 * only adds the semantics a queue job needs on top: `SpanKind::KIND_CONSUMER`
 * and the OTel messaging attributes, instead of every caller having to
 * remember them.
 */
final class QueueJob
{
    /**
     * @template T
     *
     * @param callable(): T                              $callback
     * @param array<string, bool|int|float|string|null> $attributes extra attributes, merged in last
     *
     * @return T
     */
    public static function run(string $name, callable $callback, ?string $system = null, ?string $destination = null, array $attributes = [], ?Tracer $tracer = null): mixed
    {
        $tracer ??= Agent::tracer();
        if ($tracer === null) {
            return $callback();
        }
        $merged = array_merge(array_filter([MessagingIncubatingAttributes::MESSAGING_SYSTEM => $system, MessagingIncubatingAttributes::MESSAGING_DESTINATION_NAME => $destination, MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE => MessagingIncubatingAttributes::MESSAGING_OPERATION_TYPE_VALUE_PROCESS], static fn($value) => $value !== null), $attributes);
        return $tracer->trace($name, static fn() => $callback(), $merged, SpanKind::KIND_CONSUMER);
    }
}
/**
 * Traces one queue/worker job boundary (SPEC §30-31) - a long-running worker
 * (a daemon loop, `queue:work`, a Swoole/RoadRunner-style process) never
 * restarts PHP between jobs the way a normal request does, so nothing may
 * assume "one process = one trace".
 *
 * No special context-reset code is needed here beyond what `Tracer::trace()`
 * already does: it activates a span's scope, runs the callback, then ends
 * the span and detaches the scope in a `finally` block regardless of outcome
 * (`ContextManager::runInSpan()`) - called once per job, that already leaves
 * nothing active for the next iteration to accidentally inherit. This class
 * only adds the semantics a queue job needs on top: `SpanKind::KIND_CONSUMER`
 * and the OTel messaging attributes, instead of every caller having to
 * remember them.
 */
\class_alias('CresenityDevCloudAPMVendor\Cresenity\DevCloud\APM\Instrumentation\Queue\QueueJob', 'Cresenity\DevCloud\APM\Instrumentation\Queue\QueueJob', \false);
