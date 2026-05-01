<ul class="read-more-wrap-c">
    <?php foreach ($ciudades as $keyc => $ciudad) {
        if ($keyc > 2) { ?>
            <li class="read-more-target"><a href="<?php echo visualinmu_route_search([
                "city" => $ciudad->nombre()
            ])?>" target="_blank">Encuentra
                    inmuebles en <?php echo $ciudad->nombre(); ?></a></li>
        <?php } else { ?>
            <li><a href="<?php echo visualinmu_route_search([
                "city" => $ciudad->nombre()
            ])?>" target="_blank">Encuentra inmuebles en
                    <?php echo $ciudad->nombre(); ?></a></li>
        <?php }
    } ?>
</ul>