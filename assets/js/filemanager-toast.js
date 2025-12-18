// FileManager 模块通用 Toastify 封装
// 全局方法：show_toast(message, type, options)
// type: 'success' | 'error' | 'warning' | 'info'
// options: 额外的 Toastify 配置，会与默认配置合并

(function (window) {
    if (typeof window.Toastify === 'undefined') {
        // 如果 Toastify 未加载，则降级为 alert
        window.show_toast = function (message) {
            alert(message || '');
        };
        return;
    }

    if (typeof window.show_toast === 'function') {
        // 已经定义则不重复定义，避免多次加载资源时冲突
        return;
    }

    window.show_toast = function (message, type, options) {
        type = type || 'info';
        options = options || {};

        var background = '#3498db'; // info
        if (type === 'success') {
            background = '#000';
        } else if (type === 'error' || type === 'danger') {
            background = '#e74c3c';
        } else if (type === 'warning') {
            background = '#f1c40f';
        }

        var baseOptions = {
            text: message || '',
            duration: 3000,
            close: true,
            gravity: 'top',
            position: 'center',
            stopOnFocus: true,
            style: {
                background: background,
            },
        };

        Toastify(Object.assign(baseOptions, options)).show_toast();
    };
})(window);
