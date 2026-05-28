(function (window) {
  "use strict";

  function createMofaEvents(context) {
    const el = context.el;
    const state = context.state;
    const loadList = context.loadList;
    const loadClasses = context.loadClasses;
    const showHome = context.showHome;
    const showShorts = context.showShorts;
    const showDiscover = context.showDiscover;
    const setActiveNav = context.setActiveNav;
    const showStoredList = context.showStoredList;
    const closeExternalConfirm = context.closeExternalConfirm;
    const closeHomeAnnouncement = context.closeHomeAnnouncement;
    const handleAnnouncementContentClick = context.handleAnnouncementContentClick;
    const openFeedbackDialog = context.openFeedbackDialog;
    const closeFeedbackDialog = context.closeFeedbackDialog;
    const submitFeedback = context.submitFeedback;
    const runSearch = context.runSearch;
    const openSearchPage = context.openSearchPage;
    const fetchPcSuggestions = context.fetchPcSuggestions;
    const hidePcSuggestions = context.hidePcSuggestions;
    const pcSuggestionState = context.pcSuggestionState;
    const selectPcSuggestion = context.selectPcSuggestion;
    const renderPcSuggestions = context.renderPcSuggestions;
    const searchCurrentBanner = context.searchCurrentBanner;
    const toggleCinemaTheme = context.toggleCinemaTheme;
    const handleFeatureCardClick = context.handleFeatureCardClick;
    const closeSearchPage = context.closeSearchPage;
    const hideMobileSuggestions = context.hideMobileSuggestions;
    const commitSearch = context.commitSearch;
    const fetchMobileSuggestions = context.fetchMobileSuggestions;
    const mobileSuggestionState = context.mobileSuggestionState;
    const selectMobileSuggestion = context.selectMobileSuggestion;
    const renderMobileSuggestions = context.renderMobileSuggestions;
    const closeSearchEventSource = context.closeSearchEventSource;
    const renderSearchHome = context.renderSearchHome;
    const showSearchToast = context.showSearchToast;
    const writeStore = context.writeStore;
    const renderSearchHistory = context.renderSearchHistory;
    const loadGuessList = context.loadGuessList;
    const switchNextRankingChannel = context.switchNextRankingChannel;
    const loadMoreContent = context.loadMoreContent;
    const continueLastWatch = context.continueLastWatch;
    const copyText = context.copyText;
    const buildUrl = context.buildUrl;
    const loadHomeSections = context.loadHomeSections;
    const setText = context.setText;
    const closePlayer = context.closePlayer;
    const updateNavStuck = context.updateNavStuck;
    const handleAutoLoadMore = context.handleAutoLoadMore;

    function bindEvents() {
      window.copyToClipboard = text => copyText(text);

      el.loadBtn.addEventListener("click", loadList);
      el.classBtn.addEventListener("click", loadClasses);
      el.homeNavBtn.addEventListener("click", showHome);
      el.navHomeBtn.addEventListener("click", showHome);
      if (el.navShortBtn) el.navShortBtn.addEventListener("click", showShorts);
      el.navDiscoverBtn.addEventListener("click", showDiscover);
      if (el.navHistoryBtn) el.navHistoryBtn.addEventListener("click", () => {
        setActiveNav("history");
        showStoredList("播放历史", "cinema:history");
      });
      el.externalCancelBtn.addEventListener("click", closeExternalConfirm);
      el.externalConfirm.addEventListener("click", event => {
        if (event.target === el.externalConfirm) closeExternalConfirm();
      });
      el.externalOkBtn.addEventListener("click", () => {
        const url = state.pendingExternalUrl;
        closeExternalConfirm();
        if (url) window.location.href = url;
      });
      el.homeAnnouncementOkBtn.addEventListener("click", () => closeHomeAnnouncement("confirm"));
      el.homeAnnouncementLaterBtn.addEventListener("click", () => closeHomeAnnouncement("later"));
      el.homeAnnouncementOverlay.addEventListener("click", event => {
        if (event.target === el.homeAnnouncementOverlay) closeHomeAnnouncement("confirm");
      });
      el.homeAnnouncementContent.addEventListener("click", handleAnnouncementContentClick);
      window.addEventListener("keydown", event => {
        if (event.key === "Escape" && el.homeAnnouncementOverlay.classList.contains("open")) {
          closeHomeAnnouncement("confirm");
        }
      });
      el.topSearchBtn.addEventListener("click", runSearch);
      el.topKeyword.addEventListener("focus", () => {
        if (window.innerWidth <= 720) openSearchPage(el.topKeyword.value.trim());
        else if (el.topKeyword.value.trim()) {
          fetchPcSuggestions();
        }
      });
      el.topKeyword.addEventListener("input", () => {
        const keyword = el.topKeyword.value.trim();
        if (!keyword) {
          hidePcSuggestions();
          return;
        }
        window.clearTimeout(pcSuggestionState.timer);
        pcSuggestionState.timer = window.setTimeout(fetchPcSuggestions, 240);
      });
      el.topKeyword.addEventListener("keydown", event => {
        if (event.key === "Enter") {
          event.preventDefault();
          if (pcSuggestionState.items.length && pcSuggestionState.activeIndex >= 0) {
            selectPcSuggestion(pcSuggestionState.activeIndex);
            return;
          }
          runSearch();
          return;
        }
        if (event.key === "ArrowDown") {
          if (!pcSuggestionState.items.length) return;
          event.preventDefault();
          pcSuggestionState.activeIndex = (pcSuggestionState.activeIndex + 1) % pcSuggestionState.items.length;
          renderPcSuggestions();
        }
        if (event.key === "ArrowUp") {
          if (!pcSuggestionState.items.length) return;
          event.preventDefault();
          pcSuggestionState.activeIndex = (pcSuggestionState.activeIndex - 1 + pcSuggestionState.items.length) % pcSuggestionState.items.length;
          renderPcSuggestions();
        }
        if (event.key === "Escape") {
          hidePcSuggestions();
        }
      });
      el.topKeyword.addEventListener("blur", () => {
        window.setTimeout(hidePcSuggestions, 180);
      });
      if (el.topSuggestions) {
        el.topSuggestions.addEventListener("mousedown", event => event.preventDefault());
      }
      el.heroSearchBtn.addEventListener("click", searchCurrentBanner);
      el.hero.addEventListener("click", event => {
        if (event.target.closest("button")) return;
        if (state.bannerDrag.moved || state.bannerDrag.switched || Date.now() < state.bannerClickSuppressUntil) {
          event.preventDefault();
          return;
        }
        searchCurrentBanner();
      });
      el.heroToolsBtn.addEventListener("click", () => document.body.classList.toggle("show-tools"));
      el.themeBtn.addEventListener("click", toggleCinemaTheme);
      if (el.desktopSearchHistoryBtn) {
        el.desktopSearchHistoryBtn.addEventListener("click", () => handleFeatureCardClick(el.desktopSearchHistoryBtn, "history"));
      }
      if (el.desktopFeedbackBtn) {
        el.desktopFeedbackBtn.addEventListener("click", openFeedbackDialog);
      }
      if (el.desktopDownloadBtn) {
        el.desktopDownloadBtn.addEventListener("click", () => handleFeatureCardClick(el.desktopDownloadBtn, "download"));
      }
      if (el.mofaFeedbackOverlay) {
        el.mofaFeedbackCloseBtn.addEventListener("click", closeFeedbackDialog);
        el.mofaFeedbackOverlay.addEventListener("click", event => {
          if (event.target === el.mofaFeedbackOverlay) closeFeedbackDialog();
        });
        el.mofaFeedbackSubmitBtn.addEventListener("click", submitFeedback);
        el.mofaFeedbackContent.addEventListener("keydown", event => {
          if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
            event.preventDefault();
            submitFeedback();
          }
        });
      }
      el.featureThemeBtn.addEventListener("click", () => handleFeatureCardClick(el.featureThemeBtn, "theme"));
      el.featureFavoritesBtn.addEventListener("click", () => handleFeatureCardClick(el.featureFavoritesBtn, "download"));
      el.featureHistoryBtn.addEventListener("click", () => handleFeatureCardClick(el.featureHistoryBtn, "history"));
      el.searchBackBtn.addEventListener("click", closeSearchPage);
      el.searchSubmitBtn.addEventListener("click", () => {
        hideMobileSuggestions();
        commitSearch(el.searchInput.value);
      });
      el.searchInput.addEventListener("focus", () => {
        if (el.searchInput.value.trim() && el.searchResult.style.display !== "flex") {
          fetchMobileSuggestions();
        }
      });
      el.searchInput.addEventListener("input", () => {
        const keyword = el.searchInput.value.trim();
        if (!keyword) {
          hideMobileSuggestions();
          return;
        }
        window.clearTimeout(mobileSuggestionState.timer);
        mobileSuggestionState.timer = window.setTimeout(fetchMobileSuggestions, 240);
      });
      el.searchInput.addEventListener("keydown", event => {
        if (event.key === "Enter") {
          event.preventDefault();
          if (mobileSuggestionState.items.length && mobileSuggestionState.activeIndex >= 0) {
            selectMobileSuggestion(mobileSuggestionState.activeIndex);
            return;
          }
          hideMobileSuggestions();
          commitSearch(el.searchInput.value);
          return;
        }
        if (event.key === "ArrowDown") {
          if (!mobileSuggestionState.items.length) return;
          event.preventDefault();
          mobileSuggestionState.activeIndex = (mobileSuggestionState.activeIndex + 1) % mobileSuggestionState.items.length;
          renderMobileSuggestions();
        }
        if (event.key === "ArrowUp") {
          if (!mobileSuggestionState.items.length) return;
          event.preventDefault();
          mobileSuggestionState.activeIndex = (mobileSuggestionState.activeIndex - 1 + mobileSuggestionState.items.length) % mobileSuggestionState.items.length;
          renderMobileSuggestions();
        }
        if (event.key === "Escape") {
          hideMobileSuggestions();
        }
      });
      el.searchInput.addEventListener("blur", () => {
        window.setTimeout(hideMobileSuggestions, 180);
      });
      if (el.mobileSuggestions) {
        el.mobileSuggestions.addEventListener("mousedown", event => event.preventDefault());
      }
      if (el.resultBackBtn) {
        el.resultBackBtn.addEventListener("click", () => {
          closeSearchEventSource();
          el.searchResult.style.display = "none";
          el.searchBody.style.display = "block";
          el.searchInput.value = "";
          hideMobileSuggestions();
          renderSearchHome();
        });
      }
      el.clearSearchHistoryBtn.addEventListener("click", () => {
        if (!state.clearHistoryArmed) {
          state.clearHistoryArmed = true;
          showSearchToast("再点一次清空搜索历史");
          window.clearTimeout(state.clearHistoryTimer);
          state.clearHistoryTimer = window.setTimeout(() => {
            state.clearHistoryArmed = false;
          }, 2200);
          return;
        }
        state.clearHistoryArmed = false;
        window.clearTimeout(state.clearHistoryTimer);
        writeStore("cinema:searchHistory", []);
        renderSearchHistory();
        showSearchToast("搜索历史已清空");
      });
      if (el.guessCats) {
        el.guessCats.addEventListener("click", event => {
          const btn = event.target.closest(".guess-cat");
          if (!btn) return;
          el.guessCats.querySelectorAll(".guess-cat").forEach(item => item.classList.remove("active"));
          btn.classList.add("active");
          loadGuessList(btn.dataset.t || "");
        });
      }
      if (el.guessRefreshBtn) {
        el.guessRefreshBtn.addEventListener("click", () => {
          const active = el.guessCats?.querySelector(".guess-cat.active")?.dataset.t || "";
          loadGuessList(active);
        });
      }
      el.refreshBtn.addEventListener("click", async () => {
        if (el.refreshBtn.classList.contains("spin")) return;
        el.refreshBtn.classList.remove("bump");
        void el.refreshBtn.offsetWidth;
        el.refreshBtn.classList.add("bump");
        el.refreshBtn.classList.add("spin");
        el.refreshBtn.disabled = true;
        try {
          await switchNextRankingChannel();
        } finally {
          el.refreshBtn.classList.remove("spin");
          window.setTimeout(() => el.refreshBtn.classList.remove("bump"), 320);
          el.refreshBtn.disabled = false;
        }
      });
      if (el.loadMoreBtn) {
        el.loadMoreBtn.addEventListener("click", loadMoreContent);
      }
      el.continueWatch.addEventListener("click", event => {
        if (event.target === el.closeContinueBtn) return;
        continueLastWatch();
      });
      el.closeContinueBtn.addEventListener("click", () => {
        sessionStorage.setItem("cinema:continueHidden", "1");
        el.continueWatch.style.display = "none";
      });
      el.favoritesBtn.addEventListener("click", () => showStoredList("我的收藏", "cinema:favorites"));
      el.historyBtn.addEventListener("click", () => showStoredList("播放历史", "cinema:history"));
      el.copyUrlBtn.addEventListener("click", () => copyText(el.requestUrl.value || buildUrl("videolist")));
      el.clearBtn.addEventListener("click", () => {
        el.keyword.value = "";
        el.category.value = "";
        el.source.value = "";
        el.pageInput.value = "1";
        el.requestUrl.value = "";
        el.debug.textContent = "";
        el.grid.innerHTML = "";
        el.message.className = "empty";
        el.message.style.display = "grid";
        el.message.textContent = "已清空筛选条件。";
        setText(el.countPill, "未请求");
        setText(el.pageInfo, "等待加载数据");
        setText(el.searchStatus, "");
        loadHomeSections();
      });

      if (el.prevBtn) {
        el.prevBtn.addEventListener("click", () => {
          el.pageInput.value = Math.max(1, Number(el.pageInput.value || 1) - 1);
          loadList();
        });
      }

      if (el.nextBtn) {
        el.nextBtn.addEventListener("click", () => {
          el.pageInput.value = Math.min(state.pageCount || 1, Number(el.pageInput.value || 1) + 1);
          loadList();
        });
      }

      el.keyword.addEventListener("keydown", event => {
        if (event.key === "Enter") {
          el.pageInput.value = "1";
          loadList();
        }
      });

      el.closeBtn.addEventListener("click", () => el.drawer.classList.remove("open"));
      el.drawer.addEventListener("click", event => {
        if (event.target === el.drawer) el.drawer.classList.remove("open");
      });
      el.playerCloseBtn.addEventListener("click", closePlayer);
      el.playerDrawer.addEventListener("click", event => {
        if (event.target === el.playerDrawer) closePlayer();
      });
      el.resourceModalClose.addEventListener("click", () => el.resourceModal.classList.remove("open"));
      el.resourceModal.addEventListener("click", event => {
        if (event.target === el.resourceModal) el.resourceModal.classList.remove("open");
      });
      el.openPlayerBtn.addEventListener("click", () => {
        if (el.openPlayerBtn.dataset.url) {
          window.open(el.openPlayerBtn.dataset.url, "_blank", "noopener");
        }
      });
      el.copyPlayerBtn.addEventListener("click", () => copyText(el.copyPlayerBtn.dataset.url || ""));
      el.backTopBtn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
      window.addEventListener("scroll", () => {
        updateNavStuck();
        handleAutoLoadMore();
      }, { passive: true });
    }

    return {
      bindEvents
    };
  }

  window.createMofaEvents = createMofaEvents;
})(window);
