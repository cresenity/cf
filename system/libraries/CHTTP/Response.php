<?php

use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Renderable;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CHTTP_Response extends SymfonyResponse {
    use CHTTP_Trait_ResponseTrait;
    use CTrait_Macroable {
        CTrait_Macroable::__call as macroCall;
    }

    /**
     * Create a new HTTP reponse.
     *
     * @param mixed $content
     * @param int   $status
     * @param array $headers
     *
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function __construct($content = '', $status = 200, array $headers = []) {
        $this->headers = new ResponseHeaderBag($headers);

        $this->setContent($content);
        $this->setStatusCode($status);
        $this->setProtocolVersion('1.0');
    }

    /**
     * Set the content on the response.
     *
     * @param mixed $content
     *
     * @return $this
     */
    public function setContent($content) {
        $this->original = $content;

        // If the content is "JSONable" we will set the appropriate header and convert
        // the content to JSON. This is useful when returning something like models
        // from routes that will be automatically transformed to their JSON form.
        if ($this->shouldBeJson($content)) {
            $this->header('Content-Type', 'application/json');

            $content = $this->morphToJson($content);
        } elseif ($content instanceof Renderable) {
            // If this content implements the "Renderable" interface then we will call the
            // render method on the object so we will avoid any "__toString" exceptions
            // that might be thrown and have their errors obscured by PHP's handling.
            $content = $content->render();
        }

        parent::setContent($content);

        return $this;
    }

    /**
     * Determine if the given content should be turned into JSON.
     *
     * @param mixed $content
     *
     * @return bool
     */
    protected function shouldBeJson($content) {
        return $content instanceof Arrayable
                || $content instanceof Jsonable
                || $content instanceof ArrayObject
                || $content instanceof JsonSerializable
                || is_array($content);
    }

    /**
     * Morph the given content into JSON.
     *
     * JSON_INVALID_UTF8_SUBSTITUTE keeps json_encode() from failing silently on malformed
     * UTF-8 (e.g. content from third-party sources not guaranteed valid UTF-8).
     *
     * @param mixed $content
     *
     * @return string
     */
    protected function morphToJson($content) {
        if ($content instanceof Jsonable) {
            return $content->toJson();
        } elseif ($content instanceof Arrayable) {
            $content = $content->toArray();
        }

        $json = json_encode($content, JSON_INVALID_UTF8_SUBSTITUTE);

        return $json !== false ? $json : json_encode(['error' => 'Failed to encode response content: ' . json_last_error_msg()]);
    }
}
