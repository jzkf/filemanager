<?php

namespace jzkf\filemanager\widgets;

use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * 文件上传小部件
 * 
 * 使用示例：
 * ```php
 * <?= FileUploadWidget::widget([
 *     'name' => 'image_url',
 *     'value' => $model->image_url,
 *     'options' => [
 *         'accept' => 'image/*',
 *         'multiple' => false,
 *     ],
 *     'btnLabel' => '选择图片',
 *     'mode' => 'button', // 'button' 或 'inline'
 * ]) ?>
 * ```
 */
class FileUploadWidget extends Widget
{
    /**
     * @var \yii\base\Model 模型对象（用于 ActiveForm）
     */
    public $model;

    /**
     * @var string 属性名称（用于 ActiveForm）
     */
    public $attribute;

    /**
     * @var string 输入框name属性
     */
    public $name;

    /**
     * @var string 当前值（文件URL）
     */
    public $value;

    /**
     * @var array 输入框选项
     */
    public $options = [];

    /**
     * @var string 按钮文本
     */
    public $btnLabel = '选择文件';

    /**
     * @var string 显示模式：button（按钮模式）或 inline（内联模式）
     */
    public $mode = 'button';

    /**
     * @var bool 是否显示文件列表
     */
    public $showFileList = true;

    /**
     * @var bool 是否允许选择已有文件
     */
    public $allowSelect = true;

    /**
     * @var array 允许的文件类型
     */
    public $accept = 'image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar';

    /**
     * @var bool 是否允许多文件上传
     */
    public $multiple = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        // 如果通过 ActiveForm 使用，设置 name 和 value
        if ($this->model !== null && $this->attribute !== null) {
            if ($this->name === null) {
                $this->name = Html::getInputName($this->model, $this->attribute);
            }
            if ($this->value === null) {
                $this->value = Html::getAttributeValue($this->model, $this->attribute);
            }
            if (!isset($this->options['id'])) {
                $this->options['id'] = Html::getInputId($this->model, $this->attribute);
            }
        } else {
            // 独立使用
            if ($this->name === null) {
                $this->name = 'file_url';
            }
            if (!isset($this->options['id'])) {
                $this->options['id'] = $this->getId();
            }
        }

        if (!isset($this->options['class'])) {
            $this->options['class'] = 'form-control';
        }
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        $id = $this->options['id'];
        $inputId = $id . '-input';
        $fileInputId = $id . '-file-input';
        $previewId = $id . '-preview';
        $btnId = $id . '-btn';
        $selectBtnId = $id . '-select-btn';

        $uploadUrl = Url::toRoute(['/filemanager/default/upload-file']);
        $fileListUrl = Url::toRoute(['/filemanager/default/index']);
        $filePickerUrl = Url::toRoute(['/filemanager/default/file-picker']);

        $html = Html::beginTag('div', ['class' => 'file-upload-widget', 'id' => $id]);

        if ($this->mode === 'button') {
            // 按钮模式
            $html .= Html::hiddenInput($this->name, $this->value, ['id' => $inputId]);
            $html .= Html::beginTag('div', ['class' => 'input-group']);
            $html .= Html::textInput('', $this->value, [
                'class' => 'form-control',
                'readonly' => true,
                'placeholder' => '请选择或上传文件',
            ]);

            $html .= Html::button('<i class="ti ti-folder"></i> ' . $this->btnLabel, [
                'class' => 'btn btn-default',
                'id' => $btnId,
                'title' => '选择已有文件或上传新文件',
            ]);
            $html .= Html::endTag('div'); // input-group
        } else {
            // 内联模式
            $html .= Html::hiddenInput($this->name, $this->value, ['id' => $inputId]);
            $html .= Html::fileInput('file', null, [
                'id' => $fileInputId,
                'class' => 'form-control',
                'accept' => $this->accept,
                'multiple' => $this->multiple,
                'style' => 'display: none;',
            ]);

            $html .= Html::beginTag('div', ['class' => 'upload-area border rounded p-4 text-center', 'id' => $btnId, 'style' => 'cursor: pointer; background-color: #f8f9fa;']);
            $html .= '<i class="ti ti-upload" style="font-size: 3rem; color: #6c757d;"></i><br>';
            $html .= '<p class="mb-0 mt-2">' . $this->btnLabel . '</p>';
            $html .= '<small class="text-muted">或拖拽文件到此处</small>';
            $html .= Html::endTag('div');
        }

