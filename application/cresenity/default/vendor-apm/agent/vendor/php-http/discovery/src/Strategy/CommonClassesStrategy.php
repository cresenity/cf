<?php

namespace CresenityDevCloudAPMVendor\Http\Discovery\Strategy;

use CresenityDevCloudAPMVendor\GuzzleHttp\Client as GuzzleHttp;
use CresenityDevCloudAPMVendor\GuzzleHttp\Promise\Promise;
use CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\Request as GuzzleRequest;
use CresenityDevCloudAPMVendor\Http\Adapter\Artax\Client as Artax;
use CresenityDevCloudAPMVendor\Http\Adapter\Buzz\Client as Buzz;
use CresenityDevCloudAPMVendor\Http\Adapter\Cake\Client as Cake;
use CresenityDevCloudAPMVendor\Http\Adapter\Guzzle5\Client as Guzzle5;
use CresenityDevCloudAPMVendor\Http\Adapter\Guzzle6\Client as Guzzle6;
use CresenityDevCloudAPMVendor\Http\Adapter\Guzzle7\Client as Guzzle7;
use CresenityDevCloudAPMVendor\Http\Adapter\React\Client as React;
use CresenityDevCloudAPMVendor\Http\Client\Curl\Client as Curl;
use CresenityDevCloudAPMVendor\Http\Client\HttpAsyncClient;
use CresenityDevCloudAPMVendor\Http\Client\HttpClient;
use CresenityDevCloudAPMVendor\Http\Client\Socket\Client as Socket;
use CresenityDevCloudAPMVendor\Http\Discovery\ClassDiscovery;
use CresenityDevCloudAPMVendor\Http\Discovery\Exception\NotFoundException;
use CresenityDevCloudAPMVendor\Http\Discovery\Psr17FactoryDiscovery;
use CresenityDevCloudAPMVendor\Http\Message\MessageFactory;
use CresenityDevCloudAPMVendor\Http\Message\MessageFactory\DiactorosMessageFactory;
use CresenityDevCloudAPMVendor\Http\Message\MessageFactory\GuzzleMessageFactory;
use CresenityDevCloudAPMVendor\Http\Message\MessageFactory\SlimMessageFactory;
use CresenityDevCloudAPMVendor\Http\Message\StreamFactory;
use CresenityDevCloudAPMVendor\Http\Message\StreamFactory\DiactorosStreamFactory;
use CresenityDevCloudAPMVendor\Http\Message\StreamFactory\GuzzleStreamFactory;
use CresenityDevCloudAPMVendor\Http\Message\StreamFactory\SlimStreamFactory;
use CresenityDevCloudAPMVendor\Http\Message\UriFactory;
use CresenityDevCloudAPMVendor\Http\Message\UriFactory\DiactorosUriFactory;
use CresenityDevCloudAPMVendor\Http\Message\UriFactory\GuzzleUriFactory;
use CresenityDevCloudAPMVendor\Http\Message\UriFactory\SlimUriFactory;
use CresenityDevCloudAPMVendor\Laminas\Diactoros\Request as DiactorosRequest;
use CresenityDevCloudAPMVendor\Nyholm\Psr7\Factory\HttplugFactory as NyholmHttplugFactory;
use CresenityDevCloudAPMVendor\Psr\Http\Client\ClientInterface as Psr18Client;
use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestFactoryInterface as Psr17RequestFactory;
use CresenityDevCloudAPMVendor\Slim\Http\Request as SlimRequest;
use CresenityDevCloudAPMVendor\Symfony\Component\HttpClient\HttplugClient as SymfonyHttplug;
use CresenityDevCloudAPMVendor\Symfony\Component\HttpClient\Psr18Client as SymfonyPsr18;
/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * Don't miss updating src/Composer/Plugin.php when adding a new supported class.
 */
