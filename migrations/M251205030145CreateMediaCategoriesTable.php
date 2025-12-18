<?php

namespace jzkf\filemanager\migrations;

use yii\db\Migration;

/**
 * Handles the creation of table `{{%media_categories}}`.
 */
class M251205030145CreateMediaCategoriesTable extends Migration
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

        $this->createTable('{{%media_categories}}', [
            'id'         => $this->primaryKey()->unsigned(),
            'parent_id'  => $this->integer()->unsigned()->null()->comment('父级ID，支持无限级'),
            'name'       => $this->string(100)->notNull()->comment('分类名称'),
            'slug'       => $this->string(100)->null()->unique()->comment('英文标识'),
            'sort_order' => $this->integer()->notNull()->defaultValue(0),
            'status'     => $this->smallInteger()->notNull()->defaultValue(1),
            'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'),
        ], $tableOptions);

        $this->createIndex('idx-media_category-parent_id', '{{%media_categories}}', 'parent_id');
        $this->createIndex('idx-media_category-status',    '{{%media_categories}}', 'status');
        $this->createIndex('idx-media_category-slug',      '{{%media_categories}}', 'slug');

        // 示例数据：默认分组
        $this->batchInsert('{{%media_categories}}', ['name', 'slug', 'sort_order'], [
            ['默认分类', 'default', 0],
            ['图片',     'image',   10],
            ['视频',     'video',   20],
            ['文档',     'document', 30],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%media_categories}}');
    }
}
