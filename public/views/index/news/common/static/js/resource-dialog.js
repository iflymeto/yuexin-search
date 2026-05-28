(function (window) {
    'use strict';

    function createResourceDialogModule(deps) {
        var dialogItem = deps.dialogItem;
        var showMessage = deps.showMessage;
        var copyToClipboard = deps.copyToClipboard;
        var ensureItemTreeState = deps.ensureItemTreeState;
        var supportsPanTree = deps.supportsPanTree;
        var toggleItemTree = deps.toggleItemTree;

        var showUrlFun = function (item) {
            ensureItemTreeState(item);
            dialogItem.value = item;
            if (item.showUrl) {
                setTimeout(function () {
                    var qrcodeElement = document.getElementById('qrcode');
                    if (qrcodeElement) {
                        qrcodeElement.innerHTML = '';
                        var canvas = qrcanvas.qrcanvas({
                            data: item.showUrl,
                            size: 120
                        });
                        qrcodeElement.appendChild(canvas);
                    }
                }, 200);
            }
        };

        var mergeDialogMedia = function (target, source) {
            if (!target || !source) return target;
            if (!target.image && source.image) {
                target.image = source.image;
            }
            if ((!target.images || !target.images.length) && source.images) {
                target.images = source.images;
            }
            return target;
        };

        var canShowDialogTree = function (item) {
            if (!item || !supportsPanTree(item)) return false;
            return !!(item.tree_key || item.treeKey || item.treeSourceUrl || item.originalUrl || item.url || item.treeData);
        };

        var toggleDialogTree = async function (item) {
            await toggleItemTree(item);
        };

        var copyDialogLink = function () {
            if (!dialogItem.value || !dialogItem.value.showUrl) {
                showMessage('暂无可复制的链接', 'warning');
                return;
            }
            copyToClipboard(dialogItem.value.showUrl);
        };

        return {
            showUrlFun: showUrlFun,
            mergeDialogMedia: mergeDialogMedia,
            canShowDialogTree: canShowDialogTree,
            toggleDialogTree: toggleDialogTree,
            copyDialogLink: copyDialogLink
        };
    }

    window.ResourceDialogModule = {
        create: createResourceDialogModule
    };
})(window);
