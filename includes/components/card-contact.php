<?php
$defaults    = array(
	'classes'     => [],
	'title '      => '',
	'description' => '',
	'email'       => '',
	'phone'       => '',
	'style'       => 'primary',
);
$args        = wp_parse_args( $args, $defaults );
$classes     = $args['classes'];
$title       = $args['title'];
$description = $args['description'];
$email       = $args['email'];
$phone       = $args['phone'];
$style       = $args['style'];

$wrapper_classes = [ 'component-card-contact p-5 rounded-100' ];

if ( $style === 'primary' ) {
	$wrapper_classes[] = 'bg-info-a-010';
}

if ( $style === 'secondary' ) {
	$wrapper_classes[] = 'bg-info-b-010';
}
?>

<div class="<?php echo implode( ' ', $wrapper_classes ) ?>">
	<?php if ( $title ) : ?>
		<div class="text-heading-xs font-bold mb-3">
			<?php echo esc_html( $title ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $description ) : ?>
		<div class="mb-3">
			<?php echo wp_kses_post( $description ); ?>
		</div>
	<?php endif; ?>

	<?php if ( $email || $phone ) : ?>
		<div class="flex flex-col items-start gap-2">
			<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( $email ); ?>"
					class="font-bold inline-flex items-center gap-2 hover:no-underline group">
					<i class="fa-regular fa-envelope group-hover:no-underline"></i>
					<span class="sr-only">
						<?php echo __( 'Send email to: ', 'wicket' ) ?>
					</span>
					<span class="group-hover:underline">
						<?php echo esc_html( $email ); ?>
					</span>
				</a>
			<?php endif; ?>

			<?php if ( $phone ) : ?>
				<a href="tel:<?php echo esc_attr( $phone ); ?>"
					class="font-bold inline-flex items-center gap-2 hover:no-underline group">
					<i class="fa-regular fa-phone group-hover:no-underline"></i>
					<span class="sr-only">
						<?php echo __( 'Call: ', 'wicket' ) ?>
					</span>
					<span class="group-hover:underline">
						<?php echo esc_html( $phone ); ?>
					</span>
				</a>
			<?php endif; ?>

		</div>
	<?php endif; ?>
</div>