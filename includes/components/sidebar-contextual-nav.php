<?php
$defaults  = array(
	'classes'               => [],
  'icon-type'             => 'carets',
);
$args                              = wp_parse_args( $args, $defaults );
$icon_type                         = $args['icon-type'];
global $post;
$current_post_id                   = $post->ID;
$ancestry_array                    = wicket_get_all_parent_and_child_pages($current_post_id);
$accordion_items                   = [];
$topmost_parent_id                 = 0;
$accordion_id_containing_curr_page = 999;

// Build $accordion_items (top levels of the tree are accordions, children are list items in the accorion body)
foreach( $ancestry_array['family_tree'] as $post_id => $children_branch ) {
  /*
   * Accordion item is going to look like this:
   * Array
    (
        [title] => Test
        [title_is_a_link] => 1,
        [title_link] => Array
        (
            [title] => Tubes
            [url] => http://youtube.com
            [target] => _blank
        ),
        [body_content] => Test
        [call_to_action] => Array
            (
                [button_link_style] => ghost
                [link_and_label] => Array
                    (
                        [title] => Testing
                        [url] => http://google.ca
                        [target] => _blank
                    )

            )

    )
   */

  $topmost_parent_id = $post_id;
  $body_content = '<ul class="">';
  $accordion_item_index = 0;
  foreach( $children_branch as $child_post_id => $child_2_branch ) {
    // Commenting out as these have been moved to the accordion headings
    //$child_post_name = get_the_title($child_post_id);
    //$child_post_url = get_permalink($child_post_id);
    if( $current_post_id == $child_post_id ) {
      //$body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item component-sidebar-contextual-nav__sublist-item--active' : 'mb-2 bg-light-030 bg-opacity-25 rounded-100 p-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
      $accordion_id_containing_curr_page = $accordion_item_index;
    } else {
      //$body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item' : 'mb-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
    }

    $body_content .= '<ul class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist' : 'pl-4') . '">';
    foreach( $child_2_branch as $child_2_post_id => $child_3_branch ) {
      $child_post_name = get_the_title($child_2_post_id);
      $child_post_url = get_permalink($child_2_post_id);
      if( $current_post_id == $child_2_post_id ) {
        $body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item component-sidebar-contextual-nav__sublist-item--active' : 'mb-2 bg-light-030 bg-opacity-25 rounded-100 p-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
        $accordion_id_containing_curr_page = $accordion_item_index;
      } else {
        $body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item' : 'mb-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
      }

      $body_content .= '<ul class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist' : 'pl-4') . '">';
      foreach( $child_3_branch as $child_3_post_id => $child_4_branch ) {
        $child_post_name = get_the_title($child_3_post_id);
        $child_post_url = get_permalink($child_3_post_id);
        if( $current_post_id == $child_3_post_id ) {
          $body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item component-sidebar-contextual-nav__sublist-item--active' : 'mb-2 bg-light-030 bg-opacity-25 rounded-100 p-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
          $accordion_id_containing_curr_page = $accordion_item_index;
        } else {
          $body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item' : 'mb-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
        }

        $body_content .= '<ul class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist' : 'pl-4') . '">';
        foreach( $child_4_branch as $child_4_post_id => $child_5_branch ) {
          $child_post_name = get_the_title($child_4_post_id);
          $child_post_url = get_permalink($child_4_post_id);
          if( $current_post_id == $child_4_post_id ) {
            $body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item component-sidebar-contextual-nav__sublist-item--active' : 'mb-2 bg-light-030 bg-opacity-25 rounded-100 p-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
            $accordion_id_containing_curr_page = $accordion_item_index;
          } else {
            $body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item' : 'mb-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
          }

          $body_content .= '<ul class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist' : 'pl-4') . '">';
          foreach( $child_5_branch as $child_5_post_id => $child_6_branch ) {
            $child_post_name = get_the_title($child_5_post_id);
            $child_post_url = get_permalink($child_5_post_id);
            if( $current_post_id == $child_5_post_id ) {
              $body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item component-sidebar-contextual-nav__sublist-item--active' : 'mb-2 bg-light-030 bg-opacity-25 rounded-100 p-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
              $accordion_id_containing_curr_page = $accordion_item_index;
            } else {
              $body_content .= '<li class="' . (defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__sublist-item' : 'mb-2') . '"><a href="'.$child_post_url.'">'.$child_post_name.'</a></li>';
            }          
          }
          $body_content .= '</ul>';
        }
        $body_content .= '</ul>';
      }
      $body_content .= '</ul>';
    }
    $body_content .= '</ul>';

    $accordion_items[] = [
      'title'              => get_the_title($child_post_id),
      'title_is_a_link'    => true,
      'title_link'         => [
        'title' => '',
        'url' => get_permalink($child_post_id),
        'target' => '',
      ],
      'body_content' => $body_content,
      'show_toggle_icon' => !empty( $child_2_branch ),
    ];
  
    $accordion_item_index++;
  }
}

$classes[] = 'component-sidebar-contextual-nav @container w-full';
$placeholder_styles = 'style="min-height: 40px;border: 1px solid var(--wp--preset--color--light);"';
?>

<div 
  class="<?php echo implode( ' ', $classes ); ?>"
  <?php if( is_admin() && empty($accordion_items) ){ echo $placeholder_styles; } ?>
  >

  <h4 class="<?php echo defined( 'WICKET_WP_THEME_V2' ) ? 'component-sidebar-contextual-nav__title' : 'font-bold text-body-lg mb-2' ?>">
    <a href="<?php echo get_the_permalink($topmost_parent_id); ?>"><?php echo get_the_title($topmost_parent_id); ?></a>
  </h4>
  <?php get_component( 'accordion', [
    'items'                => $accordion_items,
    'icon-type'            => $icon_type,
    'accordion-type'       => 'list',
    'initial-open-item-id' => $accordion_id_containing_curr_page,
    'classes'              => [ 'component-sidebar-contextual-nav__accordion' ],
   ] ); ?>

</div>