<?php

namespace app\admin\controller;

use think\App;
use think\facade\Filesystem;
use app\admin\QfShop;
use app\model\Feedback as FeedbackModel;

class Feedback extends QfShop
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        //查询列表时允许的字段
        $this->selectList = "*";
        //查询详情时允许的字段
        $this->selectDetail = "*";
        $this->model = new FeedbackModel();
    }


    /**
     * 获取列表接口基类 子类自动继承 如有特殊需求 可重写到子类 请勿修改父类方法
     *
     * @return void
     */
    public function getList()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        //从请求中获取筛选数据的数组
        $map = $this->getDataFilterFromRequest();
        //从请求中获取排序方式
        $order = $this->getorderfromRequest();
        //设置Model中的 per_page
        $this->setGetListPerPage();
        //查询数据
        $dataList = $this->model->getListByPage($map, $order, $this->selectList);
        return jok('数据获取成功', $dataList);
    }

    /**
     * 删除反馈记录（支持单个和批量删除）
     *
     * @return void
     */
    public function delete()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }

        // 获取要删除的ID数组
        $ids = $this->request->param('ids', []);
        
        // 验证参数
        if (empty($ids) || !is_array($ids)) {
            return jerr('请选择要删除的记录');
        }

        // 验证ID格式
        foreach ($ids as $id) {
            if (!is_numeric($id) || $id <= 0) {
                return jerr('无效的记录ID');
            }
        }

        try {
            // 执行删除操作
            $deleteCount = $this->model->whereIn('id', $ids)->delete();
            
            if ($deleteCount > 0) {
                $message = count($ids) == 1 ? '删除成功' : "成功删除 {$deleteCount} 条记录";
                return jok($message);
            } else {
                return jerr('删除失败，记录不存在或已被删除');
            }
        } catch (\Exception $e) {
            return jerr('删除失败：' . $e->getMessage());
        }
    }
    
}
