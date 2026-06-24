<?php

abstract class BaseParser
{
    protected $platform = '';
    protected $platformName = '';
    protected $hosts = [];

    abstract public function parse($url, $parsedUrl);

    public function getPlatform()
    {
        return $this->platform;
    }

    public function getPlatformName()
    {
        return $this->platformName;
    }

    public function getHosts()
    {
        return $this->hosts;
    }

    public function matchHost($host)
    {
        $host = strtolower($host);
        foreach ($this->hosts as $domain) {
            if ($host === $domain || substr($host, -strlen($domain) - 1) === '.' . $domain) {
                return true;
            }
        }
        return false;
    }

    protected function httpGet($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $response !== false) ? $response : false;
    }

    protected function httpGetMobile($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $response !== false) ? $response : false;
    }

    protected function cleanTitle($title)
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim($title);
        $title = preg_replace('/\s+/u', ' ', $title);

        if (preg_match('/\x{300A}([^\x{300B}]+)\x{300B}/u', $title, $m)) {
            $inside = trim($m[1]);
            if (mb_strlen($inside, 'UTF-8') >= 2) {
                $title = $inside;
            }
        }

        if (preg_match('/^[\x{300A}\x{300B}\[\[【（\(][^\x{300A}\x{300B}\]\]】）\)]*[\x{300B}\]\]】）\)]/u', $title, $m)) {
            $title = $this->mbTrim(mb_substr($title, mb_strlen($m[0], 'UTF-8'), null, 'UTF-8'));
        }

        $title = preg_replace('/[\x{300A}\x{300B}]/u', '', $title);
        $title = preg_replace('/[\x{300C}\x{300D}\x{300E}\x{300F}]/u', '', $title);
        $title = $this->removeAllSuffixes($title);

        if (preg_match('/^(.*?)第\s*\d+\s*[集期话](.*)$/u', $title, $m)) {
            $before = trim($m[1]);
            if (mb_strlen($before, 'UTF-8') >= 2) {
                $title = $before;
            }
        }

        $title = $this->mbTrim($title, " \t\n\r\0\x0B-_—|｜·[]()（）【】《》「」『』");
        return $title;
    }

    private function mbTrim($str, $charlist = " \t\n\r\0\x0B")
    {
        $chars = preg_quote($charlist, '/');
        $pattern = '/^[' . $chars . ']+|[' . $chars . ']+$/u';
        return preg_replace($pattern, '', $str);
    }

    private function removeAllSuffixes($title)
    {
        $suffixes = [
            '_腾讯视频', '-腾讯视频', '腾讯视频',
            '_优酷', '-优酷', '优酷',
            '_爱奇艺', '-爱奇艺', '爱奇艺',
            '_哔哩哔哩', '-哔哩哔哩', '哔哩哔哩',
            '_bilibili', '-bilibili',
            '_芒果TV', '-芒果TV', '芒果TV',
            '_搜狐视频', '-搜狐视频', '搜狐视频',
            '_PPTV', '-PPTV', 'PPTV',
            '_乐视视频', '-乐视视频', '乐视视频',
            '_高清在线观看', '-高清在线观看', '高清在线观看',
            '_完整版视频在线观看', '-完整版视频在线观看', '完整版视频在线观看',
            '_免费在线观看', '-免费在线观看', '免费在线观看',
            '_全集高清视频', '-全集高清视频', '全集高清视频',
            '_全集高清', '-全集高清', '全集高清',
            '_在线观看', '-在线观看', '在线观看',
            '_高清完整版', '-高清完整版', '高清完整版',
            '_完整版', '-完整版', '完整版',
            '_高清', '-高清', '高清',
            '_HD', '-HD', ' HD',
            '_BD', '-BD', ' BD',
            '_电影', '-电影', '电影',
            '_电视剧', '-电视剧', '电视剧',
            '_动漫', '-动漫', '动漫',
            '_综艺', '-综艺', '综艺',
            '_纪录片', '-纪录片', '纪录片',
            '_最新', '-最新', '最新',
            '_全集', '-全集', '全集',
            '_正片', '-正片', '正片',
            '_预告', '-预告', '预告',
            '_高清视频', '-高清视频', '高清视频',
            '_视频', '-视频', '视频',
        ];

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($suffixes as $suffix) {
                $len = mb_strlen($suffix, 'UTF-8');
                if ($len == 0) continue;
                if (mb_substr($title, -$len, null, 'UTF-8') === $suffix) {
                    $result = mb_substr($title, 0, mb_strlen($title, 'UTF-8') - $len, 'UTF-8');
                    $title = $this->mbTrim($result, " \t-_—|｜·");
                    $changed = true;
                }
            }
        }

        return $title;
    }

    protected function extractNameFromMeta($html)
    {
        $metaTags = ['og:title', 'og:video:title', 'video:title', 'name="title"', 'property="title"', 'itemprop="name"'];

        foreach ($metaTags as $meta) {
            $pattern = '/<meta[^>]*' . preg_quote($meta, '/') . '[^>]*content=["\']([^"\']+)["\']/is';
            if (preg_match($pattern, $html, $matches)) {
                $title = trim($matches[1]);
                if (!empty($title) && mb_strlen($title, 'UTF-8') > 1) {
                    return $this->cleanTitle($title);
                }
            }
            $pattern2 = '/<meta[^>]*content=["\']([^"\']+)["\'][^>]*' . preg_quote($meta, '/') . '/is';
            if (preg_match($pattern2, $html, $matches)) {
                $title = trim($matches[1]);
                if (!empty($title) && mb_strlen($title, 'UTF-8') > 1) {
                    return $this->cleanTitle($title);
                }
            }
        }
        return '';
    }

    protected function extractNameFromTitle($html)
    {
        if (!preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
            return '';
        }
        $title = trim($matches[1]);
        if (empty($title)) return '';

        $title = preg_replace('/[\-_｜|]在线观看.*$/', '', $title);
        $title = preg_replace('/[\-_｜|]高清.*$/', '', $title);
        $title = preg_replace('/[\-_｜|]完整版.*$/', '', $title);

        return $this->cleanTitle($title);
    }

    protected function extractNameFromJsonLd($html)
    {
        if (preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $data = json_decode($matches[1], true);
            if ($data) {
                if (isset($data['name'])) return $this->cleanTitle($data['name']);
                if (isset($data['headline'])) return $this->cleanTitle($data['headline']);
            }
        }
        return '';
    }

    protected function extractEpisodeFromHtml($html, $defaultEpisode = 1)
    {
        $patterns = [
            '/第\s*(\d+)\s*集/i', '/第\s*(\d+)\s*期/i', '/第\s*(\d+)\s*话/i',
            '/"episode"\s*:\s*(\d+)/i', '/"ep"\s*:\s*(\d+)/i', '/"ep_index"\s*:\s*(\d+)/i',
            '/"currentEp"\s*:\s*(\d+)/i', '/"currEpisode"\s*:\s*(\d+)/i',
            '/\bep\.?\s*(\d+)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $ep = intval($matches[1]);
                if ($ep > 0 && $ep < 5000) return $ep;
            }
        }
        return $defaultEpisode;
    }

    protected function parseByHtml($url)
    {
        $html = $this->httpGet($url);
        if (!$html) return false;

        $name = '';
        $episode = 1;

        $name = $this->extractNameFromMeta($html);
        if (empty($name)) $name = $this->extractNameFromTitle($html);
        if (empty($name)) $name = $this->extractNameFromJsonLd($html);

        $episode = $this->extractEpisodeFromHtml($html, $episode);

        if (empty($name) || mb_strlen($name, 'UTF-8') < 2) return false;

        $name = $this->cleanTitle($name);
        return ['name' => $name, 'episode' => $episode];
    }
}
