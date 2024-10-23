<?php
$defaults = array(
	'classes'  => [],
	'reversed' => false,
);

$args     = wp_parse_args( $args, $defaults );
$classes  = $args['classes'];
$reversed = $args['reversed'];

if ( defined( 'WICKET_WP_THEME_V2' ) ) {
	$classes[] = 'component-social-sharing';
	if ( $reversed ) {
		$classes[] = 'component-social-sharing--reversed';
	}
} else {
	$classes[] = 'component-social-sharing flex gap-2 list-none p-0 m-0 items-center';
}
?>

<ul class="<?php echo implode( ' ', $classes ) ?>">
	<li
		<?php if ( defined( 'WICKET_WP_THEME_V2' ) ) : ?>
			class="component-social-sharing__label"
		<?php else: ?>
			class="font-bold <?php echo $reversed ? 'text-white' : '' ?>"
		<?php endif; ?>
	>
		<?php _e( 'Share', 'wicket' ) ?>
	</li>
	<li>
		<?php get_component( 'button', [ 
			'classes'            => [ 'component-social-sharing__button' ],
			'size'               => 'sm',
			'variant'            => 'ghost',
			'label'              => '',
			'prefix_icon'        => 'fab fa-facebook-f fa-fw',
			'reversed'           => $reversed,
			'rounded'            => true,
			'a_tag'              => true,
			'link'               => 'https://www.facebook.com/sharer/sharer.php?u=' . get_the_permalink(),
			'link_target'        => '_blank',
			'screen_reader_text' => 'Share on Facebook (opens in a new tab)',
		] ) ?>
	</li>
	<li>
		<?php get_component( 'button', [ 
			'classes'            => [ 'component-social-sharing__button' ],
			'size'               => 'sm',
			'variant'            => 'ghost',
			'label'              => '',
			'prefix_icon'        => 'fab fa-x-twitter fa-fw',
			'reversed'           => $reversed,
			'rounded'            => true,
			'a_tag'              => true,
			'link'               => 'https://twitter.com/intent/tweet?url=' . get_the_permalink() . '&amp;text=' . urlencode( get_the_title() ) . '%20-%20' . urlencode( get_the_excerpt() ),
			'link_target'        => '_blank',
			'screen_reader_text' => 'Share on Twitter (opens in a new tab)',
		] ) ?>
	</li>
	<li>
		<?php get_component( 'button', [ 
			'classes'            => [ 'component-social-sharing__button' ],
			'size'               => 'sm',
			'variant'            => 'ghost',
			'label'              => '',
			'prefix_icon'        => 'fab fa-linkedin-in fa-fw',
			'reversed'           => $reversed,
			'rounded'            => true,
			'a_tag'              => true,
			'link'               => 'https://www.linkedin.com/shareArticle?mini=true&amp;url=' . get_the_permalink() . '&amp;title=' . urlencode( get_the_title() ),
			'link_target'        => '_blank',
			'screen_reader_text' => 'Share on LinkedIn (opens in a new tab)',
		] ) ?>
	</li>
	<li>
		<?php get_component( 'button', [ 
			'classes'            => [ 'component-social-sharing__button' ],
			'size'               => 'sm',
			'variant'            => 'ghost',
			'label'              => '',
			'prefix_icon'        => 'fas fa-envelope fa-fw',
			'reversed'           => $reversed,
			'rounded'            => true,
			'a_tag'              => true,
			'link'               => 'mailto:?subject=' . urlencode( get_the_title() ) . '&body=' . get_the_permalink(),
			'link_target'        => '_blank',
			'screen_reader_text' => 'Share via Email (opens in a new tab)',
		] ) ?>
	</li>
</ul>