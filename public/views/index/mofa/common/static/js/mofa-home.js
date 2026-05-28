(function (window) {
  "use strict";

  function createMofaHome(context) {
    const el = context.el;
    const state = context.state;
    const fallbackConfig = context.fallbackConfig;
    const imageUrl = context.imageUrl;
    const imageFallbackSvg = context.imageFallbackSvg;
    const setLazyImage = context.setLazyImage;
    const setText = context.setText;
    const requestJson = context.requestJson;
    const setError = context.setError;
    const loadList = context.loadList;
    const openPlayer = context.openPlayer;
    const readStore = context.readStore;
    const searchByKeyword = context.searchByKeyword;

    function setHeroFromItem(item) {
      if (!item) return;
      const image = item.src || item.vod_pic_slide || item.vod_pic || "";
      if (image) {
        const heroImage = 'url("' + imageUrl(image, "banner") + '")';
        el.hero.style.setProperty("--hero-image", heroImage);
        if (el.hero.parentElement) {
          el.hero.parentElement.style.setProperty("--hero-image", heroImage);
        }
        document.body.style.setProperty("--hero-image", heroImage);
      }
      el.heroTitle.textContent = item.title || item.vod_name || state.config?.site?.heroTitle || fallbackConfig.site.heroTitle;
      el.heroText.textContent = item.desc || item.vod_blurb || item.vod_content || item.vod_remarks || state.config?.site?.heroText || fallbackConfig.site.heroText;
    }

    function bannerItems() {
      return state.config?.banners?.items?.length ? state.config.banners.items : fallbackConfig.banners.items;
    }

    function renderBanners() {
      const items = bannerItems();
      el.bannerThumbs.innerHTML = "";
      el.bannerDots.innerHTML = "";
      const interval = state.config?.banners?.interval || fallbackConfig.banners.interval || 4500;
      el.hero.style.setProperty("--banner-duration", interval + "ms");
      items.forEach((item, index) => {
        const thumb = document.createElement("button");
        thumb.className = "banner-thumb";
        thumb.style.backgroundImage = 'linear-gradient(180deg, transparent, rgba(0,0,0,.55)), url("' + imageUrl(item.image, "banner") + '")';
        thumb.textContent = item.title;
        thumb.addEventListener("click", event => {
          event.stopPropagation();
          setBanner(index);
        });
        el.bannerThumbs.appendChild(thumb);

        const dot = document.createElement("button");
        dot.className = "banner-dot";
        dot.setAttribute("aria-label", "切换到" + item.title);
        dot.addEventListener("click", event => {
          event.stopPropagation();
          setBanner(index);
        });
        el.bannerDots.appendChild(dot);
      });
      setBanner(0);
      initBannerSwipe();
      startBannerTimer();
    }

    function setBanner(index, options = {}) {
      const items = bannerItems();
      if (!items.length) return;
      const nextIndex = (index + items.length) % items.length;
      const previousIndex = state.bannerIndex;
      state.bannerIndex = nextIndex;
      const item = items[state.bannerIndex];
      const nextImage = 'url("' + imageUrl(item.image, "banner") + '")';
      if (el.hero.parentElement) {
        el.hero.parentElement.style.setProperty("--hero-image", nextImage);
      }
      document.body.style.setProperty("--hero-image", nextImage);
      const instant = Boolean(options.instant);
      if (previousIndex === nextIndex || instant || !el.hero.style.getPropertyValue("--hero-image")) {
        window.clearTimeout(el.hero.__bannerFadeTimer);
        el.hero.classList.remove("banner-fading");
        el.hero.style.setProperty("--hero-image", nextImage);
        el.hero.style.setProperty("--hero-next-image", nextImage);
      } else {
        el.hero.style.setProperty("--hero-next-image", nextImage);
        el.hero.classList.add("banner-fading");
        window.clearTimeout(el.hero.__bannerFadeTimer);
        el.hero.__bannerFadeTimer = window.setTimeout(() => {
          el.hero.style.setProperty("--hero-image", nextImage);
          el.hero.classList.remove("banner-fading");
        }, 430);
      }
      el.heroTitle.textContent = item.title;
      el.heroText.textContent = item.subtitle || item.keyword || "";
      [...el.bannerThumbs.children].forEach((node, i) => {
        const active = i === state.bannerIndex;
        node.classList.toggle("active", active);
        if (active) {
          node.style.animation = "none";
          void node.offsetWidth;
          node.style.animation = "";
        }
      });
      [...el.bannerDots.children].forEach((node, i) => node.classList.toggle("active", i === state.bannerIndex));
    }

    function startBannerTimer() {
      window.clearInterval(state.bannerTimer);
      const interval = state.config?.banners?.interval || fallbackConfig.banners.interval || 4500;
      state.bannerTimer = window.setInterval(() => setBanner(state.bannerIndex + 1), interval);
    }

    function pauseBannerTimer() {
      window.clearInterval(state.bannerTimer);
      state.bannerTimer = null;
    }

    function restartBannerTimer() {
      pauseBannerTimer();
      startBannerTimer();
    }

    function moveBanner(step, options = {}) {
      const items = bannerItems();
      if (items.length <= 1) return;
      setBanner(state.bannerIndex + step, options);
      restartBannerTimer();
    }

    function resetBannerDrag() {
      state.bannerDrag.active = false;
      state.bannerDrag.pointerId = null;
      state.bannerDrag.startX = 0;
      state.bannerDrag.startY = 0;
      state.bannerDrag.lastX = 0;
      state.bannerDrag.moved = false;
      state.bannerDrag.switched = false;
    }

    function initBannerSwipe() {
      if (!el.hero || el.hero.__bannerSwipeReady) return;
      el.hero.__bannerSwipeReady = true;
      const threshold = 44;
      const verticalTolerance = 42;

      el.hero.addEventListener("pointerdown", event => {
        if (event.target.closest("button")) return;
        if (bannerItems().length <= 1) return;
        state.bannerDrag.active = true;
        state.bannerDrag.pointerId = event.pointerId;
        state.bannerDrag.startX = event.clientX;
        state.bannerDrag.startY = event.clientY;
        state.bannerDrag.lastX = event.clientX;
        state.bannerDrag.moved = false;
        state.bannerDrag.switched = false;
        pauseBannerTimer();
        if (el.hero.setPointerCapture) {
          try {
            el.hero.setPointerCapture(event.pointerId);
          } catch (error) {}
        }
      });

      el.hero.addEventListener("pointermove", event => {
        const drag = state.bannerDrag;
        if (!drag.active || drag.pointerId !== event.pointerId) return;
        const dx = event.clientX - drag.startX;
        const dy = event.clientY - drag.startY;
        drag.lastX = event.clientX;
        if (Math.abs(dx) > 8 || Math.abs(dy) > 8) {
          drag.moved = true;
        }
        if (Math.abs(dx) > 12 && Math.abs(dx) > Math.abs(dy)) {
          event.preventDefault();
        }
      });

      function finishSwipe(event) {
        const drag = state.bannerDrag;
        if (!drag.active || drag.pointerId !== event.pointerId) return false;
        const dx = (event.clientX || drag.lastX) - drag.startX;
        const dy = (event.clientY || drag.startY) - drag.startY;
        if (Math.abs(dx) >= threshold && Math.abs(dy) <= verticalTolerance) {
          moveBanner(dx < 0 ? 1 : -1, { instant: true });
          drag.switched = true;
        } else {
          restartBannerTimer();
        }
        const wasMoved = drag.moved || drag.switched;
        if (wasMoved) {
          state.bannerClickSuppressUntil = Date.now() + 350;
        }
        resetBannerDrag();
        return wasMoved;
      }

      el.hero.addEventListener("pointerup", event => {
        finishSwipe(event);
      });
      el.hero.addEventListener("pointercancel", event => {
        if (state.bannerDrag.active && state.bannerDrag.pointerId === event.pointerId) {
          restartBannerTimer();
          resetBannerDrag();
        }
      });
      el.hero.addEventListener("lostpointercapture", () => {
        if (state.bannerDrag.active) {
          restartBannerTimer();
          resetBannerDrag();
        }
      });
    }

    function searchCurrentBanner() {
      const item = bannerItems()[state.bannerIndex];
      searchByKeyword(item?.keyword || item?.title || "");
    }

    function renderContinueWatch() {
      const hidden = sessionStorage.getItem("cinema:continueHidden") === "1";
      const last = readStore("cinema:history")[0];
      if (hidden || !last) {
        el.continueWatch.style.display = hidden ? "none" : "";
        el.continueText.textContent = state.themeSettings.notice_text || "点击继续观看 雨霖铃 - 01";
        return;
      }
      el.continueWatch.style.display = "";
      el.continueText.textContent = "点击继续观看 " + last.vod_name + " - " + (last.vod_remarks || "上次播放");
      el.continueWatch.dataset.url = last.play_url || "";
    }

    function continueLastWatch() {
      const last = readStore("cinema:history")[0];
      if (last?.play_url) {
        openPlayer(last.vod_name + " · " + (last.vod_remarks || "继续观看"), last.play_url);
      }
    }


    function buildRankingUrl(channel, mobileMode = false) {
      const ranking = state.config?.ranking || fallbackConfig.ranking;
      const params = new URLSearchParams();
      params.set("channel", channel);
      if (mobileMode) params.set("is_m", "1");
      return ranking.url + "?" + params.toString();
    }

    function getRankingInitialCount() {
      const ranking = state.config?.ranking || fallbackConfig.ranking;
      return Number(ranking.initialCount || ranking.mobileCount || 6);
    }

    function getRankingPageSize() {
      const ranking = state.config?.ranking || fallbackConfig.ranking;
      return Number(ranking.pageSize || getRankingInitialCount());
    }

    function setLoadMoreVisible(visible) {
      if (el.loadMoreBtn) el.loadMoreBtn.style.display = visible ? "" : "none";
    }

    function setHomeBottomLoader(visible, text = "正在加载更多") {
      if (!el.homeBottomLoader) return;
      if (el.homeBottomLoaderText) el.homeBottomLoaderText.textContent = text;
      el.homeBottomLoader.classList.toggle("show", !!visible);
    }

    function hasMoreContent() {
      if (state.viewMode === "ranking") {
        return (state.ranking.shown || 0) < (state.ranking.list || []).length;
      }
      return state.viewMode === "list" && Number(el.pageInput.value || 1) < (state.pageCount || 1);
    }

    async function loadMoreContent() {
      if (state.autoLoadingMore) return;
      if (!hasMoreContent()) {
        const now = Date.now();
        if (state.endReachedCooldown && now < state.endReachedCooldown) return;
        state.endReachedCooldown = now + 1800;
        state.autoLoadingMore = true;
        setHomeBottomLoader(true, "已经到底了");
        window.clearTimeout(state.bottomLoaderTimer);
        state.bottomLoaderTimer = window.setTimeout(() => {
          setHomeBottomLoader(false);
          state.autoLoadingMore = false;
        }, 900);
        return;
      }
      state.autoLoadingMore = true;
      state.endReachedCooldown = 0;
      setHomeBottomLoader(true);
      const startTime = Date.now();
      try {
        if (state.viewMode === "ranking") {
          renderRankingMore(getRankingPageSize());
          return;
        }
        el.pageInput.value = String(Number(el.pageInput.value || 1) + 1);
        await loadList({ append: true });
      } finally {
        const elapsed = Date.now() - startTime;
        window.setTimeout(() => {
          state.autoLoadingMore = false;
          setHomeBottomLoader(false);
        }, Math.max(260, 560 - elapsed));
      }
    }

    function handleAutoLoadMore() {
      if (el.searchPage?.classList.contains("open")) return;
      const nearBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 420;
      if (nearBottom) loadMoreContent();
    }

    function renderRankingMore(count) {
      const list = state.ranking.list || [];
      const start = state.ranking.shown || 0;
      const end = Math.min(start + count, list.length);
      list.slice(start, end).forEach((item, index) => {
        el.grid.appendChild(createRankingCard(item, start + index < 3));
      });
      state.ranking.shown = end;
      el.message.style.display = list.length ? "none" : "grid";
      el.message.textContent = list.length ? "" : (state.ranking.channel || "热榜") + "暂无内容。";
      setText(el.countPill, (state.ranking.channel || "热榜") + "热榜 " + state.ranking.shown + " / " + list.length + " 条");
      setText(el.pageInfo, state.ranking.shown < list.length ? "下滑继续加载" : "已显示全部热榜");
      setLoadMoreVisible(state.ranking.shown < list.length);
    }

    function renderRankingList(channel, list) {
      list = Array.isArray(list) ? list : [];
      state.viewMode = "ranking";
      state.ranking = { channel, list: list || [], shown: 0 };
      el.homeSections.innerHTML = "";
      el.grid.innerHTML = "";
      el.message.style.display = list.length ? "none" : "grid";
      el.message.textContent = list.length ? "" : channel + "热榜暂无内容。";
      renderRankingMore(getRankingInitialCount());
    }

    async function loadHomeSections() {
      state.homeLoaded = true;
      const ranking = state.config?.ranking || fallbackConfig.ranking;
      const channels = ranking.channels || fallbackConfig.ranking.channels;
      const defaultChannel = ranking.defaultChannel || channels[0] || "短剧";
      state.viewMode = "ranking";
      state.ranking = { channel: defaultChannel, list: [], shown: 0 };
      renderRankingChannels(defaultChannel);
      el.homeSections.innerHTML = "";
      el.grid.innerHTML = "";
      el.message.style.display = "grid";
      el.message.textContent = "正在加载热榜...";

      for (const channel of channels) {
        const shelf = document.createElement("section");
        shelf.className = "shelf";
        const head = document.createElement("div");
        head.className = "shelf-head";
        const title = document.createElement("h3");
        title.textContent = channel + "热榜";
        const more = document.createElement("button");
        more.className = "small-btn";
        more.textContent = "搜索榜首";
        more.addEventListener("click", () => {
          const first = row.querySelector(".ranking-card .title")?.textContent || "";
          searchByKeyword(first);
        });
        head.append(title, more);

        const row = document.createElement("div");
        row.className = "shelf-row";
        shelf.append(head, row);
        el.homeSections.appendChild(shelf);

        try {
          const data = await requestJson(buildRankingUrl(channel, Boolean(ranking.mobileMode)));
          const items = data.list || [];
          const rankingItems = data.data || items;
          state.rankingCache.set(channel, rankingItems);
          rankingItems.slice(0, 12).forEach(item => row.appendChild(createRankingCard(item, false)));
          if (channel === defaultChannel) {
            el.message.style.display = rankingItems.length ? "none" : "grid";
            el.message.textContent = rankingItems.length ? "" : "热榜暂无内容。";
            state.ranking = { channel: defaultChannel, list: rankingItems, shown: 0 };
            renderRankingMore(getRankingInitialCount());
          }
        } catch (error) {
          const empty = document.createElement("div");
          empty.className = "empty error";
          empty.textContent = channel + "热榜加载失败：" + (error.message || error);
          row.appendChild(empty);
        }
      }
    }


    function renderRankingChannels(activeChannel) {
      const ranking = state.config?.ranking || fallbackConfig.ranking;
      const channels = ranking.channels || fallbackConfig.ranking.channels;
      el.quickCats.innerHTML = "";
      channels.forEach(channel => {
        const chip = document.createElement("button");
        chip.className = "cat-chip" + (channel === activeChannel ? " active" : "");
        chip.textContent = channel;
        chip.addEventListener("click", () => loadRankingChannel(channel));
        el.quickCats.appendChild(chip);
      });
      updateRankingSwitchHint();
    }

    function nextRankingChannel() {
      const ranking = state.config?.ranking || fallbackConfig.ranking;
      const channels = ranking.channels || fallbackConfig.ranking.channels || [];
      if (!channels.length) return "";
      const current = state.ranking.channel || ranking.defaultChannel || channels[0];
      const index = channels.indexOf(current);
      return channels[(index + 1 + channels.length) % channels.length];
    }

    function updateRankingSwitchHint() {
      if (!el.refreshBtn) return;
      const next = nextRankingChannel();
      const text = next ? "切换到 " + next + " 热榜" : "切换热榜分类";
      el.refreshBtn.setAttribute("aria-label", text);
      el.refreshBtn.setAttribute("title", text);
    }

    async function switchNextRankingChannel() {
      const channel = nextRankingChannel();
      if (!channel) return;
      await loadRankingChannel(channel);
    }

    function setRankingChannelLoading(channel, loading) {
      Array.from(el.quickCats.querySelectorAll(".cat-chip")).forEach(chip => {
        const active = chip.textContent === channel;
        chip.classList.toggle("active", active);
        chip.classList.toggle("loading", loading && active);
      });
      if (loading) setText(el.pageInfo, "正在加载" + channel + "热榜...");
    }

    async function loadRankingChannel(channel) {
      const ranking = state.config?.ranking || fallbackConfig.ranking;
      if (state.ranking.channel === channel && (state.ranking.list || []).length) return;
      state.viewMode = "ranking";
      renderRankingChannels(channel);
      if (state.rankingCache.has(channel)) {
        renderRankingList(channel, state.rankingCache.get(channel));
        return;
      }
      state.ranking = { channel, list: [], shown: 0 };
      setRankingChannelLoading(channel, true);
      el.homeSections.innerHTML = "";
      el.grid.innerHTML = "";
      el.message.style.display = "grid";
      el.message.textContent = "正在加载" + channel + "热榜...";
      try {
        const data = await requestJson(buildRankingUrl(channel, Boolean(ranking.mobileMode)));
        const list = data.data || [];
        state.rankingCache.set(channel, list);
        renderRankingList(channel, list);
      } catch (error) {
        setError(error);
      } finally {
        setRankingChannelLoading(channel, false);
      }
    }


    function createRankingCard(item, eager = false) {
      const card = document.createElement("article");
      card.className = "card ranking-card";
      card.title = "搜索：" + item.title;

      const poster = document.createElement("img");
      poster.className = "poster";
      poster.loading = "lazy";
      poster.decoding = "async";
      setLazyImage(poster, item.src || "", "ranking", eager);
      poster.onerror = () => {
        poster.onerror = null;
        poster.src = imageFallbackSvg(item.title || "热榜");
      };
      poster.alt = item.title || "";

      const badge = document.createElement("div");
      badge.className = "rank-badge";
      badge.textContent = "热榜第 " + (item.ranking || "-") + " 名";

      const body = document.createElement("div");
      body.className = "card-body";

      const title = document.createElement("div");
      title.className = "title";
      title.textContent = item.title || "未命名";

      body.append(title);
      card.append(poster, badge, body);
      card.addEventListener("click", () => searchByKeyword(item.title || ""));
      return card;
    }


    return {
      setHeroFromItem,
      bannerItems,
      renderBanners,
      setBanner,
      startBannerTimer,
      pauseBannerTimer,
      restartBannerTimer,
      initBannerSwipe,
      searchCurrentBanner,
      renderContinueWatch,
      continueLastWatch,
      setLoadMoreVisible,
      setHomeBottomLoader,
      hasMoreContent,
      loadMoreContent,
      handleAutoLoadMore,
      renderRankingMore,
      renderRankingList,
      loadHomeSections,
      renderRankingChannels,
      nextRankingChannel,
      updateRankingSwitchHint,
      switchNextRankingChannel,
      setRankingChannelLoading,
      loadRankingChannel,
      createRankingCard
    };
  }

  window.createMofaHome = createMofaHome;
})(window);
