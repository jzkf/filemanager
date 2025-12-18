<?php

namespace jzkf\filemanager;

use jzkf\filemanager\assets\Assets;

/**
 * file module definition class
 */
class FileManagerModule extends \yii\base\Module
{
    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'jzkf\filemanager\controllers';

    /**
     * 存储配置
     * 可在实例化模块时手动修改存储配置
     * 格式: [
     *     'default' => 'local',  // 默认存储驱动
     *     'drivers' => [
     *         'local' => [...],
     *         's3' => [...],
     *         ...
     *     ]
     * ]
     * @var array|null
     */
    public $storage = null;

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        Assets::register(\Yii::$app->view);

        // 加载模块配置
        $configFile = __DIR__ . '/config/filemanager.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            
            // 如果模块有自定义存储配置，则合并或覆盖默认配置
            if ($this->storage !== null && is_array($this->storage)) {
                if (isset($config['storage'])) {
                    // 合并存储配置
                    if (isset($this->storage['default'])) {
                        $config['storage']['default'] = $this->storage['default'];
                    }
                    if (isset($this->storage['drivers']) && is_array($this->storage['drivers'])) {
                        // 合并驱动配置
                        $config['storage']['drivers'] = array_merge(
                            $config['storage']['drivers'] ?? [],
                            $this->storage['drivers']
                        );
                    }
                } else {
                    // 如果默认配置中没有 storage，直接使用自定义配置
                    $config['storage'] = $this->storage;
                }
            }
            
            \Yii::$app->params['filemanager'] = $config;
        }

        // custom initialization code goes here
        if (!isset(\Yii::$app->i18n->translations['filemanager'])) {
            \Yii::$app->i18n->translations['filemanager'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'sourceLanguage' => \Yii::$app->sourceLanguage,
                'basePath' => '@jzkf/filemanager/messages',
            ];
        }
    }
}
