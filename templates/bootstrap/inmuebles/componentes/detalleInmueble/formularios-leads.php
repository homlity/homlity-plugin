<div class="card section-ficha-tecnica" style="width: 100%;">
    <div class="card-body">
        <h4>Enviate la ficha técnica</h4>
        <p>Te enviamos la ficha técnica de este inmueble a tu correo.</p>
        <?php echo do_shortcode('[visualinmu_lead_shortcode height="500" path="leads/fichatecnica/' . $codigo . '"]'); ?>
    </div>
</div>
<?php $fecha = getdate();
if ($fecha["wday"] != 0 && $fecha["wday"] != 6) {
    ?>
    <div class="card section-nosotros-lo-llamamos mt-3" style="width: 100%;">
        <div class="card-body nosotroslollamamos">

            <div class="card-body ">
                <h4>Nosotros te llamamos</h4>
                <!-- NOSOTROS LO LLAMAMOS -->
                <?php echo do_shortcode('[visualinmu_lead_shortcode height="550" path="leads/nosotrostellamamos/' . $codigo . '"]'); ?>
            </div>
        </div>
    </div>
<?php } ?>
