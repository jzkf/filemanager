<?php

namespace jzkf\filemanager\controllers;

use jzkf\filemanager\services\UploadService;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * EditorController 编辑器上传控制器
 * 
 * 提供各种富文本编辑器的图片上传接口
 */
class EditorController extends Controller
{
    public $enableCsrfValidation = false;

    /**
     * @var UploadService 上传服务
     */
    protected $uploadService;

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();
        // 通过容器获取服务实例（支持依赖注入和测试）
        $this->uploadService = Yii::$container->get(UploadService::class);
    }

    /**
     * Summernote 编辑器图片上传
     * 
     * Summernote 期望的响应格式：
     * - 成功：返回图片 URL 字符串，或者包含 url 字段的 JSON 对象
     * - 失败：返回包含 error 字段的 JSON 对象
     * 
     * 使用示例：
     * ```javascript
     * $('#summernote').summernote({
     *     callbacks: {
     *         onImageUpload: function(files) {
     *             var formData = new FormData();
     *             formData.append('file', files[0]);
     *             $.ajax({
     *                 url: '/filemanager/editor/summernote-upload',
     *                 method: 'POST',
     *                 data: formData,
     *                 processData: false,
     *                 contentType: false,
     *                 success: function(response) {
     *                     // 如果返回的是字符串，直接使用
     *                     // 如果返回的是对象，使用 response.url
     *                     var imageUrl = typeof response === 'string' ? response : response.url;
     *                     $('#summernote').summernote('insertImage', imageUrl);
     *                 },
     *                 error: function(xhr) {
     *                     var error = xhr.responseJSON?.error || '上传失败';
     *                     alert(error);
     *                 }
     *             });
     *         }
     *     }
     * });
     * ```
     * 
     * @return string|array
     */
    public function actionSummernoteUpload()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        if (!Yii::$app->request->isPost) {
            return [
                'error' => '请求方式错误'
            ];
        }
        
        // 使用 UploadService 处理上传
        $data = $this->uploadService->uploadImage();
        
        // Summernote 成功时返回图片 URL（字符串格式，更简洁）
        if ($data['uploaded'] == 1 && !empty($data['url'])) {
            return $data['url'];
        }
        
        // 失败时返回错误信息对象
        return [
            'error' => $data['error'] ?? '上传失败'
        ];
    }
}

