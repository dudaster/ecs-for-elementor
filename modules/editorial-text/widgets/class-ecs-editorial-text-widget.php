<?php
/**
 * Elementor Widget: DTE Editorial Text
 *
 * Combines WYSIWYG content with an optional image.
 * Image can flow as: none / before / after / float_left / float_right.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Box_Shadow;

class ECS_Editorial_Text_Widget extends Widget_Base {

	public function get_name(): string {
		return 'dte_editorial_text';
	}

	public function get_title(): string {
		return esc_html__( 'ECS Editorial Text', 'ele-custom-skin' );
	}

	public function get_icon(): string {
		return 'eicon-text-area';
	}

	public function get_categories(): array {
		return [ 'ele-custom-skin' ];
	}

	public function get_keywords(): array {
		return [ 'dte', 'editorial', 'text', 'image', 'float', 'wysiwyg', 'wrap' ];
	}

	protected function register_controls(): void {
		$this->register_content_controls();
		$this->register_image_settings_controls();
		$this->register_style_text_controls();
	}

	// ── Content Tab ───────────────────────────────────────────────────────────

	private function register_content_controls(): void {

		$this->start_controls_section( 'section_content', [
			'label' => esc_html__( 'Content', 'ele-custom-skin' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		] );

		$this->add_control( 'dte_et_text', [
			'label'   => esc_html__( 'Text', 'ele-custom-skin' ),
			'type'    => Controls_Manager::WYSIWYG,
			'default' => '<p>' . esc_html__( 'Enter your editorial text here. This text can wrap around the image when a float mode is selected.', 'ele-custom-skin' ) . '</p>',
			'dynamic' => [ 'active' => true ],
		] );

		$this->add_control( 'dte_et_image', [
			'label'   => esc_html__( 'Image', 'ele-custom-skin' ),
			'type'    => Controls_Manager::MEDIA,
			'default' => [ 'url' => '' ],
			'dynamic' => [ 'active' => true ],
		] );

		$this->add_responsive_control( 'dte_et_image_flow', [
			'label'        => esc_html__( 'Image Position', 'ele-custom-skin' ),
			'type'         => Controls_Manager::SELECT,
			'default'      => 'float_left',
			'options'      => [
				'none'        => esc_html__( 'None (block, above text)', 'ele-custom-skin' ),
				'before'      => esc_html__( 'Before Text', 'ele-custom-skin' ),
				'after'       => esc_html__( 'After Text', 'ele-custom-skin' ),
				'float_left'  => esc_html__( 'Float Left', 'ele-custom-skin' ),
				'float_right' => esc_html__( 'Float Right', 'ele-custom-skin' ),
			],
			'prefix_class' => 'e-et%s-flow-',
			'condition'    => [ 'dte_et_image[url]!' => '' ],
		] );

		$this->add_responsive_control( 'dte_et_content_align', [
			'label'        => esc_html__( 'Content Align', 'ele-custom-skin' ),
			'type'         => Controls_Manager::CHOOSE,
			'options'      => [
				'left'   => [ 'title' => esc_html__( 'Left', 'ele-custom-skin' ),   'icon' => 'eicon-text-align-left' ],
				'center' => [ 'title' => esc_html__( 'Center', 'ele-custom-skin' ), 'icon' => 'eicon-text-align-center' ],
				'right'  => [ 'title' => esc_html__( 'Right', 'ele-custom-skin' ),  'icon' => 'eicon-text-align-right' ],
			],
			'prefix_class' => 'e-et%s-align-',
		] );

		$this->end_controls_section();
	}

	// ── Image Settings Section (Content Tab) ──────────────────────────────────

	private function register_image_settings_controls(): void {

		$this->start_controls_section( 'section_image_settings', [
			'label'     => esc_html__( 'Image Settings', 'ele-custom-skin' ),
			'tab'       => Controls_Manager::TAB_CONTENT,
			'condition' => [ 'dte_et_image[url]!' => '' ],
		] );

		// 'name' => 'dte_et_image' generates:
		//   dte_et_image_size              (WP size slug)
		//   dte_et_image_custom_dimension  (custom width/height)
		// Both are read by get_attachment_image_html() via the same 'dte_et_image' key.
		$this->add_group_control( Group_Control_Image_Size::get_type(), [
			'name'    => 'dte_et_image',
			'default' => 'large',
		] );

		$this->add_responsive_control( 'dte_et_img_width', [
			'label'      => esc_html__( 'Width', 'ele-custom-skin' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', '%' ],
			'range'      => [
				'px' => [ 'min' => 50, 'max' => 1200 ],
				'%'  => [ 'min' => 5,  'max' => 100 ],
			],
			'selectors'  => [ '{{WRAPPER}} .dte-et-figure' => 'width: {{SIZE}}{{UNIT}};' ],
		] );

		$this->add_responsive_control( 'dte_et_img_max_width', [
			'label'      => esc_html__( 'Max Width', 'ele-custom-skin' ),
			'type'       => Controls_Manager::SLIDER,
			'size_units' => [ 'px', '%' ],
			'range'      => [
				'px' => [ 'min' => 50, 'max' => 1200 ],
				'%'  => [ 'min' => 5,  'max' => 100 ],
			],
			'selectors'  => [ '{{WRAPPER}} .dte-et-figure' => 'max-width: {{SIZE}}{{UNIT}};' ],
		] );

		$this->add_responsive_control( 'dte_et_img_margin', [
			'label'      => esc_html__( 'Margin', 'ele-custom-skin' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', 'em', '%' ],
			'selectors'  => [
				'{{WRAPPER}} .dte-et-figure' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_responsive_control( 'dte_et_img_border_radius', [
			'label'      => esc_html__( 'Border Radius', 'ele-custom-skin' ),
			'type'       => Controls_Manager::DIMENSIONS,
			'size_units' => [ 'px', '%', 'em' ],
			'selectors'  => [
				'{{WRAPPER}} .dte-et-figure'     => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
				'{{WRAPPER}} .dte-et-figure img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
			],
		] );

		$this->add_group_control( Group_Control_Box_Shadow::get_type(), [
			'name'     => 'dte_et_img_box_shadow',
			'selector' => '{{WRAPPER}} .dte-et-figure',
		] );

		$this->add_responsive_control( 'dte_et_img_object_fit', [
			'label'     => esc_html__( 'Object Fit', 'ele-custom-skin' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => '',
			'options'   => [
				''        => esc_html__( 'Default', 'ele-custom-skin' ),
				'cover'   => esc_html__( 'Cover', 'ele-custom-skin' ),
				'contain' => esc_html__( 'Contain', 'ele-custom-skin' ),
				'fill'    => esc_html__( 'Fill', 'ele-custom-skin' ),
			],
			'selectors' => [ '{{WRAPPER}} .dte-et-figure img' => 'object-fit: {{VALUE}};' ],
		] );

		$this->end_controls_section();
	}

	// ── Style Tab ─────────────────────────────────────────────────────────────

	private function register_style_text_controls(): void {

		$this->start_controls_section( 'section_style_text', [
			'label' => esc_html__( 'Text', 'ele-custom-skin' ),
			'tab'   => Controls_Manager::TAB_STYLE,
		] );

		$this->add_group_control( Group_Control_Typography::get_type(), [
			'name'     => 'dte_et_typography',
			'selector' => '{{WRAPPER}} .dte-et-text',
		] );

		$this->add_control( 'dte_et_color', [
			'label'     => esc_html__( 'Color', 'ele-custom-skin' ),
			'type'      => Controls_Manager::COLOR,
			'selectors' => [ '{{WRAPPER}} .dte-et-text' => 'color: {{VALUE}};' ],
		] );

		$this->end_controls_section();
	}

	// ── Render ────────────────────────────────────────────────────────────────

	protected function render(): void {
		$s         = $this->get_settings_for_display();
		$text      = $s['dte_et_text'] ?? '';
		$has_image = ! empty( $s['dte_et_image']['url'] );
		?>
		<div class="dte-editorial-text">

			<?php if ( $has_image ) { $this->render_figure( $s ); } ?>

			<div class="dte-et-text">
				<?php echo wp_kses_post( $text ); ?>
			</div>

		</div>
		<?php
	}

	/**
	 * Output <figure> with the image at the user-selected size.
	 */
	private function render_figure( array $s ): void {
		$img_html = Group_Control_Image_Size::get_attachment_image_html(
			$s,
			'dte_et_image',  // reads $s['dte_et_image_size'] + $s['dte_et_image_custom_dimension']
			'dte_et_image'   // reads $s['dte_et_image']['id'] / ['url']
		);

		if ( empty( $img_html ) ) {
			return;
		}
		?>
		<figure class="dte-et-figure">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wp_kses( $img_html, [
				'img' => [
					'src'      => [],
					'srcset'   => [],
					'sizes'    => [],
					'alt'      => [],
					'title'    => [],
					'class'    => [],
					'loading'  => [],
					'width'    => [],
					'height'   => [],
					'decoding' => [],
				],
			] );
			?>
		</figure>
		<?php
	}
}
