<?php

namespace jzkf\filemanager\models;

use common\models\User;
use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "{{%media_files}}".
 *
 * @property int $id
 * @property string|null $unique_id UUID 或业务自定义唯一ID
 * @property string $storage 存储驱动: local, oss, cos, qiniu, s3...
 * @property int|null $category_id 分组/分类ID
 * @property string $origin_name 原始文件名
 * @property string $object_name 存储对象名（含随机路径）
 * @property string|null $base_url 存储桶基础域名
 * @property string $path 相对路径（不带域名）
 * @property string $url 完整访问URL（带域名）
 * @property string $mime_type MIME 类型
 * @property string $extension 扩展名（不带.）
 * @property int $size 文件大小（字节）
 * @property int|null $width 宽度（px）
 * @property int|null $height 高度（px）
 * @property float|null $duration 音视频时长（秒）
 * @property string|null $cover_url 视频封面图URL
 * @property int|null $bitrate 码率（kbps）
 * @property string|null $alt 图片ALT文字
 * @property string|null $title 标题
 * @property string|null $description 描述
 * @property string|null $tags 标签，英文逗号分隔
 * @property int $privacy 是否公开 1=是 0=私密
 * @property int $status 状态 1=正常 0=禁用
 * @property int $sort_order 排序值，越小越靠前
 * @property int $view_count 查看次数
 * @property int $download_count 下载次数
 * @property int $usage_count 被内容引用的次数
 * @property string|null $md5 MD5
 * @property string|null $sha1 SHA1（更安全）
 * @property string|null $upload_ip 上传IP
 * @property int $created_by 创建者
 * @property int $updated_by 更新者
 * @property string $created_at 创建时间
 * @property string $updated_at 更新时间
 * @property string|null $deleted_at 删除时间
 */
class File extends \yii\db\ActiveRecord
{

    const SCENARIO_CREATE = 'create';
    const SCENARIO_UPDATE = 'update';

    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 0;
    
    const PRIVACY_PUBLIC = 1;
    const PRIVACY_PRIVATE = 0;

    public function scenarios()
    {
        $scenarios = parent::scenarios();

        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        if (\Yii::$app instanceof \yii\console\Application) {
            return [];
        }

        return [
            [
                'class' => \yii\behaviors\TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
                'value' => new \yii\db\Expression('NOW()'),
            ],
            [
                'class' => \yii\behaviors\BlameableBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_by', 'updated_by'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
            ],
        ];
    }

    /**
     * 保存后清除缓存
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        // 清除文件列表缓存
        \jzkf\filemanager\models\search\FileSearch::clearCache();
    }

    /**
     * 删除前检查（软删除）
     */
    public function beforeDelete()
    {
        if (!parent::beforeDelete()) {
            return false;
        }
        
        // 检查引用计数
        if ($this->usage_count > 0) {
            $realCount = $this->getRealUsageCount();
            if ($realCount > 0) {
                $this->addError('id', "文件正在被 {$realCount} 处使用中，无法删除");
                return false;
            }
        }
        
        // 执行软删除而不是硬删除
        $this->softDelete();
        return false; // 阻止实际删除
    }
    
    /**
     * 删除后清除缓存
     */
    public function afterDelete()
    {
        parent::afterDelete();
        
        // 清除文件列表缓存
        \jzkf\filemanager\models\search\FileSearch::clearCache();
        
        // 硬删除时才删除物理文件
        if (empty($this->deleted_at)) {
            $absolutePath = $this->getAbsolutePath();
            if (file_exists($absolutePath)) {
                @unlink($absolutePath);
            }
            
            // 删除缩略图
            $this->deleteThumbnails();
        }
    }

    /**
     * 删除所有缩略图
     */
    protected function deleteThumbnails()
    {
        $basePath = dirname($this->getAbsolutePath());
        $baseName = pathinfo($this->path, PATHINFO_FILENAME);
        $extension = $this->extension;
        
        // 删除各种尺寸的缩略图
        $sizes = ['small', 'medium', 'large', 'thumbnail'];
        foreach ($sizes as $size) {
            // JPG/PNG 等原格式
            $thumbPath = $basePath . '/' . $baseName . '_' . $size . '.' . $extension;
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
            }
            
            // WebP 格式
            $webpPath = $basePath . '/' . $baseName . '_' . $size . '.webp';
            if (file_exists($webpPath)) {
                @unlink($webpPath);
            }
        }
        
