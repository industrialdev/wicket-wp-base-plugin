<?php
$defaults  = array(
	'classes'          => [],
  'title'            => '',
	'use-fa-codes'     => false,
	'use-drop-shadows' => false,
	'icons'            => [],
);
$args             = wp_parse_args( $args, $defaults );
$classes          = $args['classes'];
$title            = $args['title'];
$use_fa_codes     = $args['use-fa-codes'];
$use_drop_shadows = $args['use-drop-shadows'];
$icons            = $args['icons'];

$classes[] = 'component-icon-grid @container';
$placeholder_styles = 'style="min-height: 40px;border: 1px solid var(--wp--preset--color--light);"';

?>

<div 
	class="<?php echo implode( ' ', $classes ) ?>"
	<?php if( is_admin() && empty($icons) ){ echo $placeholder_styles; } ?>
>

	<?php if( is_admin() && empty( $icons ) && empty( $title ) ): ?>
  <p><?php _e('Use the Block controls in edit mode or on the right to add icons.', 'wicket'); ?></p>
  <?php endif; ?>

	<?php if ( !empty( $title ) ) : ?>
		<h2 class="text-heading-xl font-bold text-center mb-2">
			<?php echo esc_html( $title ); ?>
		</h2>
	<?php endif; ?>

	<?php if( !empty( $icons ) ): ?>
		<div class="flex flex-wrap">
		<?php foreach( $icons as $icon ): ?>
			<div class="w-full md:w-1/2 lg:w-1/3 mb-2 p-2">
				<a class="rounded-100 bg-white flex items-center p-2 <?php if( $use_drop_shadows ) {echo 'shadow-lg';} ?>" href="<?php echo $icon['icon_link_url']; ?>">	
					<?php if( !$use_fa_codes ): ?>	
						<img class="mr-3" src="<?php echo $icon['icon_grid_image']['url']; ?>" />
					<?php else: ?>
						<?php 
							get_component( 'icon', [ 
								'icon' => $icon['font-awesome_icon_code'],
								'classes'=> ['mr-3', 'text-heading-xl'],
							] );
						?>
					<?php endif; ?>
					<div class="font-semibold"><?php echo $icon['icon_grid_text']; ?></div>
				</a>
			</div>
		<?php endforeach; ?>
		</div>
		
	<?php endif; ?>

</div>
