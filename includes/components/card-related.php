<?php
$defaults              = array(
	'classes'            => [],
	'content_type'       => '',
	'link'               => [],
	'document'           => [],
	'display_text'       => '',
	'body_text'          => '',
	'icon_type'          => 'default',
	'icon'               => '',
	'layout_style'       => '',
	'cta_label_override' => '',
	'rounded_corners'    => '',
);
$args                  = wp_parse_args( $args, $defaults );
$classes               = $args['classes'];
$content_type          = $args['content_type'];
$link                  = $args['link'];
$document              = $args['document'];
$display_text          = $args['display_text'];
$body_text             = $args['body_text'];
$icon_type             = $args['icon_type'];
$icon                  = $args['icon'];
$layout_style          = $args['layout_style'];
$button_label_override = $args['cta_label_override'];
$classes[]             = 'component-card-related';
$classes[]             = defined( 'WICKET_WP_THEME_V2' ) ? '' : 'p-4 h-full bg-white border border-light-020 flex gap-4 justify-between';
$button_link           = '';
$button_label          = '';
$button_icon           = '';
$button_target         = '_self';
$button_classes        = [ 'whitespace-nowrap' ];
$icon_id               = $icon['id'] ?? '';
$rounded_corners       = $args['rounded_corners'];

// Case: Layout style is list
if ( $layout_style === 'list' ) {
	$classes[] = 'flex-row items-center';
}

// Case: Layout style is card
if ( $layout_style === 'card' ) {
	$classes[] = 'flex-col items-start';
}

// Case: Content type is document
if ( $content_type === 'document' && $document ) {
	$icon             = 'fa-regular fa-file-lines';
	$button_link      = $document['url'];
	$button_label     = __( 'Download', 'wicket' );
	$button_icon      = 'fa-solid fa-arrow-down-to-bracket';
	$button_classes[] = $layout_style === 'card' ? 'w-full justify-center' : 'ml-auto min-w-36';
	$button_target    = '_blank';
	if ( empty( $display_text ) ) {
		$display_text = ucfirst( $document['title'] );
		// note: could use $document['filename'] if preferred
	}
}

// Case: Content type is link
if ( $content_type === 'link' && $link ) {
	$link_target      = $link['target'] ?? '_self';
	$icon             = $link_target === '_blank' ? 'fa-regular fa-external-link' : 'fa-regular fa-link';
	$button_link      = $link['url'];
	$button_label     = __( 'Visit Page', 'wicket' );
	$button_icon      = $link_target === '_blank' ? 'fa-regular fa-external-link' : 'fa-solid fa-arrow-right';
	$button_classes[] = $layout_style === 'card' ? 'w-full justify-center' : 'ml-auto min-w-36';
	$button_target    = $link_target;
}

// Case: Rounded corners checkbox
if ( $rounded_corners ) {
	$classes[] = defined( 'WICKET_WP_THEME_V2' ) ? 'component-card-related--rounded' : 'rounded-150';
}
?>

<div class="<?php echo implode( ' ', $classes ) ?>">
	<div class="top-aligned-content">
		<div class="component-card-related__icon" >
			<?php if ( $icon_id ) {
				get_component( 'image', [ 
					'id'      => $icon_id,
					'classes' => [ $layout_style === 'card' ? 'max-h-8' : 'max-h-6' ],
				] );
			} else {
				get_component( 'icon', [ 
					'icon'    => $icon,
					'classes' => [ 
						$layout_style === 'card' ? 'text-[32px]' : 'text-[24px]',
						$layout_style === 'list' ? 'w-6' : '',
						'leading-none',
						'inline-flex',
					],
				] );
			} ?>
		</div>

		<div>
			<?php if ( $display_text ) { ?>
				<div class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-card-related__title' : 'text-heading-xs text-dark-100 font-bold leading-7 mb-1' ?>">
					<?php echo $display_text; ?>
				</div>
			<?php } ?>
		</div>
		<?php // end top-aligned-content   ?>

		<?php if ( $body_text ) { ?>
			<p class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-card-related__body' : 'text-dark-100' ?>">
				<?php echo $body_text; ?>
			</p>
		<?php } ?>
	</div>

	<?php if ( $button_link ) {
		get_component( 'button', [ 
			'variant'     => 'primary',
			'label'       => ! empty( $button_label_override ) ? $button_label_override : $button_label,
			'suffix_icon' => $button_icon,
			'a_tag'       => true,
			'link'        => $button_link,
			'link_target' => $button_target,
			'classes'     => $button_classes,
			'atts'        => [ 
				$content_type === 'document' ? 'download' : '',
				$content_type === 'document' ? 'aria-label="Download"' : '',
				$button_target === '_blank' ? 'aria-label="Open in a new tab"' : '',
				'x-init="if( typeof widestButtonWidth !== \'undefined\' ){ 
					$el.style[\'width\'] = ( widestButtonWidth + 20 ) + \'px\'; 
					$el.style[\'justify-content\'] = \'space-between\';
				}"',
			],
		] );
	} ?>
</div>