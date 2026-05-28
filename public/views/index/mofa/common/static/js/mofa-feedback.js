(function (window, document) {
  "use strict";

  function createMofaFeedback(context) {
    const el = context.el;
    const state = context.state;

    function lockAnnouncementScroll(locked) {
      document.documentElement.style.overflow = locked ? "hidden" : "";
      document.body.style.overflow = locked ? "hidden" : "";
    }

    function lockPageScroll(locked) {
      document.documentElement.style.overflow = locked ? "hidden" : "";
      document.body.style.overflow = locked ? "hidden" : "";
    }

    function showToast(message) {
      if (!el.mineToast) return;
      el.mineToast.textContent = message;
      const announcementOpen = el.homeAnnouncementOverlay?.classList.contains("open");
      document.body.classList.toggle("has-announcement-toast", Boolean(announcementOpen));
      el.mineToast.classList.add("show");
      window.clearTimeout(el.mineToast.__timer);
      el.mineToast.__timer = window.setTimeout(() => {
        el.mineToast.classList.remove("show");
        document.body.classList.remove("has-announcement-toast");
      }, 1800);
    }

    async function copyText(text) {
      if (!text) return;
      try {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === "function" && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
          showToast("已复制");
          return;
        }
        const input = document.createElement("textarea");
        input.value = text;
        input.setAttribute("readonly", "");
        input.style.position = "fixed";
        input.style.left = "-9999px";
        document.body.appendChild(input);
        input.select();
        document.execCommand("copy");
        document.body.removeChild(input);
        showToast("已复制");
      } catch (error) {
        showToast("复制失败");
      }
    }

    function closeHomeAnnouncement(saveMode = "confirm") {
      const announcement = state.announcementData;
      el.homeAnnouncementOverlay.classList.remove("open");
      el.homeAnnouncementOverlay.setAttribute("aria-hidden", "true");
      lockAnnouncementScroll(false);
      if (!announcement) return;
      const storageKey = "announcement_" + announcement.id;
      if (saveMode === "confirm" && Number(announcement.type) === 2) {
        localStorage.setItem(storageKey, announcement.content_hash || "");
      }
      if (saveMode === "later" && Number(announcement.type) === 3) {
        const days = Number(announcement.interval_days || 7);
        localStorage.setItem(storageKey, String(Date.now() + days * 24 * 60 * 60 * 1000));
      }
    }

    function sanitizeAnnouncementHtml(html) {
      const template = document.createElement("template");
      template.innerHTML = html;
      template.content.querySelectorAll("script").forEach(node => node.remove());
      return template.innerHTML;
    }

    function openHomeAnnouncement(announcement) {
      if (!announcement || !el.homeAnnouncementOverlay) return;
      state.announcementData = announcement;
      el.homeAnnouncementTitle.textContent = announcement.title || "平台公告";
      if (el.homeAnnouncementSubtitle) el.homeAnnouncementSubtitle.style.display = "none";
      el.homeAnnouncementContent.innerHTML = sanitizeAnnouncementHtml(announcement.content || "");
      const temporary = Number(announcement.type) === 3;
      el.homeAnnouncementLaterBtn.style.display = temporary ? "" : "none";
      el.homeAnnouncementActions.classList.toggle("has-secondary", temporary);
      el.homeAnnouncementOverlay.classList.add("open");
      el.homeAnnouncementOverlay.setAttribute("aria-hidden", "false");
      lockAnnouncementScroll(true);
      window.setTimeout(() => {
        const scrollable = el.homeAnnouncementContent.scrollHeight > el.homeAnnouncementContent.clientHeight + 4;
        el.homeAnnouncementContent.classList.toggle("is-scrollable", scrollable);
      }, 0);
    }

    function showAnnouncementCopyFeedback(node) {
      if (!node) return;
      node.classList.add("copied");
      const oldTitle = node.getAttribute("title") || "";
      node.setAttribute("title", "已复制");
      window.clearTimeout(node.__copyTimer);
      node.__copyTimer = window.setTimeout(() => {
        node.classList.remove("copied");
        if (oldTitle) node.setAttribute("title", oldTitle);
        else node.removeAttribute("title");
      }, 900);
    }

    function handleAnnouncementContentClick(event) {
      const dataCopy = event.target.closest(".home-announcement-content [data-copy]");
      if (dataCopy) {
        const text = (dataCopy.getAttribute("data-copy") || "").trim();
        if (text) {
          event.preventDefault();
          copyText(text);
        }
        return;
      }

      const copyFn = event.target.closest('.home-announcement-content [onclick*="copyToClipboard"]');
      if (copyFn) {
        const onclickText = copyFn.getAttribute("onclick") || "";
        const match = onclickText.match(/copyToClipboard\((['"])([\s\S]*?)\1\)/);
        if (match && match[2]) {
          event.preventDefault();
          event.stopPropagation();
          copyText(match[2]);
          showAnnouncementCopyFeedback(copyFn);
        }
        return;
      }

      const inlineCopy = event.target.closest('.home-announcement-content [onclick*="navigator.clipboard.writeText"]');
      if (!inlineCopy) return;
      const onclickText = inlineCopy.getAttribute("onclick") || "";
      const match = onclickText.match(/navigator\.clipboard\.writeText\((['"])([\s\S]*?)\1\)/);
      if (!match || !match[2]) return;
      event.preventDefault();
      event.stopPropagation();
      copyText(match[2]);
      showAnnouncementCopyFeedback(inlineCopy);
    }

    async function checkHomeAnnouncement() {
      if (!el.homeAnnouncementOverlay || state.announcementChecked) return;
      state.announcementChecked = true;
      try {
        const res = await fetch("/api/announcement/getAnnouncement", { cache: "no-store" });
        const result = await res.json();
        if (Number(result.code) !== 200 || !result.data) return;
        const announcement = result.data;
        const storageKey = "announcement_" + announcement.id;
        const stored = localStorage.getItem(storageKey);
        const type = Number(announcement.type || 1);
        if (type === 2 && stored && stored === announcement.content_hash) return;
        if (type === 3 && stored && Date.now() < Number(stored)) return;
        openHomeAnnouncement(announcement);
      } catch (error) {
        state.announcementChecked = false;
        console.warn("[公告弹窗] 获取公告失败", error);
      }
    }

    function openFeedbackDialog() {
      if (!el.mofaFeedbackOverlay) {
        showToast("反馈入口未启用");
        return;
      }
      el.mofaFeedbackOverlay.classList.add("open");
      el.mofaFeedbackOverlay.setAttribute("aria-hidden", "false");
      lockPageScroll(true);
      window.setTimeout(() => el.mofaFeedbackContent?.focus(), 60);
    }

    function closeFeedbackDialog() {
      if (!el.mofaFeedbackOverlay) return;
      el.mofaFeedbackOverlay.classList.remove("open");
      el.mofaFeedbackOverlay.setAttribute("aria-hidden", "true");
      lockPageScroll(false);
    }

    async function submitFeedback() {
      if (!el.mofaFeedbackContent || !el.mofaFeedbackSubmitBtn) return;
      const content = el.mofaFeedbackContent.value.trim();
      const email = el.mofaFeedbackEmail ? el.mofaFeedbackEmail.value.trim() : "";
      if (!content) {
        showToast("请输入反馈内容");
        el.mofaFeedbackContent.focus();
        return;
      }
      if (el.mofaFeedbackSubmitBtn.disabled) return;
      el.mofaFeedbackSubmitBtn.disabled = true;
      el.mofaFeedbackSubmitBtn.textContent = "提交中...";
      try {
        const res = await fetch("/api/tool/feedback", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ content, email })
        });
        const data = await res.json().catch(() => ({}));
        showToast(data.message || (Number(data.code) === 200 ? "提交成功" : "提交失败"));
        if (Number(data.code) === 200) {
          el.mofaFeedbackContent.value = "";
          if (el.mofaFeedbackEmail) el.mofaFeedbackEmail.value = "";
          closeFeedbackDialog();
        }
      } catch (error) {
        showToast("提交失败，请稍后再试");
      } finally {
        el.mofaFeedbackSubmitBtn.disabled = false;
        el.mofaFeedbackSubmitBtn.textContent = "提交";
      }
    }

    return {
      showToast,
      copyText,
      closeHomeAnnouncement,
      handleAnnouncementContentClick,
      checkHomeAnnouncement,
      openFeedbackDialog,
      closeFeedbackDialog,
      submitFeedback
    };
  }

  window.createMofaFeedback = createMofaFeedback;
})(window, document);
