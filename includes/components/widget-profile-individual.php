<?php
$defaults        = array(
	'classes'  => [],
);
$args            = wp_parse_args( $args, $defaults );
$classes         = $args['classes'];

$wicket_settings = get_wicket_settings(); 

?>

<div class="wicket-section <?php implode( ' ', $classes ); ?>" role="complementary">
  <h2>Profile</h2>
  <div id="profile"></div>
</div>

<script>
    window.Wicket=function(doc,tag,id,script){
    var w=window.Wicket||{};if(doc.getElementById(id))return w;var ref=doc.getElementsByTagName(tag)[0];var js=doc.createElement(tag);js.id=id;js.src=script;ref.parentNode.insertBefore(js,ref);w._q=[];w.ready=function(f){w._q.push(f)};return w
    }(document,"script","wicket-widgets","<?php echo $wicket_settings['wicket_admin'] ?>/dist/widgets.js");
</script>

<script>
    (function (){
        Wicket.ready(function () {
            var widgetRoot = document.getElementById('profile');
            Wicket.widgets.createPersonProfile({
            rootEl: widgetRoot,
            apiRoot: '<?php echo $wicket_settings['api_endpoint'] ?>',
            accessToken: '<?php echo wicket_access_token_for_person(wicket_current_person_uuid()) ?>',
            personId: '<?php echo wicket_current_person_uuid(); ?>',
            lang: "<?php echo 'en' ?>"
            }).then(function (widget) {
                widget.listen(widget.eventTypes.SAVE_SUCCESS, function (payload) {
                    
                });
            });
        });
    })()
</script>