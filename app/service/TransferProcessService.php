<?php

namespace app\service;

class TransferProcessService
{
    private $sourceModel;
    private $panTreeService;

    public function __construct($sourceModel, PanTreePreviewService $panTreeService = null)
    {
        $this->sourceModel = $sourceModel;
        $this->panTreeService = $panTreeService ?: new PanTreePreviewService();
    }

    /**
     * Transfer one public share into a temporary local share record.
     */
    public function processUrl($value, &$numSuccess, &$datas, $type = false)
    {
        $substring = strstr($value['url'], 's/');
        if ($substring === false) {
            if ($type) {
                return jerr2("资源地址格式有误");
            }
            return;
        }

        $code = '';
        if (preg_match('/\?pwd=([^,\s&]+)/', $value['url'], $pwdMatch)) {
            $code = trim($pwdMatch[1]);
        }

        $urlData = [
            'url' => $value['url'],
            'code' => $code,
            'expired_type' => 2,
            'ad_fid' => '',
        ];

        $transfer = new \netdisk\Transfer();
        $res = $transfer->transfer($urlData);

        if ($res['code'] !== 200) {
            if ($type) {
                return jerr2($res['message']);
            }
            return;
        }

        $patterns = '/^\d+\./';
        $title = preg_replace($patterns, '', $value['title']);
        if (mb_strlen($title, 'UTF-8') > 255) {
            $title = mb_substr($title, 0, 250, 'UTF-8') . '...';
        }

        $data = [];
        $data['title'] = $title;
        $data['url'] = $res['data']['share_url'];
        $data['is_type'] = determineIsType($data['url']);
        $data['content'] = $value['url'];
        $dataFid = $res['data']['fid'] ?? '';
        $data['fid'] = is_array($dataFid) ? json_encode($dataFid) : $dataFid;
        $data['is_time'] = 1;
        $data['update_time'] = time();
        $data['create_time'] = time();
        $data['id'] = $this->sourceModel->insertGetId($data);

        $clientData = $this->panTreeService->appendKeyForClient(
            $data,
            $value['url'],
            $code,
            isset($value['stoken']) ? $value['stoken'] : ''
        );
        $datas[] = $clientData;
        $numSuccess++;

        if ($type) {
            return jok2('转存成功', $clientData);
        }
    }
}
