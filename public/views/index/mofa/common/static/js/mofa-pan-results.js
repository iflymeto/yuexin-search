(function (window) {
  "use strict";

  function createMofaPanResults(context) {
    const el = context.el;
    const state = context.state;
    const escapeHtml = context.escapeHtml;
    const copyText = context.copyText;
    const loadDetail = context.loadDetail;

    function getPanName(item) {
      const type = Number(item && item.is_type !== undefined ? item.is_type : 0);
      if (type === 1) return "阿里云盘";
      if (type === 2) return "百度网盘";
      if (type === 3) return "UC网盘";
      if (type === 4) return "迅雷网盘";
      return "夸克网盘";
    }

    function getPanIcon(item) {
      const type = Number(item && item.is_type !== undefined ? item.is_type : 0);
      if (type === 2) return "/data/百度网盘.png";
      if (type === 3) return "/data/UC网盘.png";
      if (type === 4) return "/data/迅雷.png";
      return "/data/夸克.png";
    }

    function firstNonEmpty(values) {
      for (const value of values) {
        if (Array.isArray(value) && value.length) {
          const nested = firstNonEmpty(value);
          if (nested) return nested;
        } else if (value !== undefined && value !== null && String(value).trim() !== "") {
          return String(value).trim();
        }
      }
      return "";
    }

    function normalizeSearchItem(item) {
      if (!item || typeof item !== "object") return item;
      const images = Array.isArray(item.images) ? item.images : [];
      const image = firstNonEmpty([
        item.image,
        item.cover,
        item.poster,
        item.pic,
        item.thumb,
        item.thumbnail,
        item.vod_pic,
        images
      ]);
      if (image) {
        item.image = image;
      }
      if (!item.originalUrl && item.url) {
        item.originalUrl = item.url;
      }
      if (!item.treeSourceUrl && item.url) {
        item.treeSourceUrl = item.url;
      }
      if (item.tree_key && !item.treeKey) {
        item.treeKey = item.tree_key;
      }
      if (item.treeKey && !item.tree_key) {
        item.tree_key = item.treeKey;
      }
      if (item.is_type === undefined || item.is_type === null || item.is_type === "") {
        item.is_type = state.searchPanType;
      }
      return item;
    }

    function isDirectLinkMode() {
      return String(window.MOFA_IS_QUAN_TYPE || "0").trim() === "1";
    }

    function supportsPanTree(item) {
      const type = Number(item && item.is_type !== undefined ? item.is_type : -1);
      return [0, 2, 3].includes(type);
    }

    function ensureTreeState(item) {
      if (!item) return;
      if (item.treeExpanded === undefined) item.treeExpanded = false;
      if (item.treeLoading === undefined) item.treeLoading = false;
      if (item.treeLoaded === undefined) item.treeLoaded = false;
      if (item.treeData === undefined) item.treeData = null;
      if (item.treeError === undefined) item.treeError = "";
    }

    function extractPanCode(item) {
      if (item && item.code) return item.code;
      const url = item?.treeSourceUrl || item?.originalUrl || item?.url || "";
      if (!url || !/^https?:\/\//i.test(url)) return "";
      try {
        return new URL(url).searchParams.get("pwd") || "";
      } catch (error) {
        const match = url.match(/[?&]pwd=([^&#\s]+)/);
        return match ? decodeURIComponent(match[1]) : "";
      }
    }

    function getPanTreeCacheKey(item) {
      const treeKey = item?.tree_key || item?.treeKey || "";
      if (treeKey) return "tree_key:" + treeKey;
      const sourceUrl = item?.treeSourceUrl || item?.originalUrl || item?.url || "";
      if (!sourceUrl) return "";
      return ["source", item.is_type ?? -1, sourceUrl, extractPanCode(item), item.stoken || ""].join("|");
    }

    async function loadItemPanTree(item, force = false) {
      ensureTreeState(item);
      if (!item || item.treeLoading) return;

      if (!supportsPanTree(item)) {
        item.treeExpanded = true;
        item.treeLoaded = false;
        item.treeData = null;
        item.treeError = Number(item?.is_type ?? -1) === 4 ? "暂不支持迅雷目录预览" : "当前网盘暂不支持目录预览";
        return;
      }

      const treeKey = item.tree_key || item.treeKey || "";
      const sourceUrl = item.treeSourceUrl || item.originalUrl || item.url || "";
      if (!treeKey && !sourceUrl) {
        item.treeExpanded = true;
        item.treeLoaded = false;
        item.treeData = null;
        item.treeError = "暂无可解析的资源地址";
        return;
      }

      if (!force && item.treeLoaded && item.treeData) return;

      const cacheKey = getPanTreeCacheKey(item);
      if (!force && cacheKey && state.panTreeCache.has(cacheKey)) {
        item.treeData = state.panTreeCache.get(cacheKey);
        item.treeLoaded = true;
        item.treeError = "";
        return;
      }

      item.treeLoading = true;
      item.treeError = "";
      try {
        const body = new URLSearchParams();
        if (treeKey) {
          body.set("tree_key", treeKey);
        } else {
          body.set("url", encodeURIComponent(sourceUrl));
          body.set("is_type", item.is_type ?? -1);
          body.set("code", extractPanCode(item));
          body.set("stoken", item.stoken || "");
        }

        const response = await fetch("/api/other/pan_tree", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body
        });
        const result = await response.json();
        if (result.code === 200 && result.data) {
          item.treeData = result.data;
          item.treeLoaded = true;
          item.treeError = "";
          if (cacheKey) state.panTreeCache.set(cacheKey, result.data);
        } else {
          item.treeData = null;
          item.treeLoaded = false;
          item.treeError = result.message || result.msg || "目录获取失败";
        }
      } catch (error) {
        item.treeData = null;
        item.treeLoaded = false;
        item.treeError = "目录获取失败，请稍后重试";
      } finally {
        item.treeLoading = false;
      }
    }

    async function toggleItemTree(item, panel) {
      ensureTreeState(item);
      if (!item || item.treeLoading) return;
      if (item.treeLoaded && item.treeData) {
        item.treeExpanded = !item.treeExpanded;
      } else {
        item.treeExpanded = true;
        await loadItemPanTree(item, Boolean(item.treeError));
      }
      renderTreePanel(item, panel, "search");
    }

    function flattenTreeNodes(nodes, level = 0, output = []) {
      (nodes || []).forEach(node => {
        output.push({ node, level });
        const children = node.children || node.list || node.files || [];
        if (children && children.length) flattenTreeNodes(children, level + 1, output);
      });
      return output;
    }

    function nodeDisplayName(node) {
      return node.name || node.file_name || node.filename || node.path || "未命名";
    }

    function nodeDisplaySize(node) {
      return node.size || node.file_size || node.fsize || "";
    }

    function renderTreePanel(item, panel, mode = "search") {
      if (!panel) return;
      ensureTreeState(item);
      panel.innerHTML = "";
      panel.style.display = item.treeExpanded || item.treeLoading ? "block" : "none";
      if (!item.treeExpanded && !item.treeLoading) return;

      const cls = mode === "resource" ? "resource" : "search";
      const head = document.createElement("div");
      head.className = cls + "-tree-head";
      const total = item.treeData?.total || item.treeData?.count || "";
      head.innerHTML = '<span>资源目录结构</span><em>' + (total ? "共 " + total + " 项" : "") + '</em>';
      panel.appendChild(head);

      if (item.treeLoading) {
        const tip = document.createElement("div");
        tip.className = "tree-tip";
        tip.textContent = "目录加载中...";
        panel.appendChild(tip);
        return;
      }
      if (item.treeError) {
        const tip = document.createElement("div");
        tip.className = "tree-tip error";
        tip.textContent = item.treeError;
        panel.appendChild(tip);
        return;
      }

      const nodes = item.treeData?.tree || item.treeData?.list || item.treeData?.files || [];
      if (!nodes.length) {
        const tip = document.createElement("div");
        tip.className = "tree-tip empty";
        tip.textContent = "暂无目录信息";
        panel.appendChild(tip);
        return;
      }

      const body = document.createElement("div");
      body.className = cls + "-tree-body";
      const flatNodes = flattenTreeNodes(nodes);
      flatNodes.slice(0, 120).forEach(({ node, level }) => {
        const row = document.createElement("div");
        row.className = "tree-row";
        row.style.paddingLeft = 12 + level * 16 + "px";
        const icon = node.type === "folder" || node.isdir || node.is_dir ? "□" : "•";
        row.innerHTML = '<span>' + icon + '</span><span class="tree-row-name">' + escapeHtml(nodeDisplayName(node)) + '</span><span class="tree-row-size">' + escapeHtml(nodeDisplaySize(node)) + '</span>';
        body.appendChild(row);
      });
      panel.appendChild(body);
      if (item.treeData?.truncated || flatNodes.length > 120) {
        const tip = document.createElement("div");
        tip.className = "tree-tip more";
        tip.textContent = "目录内容较多，当前展示前 120 项。";
        panel.appendChild(tip);
      }
    }

    function highlightKeywordText(text, keyword) {
      const safeText = escapeHtml(text || "");
      const term = String(keyword || "").trim();
      if (!term) return safeText;
      const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      return safeText.replace(new RegExp(escapedTerm, "gi"), match => '<span class="search-highlight">' + match + '</span>');
    }

    function createSearchResultCard(item) {
      item = normalizeSearchItem(item);
      const isPanResource = Boolean(item.url || item.title);
      const card = document.createElement("article");
      card.className = "search-resource-card";

      const title = document.createElement("button");
      title.className = "search-resource-title";
      const localBadge = item.is_local ? '<span class="search-local-badge">本地</span>' : '';
      title.innerHTML = '<span class="search-resource-title-text">' + localBadge + highlightKeywordText(item.title || item.vod_name || "未命名资源", state.searchKeyword) + '</span>';
      title.addEventListener("click", () => {
        if (isPanResource && item.url) {
          openResourceModal(item);
        } else {
          loadDetail(item.vod_id);
        }
      });

      const metaText = isPanResource
        ? (item.desc || (item.is_local ? "" : item.source) || "")
        : [item.type_name || item.vod_class || "未知分类", item.vod_remarks || "", item.vod_play_from || ""].filter(Boolean).join(" · ");
      const meta = document.createElement("div");
      meta.className = "search-resource-meta";
      meta.textContent = metaText;

      const footer = document.createElement("div");
      footer.className = "search-resource-footer";

      const panMeta = document.createElement("div");
      panMeta.className = "search-pan-meta";
      if (isPanResource) {
        const icon = document.createElement("img");
        icon.className = "search-pan-icon";
        icon.src = getPanIcon(item);
        icon.alt = getPanName(item);
        panMeta.append(icon);
      } else {
        panMeta.textContent = item.vod_year || item.vod_area || "影视资源";
      }

      const actions = document.createElement("div");
      actions.className = "search-result-actions";
      const treePanel = document.createElement("div");
      treePanel.className = "search-tree-panel";
      treePanel.style.display = "none";

      const treeBtn = document.createElement("button");
      treeBtn.className = "search-tree-btn";
      treeBtn.textContent = isPanResource ? "目录" : "详情";
      treeBtn.addEventListener("click", async () => {
        if (isPanResource && item.url) {
          treeBtn.textContent = item.treeExpanded ? "目录" : "收起";
          await toggleItemTree(item, treePanel);
          treeBtn.textContent = item.treeExpanded ? "收起" : "目录";
        } else {
          loadDetail(item.vod_id);
        }
      });

      const getBtn = document.createElement("button");
      getBtn.className = "search-get-btn";
      getBtn.textContent = isPanResource ? "获取资源" : "查看详情";
      getBtn.addEventListener("click", () => {
        if (isPanResource && item.url) {
          openResourceModal(item);
        } else {
          loadDetail(item.vod_id);
        }
      });

      actions.append(treeBtn, getBtn);
      footer.append(panMeta, actions);
      card.append(title);
      if (metaText) card.append(meta);
      card.append(footer, treePanel);
      return card;
    }

    function showResourceModalLoading(item) {
      el.resourceModalTitle.textContent = item.title || "资源获取结果";
      const posterStage = el.resourceModal.querySelector(".resource-poster-stage");
      if (posterStage) posterStage.remove();
      el.resourceModal.classList.remove("has-poster");
      el.resourceModalBody.innerHTML = '<div class="resource-modal-loading">资源获取中...</div>';
      el.resourceModal.classList.add("open");
    }

    function showResourceModalError(message) {
      el.resourceModalBody.innerHTML = '<div class="resource-modal-error">' + (message || "资源获取失败，请稍后重试") + '</div>';
    }

    function renderResourceModal(item) {
      item = normalizeSearchItem(item);
      const showUrl = item.showUrl || item.url || "";
      el.resourceModalTitle.textContent = item.title || "资源获取结果";
      el.resourceModalBody.innerHTML = "";

      const image = item.image || item.cover || item.poster || "";
      let posterStage = el.resourceModal.querySelector(".resource-poster-stage");
      if (posterStage) posterStage.remove();
      el.resourceModal.classList.toggle("has-poster", Boolean(image));
      if (image) {
        posterStage = document.createElement("div");
        posterStage.className = "resource-poster-stage";
        posterStage.innerHTML = '<img class="resource-poster-bg" alt="" loading="lazy"><img class="resource-poster-main" alt="封面" loading="lazy"><div class="resource-poster-fade"></div>';
        const bgImg = posterStage.querySelector(".resource-poster-bg");
        const mainImg = posterStage.querySelector(".resource-poster-main");
        mainImg.onerror = () => {
          if (posterStage && posterStage.parentNode) posterStage.remove();
          el.resourceModal.classList.remove("has-poster");
        };
        bgImg.onerror = () => {
          bgImg.style.display = "none";
        };
        bgImg.src = image;
        mainImg.src = image;
        el.resourceModal.querySelector(".resource-modal-card").insertBefore(posterStage, el.resourceModal.querySelector(".resource-modal-head").nextSibling);
      }

      const titleBlock = document.createElement("div");
      titleBlock.className = "resource-dialog-title-block";
      const title = document.createElement("h2");
      title.className = "resource-dialog-title";
      title.textContent = item.title || "资源获取结果";
      if (title.textContent.length > 14) title.classList.add("is-long");
      titleBlock.appendChild(title);
      el.resourceModalBody.appendChild(titleBlock);

      const card = document.createElement("div");
      card.className = "resource-link-card";

      const main = document.createElement("div");
      main.className = "resource-link-main";

      const icon = document.createElement("img");
      icon.className = "resource-link-icon";
      icon.src = getPanIcon(item);
      icon.alt = getPanName(item);

      const info = document.createElement("div");
      const name = document.createElement("div");
      name.className = "resource-link-name";
      name.textContent = getPanName(item);

      const link = document.createElement("a");
      link.className = "resource-link-url";
      link.href = showUrl;
      link.target = "_blank";
      link.rel = "noopener noreferrer";
      link.textContent = showUrl || "暂无可用链接";

      info.append(name, link);
      main.append(icon, info);

      const actions = document.createElement("div");
      actions.className = "resource-modal-actions";

      const copyBtn = document.createElement("button");
      copyBtn.className = "resource-modal-copy";
      copyBtn.textContent = "复制链接";
      copyBtn.addEventListener("click", () => copyText(showUrl));

      const openBtn = document.createElement("a");
      openBtn.className = "resource-modal-open";
      openBtn.href = showUrl;
      openBtn.target = "_blank";
      openBtn.rel = "noopener noreferrer";
      openBtn.textContent = "打开链接";

      actions.append(copyBtn, openBtn);
      card.append(main, actions);
      el.resourceModalBody.appendChild(card);

      if (supportsPanTree(item)) {
        const treeBtn = document.createElement("button");
        treeBtn.className = "resource-dialog-tree-toggle";
        treeBtn.textContent = item.treeExpanded ? "收起目录结构" : "查看目录结构";
        const treePanel = document.createElement("div");
        treePanel.className = "resource-tree-panel";
        treePanel.style.display = "none";
        treeBtn.addEventListener("click", async () => {
          const wasExpanded = Boolean(item.treeExpanded);
          treeBtn.disabled = true;
          treeBtn.classList.add("is-loading");
          treeBtn.textContent = wasExpanded ? "收起中..." : "目录加载中...";
          await toggleItemTree(item, treePanel);
          treeBtn.disabled = false;
          treeBtn.classList.remove("is-loading");
          treeBtn.textContent = item.treeExpanded ? "收起目录结构" : "查看目录结构";
          if (!wasExpanded && item.treeExpanded) {
            window.setTimeout(() => {
              treePanel.scrollIntoView({ block: "nearest", behavior: "smooth" });
            }, 60);
          }
        });
        el.resourceModalBody.append(treeBtn, treePanel);
        renderTreePanel(item, treePanel, "resource");
      }

      const notice = document.createElement("section");
      notice.className = "resource-notice-card";
      notice.innerHTML = '<p>本站链接由程序自动收集自公开网盘，不存储、不传播任何文件，跳转链接指向网盘官网。</p><p>文件内容请自行辨别，如发现违规请向网盘平台举报。本站仅供学习交流，无任何收费行为。</p>';
      el.resourceModalBody.appendChild(notice);
    }

    async function openResourceModal(item) {
      item = normalizeSearchItem(item);
      if (!item || !item.url) return;
      showResourceModalLoading(item);

      if (item.resourceFetched && item.showUrl) {
        renderResourceModal(item);
        return;
      }

      try {
        if (isDirectLinkMode() && item.url && /^https?:\/\//i.test(item.url)) {
          item.treeSourceUrl = item.url;
          const displayBody = new URLSearchParams();
          displayBody.set("url", item.url);
          displayBody.set("is_type", item.is_type ?? -1);
          const displayResponse = await fetch("/api/other/get_display_url", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: displayBody
          });
          const displayResult = await displayResponse.json();
          if (displayResult.code === 200 && displayResult.data) {
            item.showUrl = displayResult.data.showUrl || item.url;
            if (displayResult.data.is_type !== undefined) item.is_type = displayResult.data.is_type;
          } else {
            item.showUrl = item.url;
          }
          item.resourceFetched = true;
          renderResourceModal(item);
          return;
        }

        const body = new URLSearchParams();
        const transferSourceUrl = item.transferSourceUrl || item.treeSourceUrl || item.originalUrl || item.url;
        item.transferSourceUrl = transferSourceUrl;
        item.originalUrl = item.originalUrl || transferSourceUrl;
        item.treeSourceUrl = transferSourceUrl;
        body.set("url", encodeURIComponent(transferSourceUrl));
        body.set("title", item.title || "");
        if (item.stoken) body.set("stoken", item.stoken);

        const response = await fetch("/api/other/save_url", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
          body
        });
        const result = await response.json();
        if (result.code !== 200 || !result.data || !result.data.url) {
          showResourceModalError(result.msg || result.message || "获取资源失败");
          return;
        }

        item.resourceFetched = true;
        item.resourceUrl = result.data.url;
        item.showUrl = result.data.showUrl || result.data.url;
        if (result.data._source_url) {
          item.originalUrl = result.data._source_url;
          item.treeSourceUrl = result.data._source_url;
        }
        if (result.data.tree_key) {
          item.tree_key = result.data.tree_key;
          item.treeKey = result.data.tree_key;
          item.treeSourceUrl = "";
        }
        if (result.data.is_type !== undefined) item.is_type = result.data.is_type;
        if (result.data.image && !item.image) item.image = result.data.image;
        if (result.data.images && !item.images) item.images = result.data.images;
        normalizeSearchItem(item);
        renderResourceModal(item);
      } catch (error) {
        showResourceModalError(error.message || "网络错误，请稍后重试");
      }
    }


    return {
      getPanName,
      getPanIcon,
      normalizeSearchItem,
      createSearchResultCard,
      openResourceModal,
      renderTreePanel
    };
  }

  window.createMofaPanResults = createMofaPanResults;
})(window);
