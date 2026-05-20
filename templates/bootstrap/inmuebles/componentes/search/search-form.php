<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( isset( $widget ) ) {
    echo wp_kses_post( $widget['before_widget'] );
    echo wp_kses_post( $widget['before_title'] );
    echo wp_kses_post( $widget['after_title'] );
}
if ( isset( $form ) ) : ?>
    <div class="container">
        <form class=" homlity-real-estate-formulario-widget-inicio" id="<?php echo esc_attr( $form['id'] ); ?>" action="<?php echo esc_url( $form['action'] ); ?>" method="<?php echo esc_attr( $form['method'] ); ?>" x-data="VISUALINMU_SEARCH_FORM">
            <div class="row justify-content-md-center">
                <div class="col-md-2 visua_inmueble_codigo">
                    <div class="form-floating">
                        <input type="text" class="form-control" name="<?php echo esc_attr( $form['filters']['codigo']['inputName'] ); ?>" value="<?php echo esc_attr( ! empty( $form['filters']['codigo']['old'] ) ? $form['filters']['codigo']['old'] : '' ); ?>">
                        <label><?php echo esc_html( $form['filters']['codigo']['label'] ); ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_tipo_gestion">
                    <select class="form-control" id="<?php echo esc_attr( $form['filters']['tipoGestion']['id'] ); ?>" name="<?php echo esc_attr( $form['filters']['tipoGestion']['inputName'] ); ?>">
                        <option value="" <?php selected( empty( $form['filters']['tipoGestion']['old'] ) ); ?>><?php echo esc_html( $form['filters']['tipoGestion']['label'] ); ?></option>
                        <template x-for="tipoGestion in tiposGestion">
                            <option x-model="tipoGestion.codigo" x-text="tipoGestion.nombre"></option>
                        </template>
                        <?php foreach ( $form['filters']['tipoGestion']['options'] as $tipoGestion ) : ?>
                            <option value="<?php echo esc_attr( $tipoGestion->codigo() ); ?>" <?php selected( $tipoGestion->codigo(), $form['filters']['tipoGestion']['old'] ); ?>><?php echo esc_html( $tipoGestion->nombre() ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_tipo_inmueble">
                    <select class="form-control" id="<?php echo esc_attr( $form['filters']['tipoInmueble']['id'] ); ?>" name="<?php echo esc_attr( $form['filters']['tipoInmueble']['inputName'] ); ?>">
                        <option value="" <?php selected( empty( $form['filters']['tipoInmueble']['old'] ) ); ?>><?php echo esc_html( $form['filters']['tipoInmueble']['label'] ); ?></option>
                        <template x-for="tipoInmueble in tiposInmueble">
                            <option x-model="tipoInmueble.codigo" x-text="tipoInmueble.nombre"></option>
                        </template>
                        <?php foreach ( $form['filters']['tipoInmueble']['options'] as $tipoInmueble ) : ?>
                            <option value="<?php echo esc_attr( $tipoInmueble->codigo() ); ?>" <?php selected( $tipoInmueble->codigo(), $form['filters']['tipoInmueble']['old'] ); ?>><?php echo esc_html( $tipoInmueble->nombre() ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_departamentos">
                    <select class="form-control" name="<?php echo esc_attr( $form['filters']['departamentos']['inputName'] ); ?>" id="<?php echo esc_attr( $form['filters']['departamentos']['id'] ); ?>" x-model="tipoDepartamentoSelect" x-on:change="cambioDepartamento($event)">
                        <option value="" <?php selected( empty( $form['filters']['departamentos']['old'] ) ); ?>><?php echo esc_html( $form['filters']['departamentos']['label'] ); ?></option>
                        <template x-for="departamento in departamentos">
                            <option x-model="departamento.codigo" x-text="departamento.nombre"></option>
                        </template>
                        <?php foreach ( $form['filters']['departamentos']['options'] as $departamento ) : ?>
                            <option value="<?php echo esc_attr( $departamento->codigo() ); ?>" <?php selected( $departamento->codigo(), $form['filters']['departamentos']['old'] ); ?>><?php echo esc_html( $departamento->nombre() ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_ciudades">
                    <select class="form-control" name="<?php echo esc_attr( $form['filters']['ciudades']['inputName'] ); ?>" id="<?php echo esc_attr( $form['filters']['ciudades']['id'] ); ?>" x-model="ciudadSelected" x-on:change="cambioCiudad($event)">
                        <option value="" <?php selected( empty( $form['filters']['ciudades']['old'] ) ); ?>><?php echo esc_html( $form['filters']['ciudades']['label'] ); ?></option>
                        <template x-for="ciudad in ciudades">
                            <option :selected="ciudad.codigo == ciudadSelected" :key="ciudad.codigo" :value="ciudad.codigo" x-text="ciudad.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_barrios">
                    <select class="form-control" name="<?php echo esc_attr( $form['filters']['barrios']['inputName'] ); ?>" id="<?php echo esc_attr( $form['filters']['barrios']['id'] ); ?>" x-model="barrioSelected" x-on:change="cambioBarrio($event)">
                        <option value="" <?php selected( empty( $form['filters']['barrios']['old'] ) ); ?>><?php echo esc_html( $form['filters']['barrios']['label'] ); ?></option>
                        <template x-for="barrio in barrios">
                            <option :selected="barrio.codigo==barrioSelected" :key="barrio.codigo" :value="barrio.codigo" x-text="barrio.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_advisors">
                    <select class="form-control" name="<?php echo esc_attr( $form['filters']['advisors']['inputName'] ); ?>" id="<?php echo esc_attr( $form['filters']['advisors']['id'] ); ?>" x-model="advisorSelected" x-on:change="cambioAdvisor($event)">
                        <option value="" <?php selected( empty( $form['filters']['advisors']['old'] ) ); ?>><?php echo esc_html( $form['filters']['advisors']['label'] ); ?></option>
                        <template x-for="advisor in advisors">
                            <option :selected="advisor.id==advisorSelected" :key="advisor.id" :value="advisor.id" x-text="advisor.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_allies">
                    <select class="form-control" name="<?php echo esc_attr( $form['filters']['allies']['inputName'] ); ?>" id="<?php echo esc_attr( $form['filters']['allies']['id'] ); ?>" x-model="allySelected" x-on:change="cambioAlly($event)">
                        <option value="" <?php selected( empty( $form['filters']['allies']['old'] ) ); ?>><?php echo esc_html( $form['filters']['allies']['label'] ); ?></option>
                        <template x-for="ally in allies">
                            <option :selected="ally.id==allySelected" :key="ally.id" :value="ally.id" x-text="ally.nombre"></option>
                        </template>
                    </select>
                </div>
                <div class="col-md-2 visua_inmueble_precio_minimo">
                    <div class="form-floating">
                        <input type="text" class="form-control <?php echo esc_attr( $form['filters']['precioMax']['class'] ); ?>" name="<?php echo esc_attr( $form['filters']['precioMin']['inputName'] ); ?>" value="<?php echo esc_attr( ! empty( $form['filters']['precioMin']['old'] ) ? $form['filters']['precioMin']['old'] : '' ); ?>">
                        <label><?php echo esc_html( $form['filters']['precioMin']['label'] ); ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_precio_maximo">
                    <div class="form-floating">
                        <input type="text" class="form-control <?php echo esc_attr( $form['filters']['precioMax']['class'] ); ?>" name="<?php echo esc_attr( $form['filters']['precioMax']['inputName'] ); ?>" value="<?php echo esc_attr( ! empty( $form['filters']['precioMax']['old'] ) ? $form['filters']['precioMax']['old'] : '' ); ?>">
                        <label><?php echo esc_html( $form['filters']['precioMax']['label'] ); ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_area_maximo">
                    <div class="form-floating">
                        <input type="number" class="form-control <?php echo esc_attr( isset( $form['filters']['areaMax']['class'] ) ? $form['filters']['areaMax']['class'] : '' ); ?>" name="<?php echo esc_attr( $form['filters']['areaMax']['inputName'] ); ?>" value="<?php echo esc_attr( ! empty( $form['filters']['areaMax']['old'] ) ? $form['filters']['areaMax']['old'] : '' ); ?>">
                        <label><?php echo esc_html( $form['filters']['areaMax']['label'] ); ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_area_minima">
                    <div class="form-floating">
                        <input type="number" class="form-control <?php echo esc_attr( isset( $form['filters']['areaMin']['class'] ) ? $form['filters']['areaMin']['class'] : '' ); ?>" name="<?php echo esc_attr( $form['filters']['areaMin']['inputName'] ); ?>" value="<?php echo esc_attr( ! empty( $form['filters']['areaMin']['old'] ) ? $form['filters']['areaMin']['old'] : '' ); ?>">
                        <label><?php echo esc_html( $form['filters']['areaMin']['label'] ); ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_banos">
                    <div class="form-floating">
                        <input type="number" class="form-control <?php echo esc_attr( $form['filters']['precioMax']['class'] ); ?>" name="<?php echo esc_attr( $form['filters']['banos']['inputName'] ); ?>" value="<?php echo esc_attr( ! empty( $form['filters']['banos']['old'] ) ? $form['filters']['banos']['old'] : '' ); ?>">
                        <label><?php echo esc_html( $form['filters']['banos']['label'] ); ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_alcobas">
                    <div class="form-floating ">
                        <input type="number" class="form-control <?php echo esc_attr( $form['filters']['precioMax']['class'] ); ?>" name="<?php echo esc_attr( $form['filters']['alcobas']['inputName'] ); ?>" value="<?php echo esc_attr( ! empty( $form['filters']['alcobas']['old'] ) ? $form['filters']['alcobas']['old'] : '' ); ?>">
                        <label><?php echo esc_html( $form['filters']['alcobas']['label'] ); ?></label>
                    </div>
                </div>
                <div class="col-md-2 visua_inmueble_garajes">
                    <div class="form-floating">
                        <input type="number" class="form-control <?php echo esc_attr( $form['filters']['precioMax']['class'] ); ?>" name="<?php echo esc_attr( $form['filters']['garajes']['inputName'] ); ?>" value="<?php echo esc_attr( ! empty( $form['filters']['garajes']['old'] ) ? $form['filters']['garajes']['old'] : '' ); ?>">
                        <label><?php echo esc_html( $form['filters']['garajes']['label'] ); ?></label>
                    </div>
                </div>
                <div class="col-md-2 vi-boton-buscar">
                    <button type="submit" class="btn btn-primary btn-bus">Buscar</button>
                </div>
                <div class="col-md-2 vi-boton-limpiar">
                    <a href="<?php echo esc_url( $form['clear'] ); ?>" class="btn btn-primary btn-lim">Limpiar</a>
                </div>
            </div>
        </form>
    </div>
<?php endif;
if ( isset( $widget ) ) {
    echo wp_kses_post( $widget['after_widget'] );
}
?>
