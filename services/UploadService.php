<?php

namespace jzkf\filemanager\services;

use jzkf\filemanager\models\File;
use jzkf\filemanager\models\form\UploadForm;
use Yii;
use yii\web\UploadedFile;

/**
 * 上传服务类
 * 
 * 专门处理文件上传相关业务逻辑，方便其他模块调用
 * 
 * 推荐使用实例化调用（支持依赖注入和测试）：
 * ```php
 * // 方式1：直接实例化
 * $uploadService = new UploadService();
 * $result = $uploadService->uploadImage();
 * 
 * // 方式2：通过 Yii 容器获取（推荐）
 * $uploadService = Yii::$container->get(UploadService::class);
 * $result = $uploadService->uploadImage();
 * 
 * // 方式3：在 Controller 中通过属性注入
 * public $uploadService;
 * public function init() {
 *     $this->uploadService = Yii::$container->get(UploadService::class);
 * }
 * ```
 */
class UploadService
{
    /**
     * @var FlysystemService Flysystem 服务实例
     */
    protected $flysystem;
    
    /**
     * 构造函数
     * 
     * @param FlysystemService|null $flysystem Flysystem 服务实例，如果为 null 则自动创建
     */
    public function __construct($flysystem = null)
    {
        $this->flysystem = $flysystem ?: new FlysystemService();
    }
    
    /**
     * 获取服务实例（用于静态方法）
     * 
     * @return static
     */
    protected static function getInstance()
    {
        // 尝试从 Yii 容器获取，如果没有则创建新实例
        if (class_exists('Yii') && isset(Yii::$container)) {
            try {
                return Yii::$container->get(static::class);
            } catch (\Exception $e) {
                // 容器未配置，创建新实例
            }
        }
        return new static();
    }
    
    /**
     * 上传文件
     * 
     * @param UploadedFile $uploadedFile 上传的文件
     * @param array $options 上传选项
     *   - storage: string 存储驱动
     *   - enableDeduplication: bool 是否启用去重
     * @return array 文件信息
     * @throws \yii\base\Exception
     */
    public function upload($uploadedFile, $options = [])
    {
        if (!$uploadedFile) {
            throw new \yii\base\Exception('未选择文件');
        }
        
        // 验证文件类型
        $this->validateFileType($uploadedFile);
        
        // 检查文件是否已存在（去重）
        if (isset($options['enableDeduplication']) && $options['enableDeduplication']) {
            $md5 = md5_file($uploadedFile->tempName);
            $sha1 = sha1_file($uploadedFile->tempName);
            
            // 优先使用 SHA1 查找（更安全），如果没有则使用 MD5
            $existingFile = $this->findBySha1($sha1) ?: $this->findByMd5($md5);
            
            if ($existingFile) {
                // 文件已存在，增加引用计数
                $existingFile->updateCounters(['usage_count' => 1]);
                
                return [
                    'file_url' => $existingFile->url,
                    'file_path' => $existingFile->path,
                    'file_name' => $existingFile->origin_name,
                    'size' => $existingFile->size,
                    'type' => $existingFile->mime_type,
                    'width' => $existingFile->width,
                    'height' => $existingFile->height,
                    'existing' => true,
                ];
            }
        }
        
        // 获取存储驱动
        $storage = $options['storage'] ?? null;
        
        // 上传新文件（使用 Flysystem）
        $model = new UploadForm([
            'flysystem' => $this->flysystem,
            'storage' => $storage,
        ]);
        $model->imageFile = $uploadedFile;
        
        $file = $model->upload();
        
        if (!$file) {
            throw new \yii\base\Exception('文件上传失败：' . implode(', ', $model->getFirstErrors()));
        }
        
        return $file;
    }
    
