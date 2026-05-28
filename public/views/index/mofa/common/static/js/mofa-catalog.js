(function (window) {
  "use strict";

  function createMofaCatalog(context) {
    const el = context.el;
    const state = context.state;
    const readStore = context.readStore;
    const writeStore = context.writeStore;
    const setText = context.setText;
    const requestJson = context.requestJson;
    const buildUrl = context.buildUrl;
    const setLazyImage = context.setLazyImage;
    const loadDetail = context.loadDetail;
    const setLoadMoreVisible = context.setLoadMoreVisible;

    function isFavorite(id) {
      return readStore("cinema:favorites").some(item => String(item.vod_id) === String(id));
    }

    function toggleFavorite(item) {
      const list = readStore("cinema:favorites");
      const exists = list.some(saved => String(saved.vod_id) === String(item.vod_id));
      const next = exists
        ? list.filter(saved => String(saved.vod_id) !== String(item.vod_id))
        : [{ ...item, saved_at: Date.now() }, ...list].slice(0, 80);
      writeStore("cinema:favorites", next);
    }

    function setLoading(text) {
      el.message.className = "empty";
      el.message.style.display = "grid";
      el.message.textContent = text;
      el.grid.innerHTML = "";
      if (el.keyword.value.trim()) {
        setText(el.searchStatus, "正在搜索 “" + el.keyword.value.trim() + "”...");
      }
    }

    function setError(error) {
      el.message.className = "empty error";
      el.message.style.display = "grid";
      el.message.textContent = error.message || String(error);
      setText(el.countPill, "请求失败");
    }

    function updateSummary(data) {
      state.page = Number(data.page || 1);
      state.pageCount = Number(data.pagecount || 1);
      state.total = Number(data.total || 0);
      state.list = Array.isArray(data.list) ? data.list : [];
      el.pageInput.value = state.page;
      setText(el.countPill, "共 " + state.total + " 条");
      setText(el.pageInfo, "第 " + state.page + " / " + state.pageCount + " 页");
      el.debug.textContent = JSON.stringify(data, null, 2);
      if (el.keyword.value.trim()) {
        setText(el.searchStatus, "搜索 “" + el.keyword.value.trim() + "” 找到 " + state.total + " 条");
      } else {
        setText(el.searchStatus, "");
      }
    }

    function renderClasses(classes) {
      const current = el.category.value;
      el.category.innerHTML = '<option value="">全部分类</option>';
      classes.forEach(item => {
        const option = document.createElement("option");
        option.value = item.type_id;
        option.textContent = item.type_name + " (" + item.type_id + ")";
        el.category.appendChild(option);
      });
      el.category.value = current;
    }

    function renderList(list) {
      el.grid.innerHTML = "";
      el.message.style.display = list.length ? "none" : "grid";
      el.message.textContent = list.length ? "" : "没有找到数据。";

      list.forEach(item => {
        el.grid.appendChild(createCard(item));
      });
    }

    function createCard(item, compact = false) {
      const card = document.createElement("article");
      card.className = "card";

      const poster = document.createElement("img");
      poster.className = "poster";
      poster.loading = "lazy";
      poster.decoding = "async";
      const originalPic = item.vod_pic || "";
      poster.alt = item.vod_name || "";
      if (originalPic) {
        setLazyImage(poster, originalPic, "vod");
        poster.onerror = () => {
          if (poster.src !== originalPic && originalPic) {
            poster.src = originalPic;
            return;
          }
          poster.removeAttribute("src");
          poster.alt = "封面加载失败";
        };
      } else {
        poster.removeAttribute("src");
        poster.alt = "列表接口无封面字段";
      }

      const body = document.createElement("div");
      body.className = "card-body";

      const title = document.createElement("div");
      title.className = "title";
      title.textContent = item.vod_name || "未命名";

      const meta = document.createElement("div");
      meta.className = "meta";
      meta.textContent = [
        item.type_name || item.vod_class || "未知分类",
        item.vod_remarks || "",
        item.vod_play_from || ""
      ].filter(Boolean).join(" · ");

      const actions = document.createElement("div");
      actions.className = "card-actions";

      const detailBtn = document.createElement("button");
      detailBtn.className = "small-btn primary";
      detailBtn.textContent = compact ? "播放" : "详情";
      detailBtn.addEventListener("click", () => loadDetail(item.vod_id));

      const favoriteBtn = document.createElement("button");
      favoriteBtn.className = "small-btn";
      favoriteBtn.textContent = isFavorite(item.vod_id) ? "已收藏" : "收藏";
      favoriteBtn.addEventListener("click", () => {
        toggleFavorite(item);
        favoriteBtn.textContent = isFavorite(item.vod_id) ? "已收藏" : "收藏";
      });

      actions.append(detailBtn, favoriteBtn);
      body.append(title, meta, actions);
      card.append(poster, body);
      return card;
    }

    async function loadClasses() {
      setLoading("正在加载分类...");
      try {
        const data = await requestJson(buildUrl("list"));
        if (Array.isArray(data.class)) renderClasses(data.class);
        el.debug.textContent = JSON.stringify(data, null, 2);
        el.message.textContent = "分类已加载。选择分类、搜索或翻页时才会请求列表。";
      } catch (error) {
        setError(error);
      }
    }

    async function loadList(options = {}) {
      state.viewMode = "list";
      state.ranking = { channel: "", list: [], shown: 0 };
      setLoadMoreVisible(true);
      if (!options.append) {
        setLoading("正在请求列表...");
      } else {
        setText(el.searchStatus, "正在加载第 " + el.pageInput.value + " 页...");
      }
      if (el.keyword.value.trim()) {
        el.homeSections.innerHTML = "";
      }
      try {
        const data = await requestJson(buildUrl("videolist"));
        if (Array.isArray(data.class)) renderClasses(data.class);
        updateSummary(data);
        if (options.append) {
          el.message.style.display = "none";
          (data.list || []).forEach(item => el.grid.appendChild(createCard(item)));
        } else {
          renderList(data.list || []);
        }
      } catch (error) {
        setError(error);
      }
    }

    return {
      setLoading,
      setError,
      updateSummary,
      renderClasses,
      renderList,
      createCard,
      loadClasses,
      loadList
    };
  }

  window.createMofaCatalog = createMofaCatalog;
})(window);
