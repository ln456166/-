<?php

class IqiyiParser extends BaseParser
{
    protected $platform = 'iqiyi';
    protected $platformName = '爱奇艺';
    protected $hosts = ['iqiyi.com', 'www.iqiyi.com', 'm.iqiyi.com'];

    public function parse($url, $parsedUrl)
    {
        $host = $parsedUrl['host'] ?? '';
        $name = '';
        $episode = 1;

        $mobileUrl = $url;
        if (strpos($host, 'www.iqiyi.com') !== false) {
            $mobileUrl = str_replace('www.iqiyi.com', 'm.iqiyi.com', $url);
        } elseif (strpos($host, 'm.iqiyi.com') === false && strpos($host, 'iqiyi.com') !== false) {
            $mobileUrl = str_replace('iqiyi.com', 'm.iqiyi.com', $url);
        }

        $mobileHtml = $this->httpGetMobile($mobileUrl);

        $pcHtml = '';
        if (strpos($host, 'm.iqiyi.com') === false) {
            $pcHtml = $this->httpGet($url);
        }

        if ($mobileHtml) {
            $name = $this->extractNameFromMeta($mobileHtml);
            if (empty($name)) $name = $this->extractNameFromTitle($mobileHtml);
            if (empty($name)) $name = $this->extractNameFromJsonLd($mobileHtml);
            $episode = $this->extractEpisodeFromHtml($mobileHtml, $episode);
        }

        if (empty($name) || $this->isDefaultSiteTitle($name)) {
            if ($pcHtml) {
                $pcName = $this->extractNameFromMeta($pcHtml);
                if (empty($pcName)) $pcName = $this->extractNameFromTitle($pcHtml);
                if (empty($pcName)) $pcName = $this->extractNameFromJsonLd($pcHtml);
                $pcEpisode = $this->extractEpisodeFromHtml($pcHtml, $episode);

                if (!empty($pcName) && !$this->isDefaultSiteTitle($pcName)) {
                    $name = $pcName;
                    if ($pcEpisode > 0) $episode = $pcEpisode;
                }
            }
        }

        if (empty($name) || $this->isDefaultSiteTitle($name)) {
            $name = $this->extractNameFromUrl($url);
        }

        if (!empty($name)) {
            $name = $this->cleanTitle($name);
            return ['name' => $name, 'episode' => $episode];
        }

        return false;
    }

    private function isDefaultSiteTitle($title)
    {
        $defaultTitles = [
            '爱奇艺-在线视频网站',
            '海量正版高清视频在线观看',
            '爱奇艺',
            'iqiyi',
        ];
        foreach ($defaultTitles as $default) {
            if (mb_strpos($title, $default) !== false && mb_strlen($title, 'UTF-8') < 30) {
                return true;
            }
        }
        return false;
    }

    private function extractNameFromUrl($url)
    {
        if (preg_match('/m\.iqiyi\.com\/v_([a-zA-Z0-9]+)/', $url, $m)) {
            return '';
        }
        return '';
    }
}