final class CommonClassesStrategy implements DiscoveryStrategy
{
    /**
     * @var array
     */
    private static $classes = [MessageFactory::class => [['class' => NyholmHttplugFactory::class, 'condition' => [NyholmHttplugFactory::class]], ['class' => GuzzleMessageFactory::class, 'condition' => [GuzzleRequest::class, GuzzleMessageFactory::class]], ['class' => DiactorosMessageFactory::class, 'condition' => [DiactorosRequest::class, DiactorosMessageFactory::class]], ['class' => SlimMessageFactory::class, 'condition' => [SlimRequest::class, SlimMessageFactory::class]]], StreamFactory::class => [['class' => NyholmHttplugFactory::class, 'condition' => [NyholmHttplugFactory::class]], ['class' => GuzzleStreamFactory::class, 'condition' => [GuzzleRequest::class, GuzzleStreamFactory::class]], ['class' => DiactorosStreamFactory::class, 'condition' => [DiactorosRequest::class, DiactorosStreamFactory::class]], ['class' => SlimStreamFactory::class, 'condition' => [SlimRequest::class, SlimStreamFactory::class]]], UriFactory::class => [['class' => NyholmHttplugFactory::class, 'condition' => [NyholmHttplugFactory::class]], ['class' => GuzzleUriFactory::class, 'condition' => [GuzzleRequest::class, GuzzleUriFactory::class]], ['class' => DiactorosUriFactory::class, 'condition' => [DiactorosRequest::class, DiactorosUriFactory::class]], ['class' => SlimUriFactory::class, 'condition' => [SlimRequest::class, SlimUriFactory::class]]], HttpAsyncClient::class => [['class' => SymfonyHttplug::class, 'condition' => [SymfonyHttplug::class, Promise::class, [self::class, 'isPsr17FactoryInstalled']]], ['class' => Guzzle7::class, 'condition' => Guzzle7::class], ['class' => Guzzle6::class, 'condition' => Guzzle6::class], ['class' => Curl::class, 'condition' => Curl::class], ['class' => React::class, 'condition' => React::class]], HttpClient::class => [['class' => SymfonyHttplug::class, 'condition' => [SymfonyHttplug::class, [self::class, 'isPsr17FactoryInstalled'], [self::class, 'isSymfonyImplementingHttpClient']]], ['class' => Guzzle7::class, 'condition' => Guzzle7::class], ['class' => Guzzle6::class, 'condition' => Guzzle6::class], ['class' => Guzzle5::class, 'condition' => Guzzle5::class], ['class' => Curl::class, 'condition' => Curl::class], ['class' => Socket::class, 'condition' => Socket::class], ['class' => Buzz::class, 'condition' => Buzz::class], ['class' => React::class, 'condition' => React::class], ['class' => Cake::class, 'condition' => Cake::class], ['class' => Artax::class, 'condition' => Artax::class], ['class' => [self::class, 'buzzInstantiate'], 'condition' => [\CresenityDevCloudAPMVendor\Buzz\Client\FileGetContents::class, \CresenityDevCloudAPMVendor\Buzz\Message\ResponseBuilder::class]]], Psr18Client::class => [['class' => [self::class, 'symfonyPsr18Instantiate'], 'condition' => [SymfonyPsr18::class, Psr17RequestFactory::class]], ['class' => GuzzleHttp::class, 'condition' => [self::class, 'isGuzzleImplementingPsr18']], ['class' => [self::class, 'buzzInstantiate'], 'condition' => [\CresenityDevCloudAPMVendor\Buzz\Client\FileGetContents::class, \CresenityDevCloudAPMVendor\Buzz\Message\ResponseBuilder::class]]]];
    public static function getCandidates($type)
    {
        if (Psr18Client::class === $type) {
            return self::getPsr18Candidates();
        }
        return self::$classes[$type] ?? [];
    }
    /**
     * @return array The return value is always an array with zero or more elements. Each
     *               element is an array with two keys ['class' => string, 'condition' => mixed].
     */
    private static function getPsr18Candidates()
    {
        $candidates = self::$classes[Psr18Client::class];
        // HTTPlug 2.0 clients implements PSR18Client too.
        foreach (self::$classes[HttpClient::class] as $c) {
            if (!is_string($c['class'])) {
                continue;
            }
            try {
                if (ClassDiscovery::safeClassExists($c['class']) && is_subclass_of($c['class'], Psr18Client::class)) {
                    $candidates[] = $c;
                }
            } catch (\Throwable $e) {
                trigger_error(sprintf('Got exception "%s (%s)" while checking if a PSR-18 Client is available', get_class($e), $e->getMessage()), \E_USER_WARNING);
            }
        }
        return $candidates;
    }
    public static function buzzInstantiate()
    {
        return new \CresenityDevCloudAPMVendor\Buzz\Client\FileGetContents(Psr17FactoryDiscovery::findResponseFactory());
    }
    public static function symfonyPsr18Instantiate()
    {
        return new SymfonyPsr18(null, Psr17FactoryDiscovery::findResponseFactory(), Psr17FactoryDiscovery::findStreamFactory());
    }
    public static function isGuzzleImplementingPsr18()
    {
        return defined('CresenityDevCloudAPMVendor\GuzzleHttp\ClientInterface::MAJOR_VERSION');
    }
    public static function isSymfonyImplementingHttpClient()
    {
        return is_subclass_of(SymfonyHttplug::class, HttpClient::class);
    }
    /**
     * Can be used as a condition.
     *
     * @return bool
     */
    public static function isPsr17FactoryInstalled()
    {
        try {
            Psr17FactoryDiscovery::findResponseFactory();
        } catch (NotFoundException $e) {
            return \false;
        } catch (\Throwable $e) {
            trigger_error(sprintf('Got exception "%s (%s)" while checking if a PSR-17 ResponseFactory is available', get_class($e), $e->getMessage()), \E_USER_WARNING);
            return \false;
        }
        return \true;
    }
}
