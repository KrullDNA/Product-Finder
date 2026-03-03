(function ($) {
    'use strict';

    /**
     * Product Finder – Frontend Controller
     *
     * Manages the multi-step quiz flow:
     *   Question slides → Email capture → Loading → Results
     */
    function ProductFinder($el) {
        this.$el        = $el;
        this.finderId   = $el.data('finder-id');
        this.questions  = $el.data('questions') || [];
        this.options    = $el.data('options') || {};
        this.current    = 0;
        this.answers    = {};  // { questionIndex: [answerIndices] }
        this.totalQ     = this.questions.length;

        this.$progress      = $el.find('.pf-progress-fill');
        this.$progressText  = $el.find('.pf-progress-text');
        this.$container     = $el.find('.pf-questions-container');
        this.$emailScreen   = $el.find('.pf-email-screen');
        this.$loadingScreen = $el.find('.pf-loading-screen');
        this.$resultsScreen = $el.find('.pf-results-screen');

        this.init();
    }

    ProductFinder.prototype = {

        init: function () {
            if (!this.totalQ) return;
            this.applyI18n();
            this.renderQuestion(0);
            this.updateProgress();
            this.bindGlobal();
        },

        /* ───────── i18n labels ───────── */

        applyI18n: function () {
            var i = pfFrontend.i18n;
            this.$emailScreen.find('.pf-email-title').text(i.email_label);
            this.$emailScreen.find('.pf-email-input').attr('placeholder', i.email_placeholder);
            this.$emailScreen.find('.pf-send-email').text(i.send_results);
            this.$emailScreen.find('.pf-skip-email').text(i.skip_email);

            // Custom heading overrides from Elementor widget data attributes
            var customLoading = this.$el.data('loading-heading');
            var customResults = this.$el.data('results-heading');

            this.$loadingScreen.find('.pf-loading-text').text(customLoading || i.loading);
            this.$resultsScreen.find('.pf-results-title').text(customResults || i.your_results);
            this.$resultsScreen.find('.pf-start-over').text(i.start_over);
        },

        /* ───────── Global events ───────── */

        bindGlobal: function () {
            var self = this;

            this.$el.on('click', '.pf-answer-option', function () {
                self.handleAnswerClick($(this));
            });

            // Text answer hover-out animation: slide out to the right.
            // Only attach on devices that truly support hover (no touch ghost events).
            if (window.matchMedia('(hover: hover)').matches) {
                this.$el.on('mouseenter', '.pf-answer-option--text', function () {
                    $(this).removeClass('pf-hover-out pf-no-transition');
                });
                this.$el.on('mouseleave', '.pf-answer-option--text', function () {
                    if (!$(this).hasClass('pf-selected')) {
                        $(this).addClass('pf-hover-out');
                        // After slide-out animation, snap ::before back to start without transition
                        // (prevents flash through translateX(0) on the way from 100% to -100%)
                        var $opt = $(this);
                        setTimeout(function () {
                            $opt.addClass('pf-no-transition').removeClass('pf-hover-out');
                            // Force reflow so the snap happens before re-enabling transitions
                            void $opt[0].offsetHeight;
                            requestAnimationFrame(function () {
                                $opt.removeClass('pf-no-transition');
                            });
                        }, 350);
                    }
                });
            }

            this.$el.on('click', '.pf-btn-continue', function () {
                self.transitionOut(function () { self.goNext(); });
            });

            this.$el.on('click', '.pf-btn-back', function () {
                self.transitionOut(function () { self.goBack(); });
            });

            this.$el.on('click', '.pf-skip-email', function () {
                self.showLoading();
            });

            this.$el.on('click', '.pf-send-email', function () {
                self.sendEmail();
            });

            this.$el.on('click', '.pf-start-over', function () {
                self.startOver();
            });

            this.$el.on('click', '.pf-btn-view-results', function () {
                self.showLoading();
            });
        },

        /* ───────── Render question ───────── */

        renderQuestion: function (idx) {
            var q = this.questions[idx];
            if (!q) return;

            // When auto-advancing from a single-select tap, suppress pointer
            // events on the incoming answers so the browser cannot apply a
            // ghost hover / active state to whatever element lands under the
            // finger.  The class is baked into the HTML *before* DOM insertion
            // so there is zero window for the browser to match :hover.
            var suppress = this._suppressTouch;
            this._suppressTouch = false;
            var noPtr = suppress ? ' pf-no-pointer' : '';

            var hasImages = q.answers.some(function (a) { return !!a.image; });
            var html = '<div class="pf-question-slide" data-qi="' + idx + '">';

            var instructionText = q.instruction || (q.multiple ? 'Select all that apply' : 'Select one option');

            if (hasImages) {
                // Image grid layout
                html += '<h2 class="pf-question-text pf-question-text--center">' + this.escHtml(q.text) + '</h2>';
                html += '<p class="pf-question-instruction pf-question-instruction--center">' + this.escHtml(instructionText) + '</p>';
                html += '<div class="pf-answers-grid pf-answers-grid--images">';
                for (var i = 0; i < q.answers.length; i++) {
                    var a = q.answers[i];
                    var selected = this.isSelected(idx, i) ? ' pf-selected' : '';
                    html += '<div class="pf-answer-option pf-answer-option--image' + selected + noPtr + '" data-ai="' + i + '">';
                    if (a.image) {
                        html += '<div class="pf-answer-img-wrap"><img src="' + this.escHtml(a.image) + '" alt="' + this.escHtml(a.text) + '"></div>';
                    }
                    html += '<span class="pf-answer-text">' + this.escHtml(a.text) + '</span>';
                    if (a.description) {
                        html += '<span class="pf-answer-desc">' + this.escHtml(a.description) + '</span>';
                    }
                    if (q.multiple) {
                        html += '<span class="pf-checkbox"><span class="pf-check-icon"></span></span>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            } else {
                // Two-column text layout
                html += '<div class="pf-text-layout">';
                html += '<div class="pf-text-left">';
                html += '<h2 class="pf-question-text">' + this.escHtml(q.text) + '</h2>';
                html += '<p class="pf-question-instruction">' + this.escHtml(instructionText) + '</p>';
                html += '</div>';
                html += '<div class="pf-text-right">';
                html += '<div class="pf-answers-grid pf-answers-grid--text">';
                for (var j = 0; j < q.answers.length; j++) {
                    var b = q.answers[j];
                    var sel = this.isSelected(idx, j) ? ' pf-selected' : '';
                    html += '<div class="pf-answer-option pf-answer-option--text' + sel + noPtr + '" data-ai="' + j + '">';
                    html += '<span class="pf-answer-text">' + this.escHtml(b.text) + '</span>';
                    if (q.multiple) {
                        html += '<span class="pf-checkbox"><span class="pf-check-icon"></span></span>';
                    }
                    html += '</div>';
                }
                html += '</div>';
                html += '</div>';
                html += '</div>';
            }

            // Navigation buttons
            html += '<div class="pf-nav-buttons">';
            if (idx > 0) {
                html += '<button type="button" class="pf-btn pf-btn-secondary pf-btn-back">' + pfFrontend.i18n.back + '</button>';
            } else {
                html += '<span></span>';
            }
            if (q.multiple) {
                var hasSelection = this.answers[idx] && this.answers[idx].length > 0;
                html += '<button type="button" class="pf-btn pf-btn-primary pf-btn-continue' + (hasSelection ? '' : ' pf-btn-disabled') + '"' + (hasSelection ? '' : ' disabled') + '>' + pfFrontend.i18n.next + '</button>';
            }
            html += '</div>';

            html += '</div>';

            this.$container.html(html);

            // Lift the pointer suppression after a short cooldown.
            if (suppress) {
                var $answers = this.$container.find('.pf-answer-option');
                setTimeout(function () { $answers.removeClass('pf-no-pointer'); }, 400);
            }

            // Animate in
            this.$container.find('.pf-question-slide').addClass('pf-slide-in');
        },

        /* ───────── Answer click ───────── */

        handleAnswerClick: function ($opt) {
            var qi = this.current;
            var ai = $opt.data('ai');
            var q  = this.questions[qi];

            if (q.multiple) {
                // Toggle selection
                $opt.toggleClass('pf-selected');
                if (!this.answers[qi]) this.answers[qi] = [];
                var pos = this.answers[qi].indexOf(ai);
                if (pos === -1) {
                    this.answers[qi].push(ai);
                } else {
                    this.answers[qi].splice(pos, 1);
                }
                // Enable/disable continue button
                var $btn = this.$container.find('.pf-btn-continue');
                if (this.answers[qi].length > 0) {
                    $btn.removeClass('pf-btn-disabled').prop('disabled', false);
                } else {
                    $btn.addClass('pf-btn-disabled').prop('disabled', true);
                }
            } else {
                // Single select: record and advance
                this.$container.find('.pf-answer-option').removeClass('pf-selected');
                $opt.addClass('pf-selected');
                this.answers[qi] = [ai];

                // Fade the current question out, then render the next one.
                // The fade-out gap ensures no element sits under the finger
                // when the new answers appear (prevents ghost hover on touch).
                var self = this;
                var $slide = this.$container.find('.pf-question-slide');
                setTimeout(function () {
                    $slide.addClass('pf-slide-out');
                    // Wait for the fade-out animation (250ms) before rendering
                    setTimeout(function () {
                        self._suppressTouch = true;
                        self.goNext();
                    }, 280);
                }, 200);
            }
        },

        isSelected: function (qi, ai) {
            return this.answers[qi] && this.answers[qi].indexOf(ai) !== -1;
        },

        /* ───────── Navigation ───────── */

        /**
         * Fade out the current slide, then call a callback to render the next view.
         * If no slide is visible (e.g. first render) the callback fires immediately.
         */
        transitionOut: function (cb) {
            var $slide = this.$container.find('.pf-question-slide');
            if (!$slide.length) { cb(); return; }
            $slide.addClass('pf-slide-out');
            setTimeout(cb, 280); // slightly longer than the 250ms animation
        },

        goNext: function () {
            var self = this;
            if (this.current < this.totalQ - 1) {
                this.current++;
                this.renderQuestion(this.current);
                this.updateProgress();
            } else {
                // All questions answered – show email screen
                this.showEmail();
            }
        },

        goBack: function () {
            var self = this;
            if (this.current > 0) {
                this.current--;
                this.renderQuestion(this.current);
                this.updateProgress();
            }
        },

        /* ───────── Progress bar ───────── */

        updateProgress: function () {
            // Progress reflects the current position in the quiz.
            // current is 0-based, so current/totalQ gives the fraction
            // of the quiz the user has reached.
            var pct = Math.round((this.current / this.totalQ) * 100);
            this.$progress.css('width', pct + '%');
            this.$progressText.text(pct + '%');
        },

        /* ───────── Email screen ───────── */

        showEmail: function () {
            this.updateProgress();
            this.$container.hide();
            this.$emailScreen.fadeIn(300);
        },

        sendEmail: function () {
            var self  = this;
            var email = this.$emailScreen.find('.pf-email-input').val().trim();
            var $msg  = this.$emailScreen.find('.pf-email-message');

            if (!email) {
                $msg.text('Please enter a valid email.').css('color', '#b32d2e').show();
                return;
            }

            // First compute results to get product IDs, then send email
            this.computeResults(function (data) {
                var postData = {
                    action: 'pf_send_results_email',
                    nonce: pfFrontend.nonce,
                    finder_id: self.finderId,
                    email: email,
                    product_ids: data.product_ids
                };

                // In Beauty mode, pass variation-aware product data for the email.
                var opts = data.options || self.options;
                if (opts.finder_type === 'beauty' && data.products && data.products.length) {
                    postData.products_data = JSON.stringify(data.products);
                }

                $.post(pfFrontend.ajax_url, postData, function (res) {
                    if (res.success) {
                        $msg.text(pfFrontend.i18n.email_success).css('color', '#00a32a').show();
                        // Show view results button
                        self.$emailScreen.find('.pf-send-email').text(pfFrontend.i18n.view_results).removeClass('pf-send-email').addClass('pf-btn-view-results');
                        self._cachedResults = data;
                    } else {
                        $msg.text(res.data && res.data.message ? res.data.message : pfFrontend.i18n.email_fail).css('color', '#b32d2e').show();
                    }
                });
            });
        },

        /* ───────── Loading screen ───────── */

        showLoading: function () {
            var self = this;
            this.$emailScreen.hide();
            this.$container.hide();
            this.$loadingScreen.fadeIn(300);

            // Update progress to 100%
            this.$progress.css('width', '100%');
            this.$progressText.text(pfFrontend.i18n.complete);

            if (this._cachedResults) {
                setTimeout(function () { self.showResults(self._cachedResults); }, 1500);
            } else {
                this.computeResults(function (data) {
                    // Show loading for at least 1.5s for UX
                    setTimeout(function () { self.showResults(data); }, 1500);
                });
            }
        },

        /* ───────── Compute results ───────── */

        computeResults: function (callback) {
            var self = this;
            $.post(pfFrontend.ajax_url, {
                action: 'pf_compute_results',
                nonce: pfFrontend.nonce,
                finder_id: this.finderId,
                answers: JSON.stringify(this.answers)
            }, function (res) {
                // Debug: log the full AJAX response
                if (res && res.data && res.data.debug) {
                    console.group('[Product Finder] Debug – compute_results');
                    for (var i = 0; i < res.data.debug.length; i++) {
                        console.log(res.data.debug[i]);
                    }
                    console.log('listing_html length:', (res.data.listing_html || '').length);
                    console.log('products count:', (res.data.products || []).length);
                    console.groupEnd();
                }

                if (res.success) {
                    callback(res.data);
                } else {
                    console.warn('[Product Finder] AJAX returned success=false', res);
                    callback({ products: [], product_ids: [], listing_html: '', options: self.options });
                }
            }).fail(function (jqXHR, textStatus, errorThrown) {
                console.error('[Product Finder] AJAX FAILED:', textStatus, errorThrown);
                console.error('[Product Finder] Response text:', jqXHR.responseText ? jqXHR.responseText.substring(0, 1000) : '(empty)');
                callback({ products: [], product_ids: [], listing_html: '', options: self.options });
            });
        },

        /* ───────── Results screen ───────── */

        showResults: function (data) {
            var self = this;
            this.$loadingScreen.hide();

            console.log('[Product Finder] showResults – styles:', (data.styles || []).length,
                'scripts:', (data.scripts || []).length,
                'listing_html length:', (data.listing_html || '').length);

            // If CrocoBlock listing HTML was returned and has real content, use it
            var listingHtml = (data.listing_html || '').trim();
            if (listingHtml.length > 0) {
                // 1. Load any CSS enqueued during server-side rendering
                this.loadStyles(data.styles || []);

                // 2. Inject the listing HTML so DOM elements exist
                this.$resultsScreen.find('.pf-results-container').html(listingHtml);

                console.log('[Product Finder] HTML injected. Variation forms:',
                    this.$resultsScreen.find('.variations_form').length,
                    'Swatch wrappers:',
                    this.$resultsScreen.find('.fif-vse-swatches').length,
                    'Elementor widgets:',
                    this.$resultsScreen.find('.elementor-widget').length);

                // 3. Load any JS enqueued during rendering, then re-init widgets
                this.loadScripts(data.scripts || [], function () {
                    self.initDynamicContent();
                });
            } else {
                // Fallback: render product cards
                this.renderFallbackResults(data);
            }

            this.$resultsScreen.fadeIn(300);
        },

        /**
         * Dynamically load CSS files that were enqueued server-side
         * during CrocoBlock listing rendering (e.g. swatch plugin CSS).
         */
        loadStyles: function (urls) {
            console.log('[Product Finder] loadStyles:', urls.length, 'URL(s)', urls);
            for (var i = 0; i < urls.length; i++) {
                // Check if this stylesheet is already loaded.
                var alreadyLoaded = false;
                var links = document.getElementsByTagName('link');
                var base = urls[i].split('?')[0];
                for (var k = 0; k < links.length; k++) {
                    if (links[k].href && links[k].href.split('?')[0].indexOf(base.replace(/^https?:/, '')) !== -1) {
                        alreadyLoaded = true;
                        break;
                    }
                }
                if (alreadyLoaded) {
                    console.log('[Product Finder] CSS already loaded, skipping:', urls[i]);
                    continue;
                }
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = urls[i];
                document.head.appendChild(link);
                console.log('[Product Finder] Loaded CSS:', urls[i]);
            }
        },

        /**
         * Dynamically load JS files that were enqueued server-side,
         * then invoke the callback once all scripts have loaded.
         */
        loadScripts: function (urls, callback) {
            console.log('[Product Finder] loadScripts:', urls.length, 'URL(s)', urls);

            // Filter out scripts already present on the page.
            var toLoad = [];
            var existingSrcs = [];
            var scripts = document.getElementsByTagName('script');
            for (var k = 0; k < scripts.length; k++) {
                if (scripts[k].src) {
                    existingSrcs.push(scripts[k].src.split('?')[0]);
                }
            }
            for (var i = 0; i < urls.length; i++) {
                var base = urls[i].split('?')[0];
                if (existingSrcs.indexOf(base) === -1) {
                    toLoad.push(urls[i]);
                } else {
                    console.log('[Product Finder] Script already on page, skipping:', urls[i]);
                }
            }

            console.log('[Product Finder] Scripts to load (after dedup):', toLoad.length);

            if (!toLoad.length) {
                callback();
                return;
            }

            var loaded = 0;
            for (var j = 0; j < toLoad.length; j++) {
                var s = document.createElement('script');
                s.src = toLoad[j];
                s.onload = s.onerror = function () {
                    var ok = this.readyState ? /loaded|complete/.test(this.readyState) : true;
                    console.log('[Product Finder] Script ' + (ok ? 'loaded' : 'FAILED') + ':', this.src);
                    if (++loaded >= toLoad.length) {
                        callback();
                    }
                };
                document.body.appendChild(s);
            }
        },

        /**
         * Re-initialize third-party widget JS on dynamically loaded content.
         *
         * After AJAX-injected listing HTML is in the DOM and any missing
         * scripts have been loaded, trigger Elementor, WooCommerce and
         * JetEngine initialization so swatch plugins, add-to-cart buttons
         * and other interactive widgets work correctly.
         */
        initDynamicContent: function () {
            var $container = this.$resultsScreen.find('.pf-results-container');

            console.log('[Product Finder] initDynamicContent – starting');

            // 1. Add .product class to listing items that contain variation forms.
            //    Swatch plugins (FiF VSE) use $wrap.closest('.product') to scope
            //    their search for the correct variation form.
            $container.find('.variations_form').each(function () {
                $(this).closest(
                    '.jet-listing-grid__item,' +
                    '.jet-listing-grid__items > div,' +
                    '.pf-result-item,' +
                    '.elementor-widget-wrap,' +
                    '.e-con-inner,' +
                    '.e-con'
                ).addClass('product');
            });

            // 2. Initialize WooCommerce variation forms (must happen before
            //    swatch initialization so WC events are ready).
            var formsInited = 0;
            if ($.fn.wc_variation_form) {
                $container.find('.variations_form').each(function () {
                    formsInited++;
                    $(this).wc_variation_form().trigger('check_variations');
                });
            }
            console.log('[Product Finder] WC variation forms initialized:', formsInited);

            // 3. Ensure Elementor widget hooks are registered.
            //    When a swatch plugin's JS is loaded dynamically (after
            //    elementor/frontend/init already fired), its hook registration
            //    code inside $(window).on('elementor/frontend/init') hasn't
            //    executed.  Re-triggering causes plugins to register their
            //    element_ready handlers.
            if (window.elementorFrontend) {
                $(window).trigger('elementor/frontend/init');
            }

            // 4. Trigger Elementor's element_ready for all widgets in the
            //    container.  This calls registered handlers (e.g. initWrap
            //    in FiF VSE) which initialise the swatch UI.
            //    New DOM elements don't have the fifVseInit flag so initWrap
            //    will run on them; already-initialised elements are skipped.
            var widgetsTriggered = 0;
            if (window.elementorFrontend && elementorFrontend.elementsHandler) {
                if (typeof elementorFrontend.elementsHandler.runReadyTrigger === 'function') {
                    $container.find('.elementor-widget').each(function () {
                        widgetsTriggered++;
                        try {
                            elementorFrontend.elementsHandler.runReadyTrigger($(this));
                        } catch (e) {
                            console.warn('[Product Finder] runReadyTrigger error:', e);
                        }
                    });
                }
            }
            console.log('[Product Finder] Elementor widgets triggered:', widgetsTriggered);

            // 5. Log widget types for debugging
            $container.find('[data-widget_type]').each(function () {
                console.log('[Product Finder] Widget data-widget_type:', $(this).data('widget_type'));
            });
            $container.find('.elementor-widget').each(function () {
                var classes = $(this).attr('class') || '';
                var widgetClass = classes.match(/elementor-widget-(\S+)/);
                console.log('[Product Finder] Widget class:', widgetClass ? widgetClass[1] : '(none)',
                    'has data-widget_type:', !!$(this).attr('data-widget_type'));
            });

            // 6. Fallback: directly fire element_ready hooks by widget type.
            //    Handles cases where runReadyTrigger is unavailable or the
            //    Elementor version uses a different internal API.
            if (window.elementorFrontend && elementorFrontend.hooks) {
                $container.find('[data-widget_type]').each(function () {
                    var widgetType = $(this).data('widget_type');
                    if (widgetType) {
                        try {
                            elementorFrontend.hooks.doAction(
                                'frontend/element_ready/' + widgetType, $(this)
                            );
                            elementorFrontend.hooks.doAction(
                                'frontend/element_ready/global', $(this)
                            );
                        } catch (e) {}
                    }
                });
            }

            // 7. Direct swatch widget initialization fallback.
            //    If the swatch plugin's JS was loaded dynamically, its
            //    $(document).ready() should have already initialised swatches
            //    (jQuery fires ready callbacks immediately when the document
            //    is already ready).  Check for fifVseInit and only act on
            //    un-initialised wrappers.
            var $swatchWraps = $container.find('.fif-vse-swatches');
            if ($swatchWraps.length) {
                console.log('[Product Finder] Direct swatch init: found', $swatchWraps.length, 'wrapper(s)');
                $swatchWraps.each(function () {
                    var $wrap = $(this);

                    // Already initialised – skip to avoid double-init.
                    if ($wrap.data('fifVseInit')) {
                        console.log('[Product Finder] Swatch already initialised, skipping');
                        return;
                    }

                    // Try firing the swatch widget's specific Elementor hook.
                    var $widget = $wrap.closest('.elementor-widget');
                    if ($widget.length && window.elementorFrontend && elementorFrontend.hooks) {
                        console.log('[Product Finder] Firing swatch hook on widget',
                            'widget_type:', $widget.attr('data-widget_type'));
                        try {
                            elementorFrontend.hooks.doAction(
                                'frontend/element_ready/fif_vse_variation_swatches.default',
                                $widget
                            );
                        } catch (e) {
                            console.warn('[Product Finder] Swatch hook error:', e);
                        }
                    }
                });
            }

            // 8. Trigger generic post-load event (many WP plugins listen for this)
            $(document.body).trigger('post-load');

            // WooCommerce cart fragments refresh
            $(document.body).trigger('wc_fragment_refresh');

            console.log('[Product Finder] initDynamicContent – complete');
        },

        renderFallbackResults: function (data) {
            var opts = data.options || this.options;
            var colsD = opts.cols_desktop || 3;
            var colsT = opts.cols_tablet || 2;
            var colsM = opts.cols_mobile || 1;
            var isBeauty = (opts.finder_type === 'beauty');

            var html = '<div class="pf-results-grid pf-cols-d-' + colsD + ' pf-cols-t-' + colsT + ' pf-cols-m-' + colsM + '">';
            var products = data.products || [];

            for (var i = 0; i < products.length; i++) {
                var p = products[i];
                html += '<div class="pf-result-card">';

                // Match badge
                if (p.match_pct) {
                    html += '<span class="pf-match-badge">' + p.match_pct + '% match</span>';
                }

                if (p.image) {
                    html += '<a href="' + this.escHtml(p.permalink) + '" class="pf-result-img-link"><img src="' + this.escHtml(p.image) + '" alt="' + this.escHtml(p.name) + '"></a>';
                }
                html += '<div class="pf-result-info">';
                html += '<h4 class="pf-result-name"><a href="' + this.escHtml(p.permalink) + '">' + this.escHtml(p.name) + '</a></h4>';
                html += '<div class="pf-result-price">' + p.price + '</div>';

                // Recommendation reasons based on user answers
                if (p.reasons && p.reasons.length) {
                    html += '<ul class="pf-match-reasons">';
                    for (var r = 0; r < p.reasons.length; r++) {
                        html += '<li>' + this.escHtml(p.reasons[r]) + '</li>';
                    }
                    html += '</ul>';
                }

                // Add to cart button
                if (isBeauty && p.is_variable && p.variation_id) {
                    // Beauty mode: add specific variation to cart
                    html += '<button type="button" class="pf-btn pf-btn-primary pf-atc-btn pf-atc-variation-btn"'
                        + ' data-product_id="' + p.id + '"'
                        + ' data-variation_id="' + p.variation_id + '"';
                    // Include variation attributes as data attrs
                    if (p.variation_attributes) {
                        for (var attrKey in p.variation_attributes) {
                            if (p.variation_attributes.hasOwnProperty(attrKey)) {
                                html += ' data-' + this.escHtml(attrKey) + '="' + this.escHtml(p.variation_attributes[attrKey]) + '"';
                            }
                        }
                    }
                    html += '>' + pfFrontend.i18n.add_to_cart + '</button>';
                }

                html += '</div>';
                html += '</div>';
            }

            html += '</div>';
            this.$resultsScreen.find('.pf-results-container').html(html);
        },

        /* ───────── Start over ───────── */

        startOver: function () {
            this.current = 0;
            this.answers = {};
            this._cachedResults = null;

            this.$resultsScreen.hide();
            this.$emailScreen.hide();
            this.$loadingScreen.hide();

            // Reset email screen
            this.$emailScreen.find('.pf-email-input').val('');
            this.$emailScreen.find('.pf-email-message').hide();
            this.$emailScreen.find('.pf-btn-view-results').text(pfFrontend.i18n.send_results).removeClass('pf-btn-view-results').addClass('pf-send-email');

            this.$container.show();
            this.renderQuestion(0);
            this.updateProgress();
        },

        /* ───────── Utilities ───────── */

        escHtml: function (str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    /* ───────── Initialise all finders on page ───────── */

    $(function () {
        $('.pf-finder').each(function () {
            new ProductFinder($(this));
        });
    });

    /* ───────── PF Add to Cart: quantity sync ───────── */

    // When the user changes the quantity input, update the sibling
    // button's data-quantity so WooCommerce's AJAX add-to-cart
    // picks up the correct amount.
    $(document).on('change input', '.pf-atc-qty', function () {
        var qty = parseInt($(this).val(), 10) || 1;
        $(this).siblings('.pf-atc-btn').attr('data-quantity', qty);
    });

})(jQuery);
