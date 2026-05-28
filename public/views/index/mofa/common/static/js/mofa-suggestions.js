(function (window) {
  "use strict";

  function createMofaSuggestions(context) {
    const el = context.el;
    const escapeHtml = context.escapeHtml;
    const commitSearch = context.commitSearch;

    const pcState = {
      timer: null,
      activeIndex: -1,
      items: [],
      loading: false,
      requestId: 0
    };

    const mobileState = {
      timer: null,
      activeIndex: -1,
      items: [],
      loading: false,
      requestId: 0
    };

    function pcSearchUrl(keyword) {
      return "/s/" + encodeURIComponent(String(keyword || "").trim()) + ".html";
    }

    function highlight(text, keyword, className) {
      const safeText = escapeHtml(text || "");
      const term = String(keyword || "").trim();
      if (!term) return safeText;
      const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      return safeText.replace(new RegExp(escapedTerm, "gi"), '<span class="' + className + '">$&</span>');
    }

    function hidePcSuggestions() {
      pcState.activeIndex = -1;
      pcState.items = [];
      pcState.loading = false;
      if (el.topSuggestions) {
        el.topSuggestions.classList.remove("open");
        el.topSuggestions.innerHTML = "";
      }
    }

    function renderPcSuggestions() {
      if (!el.topSuggestions) return;
      const keyword = el.topKeyword.value.trim();
      if (!keyword) {
        hidePcSuggestions();
        return;
      }
      const items = pcState.items || [];
      el.topSuggestions.classList.add("open");
      if (pcState.loading) {
        el.topSuggestions.innerHTML = '<div class="top-suggestion-status">正在联想...</div>';
        return;
      }
      if (!items.length) {
        el.topSuggestions.innerHTML = '<div class="top-suggestion-status">暂无联想</div>';
        return;
      }
      el.topSuggestions.innerHTML = items.map((item, index) => {
        return '<button type="button" class="top-suggestion-item' + (index === pcState.activeIndex ? ' active' : '') + '" data-index="' + index + '">' + highlight(item, keyword, "top-suggestion-highlight") + '</button>';
      }).join("");
      el.topSuggestions.querySelectorAll(".top-suggestion-item").forEach(btn => {
        btn.addEventListener("mousedown", event => {
          event.preventDefault();
          const index = Number(btn.dataset.index || 0);
          selectPcSuggestion(index);
        });
      });
    }

    function selectPcSuggestion(index) {
      const item = pcState.items[index];
      if (!item) return;
      el.topKeyword.value = item;
      hidePcSuggestions();
      window.location.href = pcSearchUrl(item);
    }

    function fetchPcSuggestions() {
      const keyword = el.topKeyword.value.trim();
      if (!keyword) {
        hidePcSuggestions();
        return;
      }
      const requestId = ++pcState.requestId;
      pcState.loading = true;
      renderPcSuggestions();
      fetch("/api/search/suggestion?keyword=" + encodeURIComponent(keyword), { cache: "no-store" })
        .then(res => res.json())
        .then(data => {
          if (requestId !== pcState.requestId) return;
          pcState.loading = false;
          pcState.items = Array.isArray(data?.data) ? data.data.slice(0, 8) : [];
          pcState.activeIndex = -1;
          renderPcSuggestions();
        })
        .catch(() => {
          if (requestId !== pcState.requestId) return;
          pcState.loading = false;
          pcState.items = [];
          renderPcSuggestions();
        });
    }

    function hideMobileSuggestions() {
      mobileState.activeIndex = -1;
      mobileState.items = [];
      mobileState.loading = false;
      if (el.mobileSuggestions) {
        el.mobileSuggestions.classList.remove("open");
        el.mobileSuggestions.innerHTML = "";
      }
    }

    function renderMobileSuggestions() {
      if (!el.mobileSuggestions) return;
      const keyword = el.searchInput.value.trim();
      if (!keyword || el.searchResult.style.display === "flex") {
        hideMobileSuggestions();
        return;
      }
      const items = mobileState.items || [];
      el.mobileSuggestions.classList.add("open");
      if (mobileState.loading) {
        el.mobileSuggestions.innerHTML = '<div class="mobile-suggestion-status">正在联想...</div>';
        return;
      }
      if (!items.length) {
        el.mobileSuggestions.innerHTML = '<div class="mobile-suggestion-status">暂无联想</div>';
        return;
      }
      el.mobileSuggestions.innerHTML = items.map((item, index) => {
        return '<button type="button" class="mobile-suggestion-item' + (index === mobileState.activeIndex ? ' active' : '') + '" data-index="' + index + '">' + highlight(item, keyword, "mobile-suggestion-highlight") + '</button>';
      }).join("");
      el.mobileSuggestions.querySelectorAll(".mobile-suggestion-item").forEach(btn => {
        btn.addEventListener("mousedown", event => {
          event.preventDefault();
          const index = Number(btn.dataset.index || 0);
          selectMobileSuggestion(index);
        });
      });
    }

    function selectMobileSuggestion(index) {
      const item = mobileState.items[index];
      if (!item) return;
      el.searchInput.value = item;
      hideMobileSuggestions();
      commitSearch(item);
    }

    function fetchMobileSuggestions() {
      const keyword = el.searchInput.value.trim();
      if (!keyword || el.searchResult.style.display === "flex") {
        hideMobileSuggestions();
        return;
      }
      const requestId = ++mobileState.requestId;
      mobileState.loading = true;
      renderMobileSuggestions();
      fetch("/api/search/suggestion?keyword=" + encodeURIComponent(keyword), { cache: "no-store" })
        .then(res => res.json())
        .then(data => {
          if (requestId !== mobileState.requestId) return;
          mobileState.loading = false;
          mobileState.items = Array.isArray(data?.data) ? data.data.slice(0, 8) : [];
          mobileState.activeIndex = -1;
          renderMobileSuggestions();
        })
        .catch(() => {
          if (requestId !== mobileState.requestId) return;
          mobileState.loading = false;
          mobileState.items = [];
          renderMobileSuggestions();
        });
    }

    return {
      pcState,
      mobileState,
      pcSearchUrl,
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

  window.createMofaSuggestions = createMofaSuggestions;
})(window);
