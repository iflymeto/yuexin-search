(function (window) {
    'use strict';

    function createSearchModule(deps) {
        var axios = deps.axios;
        var debugLog = deps.debugLog || console;
        var keyword = deps.keyword;
        var suggestions = deps.suggestions;
        var showSuggestions = deps.showSuggestions;
        var loadingSuggestions = deps.loadingSuggestions;
        var showMessage = deps.showMessage;
        var suggestionTimer = null;

        var searchBtn = function () {
            if (!keyword.value) {
                return showMessage('请输入你要搜索的内容~', 'error');
            }
            var currentUrl = window.location.href;
            var targetUrl = '/s/' + encodeURIComponent(keyword.value) + '.html';
            if (currentUrl.indexOf('/s/') !== -1 || currentUrl.indexOf('/d/') !== -1) {
                window.location.href = targetUrl;
            } else {
                window.open(targetUrl, '_blank');
            }
        };

        var fetchSuggestions = function () {
            axios.get('/api/search/suggestion', {
                params: { keyword: keyword.value }
            }).then(function (res) {
                loadingSuggestions.value = false;
                suggestions.value = res.data && res.data.code === 200 ? (res.data.data || []) : [];
            }).catch(function (err) {
                debugLog.error('获取联想词失败:', err);
                loadingSuggestions.value = false;
                suggestions.value = [];
            });
        };

        var handleInput = function () {
            if (suggestionTimer) {
                clearTimeout(suggestionTimer);
            }

            if (!keyword.value || !keyword.value.trim()) {
                suggestions.value = [];
                showSuggestions.value = false;
                loadingSuggestions.value = false;
                return;
            }

            loadingSuggestions.value = true;
            showSuggestions.value = true;
            suggestionTimer = setTimeout(fetchSuggestions, 300);
        };

        var handleFocus = function () {
            if (keyword.value && keyword.value.trim()) {
                showSuggestions.value = true;
                if (suggestions.value.length === 0) {
                    fetchSuggestions();
                }
            }
        };

        var handleBlur = function () {
            setTimeout(function () {
                showSuggestions.value = false;
            }, 200);
        };

        var selectSuggestion = function (item) {
            keyword.value = item;
            showSuggestions.value = false;
            searchBtn();
        };

        var dispose = function () {
            if (suggestionTimer) {
                clearTimeout(suggestionTimer);
                suggestionTimer = null;
            }
        };

        return {
            searchBtn: searchBtn,
            handleInput: handleInput,
            handleFocus: handleFocus,
            handleBlur: handleBlur,
            fetchSuggestions: fetchSuggestions,
            selectSuggestion: selectSuggestion,
            dispose: dispose
        };
    }

    window.SearchModule = {
        create: createSearchModule
    };
})(window);

