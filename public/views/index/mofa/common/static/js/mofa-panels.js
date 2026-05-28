(function (window) {
  "use strict";

  function createMofaPanels(context) {
    const el = context.el;
    const state = context.state;
    const escapeHtml = context.escapeHtml;
    const readStore = context.readStore;
    const showMineToast = context.showMineToast;
    const openSearchHistoryPage = context.openSearchHistoryPage;
    const showStoredList = context.showStoredList;
    const confirmExternalUrl = context.confirmExternalUrl;
    const clearMainContentForPanel = context.clearMainContentForPanel;
    const openFeedbackDialog = context.openFeedbackDialog;

    function renderDiscoverPanel() {
      clearMainContentForPanel();
      const links = Array.isArray(state.themeSettings.friend_links) ? state.themeSettings.friend_links : [];
      const items = links.length ? links : [{ title: "悦心搜索", url: "https://pan.033030.xyz", icon: "", description: "发现和分享优质资源" }];
      if (state.panelRendered.discover) {
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
      }
      el.discoverPanel.innerHTML =
        '<div class="discover-page">' +
          '<section class="discover-head-card"><div class="discover-head-content">' +
            '<h1 class="discover-title">发现</h1>' +
            '<p class="discover-desc">发现和分享优质资源，网站、软件、工具、博客都可以在这里展示。</p>' +
            '<div class="discover-tags"><span class="discover-tag">优质资源</span><span class="discover-tag orange">友链推荐</span><span class="discover-tag gray">开放收录</span></div>' +
          '</div><div class="discover-head-metrics">' +
            '<div class="discover-metric"><strong>' + items.length + '</strong><span>已收录</span></div>' +
            '<div class="discover-metric"><strong>' + discoverKindCount(items, "tool") + '</strong><span>工具</span></div>' +
            '<div class="discover-metric"><strong>' + discoverKindCount(items, "blog") + '</strong><span>博客</span></div>' +
          '</div></section>' +
          '<section class="discover-section">' +
            '<div class="discover-section-header"><h2 class="discover-section-title">资源列表</h2><span class="discover-section-more">共 ' + items.length + ' 个</span></div>' +
            '<div class="resource-list"></div>' +
            '<button class="submit-card" type="button" data-discover-submit><div class="submit-icon"><img src="/views/index/mofa/common/static/mac/submit.svg" alt=""></div><div><div class="submit-title">提交你的资源</div><div class="submit-desc">支持提交网站、软件、工具、博客等，只需要图标、标题和介绍。</div></div></button>' +
          '</section>' +
          '<div class="discover-footer-tips">建议优先收录稳定、干净、实用的资源，避免低质量或失效链接。</div>' +
        '</div>';
      const grid = el.discoverPanel.querySelector(".resource-list");
      items.forEach((link, index) => {
        const kind = discoverResourceKind(link, index);
        const btn = document.createElement("button");
        btn.className = "resource-card";
        btn.type = "button";
        const iconHtml = link.icon
          ? '<img src="' + escapeHtml(link.icon) + '" alt="">'
          : escapeHtml(discoverResourceInitial(link, kind));
        const tags = discoverResourceTags(link, kind).map(tag => '<span class="meta-tag">' + escapeHtml(tag) + '</span>').join("");
        btn.innerHTML =
          '<div class="resource-logo ' + kind.className + '">' + iconHtml + '</div>' +
          '<div class="resource-main"><div class="resource-top"><div class="resource-title">' + escapeHtml(link.title || "未命名") + '</div><span class="resource-badge ' + kind.className + '">' + escapeHtml(kind.label) + '</span></div>' +
          '<div class="resource-desc">' + escapeHtml(link.description || link.url || "发现和分享优质资源") + '</div>' +
          '<div class="resource-meta">' + tags + '</div></div>' +
          '<div class="resource-arrow">' + mineIcon("chevron") + '</div>';
        btn.addEventListener("click", () => confirmExternalUrl(link.url || ""));
        grid.appendChild(btn);
      });
      const submitBtn = el.discoverPanel.querySelector("[data-discover-submit]");
      if (submitBtn) {
        submitBtn.addEventListener("click", () => showMineToast("提交资源入口待配置"));
      }
      state.panelRendered.discover = true;
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function discoverResourceKind(link, index) {
      const text = [link.title, link.description, link.url].filter(Boolean).join(" ").toLowerCase();
      if (text.includes("blog") || text.includes("博客") || text.includes("文章")) return { label: "博客", className: "blog", tags: ["博客", "文章", "友链"] };
      if (text.includes("tool") || text.includes("工具") || text.includes("json") || text.includes("api")) return { label: "工具", className: "tool", tags: ["工具", "效率", "友链"] };
      if (text.includes("app") || text.includes("软件") || text.includes("下载")) return { label: "软件", className: "software", tags: ["软件", "应用", "友链"] };
      const fallback = [
        { label: "网站", className: "", tags: ["网站", "资源导航", "友链"] },
        { label: "软件", className: "software", tags: ["软件", "效率", "工具"] },
        { label: "工具", className: "tool", tags: ["工具", "实用", "在线"] },
        { label: "博客", className: "blog", tags: ["博客", "文章", "分享"] }
      ];
      return fallback[index % fallback.length];
    }

    function discoverResourceInitial(link, kind) {
      if (kind.label === "工具" && (link.title || "").toLowerCase().includes("json")) return "{ }";
      return (link.title || kind.label || "资源").slice(0, 1);
    }

    function discoverResourceTags(link, kind) {
      const tags = Array.isArray(link.tags) ? link.tags : [];
      const normalized = tags.map(tag => String(tag || "").trim()).filter(Boolean);
      return (normalized.length ? normalized : kind.tags).slice(0, 3);
    }

    function discoverKindCount(items, className) {
      return items.filter((item, index) => discoverResourceKind(item, index).className === className).length;
    }

    function renderMinePanel() {
      clearMainContentForPanel();
      const searchCount = readStore("cinema:searchHistory").length;
      const favoriteCount = readStore("cinema:favorites").length;
      if (!state.panelRendered.mine) {
        el.minePanel.innerHTML = renderMinePanelHtml(searchCount, favoriteCount);
        bindMinePanelEvents();
        state.panelRendered.mine = true;
      } else {
        const searchNode = el.minePanel.querySelector("#mineSearchCount");
        const favoriteNode = el.minePanel.querySelector("#mineFavoriteCount");
        if (searchNode) searchNode.innerHTML = searchCount + "<span>条</span>";
        if (favoriteNode) favoriteNode.innerHTML = favoriteCount + "<span>条</span>";
      }
      window.scrollTo({ top: 0, behavior: "smooth" });
      if (state.mineInfoLoaded && state.mineInfo) {
        const ipNode = el.minePanel.querySelector("#mineIp");
        const locationNode = el.minePanel.querySelector("#mineLocation");
        const descNode = el.minePanel.querySelector("#mineProfileDesc");
        if (ipNode) ipNode.textContent = state.mineInfo.ip || "未知";
        if (locationNode) locationNode.innerHTML = '<span class="status-dot success"></span>' + escapeHtml(state.mineInfo.location || "已获取 IP");
        if (descNode) descNode.textContent = state.mineInfo.ip ? "欢迎回来，已为你同步本地访问状态" : "当前访客信息仅本地展示";
      } else {
        loadMineIpInfo();
      }
    }

    function getBrowserInfo() {
      const ua = navigator.userAgent;
      if (ua.includes("Edg/")) return "Edge " + ((ua.match(/Edg\/([\d.]+)/) || [])[1] || "").split(".")[0];
      if (ua.includes("Chrome/") && !ua.includes("Edg/")) return "Chrome " + ((ua.match(/Chrome\/([\d.]+)/) || [])[1] || "").split(".")[0];
      if (ua.includes("Safari/") && ua.includes("Version/")) return "Safari " + ((ua.match(/Version\/([\d.]+)/) || [])[1] || "").split(".")[0];
      if (ua.includes("Firefox/")) return "Firefox " + ((ua.match(/Firefox\/([\d.]+)/) || [])[1] || "").split(".")[0];
      return "未知浏览器";
    }

    function getSystemInfo() {
      const ua = navigator.userAgent;
      if (/iPhone|iPad|iPod/i.test(ua)) return "iOS";
      if (/Android/i.test(ua)) return "Android";
      if (/Windows/i.test(ua)) return "Windows";
      if (/Mac OS X/i.test(ua)) return "macOS";
      if (/Linux/i.test(ua)) return "Linux";
      return "未知系统";
    }

    function mineNetworkText() {
      return navigator.onLine ? "网络正常" : "离线模式";
    }

    function mineProfileDesc() {
      return navigator.onLine ? "欢迎回来，已为你同步本地访问状态" : "当前处于离线状态，部分信息可能不可用";
    }

    function mineIcon(name) {
      const icons = {
        user: '<path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle>',
        search: '<circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path>',
        star: '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9L12 3z"></path>',
        message: '<path d="M21 12a8 8 0 0 1-8 8H7l-4 3v-7a8 8 0 1 1 18-4z"></path><path d="M8 11h8"></path><path d="M8 15h5"></path>',
        ip: '<rect x="3" y="4" width="18" height="14" rx="3"></rect><path d="M8 20h8"></path><path d="M12 18v2"></path><path d="M8 9h.01"></path><path d="M12 9h.01"></path><path d="M16 9h.01"></path>',
        location: '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0z"></path><circle cx="12" cy="10" r="2.5"></circle>',
        browser: '<circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3a14 14 0 0 1 0 18"></path><path d="M12 3a14 14 0 0 0 0 18"></path>',
        system: '<rect x="4" y="5" width="16" height="12" rx="2"></rect><path d="M8 21h8"></path><path d="M12 17v4"></path>',
        trash: '<path d="M3 6h18"></path><path d="M8 6V4h8v2"></path><path d="M19 6l-1 15H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>',
        info: '<circle cx="12" cy="12" r="9"></circle><path d="M12 11v6"></path><path d="M12 7h.01"></path>',
        plus: '<path d="M12 5v14"></path><path d="M5 12h14"></path>',
        chevron: '<path d="m9 6 6 6-6 6"></path>'
      };
      return '<svg class="mine-icon" viewBox="0 0 24 24" aria-hidden="true">' + (icons[name] || icons.info) + '</svg>';
    }

    function renderMinePanelHtml(searchCount, favoriteCount) {
      return '<div class="mine-page">' +
        '<section class="profile-card"><div class="profile-main"><div class="avatar">' + mineIcon("user") + '</div><div class="profile-info"><div class="profile-title">本地访客</div><div class="profile-desc" id="mineProfileDesc">' + escapeHtml(mineProfileDesc()) + '</div></div></div><div class="tag-row"><span class="tag">本机访问</span><span class="tag gray">未登录</span><span class="tag orange" id="mineNetworkTag">' + escapeHtml(mineNetworkText()) + '</span></div></section>' +
        '<section class="stats-grid"><button class="stat-card blue" type="button" data-mine-action="history"><div class="stat-icon">' + mineIcon("search") + '</div><div class="stat-label">搜索历史</div><div class="stat-value" id="mineSearchCount">' + searchCount + '<span>条</span></div></button><button class="stat-card orange" type="button" data-mine-action="favorites"><div class="stat-icon">' + mineIcon("star") + '</div><div class="stat-label">本地收藏</div><div class="stat-value" id="mineFavoriteCount">' + favoriteCount + '<span>条</span></div></button></section>' +
        '<section class="section"><div class="section-header"><h2 class="section-title">设备信息</h2><span class="section-subtitle">仅本地展示</span></div><div class="info-card">' +
        '<div class="info-item"><div class="info-left"><div class="info-icon">' + mineIcon("ip") + '</div><span>IP 地址</span></div><div class="info-value" id="mineIp">未知</div></div>' +
        '<div class="info-item"><div class="info-left"><div class="info-icon">' + mineIcon("location") + '</div><span>位置</span></div><div class="info-value" id="mineLocation"><span class="status-dot error"></span>读取失败</div></div>' +
        '<div class="info-item" data-mine-action="ua"><div class="info-left"><div class="info-icon">' + mineIcon("browser") + '</div><span>浏览器</span></div><div class="info-value">' + escapeHtml(getBrowserInfo()) + '</div></div>' +
        '<div class="info-item"><div class="info-left"><div class="info-icon">' + mineIcon("system") + '</div><span>系统</span></div><div class="info-value">' + escapeHtml(getSystemInfo()) + '</div></div>' +
        '</div></section>' +
        '<section class="section"><div class="section-header"><h2 class="section-title">常用功能</h2><span class="section-subtitle">管理本地数据</span></div><div class="menu-card">' +
        '<button class="menu-item" type="button" data-mine-action="history"><div class="menu-icon blue">' + mineIcon("search") + '</div><div class="menu-content"><div class="menu-title">搜索历史</div><div class="menu-desc">查看最近搜索记录</div></div><div class="menu-arrow">' + mineIcon("chevron") + '</div></button>' +
        '<button class="menu-item" type="button" data-mine-action="favorites"><div class="menu-icon green">' + mineIcon("star") + '</div><div class="menu-content"><div class="menu-title">本地收藏</div><div class="menu-desc">管理你收藏的内容</div></div><div class="menu-arrow">' + mineIcon("chevron") + '</div></button>' +
        (el.mofaFeedbackOverlay ? '<button class="menu-item" type="button" data-mine-action="feedback"><div class="menu-icon orange">' + mineIcon("message") + '</div><div class="menu-content"><div class="menu-title">提交反馈</div><div class="menu-desc">提交想看的资源或使用问题</div></div><div class="menu-arrow">' + mineIcon("chevron") + '</div></button>' : '') +
        '<button class="menu-item" type="button" data-mine-action="clear"><div class="menu-icon purple">' + mineIcon("trash") + '</div><div class="menu-content"><div class="menu-title">清理缓存</div><div class="menu-desc">释放本地临时存储空间</div></div><div class="menu-arrow">' + mineIcon("chevron") + '</div></button>' +
        '<button class="menu-item" type="button" data-mine-action="about"><div class="menu-icon gray">' + mineIcon("info") + '</div><div class="menu-content"><div class="menu-title">关于应用</div><div class="menu-desc">查看版本信息与使用说明</div></div><div class="menu-arrow">' + mineIcon("chevron") + '</div></button>' +
        '</div></section><div class="footer-tips">当前信息仅用于本地展示，不会主动上传你的隐私数据。</div></div>';
    }

    function bindMinePanelEvents() {
      el.minePanel.querySelectorAll("[data-mine-action]").forEach(node => {
        node.addEventListener("click", () => {
          const action = node.dataset.mineAction;
          if (action === "history") {
            openSearchHistoryPage();
            return;
          }
          if (action === "favorites") {
            showMineToast("待开发");
            return;
          }
          if (action === "feedback") {
            if (openFeedbackDialog) openFeedbackDialog();
            return;
          }
          if (action === "clear") {
            localStorage.removeItem("cinema:searchHistory");
            showMineToast("缓存已清理");
            state.panelRendered.mine = false;
            renderMinePanel();
            return;
          }
          if (action === "ua") {
            showMineToast(navigator.userAgent);
            return;
          }
          if (action === "about") {
            showMineToast("当前版本 v1.0.0");
          }
        });
      });
      window.addEventListener("online", updateMineNetworkStatus);
      window.addEventListener("offline", updateMineNetworkStatus);
    }

    function updateMineNetworkStatus() {
      const networkTag = el.minePanel.querySelector("#mineNetworkTag");
      const profileDesc = el.minePanel.querySelector("#mineProfileDesc");
      if (networkTag) networkTag.textContent = mineNetworkText();
      if (profileDesc) profileDesc.textContent = mineProfileDesc();
    }

    async function loadMineIpInfo() {
      const ipNode = el.minePanel.querySelector("#mineIp");
      const locationNode = el.minePanel.querySelector("#mineLocation");
      const descNode = el.minePanel.querySelector("#mineProfileDesc");
      try {
        const ipRes = await fetch("https://v.api.aa1.cn/api/myip/index.php?aa1=json", { cache: "no-store" });
        const ipData = await ipRes.json();
        const ip = ipData.ip || ipData.IP || ipData.myip || ipData.data?.ip || ipData.data?.myip || "";
        if (ipNode) ipNode.textContent = ip || "未知";
        if (!ip) throw new Error("IP为空");
        const locationRes = await fetch("https://wzapi.com/api/dingwei?ip=" + encodeURIComponent(ip), { cache: "no-store" });
        const locationData = await locationRes.json();
        const locationSource = locationData.data || locationData;
        const location = locationData.address || locationData.addr || locationSource.address || locationSource.location || [
          locationSource.country,
          locationSource.prov || locationSource.province,
          locationSource.city,
          locationSource.area,
          locationSource.isp
        ].filter(Boolean).join(" ");
        if (locationNode) locationNode.innerHTML = '<span class="status-dot success"></span>' + escapeHtml(location || "已获取 IP");
        if (descNode) descNode.textContent = "欢迎回来，已为你同步本地访问状态";
        state.mineInfo = { ip, location: location || "未知" };
        state.mineInfoLoaded = true;
      } catch (error) {
        if (ipNode && ipNode.textContent === "读取中") ipNode.textContent = "读取失败";
        if (locationNode) locationNode.innerHTML = '<span class="status-dot error"></span>读取失败';
      }
    }


    return {
      renderDiscoverPanel,
      renderMinePanel,
      updateMineNetworkStatus,
      loadMineIpInfo
    };
  }

  window.createMofaPanels = createMofaPanels;
})(window);
