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
            this.$loadingScreen.find('.pf-loading-text').text(i.loading);
            this.$resultsScreen.find('.pf-results-title').text(i.your_results);
            this.$resultsScreen.find('.pf-start-over').text(i.start_over);
        },

        /* ───────── Global events ───────── */

        bindGlobal: function () {
            var self = this;

            this.$el.on('click', '.pf-answer-option', function () {
                self.handleAnswerClick($(this));
            });

            this.$el.on('click', '.pf-btn-continue', function () {
                self.goNext();
            });

            this.$el.on('click', '.pf-btn-back', function () {
                self.goBack();
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

            var hasImages = q.answers.some(function (a) { return !!a.image; });
            var html = '<div class="pf-question-slide" data-qi="' + idx + '">';

            if (hasImages) {
                // Image grid layout
                html += '<h2 class="pf-question-text pf-question-text--center">' + this.escHtml(q.text) + '</h2>';
                html += '<div class="pf-answers-grid pf-answers-grid--images">';
                for (var i = 0; i < q.answers.length; i++) {
                    var a = q.answers[i];
                    var selected = this.isSelected(idx, i) ? ' pf-selected' : '';
                    html += '<div class="pf-answer-option pf-answer-option--image' + selected + '" data-ai="' + i + '">';
                    if (a.image) {
                        html += '<div class="pf-answer-img-wrap"><img src="' + this.escHtml(a.image) + '" alt="' + this.escHtml(a.text) + '"></div>';
                    }
                    html += '<span class="pf-answer-text">' + this.escHtml(a.text) + '</span>';
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
                html += '<p class="pf-question-hint">' + (q.multiple ? 'Select all that apply' : 'Select one option') + '</p>';
                html += '</div>';
                html += '<div class="pf-text-right">';
                html += '<div class="pf-answers-grid pf-answers-grid--text">';
                for (var j = 0; j < q.answers.length; j++) {
                    var b = q.answers[j];
                    var sel = this.isSelected(idx, j) ? ' pf-selected' : '';
                    html += '<div class="pf-answer-option pf-answer-option--text' + sel + '" data-ai="' + j + '">';
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
                html += '<button type="button" class="pf-btn pf-btn-primary pf-btn-continue">' + pfFrontend.i18n.next + '</button>';
            }
            html += '</div>';

            html += '</div>';

            this.$container.html(html);

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
            } else {
                // Single select: record and advance
                this.$container.find('.pf-answer-option').removeClass('pf-selected');
                $opt.addClass('pf-selected');
                this.answers[qi] = [ai];

                // Auto-advance after a short delay
                var self = this;
                setTimeout(function () { self.goNext(); }, 350);
            }
        },

        isSelected: function (qi, ai) {
            return this.answers[qi] && this.answers[qi].indexOf(ai) !== -1;
        },

        /* ───────── Navigation ───────── */

        goNext: function () {
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
            if (this.current > 0) {
                this.current--;
                this.renderQuestion(this.current);
                this.updateProgress();
            }
        },

        /* ───────── Progress bar ───────── */

        updateProgress: function () {
            // Count how many questions have been answered
            var answered = 0;
            for (var i = 0; i < this.totalQ; i++) {
                if (this.answers[i] && this.answers[i].length) {
                    answered++;
                }
            }
            var pct = Math.round((answered / this.totalQ) * 100);
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
                $.post(pfFrontend.ajax_url, {
                    action: 'pf_send_results_email',
                    nonce: pfFrontend.nonce,
                    finder_id: self.finderId,
                    email: email,
                    product_ids: data.product_ids
                }, function (res) {
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
            $.post(pfFrontend.ajax_url, {
                action: 'pf_compute_results',
                nonce: pfFrontend.nonce,
                finder_id: this.finderId,
                answers: JSON.stringify(this.answers)
            }, function (res) {
                if (res.success) {
                    callback(res.data);
                }
            });
        },

        /* ───────── Results screen ───────── */

        showResults: function (data) {
            this.$loadingScreen.hide();

            // If CrocoBlock listing HTML was returned, use it
            if (data.listing_html) {
                this.$resultsScreen.find('.pf-results-container').html(data.listing_html);
            } else {
                // Fallback: render product cards
                this.renderFallbackResults(data);
            }

            this.$resultsScreen.fadeIn(300);
        },

        renderFallbackResults: function (data) {
            var opts = data.options || this.options;
            var colsD = opts.cols_desktop || 3;
            var colsT = opts.cols_tablet || 2;
            var colsM = opts.cols_mobile || 1;

            var html = '<div class="pf-results-grid pf-cols-d-' + colsD + ' pf-cols-t-' + colsT + ' pf-cols-m-' + colsM + '">';
            var products = data.products || [];

            for (var i = 0; i < products.length; i++) {
                var p = products[i];
                html += '<div class="pf-result-card">';
                if (p.image) {
                    html += '<a href="' + this.escHtml(p.permalink) + '" class="pf-result-img-link"><img src="' + this.escHtml(p.image) + '" alt="' + this.escHtml(p.name) + '"></a>';
                }
                html += '<div class="pf-result-info">';
                html += '<h4 class="pf-result-name"><a href="' + this.escHtml(p.permalink) + '">' + this.escHtml(p.name) + '</a></h4>';
                html += '<div class="pf-result-price">' + p.price + '</div>';
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

})(jQuery);
