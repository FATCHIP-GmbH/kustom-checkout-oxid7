if (!Array.prototype.remove) {
    Array.prototype.remove = function (item) {
        var index = this.indexOf(item);
        if (index !== -1) this.splice(index, 1);
        return this;
    };
}

function getParameterByName(name, url) {
    if (!url) url = window.location.href;
    name = name.replace(/[\[\]]/g, '\\$&');
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

var KustomApi;

(function () {
    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) === 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }

    // --- tiny helpers to mimic jQuery slide animations ---
    function slideDown(el, duration) {
        duration = duration || 400;
        el.style.removeProperty('display');
        let display = getComputedStyle(el).display;
        if (display === 'none') display = 'block';
        el.style.display = display;
        var height = el.scrollHeight;
        el.style.overflow = 'hidden';
        el.style.height = '0px';
        // force reflow
        el.getBoundingClientRect();
        el.style.transition = 'height ' + duration + 'ms';
        el.style.height = height + 'px';
        return new Promise(function (resolve) {
            setTimeout(function () {
                el.style.removeProperty('height');
                el.style.removeProperty('overflow');
                el.style.removeProperty('transition');
                resolve();
            }, duration);
        });
    }

    function slideUp(el, duration) {
        duration = duration || 400;
        el.style.overflow = 'hidden';
        el.style.height = el.scrollHeight + 'px';
        // force reflow
        el.getBoundingClientRect();
        el.style.transition = 'height ' + duration + 'ms';
        el.style.height = '0px';
        return new Promise(function (resolve) {
            setTimeout(function () {
                el.style.display = 'none';
                el.style.removeProperty('height');
                el.style.removeProperty('overflow');
                el.style.removeProperty('transition');
                resolve();
            }, duration);
        });
    }

    function isHidden(el) {
        return getComputedStyle(el).display === 'none';
    }

    function slideToggle(el, duration) {
        return isHidden(el) ? slideDown(el, duration) : slideUp(el, duration);
    }

    // --- dropdowns (formerly jQuery) ---
    Array.prototype.forEach.call(document.querySelectorAll('.drop-trigger'), function (trigger) {
        trigger.addEventListener('click', function (e) {
            var clicked = e.currentTarget;
            var container = clicked.closest('.drop-container');
            if (!container) return;

            container.classList.toggle('active');
            var content = container.querySelector('.drop-content');
            if (content) slideToggle(content, 400);

            // close others
            Array.prototype.forEach.call(document.querySelectorAll('.drop-container'), function (dc) {
                if (dc !== container) {
                    dc.classList.remove('active');
                    var c = dc.querySelector('.drop-content');
                    if (c && !isHidden(c)) slideUp(c, 400);
                }
            });
        });
    });

    var widgetPrototype = {
        sendRequest: function (promise, callback) {
            KustomApi.suspend();
            promise.then((function (response) {
                callback.call(this, response);
                KustomApi.resume();
            }).bind(this));
        },

        updateErrors: function (html) {
            // remove existing
            document.querySelectorAll('#content .alert-danger').forEach(function (n) {
                n.remove();
            });
            if (!html) return;

            // create nodes from HTML string
            var temp = document.createElement('div');
            temp.innerHTML = html;
            var parent = document.querySelector('#content');
            if (!parent) return;

            // prepend all children (keep order)
            var nodes = Array.from(temp.childNodes).filter(function (n) {
                return n.nodeType === 1;
            });
            nodes.reverse().forEach(function (node) {
                node.style.display = 'none';
                parent.insertBefore(node, parent.firstChild);
                slideDown(node, 400).then(function () {
                    setTimeout(function () {
                        slideUp(node, 400);
                    }, 4000);
                });
            });
        }
    };

    var addressWidget = Object.assign(Object.create(widgetPrototype), {
        $form: document.querySelector('form[name=address]'),
        $items: document.querySelectorAll('.js-kustom-address-list-item'),
        $selected: document.querySelector('.js-kustom-selected-address'),
        $input: document.querySelector('input[name="kustom_address_id"]'),
        $submitButton: document.getElementById('setDeliveryAddress'),

        selectAddress: function (event) {
            var target = event.target;
            if (!target) return;
            if (this.$selected) this.$selected.textContent = target.innerHTML;
            if (this.$input) {
                var id = target.parentNode && target.parentNode.getAttribute('data-address-id');
                this.$input.value = id || '';
            }
        },

        onInit: function () {
            var self = this;
            Array.prototype.forEach.call(this.$items, function (item) {
                item.addEventListener('click', self.selectAddress.bind(self));
            });
        }
    });

    var vouchersWidget = Object.assign(Object.create(widgetPrototype), {
        $content: null,
        $form: document.querySelector('form[name=voucher]'),
        $input: document.querySelector('input[name=voucherNr]'),
        $submitButton: document.getElementById('submitVoucher'),
        $voucherWidget: document.getElementById('kustomVoucherWidget'),

        submitVoucher: function (event) {
            event.preventDefault();
            var form = this.$form;
            if (!form) return;
            var action = form.getAttribute('action') || window.location.href;
            var fd = new FormData(form);
            var params = new URLSearchParams();
            fd.forEach(function (v, k) {
                params.append(k, v);
            });

            var p = fetch(action, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: params.toString()
            }).then(function (r) {
                return r.text();
            });

            this.$voucherWidget.style.display = "none";
            this.sendRequest(p, this.updateWidget);
        },

        removeVoucher: function (event) {
            event.preventDefault();
            var a = event.target && (event.target.closest('a') || (event.target.href ? event.target : null));
            if (!a) return;
            var url = a.href;
            var p = fetch(url, {method: 'GET'}).then(function (r) {
                return r.text();
            });
            this.$voucherWidget.style.display = "none";
            this.sendRequest(p, this.updateWidget);
        },

        /**
         * Updates widget content and handling error displaying
         * If additional
         * @param response stringified json response
         */
        updateWidget: function (response) {
            try {
                var data = (typeof response === 'string') ? JSON.parse(response) : response;
                this.updateErrors(data && data.error);
                if (this.$content) {
                    var box = this.$content.querySelector('.voucherData');
                    if (box) box.innerHTML = data && data.vouchers ? data.vouchers : '';
                }
            } catch (e) {
                // swallow parse errors to avoid breaking UX
                console.error('Voucher widget update failed:', e);
            }
        },

        onInit: function () {
            if (!this.$form) return;
            this.$content = this.$form.closest('.drop-content');
            if (this.$submitButton) this.$submitButton.addEventListener('click', this.submitVoucher.bind(this));

            if (this.$content) {
                this.$content.addEventListener('click', (function (e) {
                    var link = e.target && e.target.closest('.couponData a');
                    if (link) {
                        e.preventDefault();
                        this.removeVoucher({
                                               preventDefault: function () {
                                               }, target: link
                                           });
                    }
                }).bind(this));
            }
        }
    });

    // initialize widgets
    addressWidget.onInit();
    vouchersWidget.onInit();

    window._klarnaCheckout(function (api) {
        KustomApi = api;

        var urlShopId = getParameterByName('shp', window.location.search);
        var urlShopParam = urlShopId ? '&shp=' + urlShopId : '';

        /** vars track changes of this values during the 'change' event */
        var country, eventsInProgress = [];

        var kustomSendXHR = function (data, suspendMode) {
            if (eventsInProgress.indexOf(data.action) > -1) {
                console.warn('ACTION ' + data.action + ' already in progress.');
                return Promise.resolve();
            }
            eventsInProgress = eventsInProgress.concat(data.action);

            suspendMode = typeof suspendMode !== 'undefined' ? suspendMode : true;
            if (suspendMode) api.suspend();

            return fetch('?cl=order&fnc=updateKustomAjax' + urlShopParam, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify(data)
            }).then(function (r) {
                if (suspendMode) api.resume();
                return r.json();
            }).then(function (response) {
                eventsInProgress.remove(data.action);
                if (response.status === 'redirect') {
                    localStorage.setItem('skipKustomEvents', '1'); // will skip ajax events on iframe render
                    window.location.href = response.data.url;
                    return;
                }
                if (response.status === 'update_voucher_widget') {
                    fetch('?cl=KustomAjax&fnc=updateVouchers')
                        .then(function (r) {
                            return r.text();
                        })
                        .then(vouchersWidget.updateWidget.bind(vouchersWidget));
                }
            }).catch(function (err) {
                if (suspendMode) try {
                    api.resume();
                } catch (e) {
                }
                console.error('kustomSendXHR failed:', err);
            });
        };

        api.on({
                   'shipping_option_change': function shipping_option_change(eventData) {
                       eventData.action = arguments.callee.name;
                       kustomSendXHR(eventData);
                   },
                   'shipping_address_change': function shipping_address_change(eventData) {
                       eventData.action = arguments.callee.name;
                       kustomSendXHR(eventData);
                   },
                   'change': function change(eventData) {
                       eventData.action = arguments.callee.name;
                       // Shows modal after iframe is loaded and there is no user data injected
                       if (getCookie('blockCountryModal') !== '1') {
                           if (typeof showModal !== 'undefined' && showModal) {
                               var modal = document.getElementById('myModal');
                               if (modal && typeof $(modal)?.modal === 'function') {
                                   // If Bootstrap's JS is present globally (non-jQuery version or adapter), try to open via data API
                                   try {
                                       modal.dispatchEvent(new Event('show.bs.modal'));
                                   } catch (e) {
                                   }
                               } else if (modal) {
                                   // naive fallback: just display it
                                   modal.style.display = 'block';
                               }
                               document.cookie = 'blockCountryModal=1';
                           }
                       }

                       // Sends newly selected country to the backend
                       if (country && (country !== eventData.country)) {
                           kustomSendXHR(eventData, false);
                       }
                       country = eventData.country;
                   }
               });
    });
})();
