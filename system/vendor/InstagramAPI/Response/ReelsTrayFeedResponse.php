<?php

namespace InstagramAPI\Response;

use InstagramAPI\Response;

/**
 * ReelsTrayFeedResponse.
 *
 * @method Model\Broadcast[] getBroadcasts()
 * @method int getFaceFilterNuxVersion()
 * @method bool getHasNewNuxStory()
 * @method mixed getMessage()
 * @method Model\PostLive getPostLive()
 * @method string getStatus()
 * @method int getStickerVersion()
 * @method bool getStoriesViewerGesturesNuxEligible()
 * @method string getStoryRankingToken()
 * @method Model\StoryTray[] getTray()
 * @method Model\ZMessage[] getZMessages()
 * @method bool isBroadcasts()
 * @method bool isFaceFilterNuxVersion()
 * @method bool isHasNewNuxStory()
 * @method bool isMessage()
 * @method bool isPostLive()
 * @method bool isStatus()
 * @method bool isStickerVersion()
 * @method bool isStoriesViewerGesturesNuxEligible()
 * @method bool isStoryRankingToken()
 * @method bool isTray()
 * @method bool isZMessages()
 * @method $this setBroadcasts(Model\Broadcast[] $value)
 * @method $this setFaceFilterNuxVersion(int $value)
 * @method $this setHasNewNuxStory(bool $value)
 * @method $this setMessage(mixed $value)
 * @method $this setPostLive(Model\PostLive $value)
 * @method $this setStatus(string $value)
 * @method $this setStickerVersion(int $value)
 * @method $this setStoriesViewerGesturesNuxEligible(bool $value)
 * @method $this setStoryRankingToken(string $value)
 * @method $this setTray(Model\StoryTray[] $value)
 * @method $this setZMessages(Model\ZMessage[] $value)
 * @method $this unsetBroadcasts()
 * @method $this unsetFaceFilterNuxVersion()
 * @method $this unsetHasNewNuxStory()
 * @method $this unsetMessage()
 * @method $this unsetPostLive()
 * @method $this unsetStatus()
 * @method $this unsetStickerVersion()
 * @method $this unsetStoriesViewerGesturesNuxEligible()
 * @method $this unsetStoryRankingToken()
 * @method $this unsetTray()
 * @method $this unsetZMessages()
 */
class ReelsTrayFeedResponse extends Response
{
    public static $JSON_PROPERTY_MAP = [
        'story_ranking_token'                  => 'string',
        'broadcasts'                           => 'Model\Broadcast[]',
        'tray'                                 => 'Model\StoryTray[]',
        'post_live'                            => 'Model\PostLive',
        'sticker_version'                      => 'int',
        'face_filter_nux_version'              => 'int',
        'stories_viewer_gestures_nux_eligible' => 'bool',
        'has_new_nux_story'                    => 'bool',
    ];
}
