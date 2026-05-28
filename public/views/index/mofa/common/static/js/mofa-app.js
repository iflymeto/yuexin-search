    const mofaState = window.createMofaState();
    const state = mofaState.state;
    const el = mofaState.el;
    const feedback = window.createMofaFeedback({ el, state });
    const showMineToast = feedback.showToast;
    const copyText = feedback.copyText;
    const closeHomeAnnouncement = feedback.closeHomeAnnouncement;
    const handleAnnouncementContentClick = feedback.handleAnnouncementContentClick;
    const checkHomeAnnouncement = feedback.checkHomeAnnouncement;
    const openFeedbackDialog = feedback.openFeedbackDialog;
    const closeFeedbackDialog = feedback.closeFeedbackDialog;
    const submitFeedback = feedback.submitFeedback;

    const utils = window.createMofaUtils({ el });
    const readStore = utils.readStore;
    const writeStore = utils.writeStore;
    const setText = utils.setText;
    const normalizeBase = utils.normalizeBase;
    const applyProxy = utils.applyProxy;
    const buildUrl = utils.buildUrl;
    const buildSectionUrl = utils.buildSectionUrl;
    const requestJson = utils.requestJson;
    const escapeHtml = utils.escapeHtml;

    function handleBottomTab(tab) {
      navigation.handleBottomTab(tab);
    }

    const mofaConfig = window.createMofaConfig({ el, state, escapeHtml, applyProxy, buildUrl, handleBottomTab });
    const fallbackConfig = mofaConfig.fallbackConfig;
    const loadConfig = mofaConfig.loadConfig;
    const applyConfig = mofaConfig.applyConfig;
    const updateBottomTabsActive = mofaConfig.updateBottomTabsActive;
    const imageUrl = mofaConfig.imageUrl;
    const imageFallbackSvg = mofaConfig.imageFallbackSvg;
    const setLazyImage = mofaConfig.setLazyImage;

    const catalog = window.createMofaCatalog({
      el,
      state,
      readStore,
      writeStore,
      setText,
      requestJson,
      buildUrl,
      setLazyImage,
      loadDetail: (...args) => loadDetail(...args),
      setLoadMoreVisible: (...args) => setLoadMoreVisible(...args)
    });
    const setError = catalog.setError;
    const createCard = catalog.createCard;
    const loadClasses = catalog.loadClasses;
    const loadList = catalog.loadList;

    const home = window.createMofaHome({ el, state, fallbackConfig, imageUrl, imageFallbackSvg, setLazyImage, setText, requestJson, setError, loadList, openPlayer: (...args) => openPlayer(...args), readStore, searchByKeyword });
    const bannerItems = home.bannerItems;
    const renderBanners = home.renderBanners;
    const setBanner = home.setBanner;
    const searchCurrentBanner = home.searchCurrentBanner;
    const renderContinueWatch = home.renderContinueWatch;
    const continueLastWatch = home.continueLastWatch;
    const setLoadMoreVisible = home.setLoadMoreVisible;
    const loadMoreContent = home.loadMoreContent;
    const handleAutoLoadMore = home.handleAutoLoadMore;
    const loadHomeSections = home.loadHomeSections;
    const switchNextRankingChannel = home.switchNextRankingChannel;
    const loadRankingChannel = home.loadRankingChannel;

    const player = window.createMofaPlayer({ el, state, normalizeBase, applyProxy, requestJson, setError, imageUrl, readStore, writeStore, renderContinueWatch });
    const openPlayer = player.openPlayer;
    const closePlayer = player.closePlayer;
    const loadDetail = player.loadDetail;

    let navigation = null;
    let panels = null;

    let searchApi = null;

    function ensureSearchApi() {
      return searchApi;
    }

    function runSearch() {
      ensureSearchApi().runSearch();
    }

    function openSearchPage(preset = "", options = {}) {
      ensureSearchApi().openSearchPage(preset, options);
    }

    function openSearchHistoryPage() {
      ensureSearchApi().openSearchHistoryPage();
    }

    function closeSearchPage() {
      ensureSearchApi().closeSearchPage();
    }

    function commitSearch(keyword) {
      ensureSearchApi().commitSearch(keyword);
    }

    function closeSearchEventSource() {
      ensureSearchApi().closeSearchEventSource();
    }

    function renderSearchHome() {
      ensureSearchApi().renderSearchHome();
    }

    function renderSearchHistory() {
      ensureSearchApi().renderSearchHistory();
    }

    function showSearchToast(message) {
      ensureSearchApi().showSearchToast(message);
    }

    function loadGuessList(typeId = "") {
      ensureSearchApi().loadGuessList(typeId);
    }

    navigation = window.createMofaNavigation({
      el,
      state,
      readStore,
      setText,
      createCard,
      updateBottomTabsActive,
      setBanner,
      loadHomeSections,
      checkHomeAnnouncement,
      loadRankingChannel,
      renderDiscoverPanel: (...args) => renderDiscoverPanel(...args),
      renderMinePanel: (...args) => renderMinePanel(...args),
      openSearchHistoryPage,
      searchByKeyword,
      openSearchPage
    });
    const showStoredList = navigation.showStoredList;
    const setActiveNav = navigation.setActiveNav;
    const toggleCinemaTheme = navigation.toggleCinemaTheme;
    const updateNavStuck = navigation.updateNavStuck;
    const showHome = navigation.showHome;
    const showShorts = navigation.showShorts;
    const showDiscover = navigation.showDiscover;
    const closeExternalConfirm = navigation.closeExternalConfirm;
    const applyInitialPanelFromUrl = navigation.applyInitialPanelFromUrl;
    const handleFeatureCardClick = navigation.handleFeatureCardClick;

    panels = window.createMofaPanels({
      el,
      state,
      escapeHtml,
      readStore,
      showMineToast,
      openSearchHistoryPage,
      showStoredList,
      confirmExternalUrl: navigation.confirmExternalUrl,
      clearMainContentForPanel: navigation.clearMainContentForPanel,
      openFeedbackDialog
    });
    const renderDiscoverPanel = panels.renderDiscoverPanel;
    const renderMinePanel = panels.renderMinePanel;

    const panResults = window.createMofaPanResults({ el, state, escapeHtml, copyText, loadDetail });
    const normalizeSearchItem = panResults.normalizeSearchItem;
    const createSearchResultCard = panResults.createSearchResultCard;

    searchApi = window.createMofaSearch({
      el,
      state,
      escapeHtml,
      readStore,
      writeStore,
      bannerItems,
      requestJson,
      buildUrl,
      createCard,
      normalizeSearchItem,
      createSearchResultCard,
      setActiveNav
    });
    const pcSuggestionState = searchApi.pcSuggestionState;
    const mobileSuggestionState = searchApi.mobileSuggestionState;
    const hidePcSuggestions = searchApi.hidePcSuggestions;
    const renderPcSuggestions = searchApi.renderPcSuggestions;
    const selectPcSuggestion = searchApi.selectPcSuggestion;
    const fetchPcSuggestions = searchApi.fetchPcSuggestions;
    const hideMobileSuggestions = searchApi.hideMobileSuggestions;
    const renderMobileSuggestions = searchApi.renderMobileSuggestions;
    const selectMobileSuggestion = searchApi.selectMobileSuggestion;
    const fetchMobileSuggestions = searchApi.fetchMobileSuggestions;

    function searchByKeyword(keyword) {
      ensureSearchApi().searchByKeyword(keyword);
    }

    const events = window.createMofaEvents({
      el,
      state,
      loadList,
      loadClasses,
      showHome,
      showShorts,
      showDiscover,
      setActiveNav,
      showStoredList,
      closeExternalConfirm,
      closeHomeAnnouncement,
      handleAnnouncementContentClick,
      openFeedbackDialog,
      closeFeedbackDialog,
      submitFeedback,
      runSearch,
      openSearchPage,
      fetchPcSuggestions,
      hidePcSuggestions,
      pcSuggestionState,
      selectPcSuggestion,
      renderPcSuggestions,
      searchCurrentBanner,
      toggleCinemaTheme,
      handleFeatureCardClick,
      closeSearchPage,
      hideMobileSuggestions,
      commitSearch,
      fetchMobileSuggestions,
      mobileSuggestionState,
      selectMobileSuggestion,
      renderMobileSuggestions,
      closeSearchEventSource,
      renderSearchHome,
      showSearchToast,
      writeStore,
      renderSearchHistory,
      loadGuessList,
      switchNextRankingChannel,
      loadMoreContent,
      continueLastWatch,
      copyText,
      buildUrl,
      loadHomeSections,
      setText,
      closePlayer,
      updateNavStuck,
      handleAutoLoadMore
    });

    async function init() {
      events.bindEvents();
      const config = await loadConfig();
      applyConfig(config);
      renderBanners();
      updateNavStuck();
      el.requestUrl.value = buildUrl("videolist");
      if (config.defaults?.autoLoad !== false) {
        renderContinueWatch();
        loadHomeSections();
      }
      applyInitialPanelFromUrl();
      if (state.activePanel === "home") checkHomeAnnouncement();
    }

    init();
