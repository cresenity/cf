<?php
/**
  * This helper builds the request body for a /mail/send API call.
  *
  * PHP version 5.6, 7
  *
  * @author    Elmer Thomas <dx@sendgrid.com>
  * @copyright 2017 SendGrid
  * @license   https://opensource.org/licenses/MIT The MIT License
  * @version   GIT: <git_id>
  * @link      http://packagist.org/packages/sendgrid/sendgrid
  */
namespace SendGrid;


class ClickTracking implements \JsonSerializable
{
    private $enable;
    private $enable_text;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function setEnableText($enable_text)
    {
        $this->enable_text = $enable_text;
    }

    public function getEnableText()
    {
        return $this->enable_text;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable'      => $this->getEnable(),
                'enable_text' => $this->getEnableText()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class OpenTracking implements \JsonSerializable
{
    private $enable;
    private $substitution_tag;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function setSubstitutionTag($substitution_tag)
    {
        $this->substitution_tag = $substitution_tag;
    }

    public function getSubstitutionTag()
    {
        return $this->substitution_tag;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable'           => $this->getEnable(),
                'substitution_tag' => $this->getSubstitutionTag()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class SubscriptionTracking implements \JsonSerializable
{
    private $enable;
    private $text;
    private $html;
    private $substitution_tag;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setHtml($html)
    {
        $this->html = $html;
    }

    public function getHtml()
    {
        return $this->html;
    }

    public function setSubstitutionTag($substitution_tag)
    {
        $this->substitution_tag = $substitution_tag;
    }

    public function getSubstitutionTag()
    {
        return $this->substitution_tag;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable'           => $this->getEnable(),
                'text'             => $this->getText(),
                'html'             => $this->getHtml(),
                'substitution_tag' => $this->getSubstitutionTag()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class Ganalytics implements \JsonSerializable
{
    private $enable;
    private $utm_source;
    private $utm_medium;
    private $utm_term;
    private $utm_content;
    private $utm_campaign;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function setCampaignSource($utm_source)
    {
        $this->utm_source = $utm_source;
    }

    public function getCampaignSource()
    {
        return $this->utm_source;
    }

    public function setCampaignMedium($utm_medium)
    {
        $this->utm_medium = $utm_medium;
    }

    public function getCampaignMedium()
    {
        return $this->utm_medium;
    }

    public function setCampaignTerm($utm_term)
    {
        $this->utm_term = $utm_term;
    }

    public function getCampaignTerm()
    {
        return $this->utm_term;
    }

    public function setCampaignContent($utm_content)
    {
        $this->utm_content = $utm_content;
    }

    public function getCampaignContent()
    {
        return $this->utm_content;
    }

    public function setCampaignName($utm_campaign)
    {
        $this->utm_campaign = $utm_campaign;
    }

    public function getCampaignName()
    {
        return $this->utm_campaign;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable'       => $this->getEnable(),
                'utm_source'   => $this->getCampaignSource(),
                'utm_medium'   => $this->getCampaignMedium(),
                'utm_term'     => $this->getCampaignTerm(),
                'utm_content'  => $this->getCampaignContent(),
                'utm_campaign' => $this->getCampaignName()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class TrackingSettings implements \JsonSerializable
{
    private $click_tracking;
    private $open_tracking;
    private $subscription_tracking;
    private $ganalytics;

    public function setClickTracking($click_tracking)
    {
        $this->click_tracking = $click_tracking;
    }

    public function getClickTracking()
    {
        return $this->click_tracking;
    }

    public function setOpenTracking($open_tracking)
    {
        $this->open_tracking = $open_tracking;
    }

    public function getOpenTracking()
    {
        return $this->open_tracking;
    }

    public function setSubscriptionTracking($subscription_tracking)
    {
        $this->subscription_tracking = $subscription_tracking;
    }

    public function getSubscriptionTracking()
    {
        return $this->subscription_tracking;
    }

    public function setGanalytics($ganalytics)
    {
        $this->ganalytics = $ganalytics;
    }

    public function getGanalytics()
    {
        return $this->ganalytics;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'click_tracking'        => $this->getClickTracking(),
                'open_tracking'         => $this->getOpenTracking(),
                'subscription_tracking' => $this->getSubscriptionTracking(),
                'ganalytics'            => $this->getGanalytics()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class BccSettings implements \JsonSerializable
{
    private $enable;
    private $email;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function setEmail($email)
    {
        $this->email = $email;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable' => $this->getEnable(),
                'email'  => $this->getEmail()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class BypassListManagement implements \JsonSerializable
{
    private $enable;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable' => $this->getEnable()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class Footer implements \JsonSerializable
{
    private $enable;
    private $text;
    private $html;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function setText($text)
    {
        $this->text = $text;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setHtml($html)
    {
        $this->html = $html;
    }

    public function getHtml()
    {
        return $this->html;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable' => $this->getEnable(),
                'text'   => $this->getText(),
                'html'   => $this->getHtml()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class SandBoxMode implements \JsonSerializable
{
    private $enable;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }
    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable' => $this->getEnable()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class SpamCheck implements \JsonSerializable
{
    private $enable;
    private $threshold;
    private $post_to_url;

    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    public function getEnable()
    {
        return $this->enable;
    }

    public function setThreshold($threshold)
    {
        $this->threshold = $threshold;
    }

    public function getThreshold()
    {
        return $this->threshold;
    }

    public function setPostToUrl($post_to_url)
    {
        $this->post_to_url = $post_to_url;
    }

    public function getPostToUrl()
    {
        return $this->post_to_url;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'enable'      => $this->getEnable(),
                'threshold'   => $this->getThreshold(),
                'post_to_url' => $this->getPostToUrl()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class MailSettings implements \JsonSerializable
{
    private $bcc;
    private $bypass_list_management;
    private $footer;
    private $sandbox_mode;
    private $spam_check;

    public function setBccSettings($bcc)
    {
        $this->bcc = $bcc;
    }

    public function getBccSettings()
    {
        return $this->bcc;
    }

    public function setBypassListManagement($bypass_list_management)
    {
        $this->bypass_list_management = $bypass_list_management;
    }

    public function getBypassListManagement()
    {
        return $this->bypass_list_management;
    }

    public function setFooter($footer)
    {
        $this->footer = $footer;
    }

    public function getFooter()
    {
        return $this->footer;
    }

    public function setSandboxMode($sandbox_mode)
    {
        $this->sandbox_mode = $sandbox_mode;
    }

    public function getSandboxMode()
    {
        return $this->sandbox_mode;
    }

    public function setSpamCheck($spam_check)
    {
        $this->spam_check = $spam_check;
    }

    public function getSpamCheck()
    {
        return $this->spam_check;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'bcc'                    => $this->getBccSettings(),
                'bypass_list_management' => $this->getBypassListManagement(),
                'footer'                 => $this->getFooter(),
                'sandbox_mode'           => $this->getSandboxMode(),
                'spam_check'             => $this->getSpamCheck()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

class ASM implements \JsonSerializable
{
    private $group_id;
    private $groups_to_display;

    public function setGroupId($group_id)
    {
        $this->group_id = $group_id;
    }

    public function getGroupId()
    {
        return $this->group_id;
    }

    public function setGroupsToDisplay($group_ids)
    {
        $this->groups_to_display = $group_ids;
    }

    public function getGroupsToDisplay()
    {
        return $this->groups_to_display;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'group_id'          => $this->getGroupId(),
                'groups_to_display' => $this->getGroupsToDisplay()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

