<?php

use Illuminate\Contracts\Support\Arrayable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mime\Exception\InvalidArgumentException;

class CException_Context_RequestContext extends CException_ContextAbstract implements CException_Contract_ContextInterface {
    /**
     * @var null|\CHTTP_Request
     */
    protected $request;

    /**
     * Key yang nilainya tidak boleh ikut laporan exception.
     *
     * @var string
     */
    protected $sensitiveKeyPattern = '/pass(word|wd)?|pwd|secret|token|api[_-]?key|authorization|cookie|auth[_-]?pw|cvv|cvc|card[_-]?(number|no)|^pin$|otp|private[_-]?key|credential/i';

    public function __construct(?CHTTP_Request $request = null) {
        $this->request = $request ?: CHTTP::request();
    }

    public function getRequest() {
        return [
            'url' => $this->request->getUri(),
            'ip' => $this->request->getClientIp(),
            'method' => $this->request->getMethod(),
            'useragent' => $this->request->headers->get('User-Agent'),
        ];
    }

    private function getFiles() {
        if (is_null($this->request->files)) {
            return [];
        }

        return $this->mapFiles($this->request->files->all());
    }

    protected function mapFiles(array $files) {
        return array_map(function ($file) {
            if (is_array($file)) {
                return $this->mapFiles($file);
            }

            if (!$file instanceof UploadedFile) {
                return;
            }

            try {
                $fileSize = $file->getSize();
            } catch (\RuntimeException $e) {
                $fileSize = 0;
            }

            try {
                $mimeType = $file->getMimeType();
                if ($mimeType == null) {
                    $mimeType = 'undefined';
                }
            } catch (InvalidArgumentException $e) {
                $mimeType = 'undefined';
            }

            return [
                'pathname' => $file->getPathname(),
                'size' => $fileSize,
                'mimeType' => $mimeType,
            ];
        }, $files);
    }

    public function getSession() {
        try {
            $session = $this->request->session();
        } catch (\Exception $exception) {
            $session = [];
        }

        return $session ? $this->getValidSessionData($session) : [];
    }

    /**
     * @param SessionInterface $session
     *
     * @return array
     */
    protected function getValidSessionData($session) {
        try {
            json_encode($session->all());
        } catch (Throwable $e) {
            return [];
        }

        return $this->redact($session->all());
    }

    public function getCookies() {
        return array_map(function () {
            return '[redacted]';
        }, $this->request->cookies->all());
    }

    public function getHeaders() {
        return $this->redact($this->request->headers->all());
    }

    public function getRequestData() {
        return [
            'queryString' => $this->redact($this->request->query->all()),
            'body' => $this->redact($this->request->request->all()),
            'files' => $this->getFiles(),
        ];
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function redact(array $data) {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match($this->sensitiveKeyPattern, $key)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }

    public function toArray() {
        return [
            'request' => $this->getRequest(),
            'request_data' => $this->getRequestData(),
            'headers' => $this->getHeaders(),
            'cookies' => $this->getCookies(),
            'session' => $this->getSession(),
            'user' => $this->getUser(),
            'role' => $this->getRole(),
            'route' => $this->getRoute(),
            'git' => $this->getGit(),
            'app' => $this->getAppData(),
            'debug' => $this->getDebugData(),

        ];
    }

    public function getRoute() {
        $route = c::request()->route();
        $routeData = [];
        if ($route && $route->getRouteData()) {
            $routeData = $route->getRouteData()->toArray();
        }
        $controller = c::optional($route)->controller;
        $defaultData = [
            'route' => c::optional($route)->getName(),
            'routeParameters' => $this->getRouteParameters(),
            'controllerClass' => $controller ? get_class($controller) : null,
            'controllerAction' => c::optional($route)->getActionName(),
            'middleware' => array_values(c::optional($route)->gatherMiddleware() ?: []),
        ];

        return array_merge($defaultData, $routeData);
    }

    protected function getRouteParameters() {
        try {
            $parameters = c::optional($this->request->route())->parameters;
            if ($parameters == null) {
                $parameters = [];
            }

            return c::collect($parameters)
                ->map(function ($parameter) {
                    return $parameter instanceof CModel ? $parameter->withoutRelations() : $parameter;
                })
                ->map(function ($parameter) {
                    return method_exists($parameter, 'toFlare') ? $parameter->toFlare() : $parameter;
                })
                ->toArray();
        } catch (Throwable $e) {
            return [];
        }
    }

    public function getUser() {
        try {
            $user = c::app()->user();

            if (!$user) {
                return [];
            }
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }

        try {
            if ($user instanceof Arrayable) {
                return $this->redact($user->toArray());
            }
            if ($user instanceof CModel) {
                return $this->redact($user->getAttributes());
            }

            return $this->redact((array) $user);
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }

        return [];
    }

    public function getRole() {
        try {
            $role = c::app()->role();

            if (!$role) {
                return [];
            }
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }

        try {
            if ($role instanceof Arrayable) {
                return $role->toArray();
            }
            if ($role instanceof CModel) {
                return $role->getAttributes();
            }

            return (array) $role;
        } catch (Exception $e) {
            return [];
        } catch (Throwable $e) {
            return [];
        }

        return [];
    }
}
