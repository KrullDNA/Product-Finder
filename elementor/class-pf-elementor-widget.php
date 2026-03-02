<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Background;

/**
 * Product Finder – Elementor Widget with comprehensive styling controls.
 */
class PF_Elementor_Widget extends Widget_Base {

    public function get_name() {
        return 'product_finder';
    }

    public function get_title() {
        return __( 'Product Finder', 'product-finder' );
    }

    public function get_icon() {
        return 'eicon-search';
    }

    public function get_categories() {
        return array( 'product-finder' );
    }

    public function get_keywords() {
        return array( 'product', 'finder', 'quiz', 'woocommerce', 'recommendation' );
    }

    /* ═══════════════════════════════════════
       CONTROLS
       ═══════════════════════════════════════ */

    protected function register_controls() {
        $this->section_content();
        $this->section_style_container();
        $this->section_style_progress_bar();
        $this->section_style_question_text();
        $this->section_style_image_answers();
        $this->section_style_text_answers();
        $this->section_style_checkbox();
        $this->section_style_buttons();
        $this->section_style_email_screen();
        $this->section_style_loading_screen();
        $this->section_style_results();
        $this->section_style_result_cards();
    }

    /* ─── Content ─── */

    private function section_content() {
        $this->start_controls_section( 'section_content', array(
            'label' => __( 'Content', 'product-finder' ),
        ) );

        $finders = $this->get_finder_list();

        $this->add_control( 'finder_id', array(
            'label'   => __( 'Select Product Finder', 'product-finder' ),
            'type'    => Controls_Manager::SELECT2,
            'options' => $finders,
            'default' => '',
        ) );

        $this->add_control( 'loading_heading_text', array(
            'label'       => __( 'Loading Screen Heading', 'product-finder' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'placeholder' => __( 'Finding your perfect products…', 'product-finder' ),
            'label_block' => true,
            'separator'   => 'before',
        ) );

        $this->add_control( 'loading_svg_icon', array(
            'label'       => __( 'Loading Screen SVG Icon', 'product-finder' ),
            'type'        => Controls_Manager::ICONS,
            'default'     => array(
                'value'   => '',
                'library' => '',
            ),
            'description' => __( 'Choose a custom SVG icon to replace the default loading spinner. Upload your own SVG or pick from the icon library.', 'product-finder' ),
        ) );

        $this->add_control( 'results_heading_text', array(
            'label'       => __( 'Results Screen Heading', 'product-finder' ),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'placeholder' => __( 'Your Recommended Products', 'product-finder' ),
            'label_block' => true,
            'separator'   => 'before',
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Container ─── */

    private function section_style_container() {
        $this->start_controls_section( 'section_style_container', array(
            'label' => __( 'Container', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'container_max_width', array(
            'label'      => __( 'Max Width', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', '%', 'vw' ),
            'range'      => array(
                'px' => array( 'min' => 300, 'max' => 1600 ),
            ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-finder' => 'max-width: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'container_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-finder' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Background::get_type(), array(
            'name'     => 'container_background',
            'selector' => '{{WRAPPER}} .pf-finder',
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'container_border',
            'selector' => '{{WRAPPER}} .pf-finder',
        ) );

        $this->add_responsive_control( 'container_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-finder' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'container_shadow',
            'selector' => '{{WRAPPER}} .pf-finder',
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Progress Bar ─── */

    private function section_style_progress_bar() {
        $this->start_controls_section( 'section_style_progress', array(
            'label' => __( 'Progress Bar', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'progress_height', array(
            'label'      => __( 'Bar Height', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 2, 'max' => 30 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-progress-bar' => 'height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'progress_bg_color', array(
            'label'     => __( 'Track Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-progress-bar' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'progress_fill_color', array(
            'label'     => __( 'Fill Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-progress-fill' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'progress_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-progress-bar, {{WRAPPER}} .pf-progress-fill' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'progress_margin_bottom', array(
            'label'      => __( 'Bottom Spacing', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-progress-bar-wrap' => 'margin-bottom: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'progress_text_color', array(
            'label'     => __( 'Percentage Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-progress-text' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'progress_text_typography',
            'label'    => __( 'Percentage Typography', 'product-finder' ),
            'selector' => '{{WRAPPER}} .pf-progress-text',
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Question Text ─── */

    private function section_style_question_text() {
        $this->start_controls_section( 'section_style_question', array(
            'label' => __( 'Question Text', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'question_color', array(
            'label'     => __( 'Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-question-text' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'question_typography',
            'selector' => '{{WRAPPER}} .pf-question-text',
        ) );

        $this->add_responsive_control( 'question_align', array(
            'label'     => __( 'Alignment', 'product-finder' ),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => array(
                'left'   => array( 'title' => __( 'Left', 'product-finder' ), 'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'product-finder' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'product-finder' ), 'icon' => 'eicon-text-align-right' ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .pf-question-text' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'question_margin', array(
            'label'      => __( 'Margin', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-question-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'instruction_heading', array(
            'label'     => __( 'Instruction Text', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'instruction_color', array(
            'label'     => __( 'Instruction Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-question-instruction, {{WRAPPER}} .pf-question-hint' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'instruction_typography',
            'label'    => __( 'Instruction Typography', 'product-finder' ),
            'selector' => '{{WRAPPER}} .pf-question-instruction, {{WRAPPER}} .pf-question-hint',
        ) );

        $this->add_responsive_control( 'instruction_margin', array(
            'label'      => __( 'Instruction Margin', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-question-instruction, {{WRAPPER}} .pf-question-hint' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'instruction_align', array(
            'label'     => __( 'Instruction Alignment', 'product-finder' ),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => array(
                'left'   => array( 'title' => __( 'Left', 'product-finder' ), 'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'product-finder' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'product-finder' ), 'icon' => 'eicon-text-align-right' ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .pf-question-instruction, {{WRAPPER}} .pf-question-hint' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Image Answers ─── */

    private function section_style_image_answers() {
        $this->start_controls_section( 'section_style_image_answers', array(
            'label' => __( 'Image Answer Cards', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'img_grid_columns', array(
            'label'      => __( 'Columns', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 1, 'max' => 6 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answers-grid--images' => 'grid-template-columns: repeat({{SIZE}}, 1fr);',
            ),
        ) );

        $this->add_responsive_control( 'img_grid_gap', array(
            'label'      => __( 'Gap', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answers-grid--images' => 'gap: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'img_card_bg', array(
            'label'     => __( 'Card Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--image' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'img_card_border',
            'selector' => '{{WRAPPER}} .pf-answer-option--image',
        ) );

        $this->add_responsive_control( 'img_card_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answer-option--image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'img_card_shadow',
            'selector' => '{{WRAPPER}} .pf-answer-option--image',
        ) );

        // Selected state
        $this->add_control( 'img_selected_heading', array(
            'label'     => __( 'Selected State', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'img_selected_border_color', array(
            'label'     => __( 'Selected Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--image.pf-selected' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'img_selected_shadow_color', array(
            'label'     => __( 'Selected Shadow Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--image.pf-selected' => 'box-shadow: 0 0 0 3px {{VALUE}};',
            ),
        ) );

        $this->add_control( 'img_selected_bg', array(
            'label'     => __( 'Selected Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--image.pf-selected' => 'background-color: {{VALUE}};',
            ),
        ) );

        // Hover state
        $this->add_control( 'img_hover_heading', array(
            'label'     => __( 'Hover State', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'img_hover_border_color', array(
            'label'     => __( 'Hover Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--image:hover' => 'border-color: {{VALUE}};',
            ),
        ) );

        // Image
        $this->add_control( 'img_image_heading', array(
            'label'     => __( 'Image', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'img_aspect_ratio', array(
            'label'      => __( 'Aspect Ratio', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.05 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answer-img-wrap' => 'aspect-ratio: {{SIZE}};',
            ),
        ) );

        $this->add_responsive_control( 'img_image_radius', array(
            'label'      => __( 'Image Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answer-img-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        // Answer text
        $this->add_control( 'img_text_heading', array(
            'label'     => __( 'Answer Label', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'img_text_color', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--image .pf-answer-text' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'img_text_typography',
            'selector' => '{{WRAPPER}} .pf-answer-option--image .pf-answer-text',
        ) );

        $this->add_responsive_control( 'img_text_padding', array(
            'label'      => __( 'Text Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answer-option--image .pf-answer-text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        // Answer description (image layout only)
        $this->add_control( 'img_desc_heading', array(
            'label'     => __( 'Answer Description', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'img_desc_color', array(
            'label'     => __( 'Description Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--image .pf-answer-desc' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'img_desc_typography',
            'selector' => '{{WRAPPER}} .pf-answer-option--image .pf-answer-desc',
        ) );

        $this->add_responsive_control( 'img_desc_padding', array(
            'label'      => __( 'Description Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answer-option--image .pf-answer-desc' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'img_desc_margin', array(
            'label'      => __( 'Description Margin', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answer-option--image .pf-answer-desc' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Text Answers ─── */

    private function section_style_text_answers() {
        $this->start_controls_section( 'section_style_text_answers', array(
            'label' => __( 'Text Answer Options', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'text_answer_gap', array(
            'label'      => __( 'Gap Between Options', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 30 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answers-grid--text' => 'gap: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'text_answer_bg', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--text' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'text_answer_border',
            'selector' => '{{WRAPPER}} .pf-answer-option--text',
        ) );

        $this->add_responsive_control( 'text_answer_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answer-option--text' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'text_answer_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-answer-option--text' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'text_answer_shadow',
            'selector' => '{{WRAPPER}} .pf-answer-option--text',
        ) );

        $this->add_control( 'text_answer_color', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--text .pf-answer-text' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'text_answer_typography',
            'selector' => '{{WRAPPER}} .pf-answer-option--text .pf-answer-text',
        ) );

        // Selected state
        $this->add_control( 'text_selected_heading', array(
            'label'     => __( 'Selected State', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'text_selected_bg', array(
            'label'     => __( 'Selected Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--text.pf-selected::before' => 'background: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'text_selected_border_color', array(
            'label'     => __( 'Selected Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--text.pf-selected' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'text_selected_text_color', array(
            'label'     => __( 'Selected Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--text.pf-selected .pf-answer-text' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'text_selected_shadow',
            'label'    => __( 'Selected Shadow', 'product-finder' ),
            'selector' => '{{WRAPPER}} .pf-answer-option--text.pf-selected',
        ) );

        // Hover state
        $this->add_control( 'text_hover_heading', array(
            'label'     => __( 'Hover State', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'text_hover_bg', array(
            'label'     => __( 'Hover Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--text' => '--pf-text-hover-bg: {{VALUE}};',
                '{{WRAPPER}} .pf-answer-option--text::before' => 'background: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'text_hover_border_color', array(
            'label'     => __( 'Hover Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-answer-option--text:hover' => 'border-color: {{VALUE}};',
            ),
        ) );

        // Two-column layout
        $this->add_control( 'text_layout_heading', array(
            'label'     => __( 'Two-Column Layout', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'text_layout_gap', array(
            'label'      => __( 'Column Gap', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 80 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-text-layout' => 'gap: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Checkbox ─── */

    private function section_style_checkbox() {
        $this->start_controls_section( 'section_style_checkbox', array(
            'label' => __( 'Checkbox Indicator', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'checkbox_size', array(
            'label'      => __( 'Size', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 14, 'max' => 40 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-checkbox' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'checkbox_border_width', array(
            'label'      => __( 'Border Width', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 6 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-checkbox' => 'border-width: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'checkbox_border_color', array(
            'label'     => __( 'Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-checkbox' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'checkbox_border_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', '%' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 20 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-checkbox' => 'border-radius: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'checkbox_checked_bg', array(
            'label'     => __( 'Checked Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-selected .pf-checkbox' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'checkbox_check_color', array(
            'label'     => __( 'Check Mark Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-selected .pf-check-icon' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Buttons ─── */

    private function section_style_buttons() {
        $this->start_controls_section( 'section_style_buttons', array(
            'label' => __( 'Buttons', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        // Primary button
        $this->add_control( 'btn_primary_heading', array(
            'label' => __( 'Primary Button (Continue / Send)', 'product-finder' ),
            'type'  => Controls_Manager::HEADING,
        ) );

        $this->add_control( 'btn_primary_bg', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-primary' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_primary_color', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-primary' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_primary_hover_bg', array(
            'label'     => __( 'Hover Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-primary:hover' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_primary_hover_color', array(
            'label'     => __( 'Hover Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-primary:hover' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'btn_primary_typography',
            'selector' => '{{WRAPPER}} .pf-btn-primary',
        ) );

        $this->add_responsive_control( 'btn_primary_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-btn-primary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'btn_primary_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-btn-primary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'btn_primary_shadow',
            'selector' => '{{WRAPPER}} .pf-btn-primary',
        ) );

        // Secondary button
        $this->add_control( 'btn_secondary_heading', array(
            'label'     => __( 'Secondary Button (Back / Start Over)', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'btn_secondary_bg', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-secondary' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_secondary_color', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-secondary' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_secondary_border_color', array(
            'label'     => __( 'Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-secondary' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_secondary_hover_bg', array(
            'label'     => __( 'Hover Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-secondary:hover' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_secondary_hover_color', array(
            'label'     => __( 'Hover Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-btn-secondary:hover' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'btn_secondary_typography',
            'selector' => '{{WRAPPER}} .pf-btn-secondary',
        ) );

        $this->add_responsive_control( 'btn_secondary_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-btn-secondary' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'btn_secondary_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-btn-secondary' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        // Skip / Link button – full styling
        $this->add_control( 'btn_skip_heading', array(
            'label'     => __( 'Skip & View Results Button', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'btn_skip_spacing_top', array(
            'label'      => __( 'Spacing Above', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-skip-email' => 'margin-top: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'btn_skip_bg', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-skip-email' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_skip_color', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-skip-email' => 'color: {{VALUE}}; text-decoration: none;',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'btn_skip_border',
            'selector' => '{{WRAPPER}} .pf-skip-email',
        ) );

        $this->add_responsive_control( 'btn_skip_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-skip-email' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_responsive_control( 'btn_skip_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-skip-email' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'btn_skip_shadow',
            'selector' => '{{WRAPPER}} .pf-skip-email',
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'btn_skip_typography',
            'selector' => '{{WRAPPER}} .pf-skip-email',
        ) );

        // Skip button hover
        $this->add_control( 'btn_skip_hover_heading', array(
            'label'     => __( 'Skip Button Hover', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'btn_skip_hover_bg', array(
            'label'     => __( 'Hover Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-skip-email:hover' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_skip_hover_color', array(
            'label'     => __( 'Hover Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-skip-email:hover' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'btn_skip_hover_border_color', array(
            'label'     => __( 'Hover Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-skip-email:hover' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Email Screen ─── */

    private function section_style_email_screen() {
        $this->start_controls_section( 'section_style_email', array(
            'label' => __( 'Email Capture Screen', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'email_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-email-screen' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Background::get_type(), array(
            'name'     => 'email_background',
            'selector' => '{{WRAPPER}} .pf-email-screen',
        ) );

        // Title
        $this->add_control( 'email_title_heading', array(
            'label'     => __( 'Title', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'email_title_color', array(
            'label'     => __( 'Title Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-email-title' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'email_title_typography',
            'selector' => '{{WRAPPER}} .pf-email-title',
        ) );

        // Input
        $this->add_control( 'email_input_heading', array(
            'label'     => __( 'Input Field', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'email_input_bg', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-email-input' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'email_input_color', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-email-input' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'email_input_placeholder_color', array(
            'label'     => __( 'Placeholder Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-email-input::placeholder' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'email_input_border',
            'selector' => '{{WRAPPER}} .pf-email-input',
        ) );

        $this->add_control( 'email_input_focus_border', array(
            'label'     => __( 'Focus Border Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-email-input:focus' => 'border-color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'email_input_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-email-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'email_input_typography',
            'selector' => '{{WRAPPER}} .pf-email-input',
        ) );

        // Message
        $this->add_control( 'email_msg_heading', array(
            'label'     => __( 'Status Message', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'email_success_color', array(
            'label'     => __( 'Success Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#00a32a',
        ) );

        $this->add_control( 'email_error_color', array(
            'label'     => __( 'Error Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#b32d2e',
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Loading Screen ─── */

    private function section_style_loading_screen() {
        $this->start_controls_section( 'section_style_loading', array(
            'label' => __( 'Loading Screen', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_responsive_control( 'loading_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-loading-screen' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_control( 'loading_icon_color', array(
            'label'     => __( 'Spinner Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-loading-icon' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'loading_icon_size', array(
            'label'      => __( 'Spinner Size', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px' ),
            'range'      => array( 'px' => array( 'min' => 20, 'max' => 120 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-loading-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ),
        ) );

        // Heading
        $this->add_control( 'loading_text_heading', array(
            'label'     => __( 'Heading', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'loading_text_color', array(
            'label'     => __( 'Text Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-loading-text' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'loading_text_typography',
            'selector' => '{{WRAPPER}} .pf-loading-text',
        ) );

        $this->add_responsive_control( 'loading_text_align', array(
            'label'     => __( 'Alignment', 'product-finder' ),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => array(
                'left'   => array( 'title' => __( 'Left', 'product-finder' ), 'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'product-finder' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'product-finder' ), 'icon' => 'eicon-text-align-right' ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .pf-loading-text' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'loading_text_margin', array(
            'label'      => __( 'Margin', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-loading-text' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Results Screen ─── */

    private function section_style_results() {
        $this->start_controls_section( 'section_style_results', array(
            'label' => __( 'Results Screen', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        // Title
        $this->add_control( 'results_title_color', array(
            'label'     => __( 'Title Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-results-title' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'results_title_typography',
            'selector' => '{{WRAPPER}} .pf-results-title',
        ) );

        $this->add_responsive_control( 'results_title_align', array(
            'label'     => __( 'Title Alignment', 'product-finder' ),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => array(
                'left'   => array( 'title' => __( 'Left', 'product-finder' ), 'icon' => 'eicon-text-align-left' ),
                'center' => array( 'title' => __( 'Center', 'product-finder' ), 'icon' => 'eicon-text-align-center' ),
                'right'  => array( 'title' => __( 'Right', 'product-finder' ), 'icon' => 'eicon-text-align-right' ),
            ),
            'selectors' => array(
                '{{WRAPPER}} .pf-results-title' => 'text-align: {{VALUE}};',
            ),
        ) );

        $this->add_responsive_control( 'results_title_margin', array(
            'label'      => __( 'Title Margin', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-results-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        // Grid
        $this->add_control( 'results_grid_heading', array(
            'label'     => __( 'Results Grid', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'results_grid_gap', array(
            'label'      => __( 'Gap', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array( 'px', 'em' ),
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-results-grid' => 'gap: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .pf-results-container .jet-listing-grid__items' => 'gap: {{SIZE}}{{UNIT}} !important;',
                '{{WRAPPER}} .pf-results-container .jet-listing-grid' => 'gap: {{SIZE}}{{UNIT}};',
            ),
        ) );

        $this->end_controls_section();
    }

    /* ─── Style: Result Cards ─── */

    private function section_style_result_cards() {
        $this->start_controls_section( 'section_style_result_cards', array(
            'label' => __( 'Result Product Cards', 'product-finder' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'card_bg', array(
            'label'     => __( 'Background', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-result-card' => 'background-color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Border::get_type(), array(
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .pf-result-card',
        ) );

        $this->add_responsive_control( 'card_radius', array(
            'label'      => __( 'Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-result-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .pf-result-card',
        ) );

        $this->add_group_control( Group_Control_Box_Shadow::get_type(), array(
            'name'     => 'card_hover_shadow',
            'label'    => __( 'Hover Shadow', 'product-finder' ),
            'selector' => '{{WRAPPER}} .pf-result-card:hover',
        ) );

        // Image
        $this->add_control( 'card_img_heading', array(
            'label'     => __( 'Product Image', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'card_img_aspect', array(
            'label'      => __( 'Aspect Ratio', 'product-finder' ),
            'type'       => Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0.3, 'max' => 2, 'step' => 0.05 ) ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-result-img-link' => 'aspect-ratio: {{SIZE}};',
            ),
        ) );

        $this->add_responsive_control( 'card_img_radius', array(
            'label'      => __( 'Image Border Radius', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', '%' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-result-img-link' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        // Info
        $this->add_control( 'card_info_heading', array(
            'label'     => __( 'Product Info', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_responsive_control( 'card_info_padding', array(
            'label'      => __( 'Padding', 'product-finder' ),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'selectors'  => array(
                '{{WRAPPER}} .pf-result-info' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ),
        ) );

        // Name
        $this->add_control( 'card_name_color', array(
            'label'     => __( 'Name Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-result-name a' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_control( 'card_name_hover_color', array(
            'label'     => __( 'Name Hover Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-result-name a:hover' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'card_name_typography',
            'selector' => '{{WRAPPER}} .pf-result-name',
        ) );

        // Price
        $this->add_control( 'card_price_heading', array(
            'label'     => __( 'Price', 'product-finder' ),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        $this->add_control( 'card_price_color', array(
            'label'     => __( 'Price Color', 'product-finder' ),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array(
                '{{WRAPPER}} .pf-result-price' => 'color: {{VALUE}};',
            ),
        ) );

        $this->add_group_control( Group_Control_Typography::get_type(), array(
            'name'     => 'card_price_typography',
            'selector' => '{{WRAPPER}} .pf-result-price',
        ) );

        $this->end_controls_section();
    }

    /* ═══════════════════════════════════════
       RENDER
       ═══════════════════════════════════════ */

    protected function render() {
        $settings  = $this->get_settings_for_display();
        $finder_id = absint( $settings['finder_id'] ?? 0 );

        if ( ! $finder_id ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<div style="padding:40px;text-align:center;background:#f7f7f7;border:2px dashed #ccc;border-radius:8px;">';
                echo '<p style="font-size:16px;color:#666;">Product Finder Widget</p>';
                echo '<p style="color:#999;">Please select a Product Finder from the content settings.</p>';
                echo '</div>';
            }
            return;
        }

        // Build shortcode with optional custom heading overrides.
        $shortcode_atts = 'id="' . $finder_id . '"';

        $loading_heading = trim( $settings['loading_heading_text'] ?? '' );
        if ( $loading_heading ) {
            $shortcode_atts .= ' loading_heading="' . esc_attr( $loading_heading ) . '"';
        }

        $results_heading = trim( $settings['results_heading_text'] ?? '' );
        if ( $results_heading ) {
            $shortcode_atts .= ' results_heading="' . esc_attr( $results_heading ) . '"';
        }

        // Render the shortcode
        echo do_shortcode( '[product_finder ' . $shortcode_atts . ']' );

        // If a custom SVG icon was chosen, inject it to replace the default loading icon
        $icon_settings = $settings['loading_svg_icon'] ?? array();
        if ( ! empty( $icon_settings['value'] ) ) {
            $icon_html = '';

            if ( is_array( $icon_settings['value'] ) && ! empty( $icon_settings['value']['url'] ) ) {
                // SVG upload – render as <img> tag
                $icon_html = '<img src="' . esc_url( $icon_settings['value']['url'] ) . '" alt="" class="pf-custom-loading-img">';
            } else {
                // Icon library (Font Awesome, etc.) – render via Elementor helper
                ob_start();
                \Elementor\Icons_Manager::render_icon( $icon_settings, array( 'aria-hidden' => 'true', 'class' => 'pf-custom-loading-i' ) );
                $icon_html = ob_get_clean();
            }

            if ( $icon_html ) {
                ?>
                <script>
                (function(){
                    var wrap = document.getElementById('pf-finder-<?php echo esc_js( $finder_id ); ?>');
                    if (!wrap) return;
                    var oldIcon = wrap.querySelector('.pf-loading-icon');
                    if (!oldIcon) return;
                    var tmp = document.createElement('div');
                    tmp.innerHTML = <?php echo wp_json_encode( $icon_html ); ?>;
                    var newIcon = tmp.firstElementChild;
                    if (newIcon) {
                        newIcon.classList.add('pf-loading-icon');
                        oldIcon.parentNode.replaceChild(newIcon, oldIcon);
                    }
                })();
                </script>
                <?php
            }
        }
    }

    protected function content_template() {
        ?>
        <# if ( ! settings.finder_id ) { #>
        <div style="padding:40px;text-align:center;background:#f7f7f7;border:2px dashed #ccc;border-radius:8px;">
            <p style="font-size:16px;color:#666;">Product Finder Widget</p>
            <p style="color:#999;">Please select a Product Finder from the content settings.</p>
        </div>
        <# } #>
        <?php
    }

    /* ═══════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════ */

    private function get_finder_list() {
        $finders = get_posts( array(
            'post_type'      => 'product_finder',
            'posts_per_page' => -1,
            'post_status'    => array( 'publish', 'draft' ),
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $list = array();
        foreach ( $finders as $f ) {
            $list[ $f->ID ] = $f->post_title;
        }

        return $list;
    }
}
