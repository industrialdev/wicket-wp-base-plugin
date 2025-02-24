<?php
$defaults  = array(
    'classes'               => [],
  'items'                 => [],
  'icon-type'             => 'plus-minus',
  'accordion-type'        => 'list',
  'separate-title-body'   => false,
  'initial-open-item-id'  => 999,
);
$args                  = wp_parse_args($args, $defaults);
$classes               = $args['classes'];
$items                 = $args['items'] ?? [];
$icon_type             = $args['icon-type'];
$accordion_type        = $args['accordion-type'];
$separate_title_body   = $args['separate-title-body'];
$initial_open_item_id  = $args['initial-open-item-id'];

$font_awesome_icon_open = 'fa-solid fa-minus';
$font_awesome_icon_closed = 'fa-solid fa-plus';

if($icon_type == 'chevrons') {
    $font_awesome_icon_open = 'fa-solid fa-circle-chevron-up';
    $font_awesome_icon_closed = 'fa-solid fa-circle-chevron-down';
}

if ($icon_type == 'carets') {
    $font_awesome_icon_open = 'fa-solid fa-caret-up';
    $font_awesome_icon_closed = 'fa-solid fa-caret-down';
}

// Determine initial open item, if any
$initial_open_item_check_index = 0;
foreach($items as $item) {
    if(isset($item['open_by_default'])) {
        if($item['open_by_default']) {
            $initial_open_item_id = $initial_open_item_check_index;
            break;
        }
    }
    $initial_open_item_check_index++;
}

$classes[] = 'component-accordion @container w-full';
$placeholder_styles = 'style="min-height: 40px;border: 1px solid var(--wp--preset--color--light);"';
?>

<div class="<?php echo implode(' ', $classes); ?>"
	<?php if(!is_admin()) {
	    echo "x-data='{ openAccordion: ".$initial_open_item_id." }'";
	} ?>
	<?php if(is_admin() && empty($items)) {
	    echo $placeholder_styles;
	} ?>
	>

	<?php if(is_admin() && empty($items)): ?>
	<p><?php _e('Use the Block controls in edit mode or on the right to add accordion items.', 'wicket'); ?>
	</p>
	<?php endif; ?>

	<?php
  $i = 0;
