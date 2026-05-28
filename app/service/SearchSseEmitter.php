<?php

namespace app\service;

class SearchSseEmitter
{
    public function event($type, $data = null)
    {
        if ($type === 'DONE') {
            echo 'data: [DONE]' . ($data ? ' ' . $data : '') . "\n\n";
        } elseif (is_array($data)) {
            $data['type'] = $type;
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        } else {
            echo 'data: ' . $data . "\n\n";
        }
        $this->flush();
    }

    public function data(array $item)
    {
        echo 'data: ' . str_replace(["\n", "\r"], '', json_encode($item, JSON_UNESCAPED_UNICODE)) . "\n\n";
        $this->flush();
    }

    public function line($message)
    {
        echo $message . "\n\n";
        $this->flush();
    }

    private function flush()
    {
        ob_flush();
        flush();
    }
}
