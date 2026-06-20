(function (window) {
  "use strict";

  function createMofaSearch(context) {
    const el = context.el;
    const state = context.state;
    const escapeHtml = context.escapeHtml;
    const readStore = context.readStore;
    const writeStore = context.writeStore;
    const bannerItems = context.bannerItems;
    const requestJson = context.requestJson;
    const buildUrl = context.buildUrl;
    const createCard = context.createCard;
    const normalizeSearchItem = context.normalizeSearchItem;
    const createSearchResultCard = context.createSearchResultCard;
    const setActiveNav = context.setActiveNav;

    function runSearch() {
      const keyword = el.topKeyword.value.trim() || el.keyword.value.trim();
      if (!keyword) return;
      el.keyword.value = keyword;
      el.topKeyword.value = keyword;
      hidePcSuggestions();
      window.location.href = pcSearchUrl(keyword);
    }

    function lockPageScroll() {
      if (document.body.classList.contains("search-lock")) return;
      state.searchScrollY = window.scrollY || document.documentElement.scrollTop || 0;
      document.body.style.top = "-" + state.searchScrollY + "px";
      document.body.classList.add("search-lock");
    }

    function unlockPageScroll() {
      if (!document.body.classList.contains("search-lock")) return;
      const scrollY = state.searchScrollY || 0;
      document.body.classList.remove("search-lock");
      document.body.style.top = "";
      window.scrollTo(0, scrollY);
      state.searchScrollY = 0;
    }

    function openSearchPage(preset = "", options = {}) {
      lockPageScroll();
      el.searchPage.classList.add("open");
      el.searchBody.style.display = "block";
      el.searchResult.style.display = "none";
      el.searchInput.value = preset;
      renderSearchHome();
      if (preset.trim()) fetchMobileSuggestions();
      else hideMobileSuggestions();
      if (options.focus !== false) {
        window.setTimeout(() => el.searchInput.focus(), 80);
      }
    }

    function openSearchHistoryPage() {
      setActiveNav("discover");
      openSearchPage();
    }

    function closeSearchPage() {
      closeSearchEventSource();
      el.searchPage.classList.remove("open");
      el.searchInput.value = "";
      hideMobileSuggestions();
      el.searchBody.style.display = "block";
      el.searchResult.style.display = "none";
      unlockPageScroll();
    }

    function commitSearch(keyword) {
      const value = String(keyword || "").trim();
      if (!value) return;
      hideMobileSuggestions();
      const history = readStore("cinema:searchHistory");
      writeStore("cinema:searchHistory", [value, ...history.filter(item => item !== value)].slice(0, 20));
      el.searchBody.style.display = "none";
      el.searchResult.style.display = "flex";
      state.searchKeyword = value;
      state.searchPanType = 0;
      el.searchInput.value = value;
      renderSearchResultHeader(value, 0, true);
      el.searchResult.classList.add("is-empty");
      el.resultGrid.className = "search-result-list";
      el.resultGrid.innerHTML = '<div class="empty">搜索中...</div>';
      startAggregateSearch(value, el.resultGrid);
    }

    const suggestions = window.createMofaSuggestions({ el, escapeHtml, commitSearch });
    const pcSuggestionState = suggestions.pcState;
    const mobileSuggestionState = suggestions.mobileState;
    const pcSearchUrl = suggestions.pcSearchUrl;
    const hidePcSuggestions = suggestions.hidePcSuggestions;
    const renderPcSuggestions = suggestions.renderPcSuggestions;
    const selectPcSuggestion = suggestions.selectPcSuggestion;
    const fetchPcSuggestions = suggestions.fetchPcSuggestions;
    const hideMobileSuggestions = suggestions.hideMobileSuggestions;
    const renderMobileSuggestions = suggestions.renderMobileSuggestions;
    const selectMobileSuggestion = suggestions.selectMobileSuggestion;
    const fetchMobileSuggestions = suggestions.fetchMobileSuggestions;

    function panTabs() {
      return [
        { type: 0, name: "夸克网盘" },
        { type: 2, name: "百度网盘" },
        { type: 3, name: "UC网盘" },
        { type: 4, name: "迅雷网盘" }
      ];
    }

    function currentPanName() {
      const current = panTabs().find(tab => Number(tab.type) === Number(state.searchPanType));
      return current ? current.name : "当前网盘";
    }

    function renderSearchResultHeader(keyword, count, loading = false) {
      el.resultText.innerHTML = '关键词 <span class="result-keyword">' + escapeHtml(keyword) + '</span>';
      el.resultCount.textContent = loading ? "- 已找到 " + count + " 条" : "- " + count + "条";
      el.resultPanTabs.innerHTML = "";
      panTabs().forEach(tab => {
        const btn = document.createElement("button");
        btn.className = "result-pan-tab" + (Number(state.searchPanType) === tab.type ? " active" : "") + (state.searchLoading ? " loading" : "");
        btn.textContent = tab.name;
        btn.addEventListener("click", () => {
          if (Number(state.searchPanType) === tab.type) return;
          state.searchPanType = tab.type;
          closeSearchEventSource();
          const cached = state.searchCache.get(searchCacheKey(state.searchKeyword, tab.type));
          if (cached) {
            state.searchItems = cached.slice();
            state.searchLoading = false;
            state.searchPendingReplace = false;
            renderSearchResultHeader(state.searchKeyword, state.searchItems.length);
            renderSearchItems(el.resultGrid, state.searchItems);
            return;
          }
          showSearchToast("正在搜索：" + tab.name);
          renderSearchResultHeader(state.searchKeyword, 0, true);
          startAggregateSearch(state.searchKeyword, el.resultGrid);
        });
        el.resultPanTabs.appendChild(btn);
      });
    }

    function showSearchToast(message) {
      if (!el.searchToast) return;
      el.searchToast.textContent = message;
      el.searchToast.classList.add("show");
      window.clearTimeout(el.searchToast.__timer);
      el.searchToast.__timer = window.setTimeout(() => el.searchToast.classList.remove("show"), 1800);
    }

    function renderSearchStatus(target, text) {
      const status = document.createElement("div");
      status.className = "search-status-line";
      status.innerHTML = '<span class="search-status-dot"></span><span>' + escapeHtml(text) + '</span>';
      target.insertBefore(status, target.firstChild);
      return status;
    }

    function setStatusText(status, text, loading = true) {
      status.className = loading ? "search-status-line" : "search-resource-meta";
      status.innerHTML = loading ? '<span class="search-status-dot"></span><span>' + escapeHtml(text) + '</span>' : escapeHtml(text);
    }

    function renderSearchEmpty(target, title, text, error = false) {
      el.searchResult.classList.add("is-empty");
      target.innerHTML = "";
      const empty = document.createElement("div");
      empty.className = "search-empty-state" + (error ? " error" : "");
      empty.innerHTML = '<div><strong>' + escapeHtml(title) + '</strong><span>' + escapeHtml(text || "") + '</span></div>';
      target.appendChild(empty);
    }

    function searchCacheKey(keyword, panType = state.searchPanType) {
      return String(keyword || "").trim() + "|" + String(panType);
    }

    function resetSearchBuckets() {
      state.searchBuckets = {
        local: [],
        fresh: [],
        cached: [],
        seen: new Map()
      };
    }

    function getSearchItemKeys(item) {
      if (!item) return [];
      return [
        item.source_url,
        item.original_url,
        item.sourceUrl,
        item.originalUrl,
        item.treeSourceUrl,
        item.url,
        item.title
      ].map(value => String(value || "").trim()).filter(Boolean);
    }

    function getSearchItemKey(item) {
      const keys = getSearchItemKeys(item);
      return keys[0] || "";
    }

    function isLocalSearchItem(item) {
      return Boolean(item && (item.is_local || item.isLocal));
    }

    function isFreshSearchItem(item) {
      return Boolean(item && (item.is_new || item.isNew || item.insert_position === "top"));
    }

    function mergeSearchItem(target, source) {
      if (!target || !source) return target || source;
      Object.keys(source).forEach(key => {
        if (source[key] !== undefined && source[key] !== null && source[key] !== "") {
          target[key] = source[key];
        }
      });
      if (isLocalSearchItem(source)) {
        target.is_local = true;
        target.isLocal = true;
      }
      return target;
    }

    function removeBucketItem(bucket, item) {
      const index = bucket.indexOf(item);
      if (index >= 0) bucket.splice(index, 1);
    }

    function firstBucketCard(bucket) {
      for (const item of bucket) {
        if (item && item.__searchCard && item.__searchCard.parentNode) {
          return item.__searchCard;
        }
      }
      return null;
    }

    function ensureSearchItemCard(item) {
      if (!item.__searchCard) {
        item.__searchCard = createSearchResultCard(item);
      }
      return item.__searchCard;
    }

    function refreshSearchItemCard(item) {
      if (!item || !item.__searchCard) return;
      const oldCard = item.__searchCard;
      const newCard = createSearchResultCard(item);
      item.__searchCard = newCard;
      if (oldCard.parentNode) {
        oldCard.parentNode.replaceChild(newCard, oldCard);
      }
    }

    function insertSearchItemCard(target, item, status) {
      if (!target || !item) return;
      const buckets = state.searchBuckets;
      const card = ensureSearchItemCard(item);
      let beforeNode = null;

      if (item.__searchBucket === "local") {
        beforeNode = firstBucketCard(buckets.fresh) || firstBucketCard(buckets.cached) || beforeNode;
      } else if (item.__searchBucket === "fresh") {
        beforeNode = firstBucketCard(buckets.cached) || beforeNode;
      }

      if (beforeNode && beforeNode !== card) {
        target.insertBefore(card, beforeNode);
      } else if (card.parentNode !== target) {
        target.appendChild(card);
      }
    }

    function addSearchItemToBuckets(item) {
      if (!state.searchBuckets) resetSearchBuckets();
      const buckets = state.searchBuckets;
      const key = getSearchItemKey(item);
      if (!key) return state.searchItems;

      const keys = getSearchItemKeys(item);
      let existing = null;
      for (const alias of keys) {
        existing = buckets.seen.get(alias);
        if (existing) break;
      }
      if (existing) {
        const wasLocal = isLocalSearchItem(existing);
        item = mergeSearchItem(existing, item);
        removeBucketItem(buckets.local, existing);
        removeBucketItem(buckets.fresh, existing);
        removeBucketItem(buckets.cached, existing);
        getSearchItemKeys(existing).forEach(alias => {
          if (buckets.seen.get(alias) === existing) buckets.seen.delete(alias);
        });
        if (!wasLocal && isLocalSearchItem(item)) {
          refreshSearchItemCard(item);
        }
      }

      if (isLocalSearchItem(item)) {
        item.is_local = true;
        item.isLocal = true;
        item.__searchBucket = "local";
        buckets.local.push(item);
      } else if (isFreshSearchItem(item)) {
        item.__searchBucket = "fresh";
        buckets.fresh.push(item);
      } else {
        item.__searchBucket = "cached";
        buckets.cached.push(item);
      }

      getSearchItemKeys(item).forEach(alias => buckets.seen.set(alias, item));
      state.searchItems = buckets.local.concat(buckets.fresh, buckets.cached);
      return state.searchItems;
    }

    function removeSearchItemFromBuckets(item) {
      if (!state.searchBuckets || !item) return false;
      const buckets = state.searchBuckets;
      const keys = getSearchItemKeys(item);
      let existing = null;
      let matchedKey = "";
      for (const key of keys) {
        existing = buckets.seen.get(key);
        if (existing) {
          matchedKey = key;
          break;
        }
      }
      if (!existing) return false;
      removeBucketItem(buckets.local, existing);
      removeBucketItem(buckets.fresh, existing);
      removeBucketItem(buckets.cached, existing);
      if (matchedKey) buckets.seen.delete(matchedKey);
      keys.forEach(key => {
        if (buckets.seen.get(key) === existing) buckets.seen.delete(key);
      });
      if (existing.__searchCard && existing.__searchCard.parentNode) {
        existing.__searchCard.parentNode.removeChild(existing.__searchCard);
      }
      state.searchItems = buckets.local.concat(buckets.fresh, buckets.cached);
      return true;
    }

    function renderSearchItems(target, items) {
      el.searchResult.classList.toggle("is-empty", !items.length);
      target.className = "search-result-list";
      target.innerHTML = "";
      items.forEach(item => {
        item.__searchCard = createSearchResultCard(item);
        target.appendChild(item.__searchCard);
      });
    }

    function closeSearchEventSource() {
      state.searchRequestId += 1;
      if (state.searchEventSource) {
        state.searchEventSource.close();
        state.searchEventSource = null;
      }
    }

    function startAggregateSearch(keyword, target, options = {}) {
      closeSearchEventSource();
      const requestId = state.searchRequestId;
      const previousVisibleCount = options.keepPrevious ? target.querySelectorAll(".search-resource-card").length : 0;
      state.searchItems = [];
      resetSearchBuckets();
      state.searchKeyword = keyword;
      state.searchLoading = true;
      state.searchPendingReplace = Boolean(options.keepPrevious);
      target.querySelectorAll(".search-status-line").forEach(node => node.remove());
      renderSearchResultHeader(keyword, state.searchPendingReplace ? previousVisibleCount : 0, true);
      el.searchResult.classList.add("is-empty");
      target.className = "search-result-list";
      if (!state.searchPendingReplace) {
        target.innerHTML = "";
      }
      const status = renderSearchStatus(target, "正在搜索：" + currentPanName());

      const url = "/api/other/web_search?title=" + encodeURIComponent(keyword) + "&is_type=" + encodeURIComponent(state.searchPanType) + "&is_show=0";
      el.requestUrl.value = url;
      const source = new EventSource(url);
      state.searchEventSource = source;

      source.onmessage = event => {
        if (requestId !== state.searchRequestId) return;
        if (event.data === "[DONE]") {
          closeSearchEventSource();
          state.searchLoading = false;
          state.searchPendingReplace = false;
          state.searchCache.set(searchCacheKey(keyword), state.searchItems.slice());
          if (state.searchItems.length) {
            renderSearchResultHeader(keyword, state.searchItems.length);
            status.remove();
          } else {
            if (options.keepPrevious) {
              renderSearchResultHeader(keyword, previousVisibleCount);
              status.remove();
              showSearchToast("当前网盘暂无结果");
            } else {
              renderSearchResultHeader(keyword, 0);
              renderSearchEmpty(target, currentPanName() + "暂无结果", "换个关键词或切换其它网盘类型试试");
            }
          }
          return;
        }

        let data = null;
        try {
          data = JSON.parse(event.data);
        } catch (error) {
          return;
        }

        if (data.type === "progress") {
          setStatusText(status, data.message || ("正在搜索：" + currentPanName()));
          return;
        }

        if (data.type === "cache_remove") {
          data = normalizeSearchItem(data);
          if (removeSearchItemFromBuckets(data)) {
            renderSearchResultHeader(keyword, state.searchItems.length, true);
            el.searchResult.classList.toggle("is-empty", state.searchItems.length === 0);
            setStatusText(status, currentPanName() + "已找到 " + state.searchItems.length + " 条，继续搜索中");
          }
          return;
        }

        if (!data || !data.url || !data.title) return;
        data = normalizeSearchItem(data);
        el.searchResult.classList.remove("is-empty");
        if (state.searchPendingReplace) {
          target.innerHTML = "";
          target.insertBefore(status, target.firstChild);
          state.searchPendingReplace = false;
        }
        addSearchItemToBuckets(data);
        insertSearchItemCard(target, data, status);
        el.searchResult.classList.toggle("is-empty", state.searchItems.length === 0);
        renderSearchResultHeader(keyword, state.searchItems.length, true);
        setStatusText(status, currentPanName() + "已找到 " + state.searchItems.length + " 条，继续搜索中");
      };

      source.onerror = () => {
        if (requestId !== state.searchRequestId) return;
        const hasResult = state.searchItems.length > 0;
        closeSearchEventSource();
        state.searchLoading = false;
        state.searchPendingReplace = false;
        if (hasResult) state.searchCache.set(searchCacheKey(keyword), state.searchItems.slice());
        if (hasResult) {
          renderSearchResultHeader(keyword, state.searchItems.length);
          status.remove();
        } else {
          renderSearchResultHeader(keyword, options.keepPrevious ? previousVisibleCount : 0);
          if (options.keepPrevious) {
            status.remove();
            showSearchToast("搜索已切换或中断");
          } else {
            renderSearchEmpty(target, currentPanName() + "搜索连接中断", "请稍后重试，或切换其它网盘类型", true);
          }
        }
      };
    }

    function renderSearchHome() {
      renderSearchHistory();
    }

    function renderSearchHistory() {
      const history = readStore("cinema:searchHistory");
      el.searchHistoryChips.innerHTML = "";
      if (!history.length) {
        el.searchHistoryChips.innerHTML = '<span class="meta">暂无搜索记录</span>';
        return;
      }
      history.forEach(keyword => {
        const btn = document.createElement("button");
        btn.className = "history-chip" + (keyword === state.searchKeyword ? " active" : "");
        btn.textContent = keyword;
        btn.addEventListener("click", () => {
          state.searchKeyword = keyword;
          commitSearch(keyword);
        });
        el.searchHistoryChips.appendChild(btn);
      });
    }

    function renderHotSearch() {
      if (!el.hotSearchGrid) return;
      const hot = [];
      bannerItems().forEach(item => {
        if (item.keyword || item.title) hot.push(item.keyword || item.title);
      });
      readStore("cinema:searchHistory").forEach(item => hot.push(item));
      ["御赐小仵作", "剑来", "沦陷", "雨霖铃", "仙逆", "完美世界"].forEach(item => hot.push(item));
      el.hotSearchGrid.innerHTML = "";
      [...new Set(hot)].slice(0, 10).forEach((keyword, index) => {
        const btn = document.createElement("button");
        btn.className = "hot-item";
        btn.innerHTML = '<span class="hot-rank ' + (index < 3 ? "top" : "") + '">' + (index + 1) + '</span><span class="hot-text">' + keyword + '</span>';
        btn.addEventListener("click", () => commitSearch(keyword));
        el.hotSearchGrid.appendChild(btn);
      });
    }

    async function loadGuessList(typeId = "") {
      if (!el.guessGrid) return;
      el.guessGrid.innerHTML = '<div class="empty">加载中...</div>';
      try {
        const currentCategory = el.category.value;
        const currentKeyword = el.keyword.value;
        el.category.value = typeId;
        el.keyword.value = "";
        const data = await requestJson(buildUrl("videolist"));
        el.category.value = currentCategory;
        el.keyword.value = currentKeyword;
        el.guessGrid.innerHTML = "";
        (data.list || []).slice(0, 6).forEach(item => el.guessGrid.appendChild(createCard(item)));
      } catch (error) {
        el.guessGrid.innerHTML = '<div class="empty error">推荐加载失败</div>';
      }
    }



    function searchByKeyword(keyword) {
      keyword = String(keyword || "").trim();
      if (!keyword) return;
      setActiveNav("discover");
      openSearchPage(keyword, { focus: false });
      commitSearch(keyword);
    }

    return {
      pcSuggestionState,
      mobileSuggestionState,
      runSearch,
      openSearchPage,
      openSearchHistoryPage,
      closeSearchPage,
      commitSearch,
      closeSearchEventSource,
      renderSearchHome,
      renderSearchHistory,
      showSearchToast,
      loadGuessList,
      searchByKeyword,
      hidePcSuggestions,
      renderPcSuggestions,
      selectPcSuggestion,
      fetchPcSuggestions,
      hideMobileSuggestions,
      renderMobileSuggestions,
      selectMobileSuggestion,
      fetchMobileSuggestions
    };
  }

  window.createMofaSearch = createMofaSearch;
})(window);
