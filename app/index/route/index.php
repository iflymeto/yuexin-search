<?php


use think\facade\Route;


// 前端页面路由
Route::get('s/<name>-<page?>-<cate?>', 'index/index/list')->pattern(['name' => '[^-]+', 'id' => '\d+', 'cate' => '\d+']);
Route::get('d/:id','index/index/detail');
Route::get('sitemap.xml', 'index/sitemap/index');

// API路由组
Route::group('api', function () {
    // 其他API路由
    Route::post('other/save_url', 'api/other/save_url');
    Route::post('other/get_display_url', 'api/other/get_display_url');
    Route::post('other/search', 'api/other/search');
    Route::post('other/delete_search', 'api/other/delete_search');
    
    // 密码验证相关API路由
    Route::post('verify_password', 'api/other/verify_password');
    Route::get('check_password_required', 'api/other/check_password_required');
    Route::get('get_resource_url', 'api/other/get_resource_url');
    
    // 其他工具API
    Route::get('tool/ranking', 'api/tool/ranking');
});



 
