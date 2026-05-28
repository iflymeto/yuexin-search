(function (window) {
  "use strict";

  function createMofaNavigation(context) {
    const el = context.el;
    const state = context.state;
    const readStore = context.readStore;
    const setText = context.setText;
    const createCard = context.createCard;
    const updateBottomTabsActive = context.updateBottomTabsActive;
    const setBanner = context.setBanner;
    const loadHomeSections = context.loadHomeSections;
    const checkHomeAnnouncement = context.checkHomeAnnouncement;
    const loadRankingChannel = context.loadRankingChannel;
    const renderDiscoverPanel = context.renderDiscoverPanel;
    const renderMinePanel = context.renderMinePanel;
    const openSearchHistoryPage = context.openSearchHistoryPage;
    const searchByKeyword = context.searchByKeyword;
    const openSearchPage = context.openSearchPage;

    function showStoredList(title, key) {
      activateThemePage("home");
      const list = readStore(key);
      el.homeSections.innerHTML = "";
      el.grid.innerHTML = "";
      el.message.style.display = list.length ? "none" : "grid";
      el.message.textContent = list.length ? "" : title + "暂无内容。";
      setText(el.countPill, title + " " + list.length + " 条");
      setText(el.pageInfo, "本地存储");
      setText(el.searchStatus, "");
      list.forEach(item => el.grid.appendChild(createCard(item)));
    }

    function hideThemePanels() {
      activateThemePage("home");
    }

    function activateThemePage(name) {
      el.homePanel.classList.toggle("active", name === "home");
      el.discoverPanel.classList.toggle("active", name === "discover");
      el.minePanel.classList.toggle("active", name === "mine");
      document.body.classList.toggle("is-home-panel", name === "home");
      document.body.classList.toggle("is-tab-subpage", name !== "home");
      updateBottomTabsActive();
    }

    function setActiveNav(name) {
      [
        el.navHomeBtn,
        el.navShortBtn,
        el.navDiscoverBtn,
        el.navHistoryBtn
      ].filter(Boolean).forEach(btn => btn.classList.remove("active"));

      const map = {
        home: [el.navHomeBtn],
        short: [el.navShortBtn],
        discover: [el.navDiscoverBtn],
        history: [el.navHistoryBtn]
      };
      (map[name] || []).filter(Boolean).forEach(btn => btn.classList.add("active"));
      updateBottomTabsActive();
    }

    function toggleCinemaTheme() {
      document.body.classList.toggle("cinema-night");
      const night = document.body.classList.contains("cinema-night");
      el.themeBtn.textContent = night ? "☾" : "☀";
      el.themeBtn.setAttribute("aria-label", night ? "切换白天模式" : "切换夜间模式");
      el.featureThemeBtn.classList.toggle("is-night", night);
      el.featureThemeBtn.querySelector("strong").textContent = night ? "夜间模式" : "白天模式";
      el.featureThemeBtn.querySelector("span").textContent = "点击切换主题";
      updateNavStuck();
    }

    function updateNavStuck() {
      const mobile = window.innerWidth <= 520;
      const threshold = mobile && el.hero ? Math.max(48, el.hero.offsetTop - 16) : 28;
      document.body.classList.toggle("nav-stuck", window.scrollY > threshold);
    }

    function showHome() {
      state.activePanel = "home";
      setActiveNav("home");
      activateThemePage("home");
      setBanner(state.bannerIndex);
      el.keyword.value = "";
      el.topKeyword.value = "";
      el.category.value = "";
      el.source.value = "";
      el.pageInput.value = "1";
      if (!state.homeLoaded && !el.grid.children.length && !el.homeSections.children.length) {
        loadHomeSections();
      }
      checkHomeAnnouncement();
      window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function showShorts() {
      state.activePanel = "home";
      setActiveNav("short");
      activateThemePage("home");
      el.keyword.value = "";
      el.topKeyword.value = "";
      el.category.value = "";
      el.source.value = "";
      el.pageInput.value = "1";
      el.homeSections.innerHTML = "";
      loadRankingChannel("短剧");
    }

    function showDiscover() {
      state.activePanel = "discover";
      setActiveNav("discover");
      renderDiscoverPanel();
    }

    function showMine() {
      state.activePanel = "mine";
      setActiveNav("history");
      renderMinePanel();
    }

    function clearMainContentForPanel() {
      activateThemePage(state.activePanel);
      setText(el.countPill, "");
      setText(el.pageInfo, "");
      setText(el.searchStatus, "");
    }

    function confirmExternalUrl(url) {
      if (!url) return;
      state.pendingExternalUrl = url;
      el.externalConfirmText.textContent = url;
      el.externalConfirm.classList.add("open");
      el.externalConfirm.setAttribute("aria-hidden", "false");
    }

    function closeExternalConfirm() {
      state.pendingExternalUrl = "";
      el.externalConfirm.classList.remove("open");
      el.externalConfirm.setAttribute("aria-hidden", "true");
    }

    function handleBottomTab(tab) {
      const type = tab.type || "panel";
      const target = tab.target || "";
      if (type === "panel") {
        if (target === "home") showHome();
        else if (target === "discover") showDiscover();
        else if (target === "mine") showMine();
        return;
      }
      if (type === "search") {
        searchByKeyword(target);
        return;
      }
      if (type === "path") {
        if (target) window.location.href = target;
        return;
      }
      if (type === "url") {
        confirmExternalUrl(target);
        return;
      }
      if (type === "action") {
        if (target === "theme") toggleCinemaTheme();
        if (target === "history") openSearchHistoryPage();
      }
    }

    function applyInitialPanelFromUrl() {
      const tab = new URLSearchParams(window.location.search).get("tab");
      if (tab === "discover") {
        showDiscover();
      } else if (tab === "mine") {
        showMine();
      } else {
        activateThemePage(state.activePanel || "home");
      }
    }

    function handleFeatureCardClick(button, fallback) {
      const action = button?.dataset?.action || fallback || "";
      const url = button?.dataset?.url || "";
      if (action === "theme") {
        toggleCinemaTheme();
        return;
      }
      if (action === "history") {
        openSearchHistoryPage();
        return;
      }
      if (action === "favorites") {
        showStoredList("我的收藏", "cinema:favorites");
        return;
      }
      if (action === "search") {
        openSearchPage();
        return;
      }
      if ((action === "url" || action === "download") && url) {
        window.open(url, "_blank", "noopener");
        return;
      }
      if (fallback === "theme") toggleCinemaTheme();
      if (fallback === "history") openSearchHistoryPage();
      if (fallback === "download") {
        if (url) {
          window.open(url, "_blank", "noopener");
        } else {
          showStoredList("我的收藏", "cinema:favorites");
        }
      }
    }

    return {
      showStoredList,
      hideThemePanels,
      activateThemePage,
      setActiveNav,
      toggleCinemaTheme,
      updateNavStuck,
      showHome,
      showShorts,
      showDiscover,
      showMine,
      clearMainContentForPanel,
      confirmExternalUrl,
      closeExternalConfirm,
      handleBottomTab,
      applyInitialPanelFromUrl,
      handleFeatureCardClick
    };
  }

  window.createMofaNavigation = createMofaNavigation;
})(window);
