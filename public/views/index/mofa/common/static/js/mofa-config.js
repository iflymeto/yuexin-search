(function (window) {
  "use strict";

  function createMofaConfig(context) {
    const el = context.el;
    const state = context.state;
    const escapeHtml = context.escapeHtml;
    const applyProxy = context.applyProxy;
    const buildUrl = context.buildUrl;
    const handleBottomTab = context.handleBottomTab;

    const fallbackConfig = {
      site: {
        name: "Twilight Cinema",
        tagline: "聚合影视浏览与接口学习示例",
        heroTitle: "发现最新影视资源",
        heroText: "用一个本地 HTML 学会分类、搜索、分页、详情、播放地址解析与代理请求。"
      },
      api: {
        baseUrl: "http://cj.10010888.xyz/api.php/provide/vod/",
        proxyPrefix: "https://proxyd.picpi.top/",
        proxyMode: "raw",
        parserPrefix: "https://jx.xmflv.com/?url="
      },
      sources: [
        { value: "", label: "全部来源" },
        { value: "qq", label: "腾讯视频" },
        { value: "qiyi", label: "爱奇艺" },
        { value: "youku", label: "优酷" },
        { value: "mgtv", label: "芒果 TV" },
        { value: "bilibili", label: "B 站" }
      ],
      defaults: {
        keyword: "",
        page: 1,
        autoLoad: true
      },
      apis: [],
      sections: [
        { title: "最新上线", type: "", source: "", page: 1 },
        { title: "电影精选", type: "1", source: "", page: 1 },
        { title: "短剧热更", type: "7", source: "", page: 1 }
      ],
      ranking: {
        url: "/api/tool/ranking",
        channels: ["短剧", "电影", "电视剧", "动漫", "综艺"],
        defaultChannel: "短剧",
        mobileMode: false,
        initialCount: 6,
        pageSize: 6
      },
      images: {
        proxyVodImages: true,
        proxyRankingImages: false,
        proxyBannerImages: false,
        lazyRootMargin: "160px 0px"
      },
      banners: {
        interval: 4500,
        items: [
          {
            title: "御赐小仵作",
            subtitle: "王子奇苏晓彤破奇案洗冤情",
            keyword: "御赐小仵作",
            image: "https://tv.puui.qpic.cn/tv/0/mz_tv_image_frontend_442f1e-8_195920731_1768384693513190_pic_1920x800/0?imageView2/2/w/1600"
          },
          {
            title: "剑来",
            subtitle: "少年仗剑，一路向前",
            keyword: "剑来",
            image: "https://tv.puui.qpic.cn/tv/0/mz_tv_image_frontend_442f1e-8_1212520037_1768650960239121_pic_1920x800/0?imageView2/2/w/1600"
          }
        ]
      }
    };

    let lazyImageObserver = null;

    async function loadConfig() {
      try {
        const res = await fetch("/views/index/mofa/common/static/mac/config.json", { cache: "no-store" });
        if (!res.ok) throw new Error("/views/index/mofa/common/static/mac/config.json HTTP " + res.status);
        return await res.json();
      } catch (error) {
        console.warn("使用内置配置。", error);
        return fallbackConfig;
      }
    }

    function themeSettingsFromBackend() {
      const settings = window.MOFA_THEME_SETTINGS || {};
      return {
        download_url: settings.download_url || "",
        notice_text: settings.notice_text || "",
        banners: Array.isArray(settings.banners) ? settings.banners : [],
        cards: Array.isArray(settings.cards) ? settings.cards : [],
        bottom_tabs: Array.isArray(settings.bottom_tabs) ? settings.bottom_tabs : [],
        friend_links: Array.isArray(settings.friend_links) ? settings.friend_links : []
      };
    }

    function normalizeThemeBanner(item) {
      if (!item || !item.image) return null;
      return {
        title: item.title || item.keyword || "推荐",
        subtitle: item.subtitle || "",
        keyword: item.keyword || item.title || "",
        image: item.image
      };
    }

    function applyConfig(config) {
      state.config = config;
      state.themeSettings = themeSettingsFromBackend();
      const settingBanners = Array.isArray(state.themeSettings.banners)
        ? state.themeSettings.banners.map(normalizeThemeBanner).filter(Boolean)
        : [];
      if (settingBanners.length) {
        state.config.banners = {
          ...(state.config.banners || {}),
          items: settingBanners
        };
      }
      const backendSiteName = (el.topSiteName?.textContent || el.siteName?.textContent || "").trim();
      const backendTagline = (el.siteName?.nextElementSibling?.textContent || "").trim();
      const siteName = backendSiteName || config.site?.name || fallbackConfig.site.name;
      const tagline = backendTagline || config.site?.tagline || fallbackConfig.site.tagline;
      document.title = siteName;
      el.siteName.textContent = siteName;
      el.topSiteName.textContent = siteName;
      el.siteName.nextElementSibling.textContent = tagline;
      el.heroTitle.textContent = config.site?.heroTitle || fallbackConfig.site.heroTitle;
      el.heroText.textContent = config.site?.heroText || fallbackConfig.site.heroText;
      el.apiBase.value = config.api?.baseUrl || fallbackConfig.api.baseUrl;
      el.proxyPrefix.value = config.api?.proxyPrefix || "";
      el.proxyMode.value = config.api?.proxyMode || "raw";
      el.parserPrefix.value = config.api?.parserPrefix || "";
      el.keyword.value = config.defaults?.keyword || "";
      el.pageInput.value = config.defaults?.page || 1;
      applyThemeCardSettings();
      renderBottomTabs();
      renderSources(config.sources || fallbackConfig.sources);
      renderApis(config.apis?.length ? config.apis : [{ name: "默认资源", ...config.api }]);
    }

    function applyThemeCardSettings() {
      const notice = (state.themeSettings.notice_text || "").trim();
      if (notice) el.continueText.textContent = notice;
      const downloadUrl = (state.themeSettings.download_url || "").trim();
      const cards = Array.isArray(state.themeSettings.cards) ? state.themeSettings.cards : [];
      const targets = [el.featureThemeBtn, el.featureHistoryBtn, el.featureFavoritesBtn];
      targets.forEach((target, index) => {
        const card = cards[index] || {};
        if (!target) return;
        if (card.title) target.querySelector("strong").textContent = card.title;
        if (card.subtitle) target.querySelector("span").textContent = card.subtitle;
        if (card.image) {
          const img = target.querySelector("i img");
          if (img) img.src = card.image;
        }
        target.dataset.action = card.action || target.dataset.action || "";
        target.dataset.url = card.url || "";
      });
      if (!el.featureFavoritesBtn.dataset.url) el.featureFavoritesBtn.dataset.url = downloadUrl;
      if (downloadUrl && !cards[2]) {
        el.featureFavoritesBtn.querySelector("strong").textContent = "下载APP";
        el.featureFavoritesBtn.querySelector("span").textContent = "点击立即下载";
      }
      if (el.desktopDownloadBtn) {
        el.desktopDownloadBtn.dataset.action = el.featureFavoritesBtn.dataset.action || "download";
        el.desktopDownloadBtn.dataset.url = el.featureFavoritesBtn.dataset.url || downloadUrl;
      }
      if (el.desktopSearchHistoryBtn) {
        el.desktopSearchHistoryBtn.dataset.action = "history";
      }
    }

    function defaultBottomTabs() {
      return [
        { title: "首页", type: "panel", target: "home", icon: "/views/index/mofa/common/static/mac/home-1.svg", active_icon: "/views/index/mofa/common/static/mac/home-2.svg" },
        { title: "发现", type: "panel", target: "discover", icon: "/views/index/mofa/common/static/mac/fx-1.svg", active_icon: "/views/index/mofa/common/static/mac/fx-2.svg" },
        { title: "我的", type: "panel", target: "mine", icon: "/views/index/mofa/common/static/mac/user-1.svg", active_icon: "/views/index/mofa/common/static/mac/user-2.svg" }
      ];
    }

    function renderBottomTabs() {
      state.bottomTabs = Array.isArray(state.themeSettings.bottom_tabs) && state.themeSettings.bottom_tabs.length
        ? state.themeSettings.bottom_tabs
        : defaultBottomTabs();
      el.bottomTabs.innerHTML = "";
      el.bottomTabs.style.setProperty("--tab-count", String(Math.max(1, state.bottomTabs.length)));
      state.bottomTabs.forEach((tab, index) => {
        const btn = document.createElement("button");
        btn.className = "tab";
        btn.type = "button";
        btn.dataset.index = String(index);
        btn.dataset.type = tab.type || "panel";
        btn.dataset.target = tab.target || "";
        const icon = tab.icon ? '<img class="tab-icon tab-icon-off" src="' + escapeHtml(tab.icon) + '" alt="">' : "";
        const activeIcon = tab.active_icon ? '<img class="tab-icon tab-icon-on" src="' + escapeHtml(tab.active_icon) + '" alt="">' : icon;
        btn.innerHTML = '<span>' + icon + activeIcon + '</span>' + escapeHtml(tab.title || "导航");
        btn.addEventListener("click", () => handleBottomTab(tab));
        el.bottomTabs.appendChild(btn);
      });
      updateBottomTabsActive();
    }

    function updateBottomTabsActive() {
      Array.from(el.bottomTabs.children).forEach((btn, index) => {
        const tab = state.bottomTabs[index] || {};
        const active = (tab.type || "panel") === "panel" && (tab.target || "home") === state.activePanel;
        btn.classList.toggle("active", active);
      });
    }

    function renderSources(sources) {
      el.source.innerHTML = "";
      sources.forEach(source => {
        const option = document.createElement("option");
        option.value = source.value;
        option.textContent = source.label;
        el.source.appendChild(option);
      });
    }

    function renderApis(apis) {
      el.apiSelect.innerHTML = "";
      apis.forEach((api, index) => {
        const option = document.createElement("option");
        option.value = String(index);
        option.textContent = api.name || ("资源站" + (index + 1));
        el.apiSelect.appendChild(option);
      });
      el.apiSelect.addEventListener("change", () => {
        const api = apis[Number(el.apiSelect.value)] || apis[0];
        el.apiBase.value = api.baseUrl || "";
        el.proxyPrefix.value = api.proxyPrefix || "";
        el.proxyMode.value = api.proxyMode || "raw";
        el.parserPrefix.value = api.parserPrefix || "";
        el.pageInput.value = "1";
        el.requestUrl.value = buildUrl("videolist");
        el.debug.textContent = "已切换资源站。首页继续使用热榜数据；搜索或分页时才会请求采集接口。";
      });
    }

    function proxiedAssetUrl(url) {
      if (!url || !/^https?:\/\//i.test(url)) return url || "";
      return applyProxy(url);
    }

    function imageUrl(url, kind = "vod") {
      if (!url || !/^https?:\/\//i.test(url)) return url || "";
      const imageConfig = state.config?.images || fallbackConfig.images;
      const shouldProxy =
        (kind === "vod" && imageConfig.proxyVodImages) ||
        (kind === "ranking" && imageConfig.proxyRankingImages) ||
        (kind === "banner" && imageConfig.proxyBannerImages);
      return shouldProxy ? applyProxy(url) : url;
    }

    function imageFallbackSvg(label = "暂无封面") {
      const text = String(label || "暂无封面").slice(0, 8);
      return "data:image/svg+xml;charset=utf-8," + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="320" height="450" viewBox="0 0 320 450"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#eef4ff"/><stop offset="1" stop-color="#f8fafc"/></linearGradient></defs><rect width="320" height="450" rx="24" fill="url(#g)"/><circle cx="160" cy="178" r="42" fill="#dbeafe"/><path d="M112 282h96M132 318h56" stroke="#94a3b8" stroke-width="14" stroke-linecap="round"/><text x="160" y="390" text-anchor="middle" font-family="Arial, sans-serif" font-size="24" font-weight="700" fill="#94a3b8">' + escapeHtml(text) + '</text></svg>');
    }

    function getLazyImageObserver() {
      if (lazyImageObserver) return lazyImageObserver;
      const imageConfig = state.config?.images || fallbackConfig.images;
      lazyImageObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (!entry.isIntersecting) return;
          const img = entry.target;
          img.src = img.dataset.src;
          img.removeAttribute("data-src");
          lazyImageObserver.unobserve(img);
        });
      }, { rootMargin: imageConfig.lazyRootMargin || "160px 0px" });
      return lazyImageObserver;
    }

    function setLazyImage(img, rawUrl, kind = "vod", eager = false) {
      const src = imageUrl(rawUrl, kind);
      img.classList.add("lazy-image");
      img.addEventListener("load", () => img.classList.add("loaded"), { once: true });
      if (!src) return;
      if (eager || !("IntersectionObserver" in window)) {
        img.src = src;
        return;
      }
      img.dataset.src = src;
      getLazyImageObserver().observe(img);
    }

    return {
      fallbackConfig,
      loadConfig,
      applyConfig,
      updateBottomTabsActive,
      proxiedAssetUrl,
      imageUrl,
      imageFallbackSvg,
      setLazyImage
    };
  }

  window.createMofaConfig = createMofaConfig;
})(window);