        // 删除原图 WebP
        $webpOriginal = $basePath . '/' . $baseName . '.webp';
        if (file_exists($webpOriginal)) {
            @unlink($webpOriginal);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%media_files}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['category_id', 'size', 'width', 'height', 'bitrate', 'privacy', 'status', 'sort_order', 'view_count', 'download_count', 'usage_count', 'created_by', 'updated_by'], 'integer'],
            [['duration'], 'number'],
            [['description'], 'string'],
            [['created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['unique_id'], 'string', 'max' => 64],
            [['storage'], 'string', 'max' => 32],
            [['origin_name', 'alt', 'title'], 'string', 'max' => 255],
            [['object_name'], 'string', 'max' => 512],
            [['base_url', 'path'], 'string', 'max' => 1024],
            [['url', 'cover_url'], 'string', 'max' => 2048],
            [['mime_type'], 'string', 'max' => 128],
            [['extension'], 'string', 'max' => 32],
            [['tags'], 'string', 'max' => 512],
            [['md5'], 'string', 'max' => 32],
            [['sha1'], 'string', 'max' => 40],
            [['upload_ip'], 'string', 'max' => 45],
            [['storage', 'origin_name', 'object_name', 'path', 'url', 'mime_type', 'extension', 'size'], 'required'],
            [['storage'], 'default', 'value' => 'local'],
            [['privacy'], 'default', 'value' => self::PRIVACY_PUBLIC],
            [['status'], 'default', 'value' => self::STATUS_ACTIVE],
            [['sort_order', 'view_count', 'download_count', 'usage_count'], 'default', 'value' => 0],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('filemanager', 'ID'),
            'unique_id' => Yii::t('filemanager', 'UUID'),
            'storage' => Yii::t('filemanager', '存储驱动'),
            'category_id' => Yii::t('filemanager', '分组/分类ID'),
            'origin_name' => Yii::t('filemanager', '原始文件名'),
            'object_name' => Yii::t('filemanager', '存储对象名'),
            'base_url' => Yii::t('filemanager', '存储桶基础域名'),
            'path' => Yii::t('filemanager', '相对路径'),
            'url' => Yii::t('filemanager', '完整访问URL'),
            'mime_type' => Yii::t('filemanager', 'MIME 类型'),
            'extension' => Yii::t('filemanager', '扩展名'),
            'size' => Yii::t('filemanager', '文件大小'),
            'width' => Yii::t('filemanager', '宽度'),
            'height' => Yii::t('filemanager', '高度'),
            'duration' => Yii::t('filemanager', '音视频时长'),
            'cover_url' => Yii::t('filemanager', '视频封面图URL'),
            'bitrate' => Yii::t('filemanager', '码率'),
            'alt' => Yii::t('filemanager', '图片ALT文字'),
            'title' => Yii::t('filemanager', '标题'),
            'description' => Yii::t('filemanager', '描述'),
            'tags' => Yii::t('filemanager', '标签'),
            'privacy' => Yii::t('filemanager', '是否公开'),
            'status' => Yii::t('filemanager', '状态'),
            'sort_order' => Yii::t('filemanager', '排序值'),
            'view_count' => Yii::t('filemanager', '查看次数'),
            'download_count' => Yii::t('filemanager', '下载次数'),
            'usage_count' => Yii::t('filemanager', '引用次数'),
            'md5' => Yii::t('filemanager', 'MD5'),
            'sha1' => Yii::t('filemanager', 'SHA1'),
            'upload_ip' => Yii::t('filemanager', '上传IP'),
            'created_by' => Yii::t('filemanager', '创建者'),
            'updated_by' => Yii::t('filemanager', '更新者'),
            'created_at' => Yii::t('filemanager', '创建时间'),
            'updated_at' => Yii::t('filemanager', '更新时间'),
            'deleted_at' => Yii::t('filemanager', '删除时间'),
        ];
    }

    /**
     * {@inheritdoc}
     * @return FileQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new FileQuery(get_called_class());
    }

    /**
     * 获取创建时间文本.
     * @return string
     */
    public function getCreatedAtText()
    {
        return $this->created_at ? date('Y-m-d H:i:s', strtotime($this->created_at)) : '';
    }
    
    /**
     * 获取更新时间文本.
     * @return string
     */
    public function getUpdatedAtText()
    {
        return $this->updated_at ? date('Y-m-d H:i:s', strtotime($this->updated_at)) : '';
    }
    
    /**
     * 是否已软删除
     * @return bool
     */
    public function isDeleted()
    {
        return !empty($this->deleted_at);
    }
    
    /**
     * 软删除
     * @return bool
     */
    public function softDelete()
    {
        $this->deleted_at = date('Y-m-d H:i:s');
        return $this->save(false);
    }
    
    /**
     * 恢复软删除
     * @return bool
     */
    public function restore()
    {
        $this->deleted_at = null;
        return $this->save(false);
    }

    /**
     * 获取发布者.
     * @return string
     */
    public function getCreator()
    {
        return $this->hasOne(User::class, ['id' => 'created_by']);
    }

    /**
     * 获取更新者.
     * @return string
     */
    public function getUpdater()
    {
        return $this->hasOne(User::class, ['id' => 'updated_by']);
    }

    /**
     * 启用状态
     * @param $id
     * @return string|string[]
     */
    public static function enables($id = null)
    {
        $data = [
            self::STATUS_ACTIVE => '启用',
            self::STATUS_INACTIVE => '禁用',
        ];

        if ($id !== null && isset($data[$id])) {
            return $data[$id];
        } else {
            return $data;
        }
    }

    /**
     * 下拉列表.
     * @param int $num
     * @param array $fields
     * @return array
     */
    public static function dropdownList(int $num = 100, array $fields = ['id', 'name']): array
    {
        return \yii\helpers\ArrayHelper::map(
            self::find()->select($fields)->orderBy(['id' => SORT_DESC])->asArray()->limit(min($num, 100))->all(),
            'id',
            'name'
        );
    }

    /**
     * 检查当前用户是否可以访问此文件
     * 
     * @return bool
     */
    public function canAccess()
    {
        // 已删除的文件不能访问
        if ($this->isDeleted()) {
            return false;
        }
        
        // 超级管理员可以访问所有文件
        if (Yii::$app->user->can('file.admin') || Yii::$app->user->can('superAdmin')) {
            return true;
        }
        
        // 文件创建者可以访问
        if ($this->created_by == Yii::$app->user->id) {
            return true;
        }
        
        // 公开且正常状态的文件所有人都可以访问
        if ($this->privacy == self::PRIVACY_PUBLIC && $this->status == self::STATUS_ACTIVE) {
            return true;
        }
        
        return false;
    }

    /**
     * 检查当前用户是否可以删除此文件
     * 
     * @return bool
     */
    public function canDelete()
    {
        // 已删除的文件不能再删除
        if ($this->isDeleted()) {
            return false;
        }
        
        // 超级管理员可以删除所有文件
        if (Yii::$app->user->can('file.admin') || Yii::$app->user->can('superAdmin')) {
            return true;
        }
        
        // 文件创建者可以删除（如果没有被引用）
        if ($this->created_by == Yii::$app->user->id && $this->usage_count == 0) {
            return true;
        }
        
        return false;
    }

    /**
     * 获取文件的绝对路径
     * 
     * @return string
     */
    public function getAbsolutePath()
    {
        return Yii::getAlias('@web' . $this->path);
    }

    /**
     * 检查物理文件是否存在
     * 
     * @return bool
     */
    public function fileExists()
    {
        return file_exists($this->getAbsolutePath());
    }

    /**
     * 获取文件引用次数（检查在其他模块中的使用）
     * 
     * @return int
     */
    public function getRealUsageCount()
    {
        $count = 0;
        
        // 检查在 CMS 文章中的引用
        if (class_exists('\modules\cms\models\Post')) {
            $count += \modules\cms\models\Post::find()
                ->where(['like', 'content', $this->path])
                ->orWhere(['thumbnail' => $this->path])
                ->count();
        }
        
        // 检查在景区模块中的引用
        if (class_exists('\modules\scenic\models\Scenic')) {
            /** @var \yii\db\ActiveRecord $scenicModel */
            $scenicModel = '\modules\scenic\models\Scenic';
            $count += $scenicModel::find()
                ->where(['cover_image' => $this->path])
                ->orWhere(['like', 'images', $this->path])
                ->count();
        }
        
        // 可以继续添加其他模块的检查
        
        return $count;
    }


    /**
     * 获取文件统计信息
     * 
     * @return array
     */
    public static function getStatistics()
    {
        try {
            // 基础统计
            $totalCount = self::find()->count();
            $totalSize = self::find()->sum('size') ?: 0;
            
            // 按文件类型统计
            $byType = self::find()
                ->select(['extension', 'COUNT(*) as count', 'SUM(size) as total_size'])
                ->groupBy('extension')
                ->orderBy(['count' => SORT_DESC])
                ->asArray()
                ->all();
            
            // 按MIME类型统计（使用兼容的方式）
            $byMime = [];
            try {
                // 尝试使用 SUBSTRING_INDEX
                $byMime = self::find()
                    ->select([
                        'SUBSTRING_INDEX(mime_type, "/", 1) as type',
                        'COUNT(*) as count',
                        'SUM(size) as total_size'
                    ])
                    ->where(['not', ['mime_type' => null]])
                    ->groupBy('type')
                    ->orderBy(['count' => SORT_DESC])
                    ->asArray()
                    ->all();
            } catch (\Exception $e) {
                // 如果失败，使用备用方案
                \Yii::error('MIME统计失败: ' . $e->getMessage(), __METHOD__);
                $byMime = self::getMimeTypeStatsFallback();
            }
            
            // 月度趋势统计
            $byMonth = [];
            try {
                $byMonth = self::find()
                    ->select([
                        'DATE_FORMAT(created_at, "%Y-%m") as month',
                        'COUNT(*) as count',
                        'SUM(size) as total_size'
                    ])
                    ->where(['not', ['created_at' => null]])
                    ->groupBy('month')
                    ->orderBy('month DESC')
                    ->limit(12)
                    ->asArray()
                    ->all();
            } catch (\Exception $e) {
                \Yii::error('月度统计失败: ' . $e->getMessage(), __METHOD__);
                $byMonth = self::getMonthStatsFallback();
            }
            
            // Top上传者统计
            $topUploaders = self::find()
                ->select(['created_by', 'COUNT(*) as count', 'SUM(size) as total_size'])
                ->where(['not', ['created_by' => null]])
                ->groupBy('created_by')
                ->orderBy(['count' => SORT_DESC])
                ->limit(10)
                ->asArray()
                ->all();
            
            return [
                'total_count' => $totalCount,
                'total_size' => $totalSize,
                'by_type' => $byType,
                'by_mime' => $byMime,
                'by_month' => $byMonth,
                'top_uploaders' => $topUploaders,
            ];
        } catch (\Exception $e) {
            \Yii::error('统计失败: ' . $e->getMessage(), __METHOD__);
            return [
                'total_count' => 0,
                'total_size' => 0,
                'by_type' => [],
                'by_mime' => [],
                'by_month' => [],
                'top_uploaders' => [],
            ];
        }
    }

    /**
     * MIME类型统计备用方案
     */
    protected static function getMimeTypeStatsFallback()
    {
        $files = self::find()->select(['mime_type', 'size'])->asArray()->all();
        $stats = [];
        
        foreach ($files as $file) {
            if (empty($file['mime_type'])) continue;
            
            $parts = explode('/', $file['mime_type']);
            $type = $parts[0] ?? 'unknown';
            
            if (!isset($stats[$type])) {
                $stats[$type] = ['type' => $type, 'count' => 0, 'total_size' => 0];
            }
            $stats[$type]['count']++;
            $stats[$type]['total_size'] += intval($file['size'] ?? 0);
        }
        
        return array_values($stats);
    }

    /**
     * 月度统计备用方案
     */
    protected static function getMonthStatsFallback()
    {
        $files = self::find()
            ->select(['created_at', 'size'])
            ->where(['not', ['created_at' => null]])
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(1000)
            ->asArray()
            ->all();
        
        $stats = [];
        foreach ($files as $file) {
            $month = date('Y-m', strtotime($file['created_at']));
            if (!isset($stats[$month])) {
                $stats[$month] = ['month' => $month, 'count' => 0, 'total_size' => 0];
            }
            $stats[$month]['count']++;
            $stats[$month]['total_size'] += intval($file['size'] ?? 0);
        }
        
        krsort($stats);
        return array_values(array_slice($stats, 0, 12));
    }
}