    /**
     * 图片上传（支持多种编辑器格式）
     * 
     * 处理图片上传，返回标准格式的响应，适用于 CKEditor、TinyMCE、UEditor 等编辑器
     * 
     * @param UploadedFile|null $uploadedFile 上传的文件对象，如果为 null 则从请求中获取
     * @param string|array $fieldNames 上传字段名，可以是字符串或数组，默认 ['file', 'upload']
     * @return array 上传响应数组
     *   - uploaded: int 是否上传成功 (1=成功, 0=失败)
     *   - fileName: string 文件名
     *   - url: string 文件访问 URL
     *   - size: int 文件大小（字节）
     *   - type: string MIME 类型
     *   - error: string 错误信息（如果有）
     */
    public function uploadImage($uploadedFile = null, $fieldNames = ['file', 'upload'])
    {
        $errorMessage = '';
        $fileName = '';
        
        // 如果没有提供文件对象，从请求中获取
        if ($uploadedFile === null) {
            if (is_string($fieldNames)) {
                $fieldNames = [$fieldNames];
            }
            
            $uploadedFile = null;
            foreach ($fieldNames as $fieldName) {
                $uploadedFile = UploadedFile::getInstanceByName($fieldName);
                if ($uploadedFile) {
                    break;
                }
            }
        }
        
        // 检查是否获取到文件
        if (!$uploadedFile) {
            return [
                'uploaded' => 0,
                'fileName' => '',
                'url' => '',
                'error' => '未上传任何文件.'
            ];
        }
        
        $fileName = $uploadedFile->name;
        
        // 检查上传错误
        if ($uploadedFile->error !== UPLOAD_ERR_OK) {
            $max_upload = (int)(ini_get('upload_max_filesize'));
            $max_post = (int)(ini_get('post_max_size'));
            $memory_limit = (int)(ini_get('memory_limit'));
            $upload_mb = min($max_upload, $max_post, $memory_limit);
            
            // 根据错误代码生成错误信息
            switch ($uploadedFile->error) {
                case UPLOAD_ERR_INI_SIZE:
                    $errorMessage = '上传文件超过了系统设置的最大上传（' . $upload_mb . 'm）.';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = '上传的文件超出了 HTML 表单中指定的 MAX_FILE_SIZE 指令.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage = '上传的文件仅部分上传.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMessage = '未上传任何文件.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessage = '缺少临时文件夹.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessage = '无法将文件写入磁盘.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMessage = '系统停止了文件上传.';
                    break;
                default:
                    $errorMessage = '未知上传错误.';
                    break;
            }
            
            Yii::error($errorMessage, __METHOD__);
            return [
                'uploaded' => 0,
                'fileName' => $fileName,
                'url' => '',
                'error' => $errorMessage
            ];
        }
        
        // 使用现有的 upload 方法上传文件
        try {
            $file = $this->upload($uploadedFile);
            
            // 构建文件 URL
            // UploadForm 返回的数组包含 file_url，优先使用
            $fileUrl = $file['file_url'] ?? '';
            
            // 如果没有 file_url，尝试使用 file_path 构建完整 URL
            if (empty($fileUrl) && isset($file['file_path'])) {
                // 使用 Flysystem 获取完整 URL
                $fileUrl = $this->flysystem->url($file['file_path']);
            }
            
            // 如果还是没有 URL，尝试使用 frontend_url（向后兼容）
            if (empty($fileUrl) && isset($file['file_path']) && function_exists('frontend_url')) {
                $fileUrl = frontend_url() . $file['file_path'];
            }
            
            return [
                'uploaded' => 1,
                'fileName' => $fileName,
                'url' => $fileUrl,
                'size' => $file['size'] ?? 0,
                'type' => $file['type'] ?? $uploadedFile->type,
                'error' => '上传成功！'
            ];
            
        } catch (\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return [
                'uploaded' => 0,
                'fileName' => $fileName,
                'url' => '',
                'error' => $e->getMessage() ?: '上传错误，请联系网站管理员！'
            ];
        }
    }
    
    /**
     * 根据 MD5 查找文件
     * 
     * @param string $md5
     * @return File|null
     */
    protected function findByMd5($md5)
    {
        return File::find()->notDeleted()->where(['md5' => $md5])->one();
    }
    
    /**
     * 根据 SHA1 查找文件
     * 
     * @param string $sha1
     * @return File|null
     */
    protected function findBySha1($sha1)
    {
        return File::find()->notDeleted()->where(['sha1' => $sha1])->one();
    }
    
    /**
     * 验证文件类型
     * 
     * @param UploadedFile $file
     * @throws \yii\base\Exception
     */
    protected function validateFileType($file)
    {
        // 获取配置的允许类型
        $allowedMimeTypes = Yii::$app->params['filemanager']['security']['allowedMimeTypes'] ?? [
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-rar-compressed',
        ];
        
        // 使用 finfo 获取真实的 MIME 类型
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file->tempName);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                throw new \yii\base\Exception('不允许的文件类型：' . $mimeType);
            }
        }
        
        // 扩展名白名单
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
        if (!in_array(strtolower($file->extension), $allowedExtensions)) {
            throw new \yii\base\Exception('不允许的文件扩展名：' . $file->extension);
        }
    }
    
    // ==================== 静态方法 ====================
    // 以下静态方法方便其他模块直接调用，无需实例化
    
    /**
     * 上传文件（静态方法）
     * 
     * 其他模块可以通过静态方法直接调用：
     * ```php
     * use jzkf\filemanager\services\UploadService;
     * 
     * $file = UploadService::uploadFile($uploadedFile, [
     *     'enableDeduplication' => true,
     *     'storage' => 'local'
     * ]);
     * ```
     * 
     * @param UploadedFile $uploadedFile 上传的文件
     * @param array $options 上传选项
     * @return array 文件信息
     * @throws \yii\base\Exception
     */
    public static function uploadFile($uploadedFile, $options = [])
    {
        $instance = self::getInstance();
        return $instance->upload($uploadedFile, $options);
    }

}

