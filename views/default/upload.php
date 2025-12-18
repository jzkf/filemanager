<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\bootstrap5\ActiveForm;

/** @var yii\web\View $this */
/** @var jzkf\filemanager\models\form\UploadForm $model */
?>

<div class="file-upload-form">
    <?php $form = ActiveForm::begin([
        'id' => 'file-upload-form',
        'action' => Url::to(['/filemanager/default/upload-file']),
        'options' => [
            'enctype' => 'multipart/form-data',
            'class' => 'ajax-form'
        ],
    ]); ?>

    <div class="mb-3">
        <?= $form->field($model, 'imageFile')->fileInput([
            'class' => 'form-control',
            'accept' => 'image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar',
            'id' => 'file-input'
        ])->label('选择文件') ?>
    </div>

    <div class="upload-preview mb-3 d-none">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">文件预览</h5>
                <div id="preview-container"></div>
                <div class="mt-2">
                    <small class="text-muted">
                        <strong>文件名：</strong><span id="file-name"></span><br>
                        <strong>文件大小：</strong><span id="file-size"></span><br>
                        <strong>文件类型：</strong><span id="file-type"></span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="upload-progress d-none mb-3">
        <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
        </div>
        <small class="text-muted mt-1 d-block">正在上传...</small>
    </div>

    <div class="form-group d-flex justify-content-between gap-2">
        <?= Html::button('取消', [
            'class' => 'btn btn-sm btn-secondary',
            'data-bs-dismiss' => 'modal'
        ]) ?>
        <?= Html::submitButton('<i class="ti ti-upload me-2"></i>上传文件', [
            'class' => 'btn btn-sm btn-primary',
            'id' => 'btn-submit-upload'
        ]) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>

<?php
$this->registerJs(
    <<<JS
    // 文件选择预览
    $('#file-input').on('change', function(e) {
        var file = e.target.files[0];
        if (file) {
            $('.upload-preview').removeClass('d-none');
            $('#file-name').text(file.name);
            $('#file-size').text((file.size / 1024).toFixed(2) + ' KB');
            $('#file-type').text(file.type || '未知');
            
            // 图片预览
            if (file.type.startsWith('image/')) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    $('#preview-container').html('<img src="' + e.target.result + '" class="img-fluid rounded" style="max-height: 200px;">');
                };
                reader.readAsDataURL(file);
            } else {
                $('#preview-container').html('<div class="alert alert-info">非图片文件，无法预览</div>');
            }
        }
    });
    
    // AJAX表单提交
    $('#file-upload-form').on('beforeSubmit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var formData = new FormData(form[0]);
        var submitBtn = $('#btn-submit-upload');
        
        // 显示进度条
        $('.upload-progress').removeClass('d-none');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        $('.progress-bar').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    // 关闭 modal (兼容多种方式)
                    var modalEl = document.getElementById('filemanagerModal');
                    var modal = null;
                    
                    // 尝试使用 Bootstrap 5 API
                    if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
                        modal = window.bootstrap.Modal.getInstance(modalEl);
                    } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        modal = bootstrap.Modal.getInstance(modalEl);
                    }
                    
                    if (modal) {
                        modal.hide();
                    } else if (typeof $ !== 'undefined' && $.fn.modal) {
                        $('#filemanagerModal').modal('hide');
                    } else {
                        $(modalEl).removeClass('show');
                        $('.modal-backdrop').remove();
                    }
                    
                    // 显示成功 Toast
                    show_toast(response.message, 'success');
                    
                    // 触发全局事件，让 Widget 或页面处理上传成功
                    $(document).trigger('fileUploadSuccess', [response.data]);
                    
                    // 检查是否在 filemanager 模块的 index 页面
                    // 只有在文件管理页面才刷新
                    if (window.location.pathname.indexOf('/filemanager/default/index') !== -1) {
                        setTimeout(function() {
                            location.reload();
                        }, 800);
                    }
                } else {
                    show_toast(response.message, 'error');
                    $('.upload-progress').addClass('d-none');
                    submitBtn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                var message = '上传失败：' + error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                show_toast(message, 'error');
                $('.upload-progress').addClass('d-none');
                submitBtn.prop('disabled', false);
            }
        });
        
        return false;
    });

JS
);
?>