foreach($items as $item) :
    $show_toggle_icon = true;
    if(isset($item['show_toggle_icon'])) {
        $show_toggle_icon = $item['show_toggle_icon'];
    }
    ?>
	<div <?php if (defined('WICKET_WP_THEME_V2')) : ?>
		class="accordion-item
		<?php echo "accordion-item--{$accordion_type}"; ?>"
		x-bind:class="openAccordion == <?php echo $i; ?> ? 'open' :
		''"
		<?php else: ?>
		class="accordion-item p-2 hover:cursor-pointer
		<?php if($accordion_type == 'card') {
		    echo 'rounded-100 border border-primary-060 mb-3';
		} else {
		    echo 'border-b border-primary-060 border-solid ';
		} if($i == 0 && $accordion_type == 'list') {
		    echo ' border-t';
		} ?>"
		x-bind:class="openAccordion == <?php echo $i; ?> ?
		'border-b-4 bg-tertiary-010 bg-opacity-20 open
		<?php if($accordion_type == 'card') {
		    echo 'border-light-120';
		} ?>'
		: ''"
		<?php endif; ?>
		x-bind:aria-expanded="openAccordion == <?php echo $i; ?> ?
		'true' : 'false'"
		x-bind:aria-pressed="openAccordion == <?php echo $i; ?> ?
		'true' : 'false'"
		x-on:click="if(openAccordion == <?php echo $i; ?>){
		openAccordion = 999; }else{ openAccordion = <?php echo $i; ?>
		}"
		x-on:keyup.enter="if(openAccordion == <?php echo $i; ?>){
		openAccordion = 999; }else{ openAccordion = <?php echo $i; ?>
		}"
		role="button"
		tabindex="0"
		>
		<div class="flex w-full justify-between items-center">
			<h4
				class="<?php echo (defined('WICKET_WP_THEME_V2')) ? 'accordion-item__title' : 'font-bold text-body-lg' ?>">
				<?php if($item['title_is_a_link']): ?>
				<a x-on:click.stop
					href="<?php echo $item['title_link']['url']; ?>"
					target="<?php echo $item['title_link']['target']; ?>"><?php echo $item['title']; ?></a>
				<?php else : ?>
				<?php echo $item['title']; ?>
				<?php endif; ?>
			</h4>
			<?php
          if($show_toggle_icon) {
              if (defined('WICKET_WP_THEME_V2')) {
                  $icon_classes = [ 'accordion-item__icon' ];
              } else {
                  $icon_classes = ['text-heading-md', 'text-primary-100', 'ml-4'];
              }

              get_component('icon', [
                'icon' => $font_awesome_icon_open,
                'classes' => $icon_classes,
                'atts' => ["x-show='openAccordion == " . $i . "'"]
              ]);
              get_component('icon', [
                'icon' => $font_awesome_icon_closed,
                'classes' => $icon_classes,
                'atts' => ["x-show='openAccordion != " . $i . "'"]
              ]);
          }
    ?>
		</div>
		<?php if(!$separate_title_body): ?>
		<div class="<?php echo (defined('WICKET_WP_THEME_V2')) ? 'accordion-item__body' : 'mt-1 pr-12' ?>"
			x-show="openAccordion == <?php echo $i; ?>" x-transition>
			<?php echo $item['body_content']; ?>

			<?php if(!empty($item['call_to_action']['link_and_label']['title']) && !empty($item['call_to_action']['link_and_label']['url']) && $item['call_to_action']['button_link_style'] != 'link') {
			    get_component('button', [
			      'variant'     => $item['call_to_action']['button_link_style'],
			      'label'       => $item['call_to_action']['link_and_label']['title'],
			      'link'        => $item['call_to_action']['link_and_label']['url'],
			      'link_target' => $item['call_to_action']['link_and_label']['target'],
			      'a_tag'       => true,
			      'classes'     => ['mt-4'],
			      'suffix_icon' => $item['call_to_action']['link_and_label']['target'] == '_blank' ? 'fa-solid fa-arrow-up-right-from-square' : '',
			    ]);
			} elseif(!empty($item['call_to_action']['link_and_label']['title']) && !empty($item['call_to_action']['link_and_label']['url']) && $item['call_to_action']['button_link_style'] == 'link') {
			    get_component('link', [
			      'text'     => $item['call_to_action']['link_and_label']['title'],
			      'url'      => $item['call_to_action']['link_and_label']['url'],
			      'target'   => $item['call_to_action']['link_and_label']['target'],
			      'classes'  => ['mt-4', 'block', 'font-bold'],
			      'icon_end' => [
			        'icon' => $item['call_to_action']['link_and_label']['target'] == '_blank' ? 'fa-solid fa-arrow-up-right-from-square' : '',
			      ],
			    ]);
			} ?>
		</div>
		<?php endif; ?>

	</div>

	<?php if($separate_title_body): ?>
	<div class="py-4 px-12 <?php if($accordion_type == 'list') {
	    echo 'border-b border-primary-060';
	} ?>"
		x-show="openAccordion == <?php echo $i; ?>" x-transition>
		<?php echo $item['body_content']; ?>

		<?php if(!empty($item['call_to_action']['link_and_label']['title']) && !empty($item['call_to_action']['link_and_label']['url']) && $item['call_to_action']['button_link_style'] != 'link') {
		    get_component('button', [
		      'variant'     => $item['call_to_action']['button_link_style'],
		      'label'       => $item['call_to_action']['link_and_label']['title'],
		      'link'        => $item['call_to_action']['link_and_label']['url'],
		      'link_target' => $item['call_to_action']['link_and_label']['target'],
		      'a_tag'       => true,
		      'classes'     => ['mt-4'],
		      'suffix_icon' => $item['call_to_action']['link_and_label']['target'] == '_blank' ? 'fa-solid fa-arrow-up-right-from-square' : '',
		    ]);
		} elseif(!empty($item['call_to_action']['link_and_label']['title']) && !empty($item['call_to_action']['link_and_label']['url']) && $item['call_to_action']['button_link_style'] == 'link') {
		    get_component('link', [
		      'text'     => $item['call_to_action']['link_and_label']['title'],
		      'url'      => $item['call_to_action']['link_and_label']['url'],
		      'target'   => $item['call_to_action']['link_and_label']['target'],
		      'classes'  => ['mt-4', 'block', 'font-bold'],
		      'icon_end' => [
		        'icon' => $item['call_to_action']['link_and_label']['target'] == '_blank' ? 'fa-solid fa-arrow-up-right-from-square' : '',
		      ],
		    ]);
		} ?>
	</div>
	<?php endif; ?>

	<?php
    $i++;
endforeach;
?>
</div>
