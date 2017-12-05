/**
 * Copyright (C) InnoCraft Ltd - All rights reserved.
 *
 * NOTICE:  All information contained herein is, and remains the property of InnoCraft Ltd.
 * The intellectual and technical concepts contained herein are protected by trade secret or copyright law.
 * Redistribution of this information or reproduction of this material is strictly forbidden
 * unless prior written permission is obtained from InnoCraft Ltd.
 *
 * You shall use this code only in accordance with the license agreement obtained from InnoCraft Ltd.
 *
 * @link https://www.innocraft.com/
 * @license For license details see https://www.innocraft.com/license
 */

function HsrRecordingIframe (url) {
    url = String(url);
    if (url.indexOf('?') >= 0) {
        url = url.substr(0, url.indexOf('?'));
    }

    if (url.indexOf('#') >= 0) {
        url = url.substr(0, url.indexOf('#'));
    }

    if (url.indexOf('http') === -1) {
        // we need to load http or at least same protocol as current piwik install
       url = String(location.protocol) + '//' + url;
    }

    var requireSecureProtocol = String(location.protocol).toLowerCase() === 'https:';

    function convertUrlToSecureProtocolIfNeeded(url)
    {
        // otherwise we get problems re insecure content
        if (requireSecureProtocol && String(url).toLowerCase().indexOf('http:') === 0) {
            url = 'https' + url.substr('http'.length);
        }

        return url;
    }

    url = convertUrlToSecureProtocolIfNeeded(url);

    var baseUrl = url;

    this.enableDebugMode = false;

    function addRemoveSupport(window)
    {
        if (window && !('remove' in window.Element.prototype)) {
            window.Element.prototype.remove = function() {
                if (this.parentNode && this.parentNode.removeChild) {
                    this.parentNode.removeChild(this);
                }
            };
        }
    }

    addRemoveSupport(window);

    this.isSupportedBrowser = function () {
        if (typeof WebKitMutationObserver !== 'undefined') {
            return true;
        } else if (typeof MutationObserver !== 'undefined') {
            return true;
        }

        return false;
    };

    this.scrollTo = function (x,y) {
        window.scrollTo(x,y);
    };

    this.getIframeWindow = function () {
        return window;
    };

    this.findElement = function (selector) {
        return $(selector);
    };

    this.makeSvg = function (width, height) {
        var canvas = this.findElement('#mouseMoveCanvas');
        if (canvas.size()) {
            canvas.empty();
        } else {
            this.appendContent('<div id="mouseMoveCanvas" style="position:absolute !important;top: 0 !important;left:0 !important;z-index: 99999998 !important;display: block !important;visibility: visible !important;" width="100%" height="100%"></div>');
        }

        this.draw = SVG('mouseMoveCanvas').size(width, height);
    };

    this.drawLine = function (x1, y1, x2, y2, color) {
        if (this.draw) {
            var line = this.draw.line(x1, y1, x2, y2);
            line.stroke({ width: 1,color:color });
        }
    };

    this.drawCircle = function (x, y, color) {
        if (this.draw) {
            if (x > 4) {
                x = x - 4; // because of radius of 8 we need to center it
            }
            if (y > 4) {
                y = y - 4; // because of radius of 8 we need to center it
            }
            var circle = this.draw.circle(8);
            circle.fill(color);
            circle.move(x,y);
        }
    };

    this.appendContent = function (html) {
        return $('body').append(html);
    };

    this.initialMutation = function(event) {

        if (document.documentElement) {
            document.documentElement.remove();
        }

        if (document && document.doctype) {
            if (document.doctype && document.doctype.remove) {
                document.doctype.remove();
            } else if (document.doctype) {
                // fix remove is not available on IE
                document.removeChild(document.doctype);
            }
        }

        addRemoveSupport(window);

        this.mirror = new TreeMirror(document, {
            createElement: function(tagName, data) {
                if (!tagName) {
                    return;
                }

                tagName = tagName.toLowerCase();

                if (tagName === 'base') {
                    // prevent applying this element! we still need to have it in the dom eg for nth-child selector etc.
                    var element = document.createElement('NO_BASE');
                    element.style.display = 'none';
                    return element;

                } else if (tagName === 'script') {
                    // prevent execution of this element! we still need to have it in the dom eg for nth-child selector etc.
                    var element = document.createElement('NO_SCRIPT');
                    element.style.display = 'none';
                    return element;

                } else if (tagName === 'form') {
                    var element = document.createElement('FORM');
                    element.addEventListener('submit', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    });
                    return element;

                } else if (tagName === 'head') {
                    var element = document.createElement('HEAD');
                    element.appendChild(document.createElement('BASE'));
                    element.firstChild.href = baseUrl;
                    return element;

                } else if (tagName === 'a') {
                    var element = document.createElement('A');
                    element.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        return false;
                    });
                    return element;

                } else if (['svg', 'path', 'g', 'polygon', 'polyline', 'rect', 'text', 'circle', 'line'].indexOf(tagName) !== -1) {
                    return document.createElementNS('http://www.w3.org/2000/svg', tagName)
                } else if (tagName === 'meta') {
                    if (data && typeof data.attributes === 'object') {
                        if ('http-equiv' in data.attributes && data.attributes['http-equiv']) {
                            var httpEquiv = String(data.attributes['http-equiv']).toLowerCase();

                            if (httpEquiv === 'content-security-policy' || httpEquiv === 'refresh') {
                                return document.createElement('NO_META');
                            }
                        }
                        if ('name' in data.attributes && data.attributes['name']) {
                            var metaName = String(data.attributes['name']).toLowerCase();
                            if (metaName === 'csrf-token') {
                                return document.createElement('NO_META');
                            }
                        }
                    }
                }
            },
            setAttribute: function(node, name, value) {
                if (!name) {
                    return node;
                }

                var nameLower = String(name).toLowerCase();

                if (nameLower === 'src' && value && String(value).indexOf('/piwik.js') > 0) {
                    // we do not want to set piwik.js
                    return node;
                }

                if (nameLower === 'src' && value && String(value).indexOf('/HeatmapSessionRecording/') > 0) {
                    // we do not want to set configs.php etc
                    return node;
                }

                if ((nameLower === 'src' || nameLower === 'href' || name === 'background' || name === 'longdesc') && value && String(value).toLowerCase().indexOf('javascript:') >= 0) {
                    // we do not want to set any javascript URL
                    return node;
                }

                var blockedAttributes = ['onchange', 'onload', 'onunload', 'onerror', 'onclick', 'onfocus', 'onblur', 'onselect']
                if (blockedAttributes.indexOf(nameLower) > -1 || nameLower.indexOf('onmouse') === 0) {
                    // do not execute any onload method or when we set form element values
                    return node;
                }

                if (node.tagName === 'LINK') {
                    if (nameLower === 'crossorigin') {
                        // cross origin relevant for images only, not for scripts as we rename them anyway
                        return node
                    }

                    if (nameLower === 'integrity') {
                        // hash of a file should be ignored as file fetched later might have different hash etc
                        return node
                    }

                    if (requireSecureProtocol) {
                        if (nameLower === 'href' && value && String(value).indexOf('http:') === 0) {
                            value = convertUrlToSecureProtocolIfNeeded(value);
                            node.setAttribute(name, value);
                            return node;
                        }
                    }
                }

                if (node.tagName === 'IMG') {
                    if (requireSecureProtocol) {
                        if (nameLower === 'src' && value && String(value).indexOf('http:') === 0) {
                            value = convertUrlToSecureProtocolIfNeeded(value);
                            node.setAttribute(name, value);
                            return node;
                        }
                    }
                }

                if (node.tagName === 'FORM') {
                    if (requireSecureProtocol) {
                        if (nameLower === 'action' && value && String(value).indexOf('http:') === 0) {
                            value = convertUrlToSecureProtocolIfNeeded(value);
                            node.setAttribute(name, value);
                            return node;
                        }
                    }
                }

                if (node.tagName === 'IFRAME') {
                    if (requireSecureProtocol) {
                        if (nameLower === 'src' && value && String(value).indexOf('http:') === 0) {
                            value = convertUrlToSecureProtocolIfNeeded(value);
                            node.setAttribute(name, value);
                            return node;
                        }
                    }
                }
            }
        });

        if (event) {
            this.mirror.initialize(event.rootId, event.children);

            this.addClass('html', 'piwikHsr');
        }
    };

    this.addClass = function (selector, className) {
        $(selector).addClass(className);
    };

    this.applyMutation = function (event) {
        if (event) {
            this.mirror.applyChanged(event.rem || [], event.adOrMo || [], event.att || [], event.text || []);
        }
    };
    this.trim = function(text)
    {
        if (text && String(text) === text) {
            return text.replace(/^\s+|\s+$/g, '');
        }

        return text;
    };

    this.parseExcludedElementSelectors = function (excludedElements) {
        if (!excludedElements) {
            return [];
        }

        excludedElements = String(excludedElements);
        excludedElements = this.trim(excludedElements);

        if (!excludedElements) {
            return [];
        }

        excludedElements = excludedElements.split(',');

        if (!excludedElements || !excludedElements.length) {
            return [];
        }

        var selectors = [];
        for (var i = 0; i < excludedElements.length; i++) {
            var selector = this.trim(excludedElements[i]);
            selectors.push(selector);
        }
        return selectors;
    };

    this.excludeElements = function (excludedElements) {
        excludedElements = this.parseExcludedElementSelectors(excludedElements);
        if (!excludedElements || !excludedElements.length) {
            return;
        }

        var self = this;

        var style = (function() {
            var style = document.createElement('style');
            style.appendChild(document.createTextNode(''));
            document.head.appendChild(style);

            return style;
        })();

        for (var i = 0; i < excludedElements.length; i++) {
            var selector = excludedElements[i];
            if (selector && style && style.sheet) {
                if('insertRule' in style.sheet) {
                    style.sheet.insertRule(selector + "{ visibility: hidden; }", i);
                } else if('addRule' in sheet) {
                    style.sheet.addRule(selector, 'visibility: hidden; ', i);
                }

            }
        }
    };

    this.getCoordinatesInFrame = function (selector, offsetx, offsety, offsetAccuracy, ignoreHiddenElement) {
        var $node = $(selector);

        if (!$node.size()) {
            if (this.enableDebugMode) {
                if ('undefined' !== typeof console && 'undefined' !== typeof console.log){
                    console.log(selector, 'selector not found');
                }
            }
            return;
        }

        var width = $node.outerWidth();
        var height = $node.outerHeight();

        if (ignoreHiddenElement && ignoreHiddenElement === true && width === 0 || height === 0 || !$node.is(':visible')) {
            // not visible
            return;
        }

        var width = width / offsetAccuracy;
        var height = height / offsetAccuracy;
        var coordinates = $node.offset();

        var dataPoint = {
            x: parseInt(coordinates.left, 10) + parseInt(offsetx * width, 10),
            y: parseInt(coordinates.top, 10) + parseInt(offsety * height, 10),
        }

        return dataPoint;
    };

    this.getScrollTop = function () {
        return $(window).scrollTop();
    };

    this.getScrollLeft = function () {
        return $(window).scrollLeft();
    };

    this.getIframeHeight = function () {
        var documentHeight = Math.max(document.body ? document.body.offsetHeight : 0, document.body ? document.body.scrollHeight : 0, document.documentElement ? document.documentElement.offsetHeight : 0, document.documentElement ? document.documentElement.clientHeight : 0, document.documentElement ? document.documentElement.scrollHeight : 0);
        return documentHeight;
    };

    this.getIframeWidth = function () {
        var documentHeight = Math.max(document.body ? document.body.offsetWidth : 0, document.body ? document.body.scrollWidth : 0, document.documentElement ? document.documentElement.offsetWidth : 0, document.documentElement ? document.documentElement.clientWidth : 0, document.documentElement ? document.documentElement.scrollWidth : 0);
        return documentHeight;
    };

}
