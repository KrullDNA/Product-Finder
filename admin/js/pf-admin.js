(function ($) {
    'use strict';

    var questionIndex = 0;
    var answerIndex   = 0;
    var productIndex  = 0;

    /* ───────────── Init ───────────── */

    $(function () {
        initExistingIndices();
        initSortable();
        bindEvents();
    });

    function initExistingIndices() {
        // Find the highest existing indices so new items don't collide
        $('.pf-question').each(function () {
            var qi = parseInt($(this).data('qi'), 10);
            if (!isNaN(qi) && qi >= questionIndex) {
                questionIndex = qi + 1;
            }
        });
        $('.pf-answer').each(function () {
            var ai = parseInt($(this).data('ai'), 10);
            if (!isNaN(ai) && ai >= answerIndex) {
                answerIndex = ai + 1;
            }
        });
        $('.pf-product-row').each(function () {
            var pi = parseInt($(this).data('pi'), 10);
            if (!isNaN(pi) && pi >= productIndex) {
                productIndex = pi + 1;
            }
        });
    }

    /* ───────────── Sortable ───────────── */

    function initSortable() {
        $('#pf-questions-list').sortable({
            handle: '.pf-drag-handle',
            placeholder: 'pf-sortable-placeholder',
            opacity: 0.7,
            tolerance: 'pointer'
        });

        initAnswerSortable();
    }

    function initAnswerSortable() {
        $('.pf-sortable-answers').sortable({
            handle: '.pf-drag-handle-answer',
            placeholder: 'pf-sortable-placeholder',
            opacity: 0.7,
            tolerance: 'pointer',
            connectWith: false
        });
    }

    /* ───────────── Events ───────────── */

    function bindEvents() {
        // Add question
        $('#pf-add-question').on('click', addQuestion);

        // Toggle question body
        $(document).on('click', '.pf-question-header', function (e) {
            if ($(e.target).closest('.pf-remove-question').length) return;
            $(this).closest('.pf-question').find('.pf-question-body').slideToggle(200);
            $(this).find('.pf-question-toggle').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
        });

        // Remove question
        $(document).on('click', '.pf-remove-question', function (e) {
            e.stopPropagation();
            if (confirm(pfAdmin.i18n.confirm_del)) {
                $(this).closest('.pf-question').slideUp(200, function () { $(this).remove(); });
            }
        });

        // Update question title live
        $(document).on('input', '.pf-question-text-input', function () {
            var val = $(this).val() || 'New Question';
            $(this).closest('.pf-question').find('.pf-question-title').text(val);
        });

        // Toggle answer body
        $(document).on('click', '.pf-answer-header', function (e) {
            if ($(e.target).closest('.pf-remove-answer').length) return;
            $(this).closest('.pf-answer').find('.pf-answer-body').slideToggle(200);
        });

        // Add answer
        $(document).on('click', '.pf-add-answer', addAnswer);

        // Remove answer
        $(document).on('click', '.pf-remove-answer', function (e) {
            e.stopPropagation();
            if (confirm(pfAdmin.i18n.confirm_del)) {
                $(this).closest('.pf-answer').slideUp(200, function () { $(this).remove(); });
            }
        });

        // Update answer label live
        $(document).on('input', '.pf-answer-text-input', function () {
            var val = $(this).val() || 'New Answer';
            $(this).closest('.pf-answer').find('.pf-answer-label').text(val);
        });

        // Select image
        $(document).on('click', '.pf-select-image', selectImage);

        // Remove image
        $(document).on('click', '.pf-remove-image', removeImage);

        // Product search
        $(document).on('input', '.pf-product-search', debounce(searchProducts, 400));
        $(document).on('click', '.pf-product-search-result', selectProduct);

        // Remove product
        $(document).on('click', '.pf-remove-product', removeProduct);

        // Close search results on outside click
        $(document).on('click', function (e) {
            if (!$(e.target).closest('.pf-answer-products-wrap').length) {
                $('.pf-product-search-results').hide();
            }
        });
    }

    /* ───────────── Add Question ───────────── */

    function addQuestion() {
        var tmpl = wp.template('pf-question');
        var html = tmpl({ qi: questionIndex });
        questionIndex++;

        // We need to parse the template: replace {{data.qi}} placeholders that wp.template may not replace
        var $html = $(html);
        $('#pf-questions-list').append($html);
        $html.find('.pf-question-body').show();
        initAnswerSortable();
    }

    /* ───────────── Add Answer ───────────── */

    function addAnswer() {
        var $question = $(this).closest('.pf-question');
        var qi = $question.data('qi');
        var tmpl = wp.template('pf-answer');
        var html = tmpl({ qi: qi, ai: answerIndex });
        answerIndex++;

        var $html = $(html);
        $question.find('.pf-answers-list').append($html);
        $html.find('.pf-answer-body').show();
        initAnswerSortable();
    }

    /* ───────────── Image handling ───────────── */

    function selectImage() {
        var $wrap = $(this).closest('.pf-answer-image-wrap');

        var frame = wp.media({
            title: pfAdmin.i18n.select_image,
            button: { text: pfAdmin.i18n.use_image },
            multiple: false,
            library: { type: 'image' }
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            $wrap.find('.pf-image-id').val(attachment.id);
            var url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            $wrap.find('.pf-image-preview img').attr('src', url);
            $wrap.find('.pf-image-preview').show();
        });

        frame.open();
    }

    function removeImage(e) {
        e.preventDefault();
        e.stopPropagation();
        var $wrap = $(this).closest('.pf-answer-image-wrap');
        $wrap.find('.pf-image-id').val('');
        $wrap.find('.pf-image-preview').hide();
        $wrap.find('.pf-image-preview img').attr('src', '');
    }

    /* ───────────── Product search ───────────── */

    function searchProducts() {
        var $input   = $(this);
        var term     = $input.val();
        var $results = $input.siblings('.pf-product-search-results');

        if (term.length < 2) {
            $results.hide().empty();
            return;
        }

        $.ajax({
            url: pfAdmin.ajax_url,
            data: {
                action: 'pf_search_products',
                nonce: pfAdmin.nonce,
                term: term
            },
            success: function (data) {
                $results.empty();
                if (data && data.length) {
                    $.each(data, function (_, item) {
                        var $row = $('<div class="pf-product-search-result" data-id="' + item.id + '" data-name="' + escHtml(item.text) + '"></div>');
                        if (item.thumb) {
                            $row.append('<img src="' + item.thumb + '" width="30" height="30">');
                        }
                        $row.append('<span>' + escHtml(item.text) + '</span>');
                        $results.append($row);
                    });
                    $results.show();
                } else {
                    $results.hide();
                }
            }
        });
    }

    function selectProduct() {
        var $this    = $(this);
        var $wrap    = $this.closest('.pf-answer-products-wrap');
        var $answer  = $this.closest('.pf-answer');
        var $question= $this.closest('.pf-question');

        var qi = $question.data('qi');
        var ai = $answer.data('ai');
        var pi = productIndex;
        productIndex++;

        var prodId   = $this.data('id');
        var prodName = $this.data('name');

        var tmpl = wp.template('pf-product-row');
        var html = tmpl({ qi: qi, ai: ai, pi: pi, name: prodName });

        // Parse the HTML and set the product ID
        var $row = $(html);
        $row.find('.pf-product-id').val(prodId);
        $row.find('.pf-product-name').text(prodName);

        $wrap.find('.pf-products-list').append($row);
        $wrap.find('.pf-product-search').val('');
        $wrap.find('.pf-product-search-results').hide().empty();
    }

    function removeProduct() {
        $(this).closest('.pf-product-row').remove();
    }

    /* ───────────── Utilities ───────────── */

    function debounce(fn, delay) {
        var timer;
        return function () {
            var ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
