<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

/**
 * PF Category Label – Elementor Widget for CrocoBlock listing templates.
 *
 * Displays the result category name (e.g. "BASE", "LIP + CHEEK", "EYE")
 * for the current product in the finder results.
 */
class PF_Category_Label_Widget extends Widget_Base {

    public function get_name() {
        return 'pf_category_label';
    }

    public function get_title() {
        return __( 'PF Category Label', 'product-finder' );
    }

    public function get_icon() {
        return 'eicon-t-letter';
    }

    public function get_categories() {
        return array( 'product-finder' );
    }

    public function get_keywords() {
        return array( 'category', 'label', 'type', 'result', 'finder' );
    }

    /* ═══════════════════════════════════════
       CONTROLS
       ═══════════════════════════════════════ */

    protected function register_controls() {

        /* ── Content ── */

        $this->start_controls_section( 'section_content', array(
            'label' => __( 'Content', 'product-finder' ),
        ) );

        $this->add_control( 'label_base', array(
            'label'   => __( 'Base / Foundation Label', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'BASE',
        ) );

        $this->add_control( 'label_concealer', array(
            'label'   => __( 'Concealer Label', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'CONCEAL',
        ) );

        $this->add_control( 'label_lip', array(
            'label'   => __( 'Lip Label', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'LIP',
        ) );

        $this->add_control( 'label_cheek', array(
            'label'   => __( 'Cheek Label', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'CHEEK',
        ) );

        $this->add_control( 'label_lip_cheek', array(
            'label'   => __( 'Lip & Cheek Label', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'LIP + CHEEK',
        ) );

        $this->add_control( 'label_eye', array(
            'label'   => __( 'Eye Label', 'product-finder' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'EYE',
        ) );

        $this->add_control( 'fallback_label', array(
            'label'       => __( 'Fallback Label', 'product-finder' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => __( 'Shown when no category is assigned. Leave empty to hide.', 'product-finder' ),
        ) );

        $this->add_control( 'html_tag', array(
            'label'   => __( 'HTML Tag', 'product-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'div',
            'options' => array(
                'h1'   => 'H1',
                'h2'   => 'H2',
                'h3'   => 'H3',
                'h4'   => 'H4',
                'h5'   => 'H5',
                'h6'   => 'H6',
                'div'  => 'div',
                'span' => 'span',
                'p'    => 'p',
            ),
        ) );

        $this->add_responsive_control( 'align', array(
            'label'   => __( 'Alignment', 'product-finder' ),
            'type'    => Controls_Manager::CHOOSE,
            'options' => array(
                'left'   => array( 'title' => __( 'Left', 'product-finder' ),   'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'product-finder' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'product-finder' ),  'icon' => 'eicon-text-align-right' ),
            ),
            'default'   => 'left',
            'selectors' => array(
                '{{WRAPPER}} .pf-cl-wrap' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();

        /* ── Style ── */

        $this->start_controls_section( 'section_style', array(
            'label' => __( 'Label', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'label_typography',
            'selector' => '{{WRAPPER}} .pf-cl-label',
        ) );

        $this->add_control( 'label_color', array(
            'label'     => __( 'Colour', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-cl-label' => 'color: {{VALUE}};' ),
        ) );

        $this->add_control( 'label_bg_color', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .pf-cl-label' => 'background-color: {{VALUE}};' ),
        ) );

        $this->add_responsive_control( 'label_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-cl-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'label_margin', array(
            'label'      => __( 'Margin', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-cl-label' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'label_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-cl-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'label_orientation', array(
            'label'   => __( 'Orientation', 'product-finder' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'horizontal',
            'options' => array(
                'horizontal' => __( 'Horizontal', 'product-finder' ),
                'vertical'   => __( 'Vertical (top to bottom)', 'product-finder' ),
                'vertical_r' => __( 'Vertical (bottom to top)', 'product-finder' ),
            ),
        ) );

        $this->end_controls_section();
    }

    /* ═══════════════════════════════════════
       RENDER
       ═══════════════════════════════════════ */

    protected function render() {
        global $post;

        if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            $this->render_editor_placeholder();
            return;
        }

        $settings = $this->get_settings_for_display();

        // Resolve the product ID.
        $product_id = 0;
        if ( $post ) {
            $product_id = $post->ID;
        }
        if ( ! $product_id ) {
            return;
        }

        // Get the result category from the scoring engine.
        $category = '';
        if ( class_exists( 'PF_Ajax' ) ) {
            $category = PF_Ajax::get_matched_category( $product_id );
        }

        // Map category slug to display label.
        $label = $this->get_category_display( $category, $settings );

        if ( ! $label ) {
            return;
        }

        $tag = $settings['html_tag'] ?: 'div';
        $allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );
        if ( ! in_array( $tag, $allowed_tags, true ) ) {
            $tag = 'div';
        }

        $orientation = $settings['label_orientation'] ?? 'horizontal';
        $orient_class = '';
        if ( 'vertical' === $orientation ) {
            $orient_class = ' pf-cl--vertical';
        } elseif ( 'vertical_r' === $orientation ) {
            $orient_class = ' pf-cl--vertical-r';
        }

        printf(
            '<div class="pf-cl-wrap%3$s"><%1$s class="pf-cl-label">%2$s</%1$s></div>',
            $tag,
            esc_html( $label ),
            $orient_class
        );
    }

    private function get_category_display( $category, $settings ) {
        $map = array(
            'base'      => $settings['label_base'] ?? 'BASE',
            'concealer' => $settings['label_concealer'] ?? 'CONCEAL',
            'lip'       => $settings['label_lip'] ?? 'LIP',
            'cheek'     => $settings['label_cheek'] ?? 'CHEEK',
            'lip_cheek' => $settings['label_lip_cheek'] ?? 'LIP + CHEEK',
            'eye'       => $settings['label_eye'] ?? 'EYE',
        );

        if ( $category && isset( $map[ $category ] ) ) {
            return $map[ $category ];
        }

        return $settings['fallback_label'] ?? '';
    }

    private function render_editor_placeholder() {
        $settings = $this->get_settings_for_display();
        $tag = $settings['html_tag'] ?: 'div';
        $allowed_tags = array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' );
        if ( ! in_array( $tag, $allowed_tags, true ) ) {
            $tag = 'div';
        }

        $orientation = $settings['label_orientation'] ?? 'horizontal';
        $orient_class = '';
        if ( 'vertical' === $orientation ) {
            $orient_class = ' pf-cl--vertical';
        } elseif ( 'vertical_r' === $orientation ) {
            $orient_class = ' pf-cl--vertical-r';
        }

        printf(
            '<div class="pf-cl-wrap%3$s"><%1$s class="pf-cl-label">%2$s</%1$s></div>',
            $tag,
            esc_html( $settings['label_base'] ?: 'BASE' ),
            $orient_class
        );
    }

    protected function content_template() {
        ?>
        <#
        var tag   = settings.html_tag || 'div';
        var label = settings.label_base || 'BASE';
        var orient = settings.label_orientation || 'horizontal';
        var cls = 'pf-cl-wrap';
        if ( orient === 'vertical' )   cls += ' pf-cl--vertical';
        if ( orient === 'vertical_r' ) cls += ' pf-cl--vertical-r';
        #>
        <div class="{{{ cls }}}">
            <{{{ tag }}} class="pf-cl-label">{{{ label }}}</{{{ tag }}}>
        </div>
        <?php
    }
}
