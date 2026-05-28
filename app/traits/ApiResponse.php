<?php
declare(strict_types=1);

namespace app\traits;

use think\Response;

/**
 * Trait ApiResponse
 * 标准化API响应
 */
trait ApiResponse
{
    /**
     * 返回成功JSON
     * @param mixed $data 数据
     * @param string $message 提示信息
     * @param int $code 业务状态码
     * @return Response
     */
    protected function success($data = [], string $message = 'success', int $code = 200): Response
    {
        $result = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
        return Response::create($result, 'json', 200);
    }

    /**
     * 返回失败JSON
     * @param string $message 错误信息
     * @param int $code 业务状态码
     * @param mixed $data 附加数据
     * @return Response
     */
    protected function error(string $message = 'error', int $code = 500, $data = []): Response
    {
        $result = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ];
        return Response::create($result, 'json', 200);
    }
}
