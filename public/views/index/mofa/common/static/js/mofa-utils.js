(function (window) {
  "use strict";

  function createMofaUtils(context) {
    const el = context.el;

    function readStore(key) {
      try {
        return JSON.parse(localStorage.getItem(key) || "[]");
      } catch (error) {
        return [];
      }
    }

    function writeStore(key, value) {
      localStorage.setItem(key, JSON.stringify(value));
    }

    function setText(node, text) {
      if (node) node.textContent = text;
    }

    function normalizeBase(value) {
      return value.trim().replace(/\?+$/, "").replace(/\/?$/, "/");
    }

    function applyProxy(realUrl) {
      const proxy = el.proxyPrefix.value.trim();
      if (!proxy) return realUrl;

      if (el.proxyMode.value === "encoded") {
        return proxy + encodeURIComponent(realUrl);
      }

      if (el.proxyMode.value === "query") {
        const separator = proxy.includes("?") ? "&" : "?";
        return proxy + separator + "url=" + encodeURIComponent(realUrl);
      }

      return proxy.replace(/\/?$/, "/") + realUrl;
    }

    function buildUrl(action) {
      const base = normalizeBase(el.apiBase.value);
      const params = new URLSearchParams();
      params.set("ac", action);

      if (action === "videolist") {
        params.set("pg", String(Math.max(1, Number(el.pageInput.value) || 1)));
        if (el.category.value) params.set("t", el.category.value);
        if (el.keyword.value.trim()) params.set("wd", el.keyword.value.trim());
      }

      let url = base;
      if (el.source.value) {
        url = base.replace(/\/$/, "") + "/from/" + encodeURIComponent(el.source.value) + "/";
      }

      return applyProxy(url + "?" + params.toString());
    }

    function buildSectionUrl(section) {
      const base = normalizeBase(el.apiBase.value);
      const params = new URLSearchParams();
      params.set("ac", "videolist");
      params.set("pg", String(section.page || 1));
      if (section.type) params.set("t", section.type);
      if (section.keyword) params.set("wd", section.keyword);

      let url = base;
      if (section.source) {
        url = base.replace(/\/$/, "") + "/from/" + encodeURIComponent(section.source) + "/";
      }

      return applyProxy(url + "?" + params.toString());
    }

    async function requestJson(url) {
      el.requestUrl.value = url;
      const res = await fetch(url);
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.json();
    }

    function escapeHtml(value) {
      return String(value || "").replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      }[char]));
    }

    return {
      readStore,
      writeStore,
      setText,
      normalizeBase,
      applyProxy,
      buildUrl,
      buildSectionUrl,
      requestJson,
      escapeHtml
    };
  }

  window.createMofaUtils = createMofaUtils;
})(window);
