<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div>
	<ul  class="read-more-wrap-b">
		<?php foreach ($barrios as $keyb => $barrio) { 
		if($keyb > 2){	?>
		<li  class="read-more-target" ><a href="<?php echo esc_url( visualinmu_route_search( [
                'neighborhood' => [ $barrio->nombre() ],
            ] ) ); ?>"target="_blank">Encuentra inmuebles en <?php echo esc_html( $barrio->nombre() ); ?></a></li>
		<?php }else{ ?>
		<li><a href="<?php echo esc_url( visualinmu_route_search( [
                'neighborhood' => [ $barrio->nombre() ],
            ] ) ); ?>" target="_blank">Encuentra inmuebles en <?php echo esc_html( $barrio->nombre() ); ?></a></li>
		<?php } } ?>
	</ul>
</div>
