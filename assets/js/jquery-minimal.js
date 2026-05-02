// Minimal jQuery replacement for this project
(function(window) {
    function $(selector) {
        if (selector === document || selector === window) {
            return new JQueryLike([selector]);
        }
        if (typeof selector === 'function') {
            // $(function() {...}) shorthand for document ready
            if (document.readyState !== 'loading') {
                selector();
            } else {
                document.addEventListener('DOMContentLoaded', selector);
            }
            return;
        }
        if (typeof selector !== 'string') {
            return new JQueryLike([]);
        }
        if (selector.charAt(0) === '#') {
            return new JQueryLike([document.getElementById(selector.slice(1))]);
        }
        return new JQueryLike(document.querySelectorAll(selector));
    }

    function JQueryLike(elements) {
        this.elements = Array.from(elements).filter(el => el !== null);
        this.length = this.elements.length;
    }

    JQueryLike.prototype.click = function(handler) {
        this.elements.forEach(el => el.addEventListener('click', handler));
        return this;
    };

    JQueryLike.prototype.val = function(value) {
        if (value === undefined) {
            return this.elements[0] ? this.elements[0].value : '';
        }
        this.elements.forEach(el => el.value = value);
        return this;
    };

    JQueryLike.prototype.ready = function(callback) {
        if (document.readyState !== 'loading') {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback);
        }
        return this;
    };

    $.ajax = function(options) {
        var xhr = new XMLHttpRequest();
        xhr.open(options.method || 'GET', options.url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                if (options.success) options.success(xhr.responseText);
            } else {
                if (options.error) options.error(xhr, xhr.statusText, xhr.responseText);
            }
        };
        
        xhr.onerror = function() {
            if (options.error) options.error(xhr, 'error', 'Network Error');
        };
        
        if (options.data) {
            var params = Object.keys(options.data)
                .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(options.data[key]))
                .join('&');
            xhr.send(params);
        } else {
            xhr.send();
        }
    };

    window.$ = $;
})(window);
