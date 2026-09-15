<?php

namespace CresenityDevCloudAPMVendor\Http\Discovery\Strategy;

use CresenityDevCloudAPMVendor\Psr\Http\Message\RequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ResponseFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\ServerRequestFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\StreamFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\UploadedFileFactoryInterface;
use CresenityDevCloudAPMVendor\Psr\Http\Message\UriFactoryInterface;
/**
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * Don't miss updating src/Composer/Plugin.php when adding a new supported class.
 */
final class CommonPsr17ClassesStrategy implements DiscoveryStrategy
{
    /**
     * @var array
     */
    private static $classes = [RequestFactoryInterface::class => ['CresenityDevCloudAPMVendor\Phalcon\Http\Message\RequestFactory', 'CresenityDevCloudAPMVendor\Nyholm\Psr7\Factory\Psr17Factory', 'CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\HttpFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Diactoros\RequestFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Guzzle\RequestFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Slim\RequestFactory', 'CresenityDevCloudAPMVendor\Laminas\Diactoros\RequestFactory', 'CresenityDevCloudAPMVendor\Slim\Psr7\Factory\RequestFactory', 'CresenityDevCloudAPMVendor\HttpSoft\Message\RequestFactory'], ResponseFactoryInterface::class => ['CresenityDevCloudAPMVendor\Phalcon\Http\Message\ResponseFactory', 'CresenityDevCloudAPMVendor\Nyholm\Psr7\Factory\Psr17Factory', 'CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\HttpFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Diactoros\ResponseFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Guzzle\ResponseFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Slim\ResponseFactory', 'CresenityDevCloudAPMVendor\Laminas\Diactoros\ResponseFactory', 'CresenityDevCloudAPMVendor\Slim\Psr7\Factory\ResponseFactory', 'CresenityDevCloudAPMVendor\HttpSoft\Message\ResponseFactory'], ServerRequestFactoryInterface::class => ['CresenityDevCloudAPMVendor\Phalcon\Http\Message\ServerRequestFactory', 'CresenityDevCloudAPMVendor\Nyholm\Psr7\Factory\Psr17Factory', 'CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\HttpFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Diactoros\ServerRequestFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Guzzle\ServerRequestFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Slim\ServerRequestFactory', 'CresenityDevCloudAPMVendor\Laminas\Diactoros\ServerRequestFactory', 'CresenityDevCloudAPMVendor\Slim\Psr7\Factory\ServerRequestFactory', 'CresenityDevCloudAPMVendor\HttpSoft\Message\ServerRequestFactory'], StreamFactoryInterface::class => ['CresenityDevCloudAPMVendor\Phalcon\Http\Message\StreamFactory', 'CresenityDevCloudAPMVendor\Nyholm\Psr7\Factory\Psr17Factory', 'CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\HttpFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Diactoros\StreamFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Guzzle\StreamFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Slim\StreamFactory', 'CresenityDevCloudAPMVendor\Laminas\Diactoros\StreamFactory', 'CresenityDevCloudAPMVendor\Slim\Psr7\Factory\StreamFactory', 'CresenityDevCloudAPMVendor\HttpSoft\Message\StreamFactory'], UploadedFileFactoryInterface::class => ['CresenityDevCloudAPMVendor\Phalcon\Http\Message\UploadedFileFactory', 'CresenityDevCloudAPMVendor\Nyholm\Psr7\Factory\Psr17Factory', 'CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\HttpFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Diactoros\UploadedFileFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Guzzle\UploadedFileFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Slim\UploadedFileFactory', 'CresenityDevCloudAPMVendor\Laminas\Diactoros\UploadedFileFactory', 'CresenityDevCloudAPMVendor\Slim\Psr7\Factory\UploadedFileFactory', 'CresenityDevCloudAPMVendor\HttpSoft\Message\UploadedFileFactory'], UriFactoryInterface::class => ['CresenityDevCloudAPMVendor\Phalcon\Http\Message\UriFactory', 'CresenityDevCloudAPMVendor\Nyholm\Psr7\Factory\Psr17Factory', 'CresenityDevCloudAPMVendor\GuzzleHttp\Psr7\HttpFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Diactoros\UriFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Guzzle\UriFactory', 'CresenityDevCloudAPMVendor\Http\Factory\Slim\UriFactory', 'CresenityDevCloudAPMVendor\Laminas\Diactoros\UriFactory', 'CresenityDevCloudAPMVendor\Slim\Psr7\Factory\UriFactory', 'CresenityDevCloudAPMVendor\HttpSoft\Message\UriFactory']];
    public static function getCandidates($type)
    {
        $candidates = [];
        if (isset(self::$classes[$type])) {
            foreach (self::$classes[$type] as $class) {
                $candidates[] = ['class' => $class, 'condition' => [$class]];
            }
        }
        return $candidates;
    }
}
