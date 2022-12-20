<?php

//declare(strict_types=1);

namespace Embed\Http;

use Composer\CaBundle\CaBundle;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Class to fetch html pages.
 */
final class CurlDispatcher {
    private $request;

    private $curl;

    private $headers = [];

    private $isBinary = false;

    private $body;

    private $error = null;

    private $settings;

    /**
     * @return ResponseInterface[]
     */
    public static function fetch(array $settings, ResponseFactoryInterface $responseFactory, RequestInterface ...$requests) {
        if (count($requests) === 1) {
            $connection = new static($settings, $requests[0]);

            return [$connection->exec($responseFactory)];
        }

        //Init connections
        $multi = curl_multi_init();
        $connections = [];

        foreach ($requests as $request) {
            $connection = new static($settings, $request);
            curl_multi_add_handle($multi, $connection->curl);

            $connections[] = $connection;
        }

        //Run
        $active = null;
        do {
            $status = curl_multi_exec($multi, $active);

            if ($active) {
                curl_multi_select($multi);
            }

            $info = curl_multi_info_read($multi);

            if ($info) {
                foreach ($connections as $connection) {
                    if ($connection->curl === $info['handle']) {
                        $connection->result = $info['result'];

                        break;
                    }
                }
            }
        } while ($active && $status == CURLM_OK);

        //Close connections
        foreach ($connections as $connection) {
            curl_multi_remove_handle($multi, $connection->curl);
        }

        curl_multi_close($multi);

        return array_map(function ($connection) use ($responseFactory) {
            return $connection->exec($responseFactory);
        }, $connections);
    }

    private function __construct(array $settings, RequestInterface $request) {
        $this->request = $request;
        $this->curl = curl_init((string) $request->getUri());
        $this->settings = $settings;

        $cookies = isset($settings['cookies_path']) ? $settings['cookies_path'] : str_replace('//', '/', sys_get_temp_dir() . '/embed-cookies.txt');

        curl_setopt_array($this->curl, [
            CURLOPT_HTTPHEADER => $this->getRequestHeaders(),
            CURLOPT_POST => strtoupper($request->getMethod()) === 'POST',
            CURLOPT_MAXREDIRS => isset($settings['max_redirs']) ? $settings['max_redirs'] : 10,
            CURLOPT_CONNECTTIMEOUT => isset($settings['connect_timeout']) ? $settings['connect_timeout'] : 10,
            CURLOPT_TIMEOUT => isset($settings['timeout']) ? $settings['timeout'] : 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => isset($settings['ssl_verify_host']) ? $settings['ssl_verify_host'] : 0,
            CURLOPT_SSL_VERIFYPEER => isset($settings['ssl_verify_peer']) ? $settings['ssl_verify_peer'] : false,
            CURLOPT_ENCODING => '',
            CURLOPT_CAINFO => CaBundle::getSystemCaRootBundlePath(),
            CURLOPT_AUTOREFERER => true,
            CURLOPT_FOLLOWLOCATION => isset($settings['follow_location']) ? $settings['follow_location'] : true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_USERAGENT => isset($settings['user_agent']) ? $settings['user_agent'] : $request->getHeaderLine('User-Agent'),
            CURLOPT_COOKIEJAR => $cookies,
            CURLOPT_COOKIEFILE => $cookies,
            CURLOPT_HEADERFUNCTION => [$this, 'writeHeader'],
            CURLOPT_WRITEFUNCTION => [$this, 'writeBody'],
        ]);
    }

    private function exec(ResponseFactoryInterface $responseFactory) {
        curl_exec($this->curl);

        $info = curl_getinfo($this->curl);

        if ($this->error) {
            $this->error(curl_strerror($this->error), $this->error);
        }

        if (curl_errno($this->curl)) {
            $this->error(curl_error($this->curl), curl_errno($this->curl));
        }

        curl_close($this->curl);

        $response = $responseFactory->createResponse($info['http_code']);

        foreach ($this->headers as $header) {
            list($name, $value) = $header;
            $response = $response->withAddedHeader($name, $value);
        }

        $response = $response
            ->withAddedHeader('Content-Location', $info['url'])
            ->withAddedHeader('X-Request-Time', sprintf('%.3f ms', $info['total_time']));

        if ($this->body) {
            //5Mb max
            $response->getBody()->write(stream_get_contents($this->body, 5000000, 0));
        }

        return $response;
    }

    private function error($message, $code) {
        $ignored = isset($this->settings['ignored_errors']) ? $this->settings['ignored_errors'] : null;

        if ($ignored === true || (is_array($ignored) && in_array($code, $ignored))) {
            return;
        }

        if ($this->isBinary && $code === CURLE_WRITE_ERROR) {
            // The write callback aborted the request to prevent a download of the binary file
            return;
        }

        throw new NetworkException($message, $code, $this->request);
    }

    private function getRequestHeaders() {
        $headers = [];

        foreach ($this->request->getHeaders() as $name => $values) {
            switch (strtolower($name)) {
                case 'user-agent':
                    break;
                default:
                    $headers[$name] = implode(', ', $values);
            }
        }

        return $headers;
    }

    private function writeHeader($curl, $string) {
        if (preg_match('/^([\w-]+):(.*)$/', $string, $matches)) {
            $name = strtolower($matches[1]);
            $value = trim($matches[2]);
            $this->headers[] = [$name, $value];
            if ($name === 'content-type') {
                $this->isBinary = !preg_match('/(text|html|json)/', strtolower($value));
            }
        } elseif ($this->headers) {
            //$key = \array_key_last($this->headers);
            $key = null;
            if (is_array($this->headers) && !empty($this->headers)) {
                $key = array_keys($this->headers)[count($this->headers) - 1];
                $this->headers[$key][1] .= ' ' . trim($string);
            }
        }

        return strlen($string);
    }

    private function writeBody($curl, $string) {
        if ($this->isBinary) {
            return -1;
        }
        if (!$this->body) {
            $this->body = fopen('php://temp', 'w+');
        }

        return fwrite($this->body, $string);
    }
}
