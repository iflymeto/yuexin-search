/**
 * 新月搜索系统 - 统一调试日志管理器
 * 
 * 功能：根据后端APP_DEBUG配置控制所有F12控制台输出
 * 用法：将所有 console.log 替换为 debugLog.log
 * 
 * @author AI Assistant
 * @date 2025-10-17
 */

(function(window) {
    'use strict';
    
    // 调试日志管理器
    const DebugLogger = {
        // 默认关闭（更安全，防止初始化前泄露日志）
        // 初始化完成后根据APP_DEBUG配置动态设置
        enabled: false,
        
        // 是否已初始化
        initialized: false,
        
        // 缓存原生console方法
        _console: {
            log: console.log.bind(console),
            warn: console.warn.bind(console),
            error: console.error.bind(console),
            info: console.info.bind(console),
            debug: console.debug.bind(console),
            group: console.group.bind(console),
            groupEnd: console.groupEnd.bind(console),
            groupCollapsed: console.groupCollapsed.bind(console),
            table: console.table.bind(console),
            time: console.time.bind(console),
            timeEnd: console.timeEnd.bind(console)
        },
        
        /**
         * 初始化调试日志管理器
         * 从后端获取APP_DEBUG配置
         */
        async init() {
            if (this.initialized) {
                return;
            }

            try {
                // 从后端获取DEBUG配置
                const response = await fetch('/api/other/get_debug_config');
                const result = await response.json();
                
                if (result.code === 200 && result.data) {
                    this.enabled = result.data.debug === true || result.data.debug === 1 || result.data.debug === '1';
                    
                    // 显示初始化信息
                    if (this.enabled) {
                        this._console.log(
                            '%c🔧 调试模式已启用 (APP_DEBUG = true)',
                            'color: #67C23A; font-size: 14px; font-weight: bold; padding: 5px;'
                        );
                        this._console.log(
                            '%c💡 提示：生产环境请在.env中设置 APP_DEBUG = false 关闭调试输出',
                            'color: #E6A23C; font-size: 12px;'
                        );
                    } else {
                        this._console.log(
                            '%c🔒 调试模式已关闭 (APP_DEBUG = false)',
                            'color: #909399; font-size: 12px;'
                        );
                    }
                }
            } catch (error) {
                // API请求失败时默认关闭（生产环境安全优先）
                this._console.warn('[DebugLogger] 获取DEBUG配置失败，默认关闭调试模式:', error);
                this._console.warn('[DebugLogger] 如需开启调试，请设置: debugLog.enabled = true');
                this.enabled = false;
            }
            
            this.initialized = true;
        },
        
        /**
         * 日志输出 - 替代 console.log
         */
        log(...args) {
            if (this.enabled) {
                this._console.log(...args);
            }
        },
        
        /**
         * 警告输出 - 替代 console.warn
         * 注意：warn/error在生产环境也会输出（用于错误追踪）
         */
        warn(...args) {
            this._console.warn(...args);
        },
        
        /**
         * 错误输出 - 替代 console.error
         * 注意：error在任何环境都会输出
         */
        error(...args) {
            this._console.error(...args);
        },
        
        /**
         * 信息输出 - 替代 console.info
         */
        info(...args) {
            if (this.enabled) {
                this._console.info(...args);
            }
        },
        
        /**
         * 调试输出 - 替代 console.debug
         */
        debug(...args) {
            if (this.enabled) {
                this._console.debug(...args);
            }
        },
        
        /**
         * 分组开始 - 替代 console.group
         */
        group(...args) {
            if (this.enabled) {
                this._console.group(...args);
            }
        },
        
        /**
         * 分组结束 - 替代 console.groupEnd
         */
        groupEnd() {
            if (this.enabled) {
                this._console.groupEnd();
            }
        },
        
        /**
         * 折叠分组 - 替代 console.groupCollapsed
         */
        groupCollapsed(...args) {
            if (this.enabled) {
                this._console.groupCollapsed(...args);
            }
        },
        
        /**
         * 表格输出 - 替代 console.table
         */
        table(...args) {
            if (this.enabled) {
                this._console.table(...args);
            }
        },
        
        /**
         * 计时开始 - 替代 console.time
         */
        time(label) {
            if (this.enabled) {
                this._console.time(label);
            }
        },
        
        /**
         * 计时结束 - 替代 console.timeEnd
         */
        timeEnd(label) {
            if (this.enabled) {
                this._console.timeEnd(label);
            }
        },
        
        /**
         * 条件日志（高级用法）
         * @param {boolean} condition - 条件
         * @param {...any} args - 日志参数
         */
        logIf(condition, ...args) {
            if (this.enabled && condition) {
                this._console.log(...args);
            }
        },
        
        /**
         * 彩色日志（快捷方法）
         */
        success(...args) {
            if (this.enabled) {
                this._console.log('%c✓ ' + args[0], 'color: #67C23A; font-weight: bold;', ...args.slice(1));
            }
        },
        
        fail(...args) {
            if (this.enabled) {
                this._console.log('%c✗ ' + args[0], 'color: #F56C6C; font-weight: bold;', ...args.slice(1));
            }
        },
        
        warning(...args) {
            if (this.enabled) {
                this._console.log('%c⚠ ' + args[0], 'color: #E6A23C; font-weight: bold;', ...args.slice(1));
            }
        },
        
        /**
         * 性能监控（用于替换缓存监控日志）
         */
        perf(label, ...args) {
            if (this.enabled) {
                this._console.log('%c⚡ ' + label, 'color: #409EFF; font-weight: bold;', ...args);
            }
        }
    };
    
    // 自动初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            DebugLogger.init();
        });
    } else {
        DebugLogger.init();
    }
    
    // 导出到全局
    window.debugLog = DebugLogger;
    
    // 兼容旧代码（可选）
    // window.console.log = DebugLogger.log.bind(DebugLogger);
    // window.console.warn = DebugLogger.warn.bind(DebugLogger);
    // window.console.error = DebugLogger.error.bind(DebugLogger);
    
})(window);

