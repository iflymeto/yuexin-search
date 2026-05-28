<?php

namespace app\service;

class SearchHtmlLineParser
{
    public function parse($line, $title)
    {
        $results = [];
        $url = str_replace('{keyword}', urlencode($title), (string)($line['url'] ?? ''));
        $maxCount = intval($line['count'] ?? 10);
        $type = intval($line['pantype'] ?? 0);

        $panPatterns = [
            0 => '/https:\/\/pan\.quark\.cn\/s\/[a-zA-Z0-9]+/',
            2 => '/https:\/\/pan\.baidu\.com\/s\/[a-zA-Z0-9_-]+(\?pwd=[a-zA-Z0-9]+)?/',
            3 => '/https:\/\/drive\.uc\.cn\/s\/[a-zA-Z0-9]+/',
            4 => '/https:\/\/pan\.xunlei\.com\/s\/[a-zA-Z0-9_-]+(\?pwd=[a-zA-Z0-9]+)?/',
        ];

        if ($url === '' || !isset($panPatterns[$type]) || $maxCount <= 0) {
            return [];
        }

        list($tag, $classString) = $this->splitTagConfig($line['html_item'] ?? '');
        list($tagTitle, $classStringTitle) = $this->splitTagConfig($line['html_title'] ?? '');
        list($tagUrl, $classStringUrl) = $this->splitTagConfig($line['html_url2'] ?? '');

        $dom = getDom($url);
        if (!$dom) {
            return $results;
        }

        $finder = new \DomXPath($dom);
        $nodes = $finder->query($this->buildXPathQuery($tag, $classString));

        foreach ($nodes as $node) {
            if (count($results) >= $maxCount) {
                break;
            }

            $html = $dom->saveHTML($node);
            $item = [
                'title' => $this->extractTitle($html, $tagTitle, $classStringTitle),
                'url' => '',
            ];

            if (preg_match($panPatterns[$type], $html, $match)) {
                $item['url'] = trim($match[0]);
            } elseif (intval($line['html_type'] ?? 0) === 1) {
                $item['url'] = $this->extractUrlFromDetailPage($html, $line, $url, $tagUrl, $classStringUrl, $panPatterns[$type]);
            } else {
                $item['url'] = $this->extractUrlFromListPage($html, $tagUrl, $classStringUrl, $panPatterns[$type]);
            }

            if ($item['title'] && $item['url']) {
                $results[] = $item;
            }
        }

        return $results;
    }

    private function splitTagConfig($value)
    {
        $parts = explode('+', (string)$value, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function buildXPathQuery($tag, $classString)
    {
        $tag = trim((string)$tag);
        if ($tag === '') {
            $tag = '*';
        }

        $classArray = explode(' ', trim((string)$classString));
        $xpathConditions = [];
        foreach ($classArray as $cls) {
            if ($cls !== '') {
                $xpathConditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' {$cls} ')";
            }
        }

        return '//' . $tag . (empty($xpathConditions) ? '' : '[' . implode(' and ', $xpathConditions) . ']');
    }

    private function extractTitle($html, $tagTitle, $classStringTitle)
    {
        if (preg_match('/名称：(.*?)\n\n描述：/s', $html, $match)) {
            return trim(strip_tags($match[1]));
        }

        $escapedClass = preg_quote($classStringTitle, '#');
        $escapedTag = preg_quote($tagTitle, '#');
        if ($escapedTag === '') {
            return '';
        }

        $pattern = '#<' . $escapedTag . '[^>]*class=["\'][^"\']*' . $escapedClass . '[^"\']*["\'][^>]*>(.*?)</' . $escapedTag . '>#s';
        if (preg_match($pattern, $html, $titleMatch)) {
            return trim(strip_tags($titleMatch[1]));
        }

        return '';
    }

    private function extractUrlFromDetailPage($html, $line, $baseUrl, $tagUrl, $classStringUrl, $panPattern)
    {
        list($tagD, $classStringD) = $this->splitTagConfig($line['html_url'] ?? '');
        $detailUrlPattern = $this->buildHrefPattern($tagD, $classStringD);

        if (!preg_match($detailUrlPattern, $html, $match)) {
            return '';
        }

        $fullDetailUrl = $this->buildFullUrl(trim($match[1]), $baseUrl);
        $dom2 = getDom($fullDetailUrl);
        if (!$dom2) {
            return '';
        }

        $finder2 = new \DomXPath($dom2);
        $nodes2 = $finder2->query($this->buildXPathQuery($tagUrl, $classStringUrl));

        foreach ($nodes2 as $node2) {
            $html2 = $dom2->saveHTML($node2);
            $url = $this->extractUrlFromListPage($html2, $tagUrl, $classStringUrl, $panPattern);
            if ($url !== '') {
                return $url;
            }
        }

        return '';
    }

    private function extractUrlFromListPage($html, $tagUrl, $classStringUrl, $panPattern)
    {
        $escapedClass = preg_quote($classStringUrl, '#');
        $escapedTag = preg_quote($tagUrl, '#');

        if ($escapedTag !== '') {
            $contentPattern = '#<' . $escapedTag . '[^>]*class=["\'][^"\']*' . $escapedClass . '[^"\']*["\'][^>]*>(.*?)</' . $escapedTag . '>#s';
            if (preg_match($contentPattern, $html, $titleMatch)) {
                $extractedUrl = trim(strip_tags($titleMatch[1]));
                if (preg_match($panPattern, $extractedUrl, $urlMatch)) {
                    return trim($urlMatch[0]);
                }
            }
        }

        $hrefPattern = $this->buildHrefPattern($tagUrl, $classStringUrl);
        if (preg_match($hrefPattern, $html, $match)) {
            $extractedUrl = trim($match[1]);
            if (preg_match($panPattern, $extractedUrl, $urlMatch)) {
                return trim($urlMatch[0]);
            }
        }

        return '';
    }

    private function buildHrefPattern($tag, $classString)
    {
        $escapedClass = preg_quote($classString, '#');
        $escapedTag = preg_quote($tag, '#');
        if ($escapedTag === '') {
            return '#a^#';
        }

        if ($escapedClass === '') {
            return '#<' . $escapedTag . '\b[^>]*href=["\']([^"\']+)["\'][^>]*>#i';
        }

        return '#<' . $escapedTag . '\b(?=[^>]*class=["\'][^"\']*' . $escapedClass . '[^"\']*["\'])(?=[^>]*href=["\']([^"\']+)["\'])[^>]*>#i';
    }

    private function buildFullUrl($url, $baseUrl)
    {
        if (strpos($url, 'http') !== 0) {
            $parsed = parse_url($baseUrl);
            if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
                return $url;
            }
            return $parsed['scheme'] . '://' . $parsed['host'] . $url;
        }
        return $url;
    }
}
