<?php

namespace app\service;

class SearchTgLineParser
{
    public function parse($line, $title)
    {
        $type = intval($line['search_pantype'] ?? ($line['pantype'] ?? 0));
        $maxCount = intval($line['count'] ?? 0);

        $panType = [
            0 => 'quark',
            2 => 'baidu',
            3 => 'uc',
            4 => 'xunlei',
        ];

        if (!isset($panType[$type]) || $maxCount <= 0) {
            return [];
        }

        $channels = $this->getEnabledChannels($line);
        if (empty($channels)) {
            return [];
        }

        $results = [];
        $scannedChannels = 0;
        foreach ($channels as $channel) {
            if (count($results) >= $maxCount) {
                return $results;
            }
            $scannedChannels++;
            if ($scannedChannels > $this->getMaxScanChannels($line)) {
                return $results;
            }
            $url = 'https://t.me/s/' . $channel . '?q=' . urlencode($title);
            $dom = $this->fetchDom($url);
            if (!$dom) {
                continue;
            }

            $finder = new \DomXPath($dom);
            $nodes = $finder->query('//div[contains(@class, "tgme_widget_message_text")]');

            foreach ($nodes as $node) {
                $htmlContent = $dom->saveHTML($node);
                if (!$this->messageMatchesKeyword($htmlContent, $title)) {
                    continue;
                }
                $parsedItem = [
                    'title' => $this->extractTitle($htmlContent, $title),
                    'url' => $this->extractPanUrl($htmlContent, $type),
                ];

                if ($parsedItem['title'] && $parsedItem['url']) {
                    $results[] = $parsedItem;
                }

                if (count($results) >= $maxCount) {
                    return $results;
                }
            }
        }

        return $results;
    }

    private function getMaxScanChannels($line)
    {
        $limit = intval($line['tg_scan_limit'] ?? 5);
        if ($limit <= 0) {
            return 5;
        }
        return min($limit, 50);
    }

    private function messageMatchesKeyword($htmlContent, $keyword)
    {
        $keyword = trim((string)$keyword);
        if ($keyword === '') {
            return true;
        }
        $text = html_entity_decode(strip_tags($htmlContent), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', '', $text);
        $needle = preg_replace('/\s+/u', '', $keyword);
        return mb_stripos($text, $needle, 0, 'UTF-8') !== false;
    }

    private function fetchDom($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $html = curl_exec($ch);
        curl_close($ch);
        if (!$html) {
            return null;
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        return $dom;
    }

    private function getEnabledChannels($line)
    {
        $channels = [];
        $raw = $line['tg_channels'] ?? '';
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $channel = $this->normalizeChannel($item['channel'] ?? ($item['url'] ?? ''));
                    $enabled = isset($item['enabled']) ? intval($item['enabled']) === 1 : true;
                    if ($channel !== '' && $enabled) {
                        $channels[] = $channel;
                    }
                }
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $item) {
                $channel = is_array($item) ? $this->normalizeChannel($item['channel'] ?? ($item['url'] ?? '')) : $this->normalizeChannel($item);
                $enabled = is_array($item) && isset($item['enabled']) ? intval($item['enabled']) === 1 : true;
                if ($channel !== '' && $enabled) {
                    $channels[] = $channel;
                }
            }
        }

        if (empty($channels)) {
            $legacy = preg_split('/[,\r\n]+/', (string)($line['url'] ?? ''));
            foreach ($legacy as $item) {
                $channel = $this->normalizeChannel($item);
                if ($channel !== '') {
                    $channels[] = $channel;
                }
            }
        }
        return array_values(array_unique($channels));
    }

    private function normalizeChannel($channel)
    {
        $channel = trim((string)$channel);
        $channel = preg_replace('#^https?://t\.me/s/#i', '', $channel);
        $channel = preg_replace('#^https?://t\.me/#i', '', $channel);
        return trim($channel, " \t\n\r\0\x0B/@");
    }

    private function extractTitle($htmlContent, $fallbackTitle)
    {
        if (preg_match('/名称：(.+?)<br/i', $htmlContent, $titleMatch)) {
            return trim(html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES, 'UTF-8'));
        }
        return $fallbackTitle;
    }

    private function extractPanUrl($htmlContent, $type)
    {
        if ($type === 0 && preg_match('/https:\/\/pan\.quark\.cn\/s\/[a-zA-Z0-9]+/', $htmlContent, $urlMatch)) {
            return trim($urlMatch[0]);
        }
        if ($type === 3 && preg_match('/https:\/\/drive\.uc\.cn\/s\/[a-zA-Z0-9]+/', $htmlContent, $urlMatch)) {
            return trim($urlMatch[0]);
        }
        if ($type === 2 && preg_match('/https:\/\/pan\.baidu\.com\/s\/[a-zA-Z0-9_-]+(\?pwd=[a-zA-Z0-9]+)?/', $htmlContent, $urlMatch)) {
            return trim($urlMatch[0]);
        }
        if ($type === 4 && preg_match('/https:\/\/pan\.xunlei\.com\/s\/[a-zA-Z0-9_-]+(\?pwd=[a-zA-Z0-9]+)?/', $htmlContent, $urlMatch)) {
            return trim($urlMatch[0]);
        }
        return '';
    }
}
