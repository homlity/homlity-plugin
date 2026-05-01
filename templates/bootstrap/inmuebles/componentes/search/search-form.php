<?php if (isset($form)) : ?>
    <div>
       
        <form class="row visualinmueble-formulario-widget-busqueda" id="<?php echo $form["id"]; ?>" action="<?php echo $form["action"]; ?>" method="<?php echo $form["method"]; ?>" x-data="VISUALINMU_SEARCH_FORM">
            <?php if (isset($_GET["tags"])) { ?>
                <input class="form-control" type="hidden" name="tag" value="<?php echo $_GET["tags"]; ?>" />
            <?php } ?>
            <div class="col-md-12">
                <label><?php echo $form["filters"]["column_order"]["label"]; ?></label>
                <select class="form-control" name="<?php echo $form["filters"]["column_order"]["inputName"]; ?>">
                    <option value=""<?php echo empty($form["filters"]["column_order"]["old"]) ? "selected" : "" ?>>Seleccionar</option>
                    <?php foreach ($form["filters"]["column_order"]["options"] as $tipoInmueble) : ?>
                        <option value="<?php echo $tipoInmueble['value']; ?>" <?php echo $tipoInmueble['value'] == $form["filters"]["column_order"]["old"] ? "selected" : "" ?>><?php echo $tipoInmueble['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12">
                <label><?php echo $form["filters"]["direccion_order"]["label"]; ?></label>
                <select class="form-control" name="<?php echo $form["filters"]["direccion_order"]["inputName"]; ?>">
                    <option value="" <?php echo empty($form["filters"]["direccion_order"]["old"]) ? "selected" : "" ?>></option>
                    <?php foreach ($form["filters"]["direccion_order"]["options"] as $tipoInmueble) : ?>
                        <option value="<?php echo $tipoInmueble['value']; ?>" <?php echo $tipoInmueble['value'] == $form["filters"]["direccion_order"]["old"] ? "selected" : "" ?>><?php echo $tipoInmueble['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <br>
            <div class="col-md-12 visua_inmueble_codigo">
                <label><?php echo $form["filters"]["codigo"]["label"]; ?></label>
                <input type="text" class="form-control" name="<?php echo $form["filters"]["codigo"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["codigo"]["old"]) ? $form["filters"]["codigo"]["old"] : ""; ?>">
            </div>
            <div class="col-md-12 visua_inmueble_tipo_inmueble">
                <label><?php echo $form["filters"]["tipoInmueble"]["label"]; ?></label>
                <select class="form-control" id="<?php echo $form["filters"]["tipoInmueble"]["id"] ?>" x-model="tipoInmuebleSelect" x-on:change="cambioTipoInmueble($event)" name="<?php echo $form["filters"]["tipoInmueble"]["inputName"]; ?>">
                    <option value="" <?php echo empty($form["filters"]["tipoInmueble"]["old"]) ? "selected" : "" ?>></option>
                    <template x-for="tipoInmueble in tiposInmueble">
                        <option x-model="tipoInmueble.codigo" x-text="tipoInmueble.nombre"></option>
                    </template>
                    <?php foreach ($form["filters"]["tipoInmueble"]["options"] as $tipoInmueble) : ?>
                        <option value="<?php echo $tipoInmueble->codigo(); ?>" <?php echo $tipoInmueble->codigo() == $form["filters"]["tipoInmueble"]["old"] ? "selected" : "" ?>><?php echo $tipoInmueble->nombre(); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12 visua_inmueble_tipo_gestion">
                <label><?php echo $form["filters"]["tipoGestion"]["label"]; ?></label>
                <select class="form-control" id="<?php echo $form["filters"]["tipoGestion"]["id"] ?>" name="<?php echo $form["filters"]["tipoGestion"]["inputName"]; ?>">
                    <option value="" <?php echo empty($form["filters"]["tipoGestion"]["old"]) ? "selected" : "" ?>></option>
                    <template x-for="tipoGestion in tiposGestion">
                        <option x-model="tipoGestion.codigo" x-text="tipoGestion.nombre"></option>
                    </template>
                    <?php foreach ($form["filters"]["tipoGestion"]["options"] as $tipoGestion) : ?>
                        <option value="<?php echo $tipoGestion->codigo(); ?>" <?php echo $tipoGestion->codigo() == $form["filters"]["tipoGestion"]["old"] ? "selected" : "" ?>><?php echo $tipoGestion->nombre(); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-12 visua_inmueble_ciudades">
                <label><?php echo $form["filters"]["ciudades"]["label"]; ?></label>
                <select class="form-control" name="<?php echo $form["filters"]["ciudades"]["inputName"] ?>" id="<?php echo $form["filters"]["ciudades"]["id"]; ?>" x-model="ciudadSelected" x-on:change="cambioCiudad($event)">
                    <option value="" <?php echo empty($form["filters"]["ciudades"]["old"]) ? "selected" : "" ?>></option>
                    <template x-for="ciudad in ciudades">
                        <option :selected="ciudad.codigo == ciudadSelected" :key="ciudad.codigo" :value="ciudad.codigo" x-text="ciudad.nombre"></option>
                    </template>
                </select>
            </div>
            <div class="col-md-12 visua_inmueble_barrios">
                <label><?php echo $form["filters"]["barrios"]["label"]; ?></label>
                <select class="form-control" name="<?php echo $form["filters"]["barrios"]["inputName"] ?>" id="<?php echo $form["filters"]["barrios"]["id"]; ?>" x-model="barrioSelected" x-on:change="cambioBarrio($event)">
                    <option value="" <?php echo empty($form["filters"]["barrios"]["old"]) ? "selected" : "" ?>></option>
                    <template x-for="barrio in barrios">
                        <option :selected="barrio.codigo==barrioSelected" :key="barrio.codigo" :value="barrio.codigo" x-text="barrio.nombre"></option>
                    </template>
                </select>
            </div>
            <div class="col-md-12 visua_inmueble_precio_minimo">
                <label ><?php echo $form["filters"]["precioMin"]["label"]; ?></label>
                <input type="text" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"  name="<?php echo $form["filters"]["precioMin"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["precioMin"]["old"]) ? $form["filters"]["precioMin"]["old"] : ""; ?>">
            </div>
            <div class="col-md-12 visua_inmueble_precio_maximo">
                <label ><?php echo $form["filters"]["precioMax"]["label"]; ?></label>
                <input type="text" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"  name="<?php echo $form["filters"]["precioMax"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["precioMax"]["old"]) ? $form["filters"]["precioMax"]["old"] : ""; ?>">
            </div>
            <div class="col-md-12 visua_inmueble_area_maximo">
                <label ><?php echo $form["filters"]["areaMax"]["label"]; ?></label>
                <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"  name="<?php echo $form["filters"]["areaMax"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["areaMax"]["old"]) ? $form["filters"]["areaMax"]["old"] : ""; ?>">
            </div>
            <div class="col-md-12  visua_inmueble_area_minima">
                <label ><?php echo $form["filters"]["areaMin"]["label"]; ?></label>
                <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"  name="<?php echo $form["filters"]["areaMin"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["areaMin"]["old"]) ? $form["filters"]["areaMin"]["old"] : ""; ?>">
            </div>
            <template x-if="mostrarCaracteristicasGenerales">
                <div>
                    <div class="col-md-12 visua_inmueble_banos">
                        <label ><?php echo $form["filters"]["baños"]["label"]; ?></label>
                        <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"  name="<?php echo $form["filters"]["baños"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["baños"]["old"]) ? $form["filters"]["baños"]["old"] : ""; ?>">

                    </div>

                    <div class="col-md-12  visua_inmueble_area_minima">
                        <label ><?php echo $form["filters"]["areaMin"]["label"]; ?></label>
                        <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"  name="<?php echo $form["filters"]["areaMin"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["areaMin"]["old"]) ? $form["filters"]["areaMin"]["old"] : ""; ?>">
                    </div>

                    <div class="col-md-12 visua_inmueble_alcobas">
                        <label ><?php echo $form["filters"]["alcobas"]["label"]; ?></label>
                        <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"  name="<?php echo $form["filters"]["alcobas"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["alcobas"]["old"]) ? $form["filters"]["alcobas"]["old"] : ""; ?>">

                    </div>
                    <div class="col-md-12 visua_inmueble_garajes">
                        <label ><?php echo $form["filters"]["garajes"]["label"]; ?></label>
                        <input type="number" class="form-control <?php echo $form["filters"]["precioMax"]["class"]; ?>"  name="<?php echo $form["filters"]["garajes"]["inputName"]; ?>" value="<?php echo !empty($form["filters"]["garajes"]["old"]) ? $form["filters"]["garajes"]["old"] : ""; ?>">
                    </div>
                </div>
            </template>
            <div class="col-md-12 boton-buscar">
                <button type="submit" class="btn btn-primary btn-bus btn-block">Buscar</button>
                <br> <br>
                <a href="#!" data-bs-toggle="modal" data-bs-target="#exampleModal">¿No encuentra el inmueble que
                    desea?</a>
                <a href="#!" data-bs-toggle="modal" data-bs-target="#exampleModalconsignacion">¿Desea consignar su
                    inmueble?</a>
            </div>
        </form>
    </div>
<?php endif; ?>