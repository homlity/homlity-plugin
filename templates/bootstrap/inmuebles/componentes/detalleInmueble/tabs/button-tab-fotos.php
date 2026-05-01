
    <?php if (($conVideo || $conVideo360 || $conFotos360)) { ?>
         <li class="nav-item" role="presentation">
       <button onclick="gtag('event', 'wp_property_tab_photo', {
 // Este valor puede ser un número
  });" class="nav-link <?php echo ($conVideo && $firstVideo) ? '' : 'active' ?>" id="fotos-tab" data-bs-toggle="tab"
            data-bs-target="#fotos" type="button" role="tab" aria-controls="fotos" aria-selected="true">
            <i class="icon-homlity icon-uniE931"></i> Fotos
        </button>
        </li>
    <?php } ?>
