(function (window) {
    'use strict';

    function createAnnouncementModule(deps) {
        var axios = deps.axios;
        var debugLog = deps.debugLog || console;
        var announcementData = deps.announcementData;
        var announcementVisible = deps.announcementVisible;
        var announcementScrollable = deps.announcementScrollable;
        var getConfigVersion = deps.getConfigVersion;
        var lockAnnouncementScroll = deps.lockAnnouncementScroll;
        var updateAnnouncementScrollable = deps.updateAnnouncementScrollable;
        var copyToClipboard = deps.copyToClipboard;

        var handleAnnouncementContentClick = function (event) {
            var clickable = event.target ? event.target.closest('[data-copy]') : null;
            if (!clickable) return;

            var contentContainer = clickable.closest('.ios-announcement-content');
            if (!contentContainer) return;

            var copyText = (clickable.getAttribute('data-copy') || '').trim();
            if (!copyText) return;

            event.preventDefault();
            copyToClipboard(copyText, clickable);
        };

        var handleAnnouncementInlineClipboardClick = function (event) {
            var clickable = event.target ? event.target.closest('.ios-announcement-content [onclick*="navigator.clipboard.writeText"]') : null;
            if (!clickable) return;

            var onclickText = clickable.getAttribute('onclick') || '';
            var match = onclickText.match(/navigator\.clipboard\.writeText\((['"])([\s\S]*?)\1\)/);
            if (!match || !match[2]) return;

            event.preventDefault();
            event.stopPropagation();
            copyToClipboard(match[2], clickable);
        };

        var checkAnnouncement = async function () {
            try {
                debugLog.log('[公告弹窗] 开始检查公告');

                var currentVersion = null;
                var announcement = null;
                var cachedVersion = null;
                var versionChanged = false;

                try {
                    currentVersion = await getConfigVersion();
                    cachedVersion = localStorage.getItem('announcement_version');
                    var cachedAnnouncement = localStorage.getItem('announcement_data');
                    versionChanged = Boolean(cachedVersion && cachedVersion !== currentVersion);

                    if (cachedAnnouncement && cachedVersion === currentVersion) {
                        announcement = JSON.parse(cachedAnnouncement);
                        debugLog.log('[公告弹窗] ✓ 使用缓存（版本:', currentVersion, '）');
                    }
                } catch (verError) {
                    debugLog.warn('[公告弹窗] 版本检测失败，将直接加载公告:', verError);
                }

                if (!announcement) {
                    debugLog.log('[公告弹窗] ↻ 重新加载公告数据');
                    var response = await axios.get('/api/announcement/getAnnouncement');
                    var result = response.data;

                    debugLog.log('[公告弹窗] API返回结果:', result);

                    if (result.code !== 200 || !result.data) {
                        debugLog.log('[公告弹窗] 公告未启用或无数据');
                        return;
                    }

                    announcement = result.data;

                    if (currentVersion) {
                        localStorage.setItem('announcement_data', JSON.stringify(announcement));
                        localStorage.setItem('announcement_version', currentVersion);
                        debugLog.log('[公告弹窗] ✓ 已缓存');
                    }

                    if (versionChanged) {
                        var previousStorageKey = 'announcement_' + announcement.id;
                        localStorage.removeItem(previousStorageKey);
                        debugLog.log('[公告弹窗] 版本变化，已清理显示状态:', previousStorageKey, cachedVersion, '->', currentVersion);
                    }
                }

                debugLog.log('[公告弹窗] 公告数据:', announcement);

                var storageKey = 'announcement_' + announcement.id;
                var stored = localStorage.getItem(storageKey);
                debugLog.log('[公告弹窗] 本地存储key:', storageKey, '存储值:', stored);

                if (announcement.type == 1) {
                    debugLog.log('[公告弹窗] 类型1: 每次都显示');
                    announcementData.value = announcement;
                    announcementVisible.value = true;
                    lockAnnouncementScroll(true);
                    updateAnnouncementScrollable();
                } else if (announcement.type == 2) {
                    debugLog.log('[公告弹窗] 类型2: 内容变化时显示');
                    if (!stored || stored !== announcement.content_hash) {
                        debugLog.log('[公告弹窗] 内容已变化或首次访问，显示弹窗');
                        announcementData.value = announcement;
                        announcementVisible.value = true;
                        lockAnnouncementScroll(true);
                        updateAnnouncementScrollable();
                    } else {
                        debugLog.log('[公告弹窗] 内容未变化，不显示');
                    }
                } else if (announcement.type == 3) {
                    debugLog.log('[公告弹窗] 类型3: 近期不再显示模式');
                    if (stored) {
                        var hiddenUntil = parseInt(stored, 10);
                        var now = Date.now();
                        debugLog.log('[公告弹窗] 隐藏到期时间:', new Date(hiddenUntil), '当前时间:', new Date(now));
                        if (now < hiddenUntil) {
                            debugLog.log('[公告弹窗] 还在隐藏期内，不显示');
                            return;
                        }
                    }
                    debugLog.log('[公告弹窗] 显示弹窗');
                    announcementData.value = announcement;
                    announcementVisible.value = true;
                    lockAnnouncementScroll(true);
                    updateAnnouncementScrollable();
                }
            } catch (error) {
                debugLog.error('[公告弹窗] 获取公告失败:', error);
            }
        };

        var confirmAnnouncement = function () {
            debugLog.log('[公告弹窗] 点击确认按钮');
            announcementVisible.value = false;
            lockAnnouncementScroll(false);

            if (!announcementData.value) {
                debugLog.log('[公告弹窗] announcementData为空，仅关闭弹窗');
                return;
            }

            var announcement = announcementData.value;
            var storageKey = 'announcement_' + announcement.id;
            debugLog.log('[公告弹窗] 公告类型:', announcement.type);

            if (announcement.type == 2) {
                localStorage.setItem(storageKey, announcement.content_hash);
                debugLog.log('[公告弹窗] 已保存内容hash:', announcement.content_hash);
            }
            announcementScrollable.value = false;
        };

        var hideAnnouncementTemporarily = function () {
            debugLog.log('[公告弹窗] 点击近期不再显示按钮');
            announcementVisible.value = false;
            lockAnnouncementScroll(false);

            if (!announcementData.value) {
                debugLog.log('[公告弹窗] announcementData为空，仅关闭弹窗');
                return;
            }

            var announcement = announcementData.value;
            var storageKey = 'announcement_' + announcement.id;
            var hiddenUntil = Date.now() + (announcement.interval_days * 24 * 60 * 60 * 1000);
            localStorage.setItem(storageKey, hiddenUntil.toString());
            announcementScrollable.value = false;

            debugLog.log('[公告弹窗] 已设置隐藏期限，到期时间:', new Date(hiddenUntil));
        };

        return {
            checkAnnouncement: checkAnnouncement,
            confirmAnnouncement: confirmAnnouncement,
            hideAnnouncementTemporarily: hideAnnouncementTemporarily,
            handleAnnouncementContentClick: handleAnnouncementContentClick,
            handleAnnouncementInlineClipboardClick: handleAnnouncementInlineClipboardClick
        };
    }

    window.AnnouncementModule = {
        create: createAnnouncementModule
    };
})(window);

