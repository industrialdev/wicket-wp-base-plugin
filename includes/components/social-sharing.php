<?php
$defaults = [
    'classes'  => [],
    'reversed' => false,
];

$args = wp_parse_args($args, $defaults);
$classes = $args['classes'];
$reversed = $args['reversed'];

if (defined('WICKET_WP_THEME_V2')) {
    $classes[] = 'component-social-sharing';
    if ($reversed) {
        $classes[] = 'component-social-sharing--reversed';
    }
} else {
    $classes[] = 'component-social-sharing flex gap-2 list-none p-0 m-0 items-center';
}
?>

<ul class="<?php echo implode(' ', $classes) ?>">
	<li
		<?php if (defined('WICKET_WP_THEME_V2')) : ?>
			class="component-social-sharing__label"
		<?php else: ?>
			class="font-bold <?php echo $reversed ? 'text-white' : '' ?>"
		<?php endif; ?>
	>
		<?php _e('Share', 'wicket') ?>
	</li>
	<li>
		<?php get_component('button', [
		    'classes'            => ['component-social-sharing__button'],
		    'size'               => 'sm',
		    'variant'            => 'ghost',
		    'label'              => '',
		    'prefix_icon'        => 'fab fa-facebook-f fa-fw',
		    'reversed'           => $reversed,
		    'rounded'            => true,
		    'a_tag'              => true,
		    'link'               => 'https://www.facebook.com/sharer/sharer.php?u=' . get_the_permalink(),
		    'link_target'        => '_blank',
		    'screen_reader_text' => __('Share on Facebook (opens in a new tab)', 'wicket'),
		]) ?>
	</li>
	<li>
		<?php get_component('button', [
		    'classes'            => ['component-social-sharing__button'],
		    'size'               => 'sm',
		    'variant'            => 'ghost',
		    'label'              => '',
		    'prefix_icon'        => 'fab fa-x-twitter fa-fw',
		    'reversed'           => $reversed,
		    'rounded'            => true,
		    'a_tag'              => true,
		    'link'               => 'https://twitter.com/intent/tweet?url=' . get_the_permalink() . '&amp;text=' . urlencode(get_the_title()) . '%20-%20' . urlencode(get_the_excerpt()),
		    'link_target'        => '_blank',
		    'screen_reader_text' => __('Share on Twitter (opens in a new tab)', 'wicket'),
		]) ?>
	</li>
	<li>
		<?php get_component('button', [
		    'classes'            => ['component-social-sharing__button'],
		    'size'               => 'sm',
		    'variant'            => 'ghost',
		    'label'              => '',
		    'prefix_icon'        => 'fab fa-linkedin-in fa-fw',
		    'reversed'           => $reversed,
		    'rounded'            => true,
		    'a_tag'              => true,
		    'link'               => 'https://www.linkedin.com/shareArticle?mini=true&amp;url=' . get_the_permalink() . '&amp;title=' . urlencode(get_the_title()),
		    'link_target'        => '_blank',
		    'screen_reader_text' => __('Share on LinkedIn (opens in a new tab)', 'wicket'),
		]) ?>
	</li>
	<li>
		<?php get_component('button', [
		    'classes'            => ['component-social-sharing__button'],
		    'size'               => 'sm',
		    'variant'            => 'ghost',
		    'label'              => '',
		    'prefix_icon'        => 'fas fa-envelope fa-fw',
		    'reversed'           => $reversed,
		    'rounded'            => true,
		    'a_tag'              => true,
		    'link'               => 'mailto:?subject=' . urlencode(get_the_title()) . '&body=' . get_the_permalink(),
		    'link_target'        => '_blank',
		    'screen_reader_text' => __('Share via Email (opens in email client)', 'wicket'),
		]) ?>
	</li>
	<li>
		<?php get_component('button', [
		    'classes'            => ['component-social-sharing__button'],
		    'size'               => 'sm',
		    'variant'            => 'ghost',
		    'label'              => '',
		    'prefix_icon'        => 'fab fa-bluesky fa-fw',
		    'reversed'           => $reversed,
		    'rounded'            => true,
		    'a_tag'              => true,
		    'link'               => 'https://bsky.app/intent/compose?text=' . urlencode(get_the_title() . ' - ' . get_the_permalink()),
		    'link_target'        => '_blank',
		    'screen_reader_text' => __('Share on Bluesky (opens in a new tab)', 'wicket'),
		]) ?>
	</li>
	<li>
		<?php get_component('button', [
		    'classes'            => ['component-social-sharing__button'],
		    'size'               => 'sm',
		    'variant'            => 'ghost',
		    'label'              => '',
		    'prefix_icon'        => 'fab fa-threads fa-fw',
		    'reversed'           => $reversed,
		    'rounded'            => true,
		    'a_tag'              => true,
		    'link'               => 'https://www.threads.net/intent/post?text=' . urlencode(get_the_title() . ' - ' . get_the_permalink()),
		    'link_target'        => '_blank',
		    'screen_reader_text' => __('Share on Threads (opens in a new tab)', 'wicket'),
		]) ?>
	</li>
	<li>
		<?php get_component('button', [
		    'classes'            => ['component-social-sharing__button', 'component-social-sharing__copy-url'],
		    'size'               => 'sm',
		    'variant'            => 'ghost',
		    'label'              => '',
		    'prefix_icon'        => 'fas fa-link fa-fw',
		    'reversed'           => $reversed,
		    'rounded'            => true,
		    'a_tag'              => false,
		    'atts'               => [
																	'data-copy-url' => get_the_permalink(),
																	'title' => __('Copy page URL to clipboard', 'wicket'),
																],
		    'screen_reader_text' => __('Copy page URL to clipboard', 'wicket'),
		]) ?>
	</li>
</ul>

<style>
.component-social-sharing__copy-url {
	position: relative;
}
.component-social-sharing__copied-tip {
	position: absolute;
	bottom: calc(100% + 6px);
	left: 50%;
	transform: translateX(-50%);
	background: #333;
	color: #fff;
	font-size: 0.75rem;
	line-height: 1;
	white-space: nowrap;
	padding: 4px 8px;
	border-radius: 4px;
	pointer-events: none;
	opacity: 0;
	transition: opacity 0.15s ease;
	z-index: 100;
}
.component-social-sharing__copied-tip::after {
	content: '';
	position: absolute;
	top: 100%;
	left: 50%;
	transform: translateX(-50%);
	border: 4px solid transparent;
	border-top-color: #333;
}
.component-social-sharing__copied-tip--visible {
	opacity: 1;
}
</style>

<script>
(function () {
	document.querySelectorAll('.component-social-sharing__copy-url:not([data-copy-url-init])').forEach(function (btn) {
		btn.setAttribute('data-copy-url-init', '1');

		var tip = document.createElement('span');
		tip.className = 'component-social-sharing__copied-tip';
		tip.setAttribute('aria-live', 'polite');
		tip.textContent = '<?php echo esc_js(__('Copied!', 'wicket')); ?>';
		btn.appendChild(tip);

		var hideTimer;

		btn.addEventListener('click', function () {
			var url = btn.getAttribute('data-copy-url');
			var done = function () {
				clearTimeout(hideTimer);
				tip.classList.add('component-social-sharing__copied-tip--visible');
				hideTimer = setTimeout(function () {
					tip.classList.remove('component-social-sharing__copied-tip--visible');
				}, 2000);
			};

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(url).then(done, done);
			} else {
				var ta = document.createElement('textarea');
				ta.value = url;
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				document.body.removeChild(ta);
				done();
			}
		});
	});
}());
</script>