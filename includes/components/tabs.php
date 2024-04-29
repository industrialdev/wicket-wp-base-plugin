<?php
$defaults = array(
	'classes' => [],
	'items'   => [],
);
$args     = wp_parse_args( $args, $defaults );
$classes  = $args['classes'];
$items    = $args['items'] ?? [];
$id       = uniqid();

$classes[]          = 'component-tabs px-4 xl:px-0';
$placeholder_styles = 'style="min-height: 40px;border: 1px solid var(--wp--preset--color--light);"';
?>

<div class="<?php echo implode( ' ', $classes ); ?>" x-data="{ activeTab: 0 }">
	<div class="flex flex-col md:flex-row gap-3" role="tablist">
		<?php
		$title_index = 0;
		foreach ( $items as $item ) : ?>
			<button class="text-body-lg py-3 md:px-8 lg:px-16 bg-white border-b-2 hover:border-dark-100 focus:border-dark-100"
				role="tab" id="tab-button-<?php echo $id; ?>-<?php echo $title_index; ?>"
				aria-controls="tabs__contents-single-<?php echo $id; ?>-<?php echo $title_index; ?>"
				x-bind:class="activeTab == <?php echo $title_index ?> ? 'border-dark-100 border-b-4 pb-[10px]' : 'border-light-020'"
				x-bind:aria-selected="activeTab == <?php echo $title_index ?> ? 'true' : 'false'"
				x-on:click="activeTab = <?php echo $title_index ?>">
				<?php echo $item['title'] ? $item['title'] : __( 'Tab ', 'wicket' ) . $title_index + 1 ?>
			</button>
			<?php
			$title_index++;
		endforeach; ?>
	</div>

	<div class="p-6">
		<?php
		$content_index = 0;
		foreach ( $items as $item ) : ?>
			<div class="<?php echo $content_index === 1 ? 'active' : '' ?>"
				id="tabs__contents-single-<?php echo $id; ?>-<?php echo $content_index; ?>"
				tabindex="<?php echo $content_index; ?>" role="tabpanel"
				aria-labelledby="tab-button-<?php echo $id; ?>-<?php echo $content_index; ?>"
				x-bind:hidden="activeTab !== <?php echo $content_index ?>">
				<?php echo $item['body_content'] ?>

				<?php
				if ( isset( $item['call_to_action'], $item['call_to_action']['link_and_label']['url'] ) ) {
					get_component( 'button', [ 
						'variant'     => $item['call_to_action']['button_style'],
						'label'       => $item['call_to_action']['link_and_label']['title'],
						'link'        => $item['call_to_action']['link_and_label']['url'],
						'link_target' => $item['call_to_action']['link_and_label']['target'],
						'a_tag'       => true,
						'classes'     => [ 'mt-4' ],
						'suffix_icon' => $item['call_to_action']['link_and_label']['target'] == '_blank' ? 'fa-solid fa-arrow-up-right-from-square' : '',
					] );
				} ?>
			</div>
			<?php
			$content_index++;
		endforeach; ?>
	</div>
</div>