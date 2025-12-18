<?php

namespace jzkf\filemanager\migrations;

use yii\db\Migration;

/**
 * Handles the creation of table `{{%media_files}}`.
 */
class M251205032620CreateMediaFilesTable extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%media_files}}', [
            'id'            => $this->bigPrimaryKey()->unsigned()->comment('主键'),

            // 核心文件信息
            'unique_id'     => $this->string(64)->null()->comment('UUID 或业务自定义唯一ID'),
            'storage'       => $this->string(32)->notNull()->defaultValue('local')->comment('存储驱动: local, oss, cos, qiniu, s3...'),
            'category_id'   => $this->integer()->unsigned()->null()->comment('分组/分类ID'),

            'origin_name'   => $this->string(255)->notNull()->comment('原始文件名'),
            'object_name'   => $this->string(512)->notNull()->comment('存储对象名（含随机路径）'),
            'base_url'      => $this->string(1024)->null()->comment('存储桶基础域名'),
            'path'          => $this->string(1024)->notNull()->comment('相对路径（不带域名）'),
            'url'           => $this->string(2048)->notNull()->comment('完整访问URL（带域名）'),

            'mime_type'     => $this->string(128)->notNull()->comment('MIME 类型'),
            'extension'     => $this->string(32)->notNull()->comment('扩展名（不带.）'),
            'size'          => $this->bigInteger()->unsigned()->notNull()->defaultValue(0)->comment('文件大小（字节）'),

            // 图片/视频专用元数据
            'width'         => $this->integer()->unsigned()->null()->comment('宽度（px）'),
            'height'        => $this->integer()->unsigned()->null()->comment('高度（px）'),
            'duration'      => $this->decimal(10, 3)->null()->comment('音视频时长（秒）'),
            'cover_url'     => $this->string(2048)->null()->comment('视频封面图URL'),
            'bitrate'       => $this->integer()->unsigned()->null()->comment('码率（kbps）'),

            // SEO & 内容管理
            'alt'           => $this->string(255)->null()->comment('图片ALT文字'),
            'title'         => $this->string(255)->null()->comment('标题'),
            'description'   => $this->text()->null()->comment('描述'),
            'tags'          => $this->string(512)->null()->comment('标签，英文逗号分隔'),

            // 权限与统计
            'privacy'     => $this->boolean()->notNull()->defaultValue(1)->comment('是否公开 1=是 0=私密'),
            'status'        => $this->smallInteger()->notNull()->defaultValue(1)->comment('状态 1=正常 0=禁用'),
            'sort_order'    => $this->integer()->notNull()->defaultValue(0)->comment('排序值，越小越靠前'),

            'view_count'    => $this->bigInteger()->unsigned()->notNull()->defaultValue(0),
            'download_count' => $this->bigInteger()->unsigned()->notNull()->defaultValue(0),
            'usage_count'   => $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('被内容引用的次数'),

            // 秒传&校验
            'md5'           => $this->string(32)->null()->comment('MD5'),
            'sha1'          => $this->string(40)->null()->comment('SHA1（更安全）'),

            // 操作记录
            'upload_ip'     => $this->string(45)->null()->comment('上传IP'),
            'created_by'    => $this->integer()->unsigned()->notNull()->defaultValue(0),
            'updated_by'    => $this->integer()->unsigned()->notNull()->defaultValue(0),

            // 时间戳（使用 datetime 更直观，查询更方便）
            'created_at'    => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at'    => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
            'deleted_at'    => $this->dateTime()->null(),
        ], $tableOptions);

        // ==================== 索引 ====================
        $this->createIndex('idx-media_file-unique_id',   '{{%media_files}}', 'unique_id');
        $this->createIndex('idx-media_file-storage',     '{{%media_files}}', 'storage');
        $this->createIndex('idx-media_file-category_id', '{{%media_files}}', 'category_id');
        $this->createIndex('idx-media_file-mime_type',   '{{%media_files}}', 'mime_type');
        $this->createIndex('idx-media_file-extension',   '{{%media_files}}', 'extension');
        $this->createIndex('idx-media_file-md5',         '{{%media_files}}', 'md5');
        $this->createIndex('idx-media_file-sha1',        '{{%media_files}}', 'sha1');
        $this->createIndex('idx-media_file-status',      '{{%media_files}}', 'status');
        $this->createIndex('idx-media_file-privacy',   '{{%media_files}}', 'privacy');
        $this->createIndex('idx-media_file-created_at',  '{{%media_files}}', 'created_at');

        // 高频复合索引（后台列表必备）
        $this->createIndex('idx-media_file-cat_status',  '{{%media_files}}', ['category_id', 'status', 'deleted_at']);
        $this->createIndex('idx-media_file-type_created', '{{%media_files}}', ['mime_type', 'created_at']);
        $this->createIndex('idx-media_file-status_deleted', '{{%media_files}}', ['status', 'deleted_at']);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%media_files}}');
    }
}
