<div class="card card-space" style="width: 100%;">
    <a target="_blank" href="<?php echo visualinmu_route_detalleInmueble($inmueble->codigo()); ?>" class="inmueblelink" target="_blank">
        <div class="imagenportada portada-<?php echo $cont; ?> blur-content"
            style="background: url(<?php echo $inmueble->fotoPortada(); ?>);  background-size: cover; background-position: center; ">
        </div>
    </a>
    <div class="card-body">
        <div class="card-inmueble-container">
            <div class="card-inmueble-caracteristicas">
                <div class="row">
                <div class="col-sm-12   text-center">
                        <a href="<?php echo  visualinmu_route_detalleInmueble($inmueble->codigo()); ?>" target="_blank" class="cb-nombre">
                            <h6 class="d-inline-block text-truncate p-0 m-0" style="max-width:100%"><?php echo $inmueble->tipoInmueble()->nombreSingular(); ?> con contraseña
                            </h6>
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-inmueble-contacto ">
                <div class="row justify-content-center text-center align-items-center">
                    <a target="_blank" href="<?php echo visualinmu_route_detalleInmueble($inmueble->codigo());?>"
                    class="align-middle d-flex align-items-center justify-content-center "
                    style="height: 50px;" ><i class="fa fa-unlock me-1"  aria-hidden="true" style="font-size: 15px;"></i> <span>Desbloquear información</span></a>
                </div>
            </div>
        </div>

    </div>
</div>