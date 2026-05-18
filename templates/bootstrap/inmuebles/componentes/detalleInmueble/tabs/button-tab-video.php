<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<?php if ($conVideo): ?>
      <li class="nav-item" role="presentation">
    <button onclick="gtag('event', 'wp_property_tab_video', {
// Este valor puede ser un número
 });" class="nav-link <?php echo ($conVideo && $firstVideo) ? 'active' : '' ?>" id="video-tab" data-bs-toggle="tab"
        data-bs-target="#video" type="button" role="tab" aria-controls="video" aria-selected="false">
        <i class="icon-homlity icon-uniE932"></i> Video
    </button>
    </li>
<?php endif; ?>