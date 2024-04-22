<?php
$defaults           = array(
	'classes'            => [],
	'author'             => '',
	'hide_profile_image' => false,
	'hide_bio'           => false,
);
$args               = wp_parse_args( $args, $defaults );
$classes            = $args['classes'];
$author             = $args['author'];
$hide_profile_image = $args['hide_profile_image'];
$hide_bio           = $args['hide_bio'];

$classes[] = 'component-author flex flex-col md:flex-row gap-6';
?>

<div class="<?php echo implode( ' ', $classes ) ?>">
	<?php if ( ! $hide_profile_image && $author['user_avatar'] ) {
		$avatar = $author['user_avatar'];
		$avatar = str_replace( 'width=\'96\'', 'width=\'120\'', $avatar );
		$avatar = str_replace( 'height=\'96\'', 'height=\'120\'', $avatar );

		echo '<div class="flex-[0_1_120px] min-w-[120px]">';
		echo $avatar;
		echo '</div>';
	} ?>
	<div class="flex flex-col gap-4">
		<?php if ( $author['display_name'] ) : ?>
			<div class="text-heading-xs font-bold"><?php echo $author['display_name']; ?></div>
		<?php endif; ?>

		<?php if ( $author['user_description'] && ! $hide_bio ) : ?>
			<div class="text-body"><?php echo $author['user_description']; ?></div>
		<?php endif; ?>
	</div>
</div>