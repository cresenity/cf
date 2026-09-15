<?php

declare (strict_types=1);
namespace CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Trace;

use CresenityDevCloudAPMVendor\Http\Discovery\Exception\NotFoundException;
use CresenityDevCloudAPMVendor\Http\Discovery\Psr17FactoryDiscovery;
use CresenityDevCloudAPMVendor\Nyholm\Psr7Server\ServerRequestCreator;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Behavior\LogsMessagesTrait;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Common\Time\ClockInterface;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Globals;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\Span;
use CresenityDevCloudAPMVendor\OpenTelemetry\API\Trace\SpanKind;
use CresenityDevCloudAPMVendor\OpenTelemetry\Context\Context;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Configuration;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Configuration\Variables;
use CresenityDevCloudAPMVendor\OpenTelemetry\SDK\Common\Util\ShutdownHandler;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\TraceAttributes;
use CresenityDevCloudAPMVendor\OpenTelemetry\SemConv\Version;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ServerRequestInterface;
class AutoRootSpan
{
    use LogsMessagesTrait;
    public static function isEnabled(): bool
    {
        return !empty($_SERVER['REQUEST_METHOD'] ?? null) && Configuration::getBoolean(Variables::OTEL_PHP_EXPERIMENTAL_AUTO_ROOT_SPAN);
    }
    /**
     * @psalm-suppress ArgumentTypeCoercion
     * @internal
     */
    public static function create(ServerRequestInterface $request): void
    {
        $tracer = Globals::tracerProvider()->getTracer('io.opentelemetry.php.auto-root-span', null, Version::VERSION_1_25_0->url());
        $parent = Globals::propagator()->extract($request->getHeaders());
        $startTimeInNanos = array_key_exists('REQUEST_TIME_FLOAT', $request->getServerParams()) ? (int) ((float) $request->getServerParams()['REQUEST_TIME_FLOAT'] * (float) ClockInterface::NANOS_PER_SECOND) : (int) (microtime(\true) * (float) ClockInterface::NANOS_PER_SECOND);
        $span = $tracer->spanBuilder($request->getMethod())->setSpanKind(SpanKind::KIND_SERVER)->setStartTimestamp($startTimeInNanos)->setParent($parent)->setAttribute(TraceAttributes::URL_FULL, (string) $request->getUri())->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())->setAttribute(TraceAttributes::HTTP_REQUEST_BODY_SIZE, $request->getHeaderLine('Content-Length'))->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->getHeaderLine('User-Agent'))->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getUri()->getHost())->setAttribute(TraceAttributes::SERVER_PORT, $request->getUri()->getPort())->setAttribute(TraceAttributes::URL_SCHEME, $request->getUri()->getScheme())->setAttribute(TraceAttributes::URL_PATH, $request->getUri()->getPath())->startSpan();
        Context::storage()->attach($span->storeInContext($parent));
    }
    /**
     * @internal
     */
    public static function createRequest(): ?ServerRequestInterface
    {
        assert(array_key_exists('REQUEST_METHOD', $_SERVER) && !empty($_SERVER['REQUEST_METHOD']));
        try {
            $creator = new ServerRequestCreator(Psr17FactoryDiscovery::findServerRequestFactory(), Psr17FactoryDiscovery::findUriFactory(), Psr17FactoryDiscovery::findUploadedFileFactory(), Psr17FactoryDiscovery::findStreamFactory());
            return $creator->fromGlobals();
        } catch (NotFoundException $e) {
            self::logError('Unable to initialize server request creator for auto root span creation', ['exception' => $e]);
        }
        return null;
    }
    /**
     * @internal
     */
    public static function registerShutdownHandler(): void
    {
        ShutdownHandler::register(self::shutdownHandler(...));
    }
    /**
     * @internal
     */
    public static function shutdownHandler(): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());
        $span->end();
    }
}
