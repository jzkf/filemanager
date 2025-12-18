<?php

namespace jzkf\filemanager\assets;

use yii\web\AssetBundle;

class Assets extends AssetBundle
{
    public $sourcePath = '@jzkf/filemanager/assets';
    
    public $css = [
        'js/toastify.css',
    ];
    public $js = [
        'js/toastify.js',
        'js/filemanager-toast.js',
    ];
    public $depends = [];
}
