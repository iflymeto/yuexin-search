(function (window) {
  "use strict";

  function createMofaPlayer(context) {
    const el = context.el;
    const state = context.state;
    const normalizeBase = context.normalizeBase;
    const applyProxy = context.applyProxy;
    const requestJson = context.requestJson;
    const setError = context.setError;
    const imageUrl = context.imageUrl;
    const readStore = context.readStore;
    const writeStore = context.writeStore;
    const renderContinueWatch = context.renderContinueWatch;

    function buildParsedPlayUrl(url) {
      const prefix = el.parserPrefix.value.trim();
      if (!prefix || !url) return url || "";
      return prefix + encodeURIComponent(url);
    }

    function openPlayer(title, url) {
      el.playerTitle.textContent = title || "播放";
      el.playerUrl.textContent = url;
      el.playerFrame.src = url;
      el.openPlayerBtn.dataset.url = url;
      el.copyPlayerBtn.dataset.url = url;
      el.playerDrawer.classList.add("open");
    }

    function closePlayer() {
      el.playerDrawer.classList.remove("open");
      el.playerFrame.removeAttribute("src");
    }

    function addHistory(detail, episodeName, url) {
      const list = readStore("cinema:history");
      const entry = {
        vod_id: detail?.vod_id,
        vod_name: detail?.vod_name || "未知影片",
        vod_pic: detail?.vod_pic || "",
        type_name: detail?.type_name || detail?.vod_class || "",
        vod_remarks: episodeName || "",
        play_url: url,
        played_at: Date.now()
      };
      const next = [
        entry,
        ...list.filter(item => !(String(item.vod_id) === String(entry.vod_id) && item.vod_remarks === entry.vod_remarks))
      ].slice(0, 80);
      writeStore("cinema:history", next);
      renderContinueWatch();
    }

    function parsePlayList(fromValue, urlValue) {
      const sources = String(fromValue || "").split("$$$");
      const groups = String(urlValue || "").split("$$$");

      return groups.map((group, index) => {
        const sourceName = sources[index] || "source-" + (index + 1);
        const episodes = group.split("#").filter(Boolean).map(row => {
          const cut = row.indexOf("$");
          return {
            name: cut >= 0 ? row.slice(0, cut) : row,
            url: cut >= 0 ? row.slice(cut + 1) : ""
          };
        });
        return { sourceName, episodes };
      });
    }

    async function loadDetail(id) {
      if (!id) return;
      const base = normalizeBase(el.apiBase.value);
      const params = new URLSearchParams({ ac: "videolist", ids: String(id) });
      const realUrl = base + "?" + params.toString();
      const url = applyProxy(realUrl);

      try {
        const data = await requestJson(url);
        const item = data.list && data.list[0];
        if (!item) throw new Error("没有详情数据");
        openDetail(item);
      } catch (error) {
        setError(error);
      }
    }

    function openDetail(item) {
      state.currentDetail = item;
      el.detailTitle.textContent = item.vod_name || "详情";
      el.detailMeta.textContent = [
        item.type_name || item.vod_class || "",
        item.vod_year ? item.vod_year + "年" : "",
        item.vod_area || "",
        item.vod_remarks || ""
      ].filter(Boolean).join(" · ");
      el.detailPic.loading = "lazy";
      el.detailPic.decoding = "async";
      el.detailPic.src = imageUrl(item.vod_pic || "", "vod");
      el.detailPic.alt = item.vod_name || "";
      el.detailContent.textContent = item.vod_content || item.vod_blurb || "暂无简介。";
      el.episodes.innerHTML = "";

      parsePlayList(item.vod_play_from, item.vod_play_url).forEach(group => {
        const block = document.createElement("div");
        block.className = "source-block";

        const title = document.createElement("div");
        title.className = "source-title";
        title.textContent = group.sourceName + " · " + group.episodes.length + " 集";

        const list = document.createElement("div");
        list.className = "episode-grid";

        group.episodes.forEach(ep => {
          const btn = document.createElement("button");
          btn.className = "episode";
          const parsedUrl = buildParsedPlayUrl(ep.url);
          btn.textContent = ep.name || "播放";
          btn.title = parsedUrl;
          btn.addEventListener("click", () => {
            addHistory(state.currentDetail, ep.name || "播放", parsedUrl);
            openPlayer(ep.name || "播放", parsedUrl);
          });
          list.appendChild(btn);
        });

        block.append(title, list);
        el.episodes.appendChild(block);
      });

      el.drawer.classList.add("open");
    }

    return {
      buildParsedPlayUrl,
      openPlayer,
      closePlayer,
      addHistory,
      parsePlayList,
      loadDetail,
      openDetail
    };
  }

  window.createMofaPlayer = createMofaPlayer;
})(window);
