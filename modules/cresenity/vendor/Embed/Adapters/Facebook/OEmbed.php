<?php

namespace Embed\Adapters\Facebook;

use Embed\OEmbed as Base;
use Psr\Http\Message\UriInterface;

class OEmbed extends Base {
    const ENDPOINT_PAGE = 'https://graph.facebook.com/v8.0/oembed_page';

    const ENDPOINT_POST = 'https://graph.facebook.com/v8.0/oembed_post';

    const ENDPOINT_VIDEO = 'https://graph.facebook.com/v8.0/oembed_video';

    protected function detectEndpoint() {
        $token = $this->extractor->getSetting('facebook:token');

        if (!$token) {
            return null;
        }

        $uri = $this->extractor->getUri();
        $queryParameters = $this->getOembedQueryParameters((string) $uri);
        $queryParameters['access_token'] = $token;

        return $this->extractor->getCrawler()
            ->createUri($this->getEndpointByPath($uri->getPath()))
            ->withQuery(http_build_query($queryParameters));
    }

    private function getEndpointByPath($path) {
        /* Videos
        https://www.facebook.com/{page-name}/videos/{video-id}/
        https://www.facebook.com/{username}/videos/{video-id}/
        https://www.facebook.com/video.php?id={video-id}
        https://www.facebook.com/video.php?v={video-id}
        */
        if (strpos($path, '/video.php') === 0
            || strpos($path, '/videos/') !== false
        ) {
            return self::ENDPOINT_VIDEO;
        }

        /* Posts
        https://www.facebook.com/{page-name}/posts/{post-id}
        https://www.facebook.com/{username}/posts/{post-id}
        https://www.facebook.com/{username}/activity/{activity-id}
        https://www.facebook.com/photo.php?fbid={photo-id}
        https://www.facebook.com/photos/{photo-id}
        https://www.facebook.com/permalink.php?story_fbid={post-id}
        https://www.facebook.com/media/set?set={set-id}
        https://www.facebook.com/questions/{question-id}
        https://www.facebook.com/notes/{username}/{note-url}/{note-id}
        Not in the facebook docs:
        https://www.facebook.com/{page-name}/photos/{post-id}/{photo-id}
        */
        if (strpos($path, '/photo.php') === 0
            || strpos($path, '/photos/') !== false
            || strpos($path, '/permalink.php') === 0
            || strpos($path, '/media/') === 0
            || strpos($path, '/questions/') === 0
            || strpos($path, '/notes/') === 0
            || strpos($path, '/posts/') !== false
            || strpos($path, '/activity/') !== false
        ) {
            return self::ENDPOINT_POST;
        }

        return self::ENDPOINT_PAGE;
    }
}
