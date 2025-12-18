<?php

use yii\helpers\Html;
use yii\helpers\Url;
use jzkf\filemanager\models\File;
use yii\widgets\LinkPager;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var string $accept */

$accept = $accept ?? 'image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar';

$this->registerCss('
.file-picker-container {
    height: 66vh;
    overflow-y: auto;
    overflow-x: hidden;
    scrollbar-width: thin;
}
.pagination {
    margin-bottom: 0;
}
.file-item {
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}
.file-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.file-item.selected {
    border-color: var(--bs-primary);
    background-color: var(--bs-primary-lt);
}
.file-item img {
    width: 100%;
    height: 120px;
    object-fit: cover;
}
.upload-zone {
    border: 2px dashed #d1d5db;
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    transition: all 0.2s;
    background-color: #f8f9fa;
}
.upload-zone:hover,
.upload-zone.dragover {
    border-color: var(--bs-primary);
    background-color: var(--bs-primary-lt);
}
.file-name {
    font-size: 0.75rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
');
?>

<div class="file-picker">
    <!-- 标签页导航 -->
    <ul class="nav nav-pills mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="select-tab" data-bs-toggle="tab" data-bs-target="#select-pane"
                type="button" role="tab">
                <i class="ti ti-photo me-2"></i>选择文件
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-pane"
                type="button" role="tab">
                <i class="ti ti-upload me-2"></i>上传文件
            </button>
        </li>
    </ul>

    <!-- 标签页内容 -->
    <div class="tab-content">
        <!-- 选择文件标签页 -->
        <div class="tab-pane fade show active" id="select-pane" role="tabpanel">
            <div class="mb-3">
                <input type="text" class="form-control" id="search-file" placeholder="搜索文件名...">
            </div>

            <div class="file-picker-container container-fluid">
                <div class="row g-3" id="file-grid">
                    <?php foreach ($dataProvider->models as $file): ?>
                        <div class="col-6 col-md-4 col-lg-3">
                            <div class="card file-item" data-file-id="<?= $file->id ?>"
                                data-file-url="<?= Html::encode($file->url) ?>"
                                data-file-name="<?= Html::encode($file->origin_name) ?>">
                                <div class="card-body p-2">
                                    <?php if (in_array($file->extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'])): ?>
                                        <img src="<?= $file->url ?>" alt="<?= Html::encode($file->origin_name) ?>" class="rounded mb-2">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center bg-light rounded mb-2" style="height: 120px;">
                                            <i class="ti ti-file" style="font-size: 3rem; color: #6c757d;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="file-name" title="<?= Html::encode($file->origin_name) ?>">
                                        <?= Html::encode($file->origin_name) ?>
                                    </div>
                                    <small class="text-muted"><?= Yii::$app->formatter->asShortSize($file->size) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (count($dataProvider->models) == 0): ?>
                    <div class="text-center text-muted py-5">
                        <i class="ti ti-photo-off" style="font-size: 4rem;"></i>
                        <p class="mt-3">暂无文件</p>
                    </div>
                <?php endif; ?>

                <div class="mt-2">
                    <?= LinkPager::widget([
                        'pagination' => $dataProvider->pagination,
                        'options' => [
                            'class' => 'd-flex justify-content-center mb-0',
                            'id' => 'file-pagination'
                        ],
                        'linkOptions' => ['class' => 'page-link file-page-link'],
                    ]) ?>
                </div>
            </div>
        </div>

        <!-- 上传文件标签页 -->
        <div class="tab-pane fade" id="upload-pane" role="tabpanel">
            <div class="file-upload-container upload-zone" id="upload-zone">
                <input type="file" id="file-input-picker" style="display: none;" accept="<?= Html::encode($accept) ?>" multiple>
                <i class="ti ti-cloud-upload" style="font-size: 4rem; color: #6c757d;"></i>
                <h4 class="mt-3">点击或拖拽文件到此处上传</h4>
                <p class="text-muted">支持图片、文档、视频等多种格式</p>
                <button type="button" class="btn btn-primary mt-2" id="select-file-btn">
                    <i class="ti ti-file-plus me-2"></i>选择文件
                </button>
            </div>

            <div id="upload-list" class="mt-3"></div>
        </div>
    </div>
</div>

<!-- 底部按钮 -->
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
    <button type="button" class="btn btn-primary" id="confirm-select-btn" disabled>
        <i class="ti ti-check me-2"></i>确定选择
    </button>
</div>

<?php
$uploadUrl = Url::to(['/filemanager/default/upload-file']);
$fileListUrl = Url::toRoute(['/filemanager/default/index']);

$this->registerJs(
    <<<JS
(function() {
    var selectedFile = null;
    var currentPage = 1;
    
    // 先移除所有旧的事件监听器，防止重复绑定
    $('.file-item').off('click');
    $('#confirm-select-btn').off('click');
    $('#search-file').off('input');
    $('#select-file-btn').off('click');
    $('#upload-zone').off('click');
    $('#file-input-picker').off('change');
    
    // AJAX分页处理
    function initPagination() {
        $('#file-pagination').off('click', '.file-page-link');
        $('#file-pagination').on('click', '.file-page-link', function(e) {
            e.preventDefault();
            var url = $(this).attr('href');
            if (url) {
                loadFilePage(url);
            }
        });
    }
    
    // 加载文件列表页面
    function loadFilePage(url) {
        var fileGrid = $('#file-grid');
        var pagination = $('#file-pagination');
        
        // 显示加载状态
        fileGrid.html('<div class="col-12 text-center py-5"><div class="spinner-border" role="status"></div><div class="mt-2">加载中...</div></div>');
        
        // 确保 URL 包含 accept 参数
        var acceptParam = $('#file-input-picker').attr('accept') || '';
        if (acceptParam) {
            var separator = url.indexOf('?') !== -1 ? '&' : '?';
            url += separator + 'accept=' + encodeURIComponent(acceptParam);
        }
        
        $.ajax({
            url: url,
            type: 'GET',
            dataType: 'html',
            success: function(response) {
                // 解析返回的HTML，提取文件网格和分页
                var temp = $('<div>').html(response);
                var newFileGrid = temp.find('#file-grid').html();
                var newPagination = temp.find('#file-pagination').parent().html();
                
                if (newFileGrid) {
                    fileGrid.html(newFileGrid);
                    // 重新绑定文件项点击事件
                    bindFileItemClick();
                }
                
                if (newPagination) {
                    pagination.parent().html(newPagination);
                    // 重新初始化分页
                    initPagination();
                }
            },
            error: function() {
                fileGrid.html('<div class="col-12 text-center text-danger py-5"><i class="ti ti-alert-circle" style="font-size: 3rem;"></i><p class="mt-3">加载失败，请重试</p></div>');
            }
        });
    }
    
    // 绑定文件项点击事件
    function bindFileItemClick() {
        $('.file-item').off('click').on('click', function() {
            $('.file-item').removeClass('selected');
            $(this).addClass('selected');
            selectedFile = {
                id: $(this).data('file-id'),
                url: $(this).data('file-url'),
                name: $(this).data('file-name')
            };
            // 使用事件委托查找按钮，因为按钮可能在 modal footer 中
            var btn = $('#filemanagerModal').find('#confirm-select-btn');
            if (btn.length > 0) {
                btn.prop('disabled', false);
            } else {
                // 如果找不到，尝试直接查找（兼容旧代码）
                $('#confirm-select-btn').prop('disabled', false);
            }
        });
    }
    
    // 初始化分页
    initPagination();
    
    // 初始绑定文件项点击事件
    bindFileItemClick();
    
    // 确定选择按钮 - 使用事件委托，因为按钮可能在 modal footer 中
    $(document).off('click', '#confirm-select-btn').on('click', '#confirm-select-btn', function() {
        if (selectedFile) {
            $(document).trigger('fileSelected', [selectedFile]);
            $('#filemanagerModal').modal('hide');
        }
    });
    
    // 搜索功能 - 使用防抖
    var searchTimeout;
    $('#search-file').on('input', function() {
        var keyword = $(this).val();
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            if (keyword) {
                // AJAX搜索
                var searchUrl = '{$fileListUrl}?FileSearch[origin_name]=' + encodeURIComponent(keyword);
                loadFilePage(searchUrl);
            } else {
                // 清空搜索，重新加载第一页
                loadFilePage('{$fileListUrl}');
            }
        }, 500); // 500ms 防抖
    });
    
    // 上传相关
    var fileInput = $('#file-input-picker');
    var uploadZone = $('#upload-zone');
    var uploadList = $('#upload-list');
    
    // 选择文件按钮点击事件
    $('#select-file-btn').on('click', function(e) {
        e.stopPropagation(); // 阻止事件冒泡
        console.log('Select file button clicked'); // 调试日志
        
        // 直接触发 input 的点击
        var inputEl = document.getElementById('file-input-picker');
        if (inputEl) {
            inputEl.click();
        }
    });
    
    // 点击上传区域（但不是按钮）
    uploadZone.on('click', function(e) {
        // 如果点击的是按钮或按钮的子元素，不触发
        if ($(e.target).closest('#select-file-btn').length === 0) {
            console.log('Upload zone clicked'); // 调试日志
            var inputEl = document.getElementById('file-input-picker');
            if (inputEl) {
                inputEl.click();
            }
        }
    });
    
    // 文件选择
    fileInput.on('change', function() {
        console.log('File input changed, files:', this.files); // 调试日志
        if (this.files && this.files.length > 0) {
            handleFiles(this.files);
            // 清空 input，允许重复选择同一文件
            this.value = '';
        }
    });
    
    // 拖拽上传 - 移除旧事件
    uploadZone.off('dragover dragleave drop');
    
    uploadZone.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });
    
    uploadZone.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });
    
    uploadZone.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        
        var files = e.originalEvent.dataTransfer.files;
        if (files && files.length > 0) {
            handleFiles(files);
        }
    });
    
    // 处理文件上传
    function handleFiles(files) {
        Array.from(files).forEach(function(file) {
            uploadFile(file);
        });
    }
    
    function uploadFile(file) {
        var fileId = 'file-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        
        // 添加到上传列表
        var itemHtml = '<div class="card mb-2" id="' + fileId + '">' +
            '<div class="card-body py-2">' +
            '<div class="row align-items-center">' +
            '<div class="col">' +
            '<div class="file-name">' + file.name + '</div>' +
            '<small class="text-muted">' + (file.size / 1024).toFixed(2) + ' KB</small>' +
            '</div>' +
            '<div class="col-auto">' +
            '<div class="progress" style="width: 100px;">' +
            '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        uploadList.append(itemHtml);
        
        // 创建 FormData
        var formData = new FormData();
        formData.append('UploadForm[imageFile]', file);
        
        // 上传
        $.ajax({
            url: '$uploadUrl',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = (evt.loaded / evt.total) * 100;
                        $('#' + fileId).find('.progress-bar').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                if (response.success) {
                    $('#' + fileId).find('.progress-bar')
                        .removeClass('progress-bar-animated progress-bar-striped')
                        .addClass('bg-success')
                        .css('width', '100%');
                    
                    // 自动触发选择
                    setTimeout(function() {
                        $(document).trigger('fileSelected', [{
                            url: response.data.url,
                            name: response.data.name
                        }]);
                        $('#filemanagerModal').modal('hide');
                    }, 500);
                } else {
                    $('#' + fileId).find('.progress-bar')
                        .removeClass('progress-bar-animated progress-bar-striped')
                        .addClass('bg-danger')
                        .css('width', '100%');
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                $('#' + fileId).find('.progress-bar')
                    .removeClass('progress-bar-animated progress-bar-striped')
                    .addClass('bg-danger')
                    .css('width', '100%');
                showToast('上传失败', 'error');
            }
        });
    }
})();
JS
);
?>