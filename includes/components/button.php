<?php
$defaults           = array(
	'classes'            => [],
	'variant'            => 'primary',
	'size'               => '',
	'label'              => 'Button',
	'prefix_icon'        => '',
	'suffix_icon'        => '',
	'reversed'           => false,
	'rounded'            => false,
	'a_tag'              => false,
	'link'               => '',
	'link_target'        => '_self',
	'type'               => 'button',
	'disabled'           => false,
	'screen_reader_text' => '',
	'atts'               => [],
);
$args               = wp_parse_args( $args, $defaults );
$classes            = $args['classes'];
$variant            = $args['variant']; // primary, secondary, ghost
$size               = $args['size']; // sm, lg
$label              = $args['label'];
$prefix             = $args['prefix_icon'];
$suffix             = $args['suffix_icon'];
$reversed           = $args['reversed'];
$rounded            = $args['rounded'];
$a_tag              = $args['a_tag'];
$link               = $args['link'];
$link_target        = $args['link_target'];
$type               = $args['type'];
$disabled           = $args['disabled'];
$screen_reader_text = $args['screen_reader_text'];
$classes[]          = 'component-button';
$classes[]          = 'inline-flex';
$classes[]          = 'items-center';
$atts               = $args['atts'];
$icon_size_class 		= '';

if ( defined( 'WICKET_WP_THEME_V2' ) ) {
	switch ( $variant ) {
		case 'primary':
			$classes = array_merge($classes, [
				'bg-[--bg-interactive]',
				'border-[length:--border-interactive-md]',
				'border-transparent',
				'hover:bg-transparent',
				'text-[--text-label-button-reversed]',
				'hover:border-[--border-interactive]',
				'hover:border-[length:--border-interactive-md]',
				'hover:underline',
				'focus:bg-[--highlight-light]',
				'focus:border-[--border-interactive]',
				'focus:border-[length:--border-interactive-md]',
				'focus:text-[--text-button-label]',
				'focus:underline',
				'active:bg-[--highlight-light]',
				'active:border-transparent',
				'active:text-[--text-button-label]',
			]);
			break;
	}
} else {
	$classes[] = 'button';
	$classes[] = 'button--' . $variant;
}

if ( $size ) {
	if ( defined( 'WICKET_WP_THEME_V2' ) ) {
		$icon_size_class = 'text-[16px]';

		switch ( $size ) {
			case 'sm':
				$classes = array_merge($classes, [
					'text-[--button-label-sm]',
					'rounded-[--interactive-corner-radius-md]',
					'p-[--space-150]'
				]);
				break;
			case 'lg':
				$classes = array_merge($classes, [
					'text-[--button-label-lg]',
					'rounded-[--interactive-corner-radius-lg]',
					'p-[--space-250]'
				]);
				$icon_size_class = 'text-[24px]';
				break;
		}
	} else {
		// Legacy sizes
		$classes[] = 'button--' . $size;
	}
} else {
	// Default size
	if ( defined( 'WICKET_WP_THEME_V2' ) ) {
		$classes = array_merge($classes, [
			'rounded-[--interactive-corner-radius-md]',
			'p-[--space-200]',
			'text-[--button-label-md]'
		]);
	}
}

// Reversed option
if ( defined( 'WICKET_WP_THEME_V2' ) ) {
	// TODO:
} else {
	if ( $reversed ) {
		$classes[] = 'button--reversed';
	}	
}

// Rounded option
if ( ! defined( 'WICKET_WP_THEME_V2' ) ) {
	if ( $rounded ) {
		$classes[] = 'button--rounded';
	}
}

$tag_type    = 'button';
$href_markup = '';
if ( $a_tag ) {
	$tag_type    = 'a';
	$href_markup = "href='$link' target='$link_target'";
}

if ( $disabled ) {
	if ( defined( 'WICKET_WP_THEME_V2' ) ) {
		switch ( $variant ) {
			case 'primary':
				$classes = array_merge($classes, [
					'bg-[--bg-disabled]',
					'text-[--text-disabled]',
					'border-trasparent',
				]);
				break;
		}	
	} else {
		$classes[] = 'button--disabled';
		$atts[]    = 'disabled';
	}
}
?>

<<?php echo $tag_type; ?>
	<?php echo $href_markup; ?>
	<?php echo implode( ' ', $atts ); ?>
	class="
	<?php echo implode( ' ', $classes ) ?>"

	<?php if ( ! $a_tag ) {
		echo "type='" . $type . "'";
	} ?>
	>
	<?php
	if ( $prefix ) {
		get_component( 'icon', [ 
			'classes' => [ 'custom-icon-class', $icon_size_class ],
			'icon'    => $prefix,
			'text'    => $screen_reader_text,
		] );
	}

	echo $label;

	if ( $suffix ) {
		get_component( 'icon', [ 
			'classes' => [ 'custom-icon-class', $icon_size_class ],
			'icon'    => $suffix,
			'text'    => $screen_reader_text,
		] );
	}
	?>
</<?php echo $tag_type; ?>>