        // 预览区域
        if ($this->showFileList) {
            $html .= Html::beginTag('div', ['class' => 'file-preview mt-3', 'id' => $previewId]);
            if ($this->value) {
                $html .= $this->renderPreview($this->value);
            }
            $html .= Html::endTag('div');
        }

        $html .= Html::endTag('div');

        $this->registerClientScript($id, $inputId, $fileInputId, $previewId, $btnId, $selectBtnId, $uploadUrl, $fileListUrl, $filePickerUrl);

        return $html;
    }

    /**
     * 渲染文件预览
     * @param string $url
     * @return string
     */
    protected function renderPreview($url)
    {
        $html = Html::beginTag('div', ['class' => 'card card-sm']);
        $html .= Html::beginTag('div', ['class' => 'card-body d-flex align-items-center']);

        // 判断是否为图片
        $extension = pathinfo($url, PATHINFO_EXTENSION);
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

        if (in_array(strtolower($extension), $imageExtensions)) {
            $html .= Html::img($url, ['class' => 'rounded me-3', 'style' => 'width: 60px; height: 60px; object-fit: cover;']);
        } else {
            $html .= '<div class="me-3"><i class="ti ti-file" style="font-size: 3rem;"></i></div>';
        }

        $html .= Html::beginTag('div', ['class' => 'flex-grow-1']);
        // $html .= '<div class="text-truncate" style="max-width: 300px;">' . basename($url) . '</div>';
        // $html .= '<small class="text-muted">' . $url . '</small>';
        $html .= Html::endTag('div');

        $html .= Html::a('<i class="ti ti-x"></i>', 'javascript:void(0)', [
            'class' => 'btn btn-sm btn-danger remove-file',
            'title' => '移除',
        ]);

        $html .= Html::endTag('div');
        $html .= Html::endTag('div');

        return $html;
    }

    /**
     * 注册客户端脚本
     */
    protected function registerClientScript($id, $inputId, $fileInputId, $previewId, $btnId, $selectBtnId, $uploadUrl, $fileListUrl, $filePickerUrl)
    {
        $options = Json::encode([
            'uploadUrl' => $uploadUrl,
            'fileListUrl' => $fileListUrl,
            'filePickerUrl' => $filePickerUrl,
            'multiple' => $this->multiple,
            'accept' => $this->accept,
            'mode' => $this->mode,
        ]);

        $this->view->registerJs(
            <<<JS
(function() {
    var widget = $('#{$id}');
    var input = $('#{$inputId}');
    var fileInput = $('#{$fileInputId}');
    var preview = $('#{$previewId}');
    var btn = $('#{$btnId}');
    var selectBtn = $('#{$selectBtnId}');
    var options = {$options};
    
    // 监听全局上传成功事件（来自 upload.php 的 modal）
    $(document).off('fileUploadSuccess.{$id}').on('fileUploadSuccess.{$id}', function(e, fileData) {
        // 更新输入框的值
        input.val(fileData.url).trigger('change');
        if (options.mode === 'button') {
            widget.find('input[readonly]').val(fileData.url);
        }
        // 更新预览
        preview.html(renderPreview(fileData));
    });
    
    // 监听文件选择事件（来自 file-picker 的选择），检查是否是当前widget触发的
    $(document).on('fileSelected', function(e, fileData) {
        // 检查是否是当前widget打开的文件选择器
        var modal = $('#filemanagerModal');
        var currentWidgetId = modal.data('current-widget-id');
        if (currentWidgetId === '{$id}') {
            // 更新输入框的值
            input.val(fileData.url).trigger('change');
            if (options.mode === 'button') {
                widget.find('input[readonly]').val(fileData.url);
            }
            // 更新预览
            preview.html(renderPreview(fileData));
            
            // 显示成功提示
            show_toast('已选择文件: ' + fileData.name, 'success');
            
            // 清除modal中的widget ID
            modal.data('current-widget-id', '');
        }
    });
    
    // 按钮点击事件
    btn.on('click', function() {
        if (options.mode === 'inline') {
            fileInput.click();
        } else {
            // 打开统一的文件选择器（选择或上传）
            // 确保 modal 存在，如果不存在则创建
            if ($('#filemanagerModal').length === 0) {
                $('body').append('<div class="modal fade" id="filemanagerModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"></div><div class="modal-footer d-none"></div></div></div></div>');
            }
            var modal = $('#filemanagerModal');
            modal.find('.modal-title').text('选择文件');
            modal.find('.modal-body').html('<div class="text-center p-5"><div class="spinner-border" role="status"></div><div class="mt-2">加载中...</div></div>');
            modal.find('.modal-footer').removeClass('d-none');
            modal.modal('show');
            
            // 将 accept 参数传递给文件选择器
            $.get('{$filePickerUrl}', { accept: options.accept }, function(data) {
                // 解析返回的HTML，分离 body 和 footer
                var temp = $('<div>').html(data);
                var footerContent = temp.find('.modal-footer').html() || '';
                
                // 移除 footer 部分，只保留 body 内容
                var bodyContent = data;
                if (footerContent) {
                    // 使用正则表达式移除 footer
                    bodyContent = bodyContent.replace(/<!-- 底部按钮 -->[\s\S]*?<div class="modal-footer">[\s\S]*?<\/div>/g, '');
                }
                
                modal.find('.modal-body').html(bodyContent);
                
                // 如果有 footer 内容，更新 modal 的 footer
                if (footerContent) {
                    modal.find('.modal-footer').html(footerContent).removeClass('d-none');
                } else {
                    modal.find('.modal-footer').addClass('d-none');
                }
                
                // 设置文件输入框的 accept 属性
                if (options.accept) {
                    $('#file-input-picker').attr('accept', options.accept);
                }
                
                // 存储当前widget的ID，用于接收文件选择事件
                modal.data('current-widget-id', '{$id}');
            });
        }
    });
    
    // 选择文件按钮（已移除，统一使用文件选择器）
    
    // 内联模式的文件选择
    fileInput.on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            uploadFile(file);
        }
    });
    
    // 移除文件
    preview.on('click', '.remove-file', function() {
        input.val('').trigger('change');
        widget.find('input[readonly]').val('');
        preview.html('');
    });
    
    // 拖拽上传（内联模式）
    if (options.mode === 'inline') {
        var uploadArea = btn;
        
        uploadArea.on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('background-color', '#e9ecef');
        });
        
        uploadArea.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('background-color', '#f8f9fa');
        });
        
        uploadArea.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).css('background-color', '#f8f9fa');
            
            var files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                uploadFile(files[0]);
            }
        });
    }
    
    // 上传文件
    function uploadFile(file) {
        var formData = new FormData();
        formData.append('UploadForm[imageFile]', file);
        
        $.ajax({
            url: options.uploadUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    show_toast(response.message, 'success');
                    input.val(response.data.url).trigger('change');
                    widget.find('input[readonly]').val(response.data.url);
                    preview.html(renderPreview(response.data));
                    $(document).trigger('fileUploaded.{$id}', [response.data]);
                } else {
                    show_toast(response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                var message = '上传失败：' + error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                show_toast(message, 'error');
            }
        });
    }
    
    // 渲染预览
    function renderPreview(data) {
        var url = data.url;
        var name = data.name;
        var size = data.size;
        var type = data.type;

        var ext = url.split('.').pop().toLowerCase();
        var imageExts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
        var isImage = imageExts.indexOf(ext) !== -1;
        
        var html = '<div class="card card-sm">';
        html += '<div class="card-body d-flex align-items-center">';
        
        if (isImage) {
            html += '<img src="' + url + '" class="rounded me-3" style="width: 60px; height: 60px; object-fit: cover;">';
        } else {
            html += '<div class="me-3"><i class="ti ti-file" style="font-size: 3rem;"></i></div>';
        }
        
        // html += '<div class="flex-grow-1">';
        // html += '<div class="text-truncate" style="max-width: 300px;">' + url.split('/').pop() + '</div>';
        // html += '<small class="text-muted">' + url + '</small>';
        // html += '</div>';
        html += '<a href="javascript:void(0)" class="btn btn-sm btn-danger remove-file" title="移除"><i class="ti ti-x"></i></a>';
        html += '</div></div>';
        
        return html;
    }
})();
JS
        );
    }
}
