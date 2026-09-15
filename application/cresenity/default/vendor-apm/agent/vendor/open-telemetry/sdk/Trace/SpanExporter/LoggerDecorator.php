<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporter;

use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\Behavior\LoggerAwareTrait;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\Behavior\SpanExporterDecoratorTrait;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\Behavior\UsesSpanConverterTrait;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanConverterInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace\SpanExporterInterface;
use CresenityDevCloudAPMVendor\Psr\Log\LoggerAwareInterface;
use CresenityDevCloudAPMVendor\Psr\Log\LoggerInterface;
use CresenityDevCloudAPMVendor\Psr\Log\LogLevel;
use CresenityDevCloudAPMVendor\Psr\Log\NullLogger;
class LoggerDecorator implements SpanExporterInterface, LoggerAwareInterface
{
    use SpanExporterDecoratorTrait;
    use UsesSpanConverterTrait;
    use LoggerAwareTrait;
    public function __construct(SpanExporterInterface $decorated, ?LoggerInterface $logger = null, ?SpanConverterInterface $converter = null)
    {
        $this->setDecorated($decorated);
        $this->setLogger($logger ?? new NullLogger());
        $this->setSpanConverter($converter ?? new FriendlySpanConverter());
    }
    #[\Override]
    protected function beforeExport(iterable $spans): iterable
    {
        return $spans;
    }
    #[\Override]
    protected function afterExport(iterable $spans, bool $exportSuccess): void
    {
        if ($exportSuccess) {
            $this->log('Status Success', $this->getSpanConverter()->convert($spans), LogLevel::INFO);
        } else {
            $this->log('Status Failed Retryable', $this->getSpanConverter()->convert($spans), LogLevel::ERROR);
        }
    }
}
