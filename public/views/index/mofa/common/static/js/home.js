(function (window) {
    'use strict';

    function createHomeModule(deps) {
        var axios = deps.axios;
        var debugLog = deps.debugLog || console;
        var mobileLimit = parseInt(deps.mobileLimit, 10) || 6;
        var desktopLimit = parseInt(deps.desktopLimit, 10) || 10;
        var rankingType = parseInt(deps.rankingType, 10) || 0;
        var rankList = Array.isArray(deps.rankList) ? deps.rankList : [];

        var switchCategory = function (categoryId) {
            var categoryKey = String(categoryId);
            var navItem = document.querySelector('[data-category="' + categoryKey + '"]');
            if (!navItem) return;

            document.querySelectorAll('.nav-item').forEach(function (item) {
                item.classList.remove('nav-active');
            });
            navItem.classList.add('nav-active');

            document.querySelectorAll('.resource-list').forEach(function (list) {
                list.classList.add('hidden');
            });

            var selectedList = document.getElementById('category-' + categoryKey);
            if (!selectedList) return;

            selectedList.classList.remove('hidden');
            var title = categoryKey === 'all' ? '最新更新' : navItem.textContent;
            var titleNode = document.getElementById('current-category');
            if (titleNode) {
                titleNode.textContent = title;
            }

            applyResponsiveResourceLimit();
            selectedList.classList.add('fade-in');
            setTimeout(function () {
                selectedList.classList.remove('fade-in');
            }, 500);
        };

        var getCurrentResourceLimit = function () {
            return window.matchMedia('(max-width: 768px)').matches ? mobileLimit : desktopLimit;
        };

        var updateActiveResourceCount = function () {
            var active = document.querySelector('.nav-item.nav-active');
            var categoryKey = active ? active.getAttribute('data-category') : 'all';
            var selectedList = document.getElementById('category-' + categoryKey);
            var countNode = document.getElementById('resource-count');
            if (!selectedList || !countNode) return;

            var count = Array.prototype.slice.call(selectedList.querySelectorAll('.resource-item'))
                .filter(function (item) {
                    return item.style.display !== 'none';
                }).length;
            countNode.textContent = count;
        };

        var applyResponsiveResourceLimit = function () {
            var limit = getCurrentResourceLimit();
            document.querySelectorAll('.resource-list').forEach(function (list) {
                Array.prototype.slice.call(list.querySelectorAll('.resource-item')).forEach(function (item, index) {
                    item.style.display = index < limit ? '' : 'none';
                });
            });
            updateActiveResourceCount();
        };

        var escapeHtml = function (value) {
            return String(value || '').replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[char];
            });
        };

        var renderImageModeList = function (data, categoryId) {
            var container = document.getElementById('category-' + categoryId);
            if (!container) {
                debugLog.error('找不到容器元素: category-' + categoryId);
                return;
            }

            var ul = container.querySelector('ul');
            if (!ul) {
                debugLog.error('在容器category-' + categoryId + '中找不到ul元素');
                return;
            }

            ul.innerHTML = '';
            ul.className = 'image-mode-grid';

            data.forEach(function (item, index) {
                var li = document.createElement('li');
                li.className = 'resource-item with-image';

                var numberClass = index === 0 ? 'number-1' :
                    index === 1 ? 'number-2' :
                    index === 2 ? 'number-3' : 'number-default';

                var title = item.title || '';
                var safeTitle = escapeHtml(title);
                var imageHtml = item.src ?
                    "<img src=\"" + escapeHtml(item.src) + "\" alt=\"" + safeTitle + "\" class=\"resource-image\" onerror=\"this.style.display='none'; this.parentElement.querySelector('.image-placeholder').style.display='flex';\">" :
                    '<div class="resource-image image-placeholder" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;"><i class="fa fa-image"></i></div>';

                li.innerHTML =
                    '<a href="/s/' + encodeURIComponent(title) + '.html" target="_blank" class="resource-card-link">' +
                        '<div class="resource-image-container">' +
                            imageHtml +
                            (item.src ? '<div class="image-placeholder" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: none; align-items: center; justify-content: center; color: white; font-size: 24px; position: absolute; top: 0; left: 0; width: 100%; height: 100%;"><i class="fa fa-image"></i></div>' : '') +
                            '<span class="resource-number ' + numberClass + '">' + (index + 1) + '</span>' +
                        '</div>' +
                        '<div class="resource-content">' +
                            '<div class="resource-title">' + safeTitle + '</div>' +
                            '<div class="resource-meta">' +
                                '<span class="resource-ranking">#' + escapeHtml(item.ranking || index + 1) + '</span>' +
                                '<span class="hot-score">' + escapeHtml(item.hot_score || '--') + '</span>' +
                            '</div>' +
                        '</div>' +
                    '</a>';

                ul.appendChild(li);
            });
        };

        var loadImageModeRankings = function () {
            if (rankingType != 1) return;

            if (!Array.isArray(rankList) || rankList.length === 0) {
                debugLog.warn('rankList数据为空，无法处理热搜榜单');
                return;
            }

            rankList.forEach(function (item) {
                if (item.is_sys != 1) return;

                axios.get('/api/tool/ranking', {
                    params: { channel: item.name }
                }).then(function (response) {
                    if (response.data && response.data.code === 1 && response.data.data) {
                        renderImageModeList(response.data.data, item.source_category_id);
                        var firstCategoryNode = document.querySelector('.nav-item[data-category]:not([data-category="all"])');
                        var firstCategoryId = firstCategoryNode ? firstCategoryNode.getAttribute('data-category') : null;
                        if (firstCategoryId && item.source_category_id == firstCategoryId) {
                            firstCategoryNode.click();
                        }
                        applyResponsiveResourceLimit();
                    } else {
                        debugLog.warn('热搜API返回数据格式不正确:', response.data);
                    }
                }).catch(function (error) {
                    debugLog.error('获取热搜数据失败:', error);
                });
            });
        };

        return {
            switchCategory: switchCategory,
            getCurrentResourceLimit: getCurrentResourceLimit,
            updateActiveResourceCount: updateActiveResourceCount,
            applyResponsiveResourceLimit: applyResponsiveResourceLimit,
            renderImageModeList: renderImageModeList,
            loadImageModeRankings: loadImageModeRankings
        };
    }

    window.HomeModule = {
        create: createHomeModule
    };
})(window);

