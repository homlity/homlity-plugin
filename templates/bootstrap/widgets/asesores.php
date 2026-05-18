<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="row">
	<?php foreach ($asesores as $asesor) { ?>
		<div class="col-md-3">
			<div class="card card-asesor">
				<a href="<?php echo esc_url( visualinmu_route_detalleAsesor( $asesor->slug() ) ); ?>" target="_blank"><img
						class="card-img-top" src="<?php echo esc_url( $asesor->fotoUrl() ); ?>" class="rounded"
						alt="<?php echo esc_attr( $asesor->nombre() ); ?>"></a>
				<div class="card-body">

					<h5 class="text-center text-capitalize"><a
							href="<?php echo esc_url( visualinmu_route_detalleAsesor( $asesor->slug() ) ); ?>"
							target="_blank"><?php echo esc_html( $asesor->nombre() ); ?></a></h5>
					<div class="row">
						<div class="col-md-6">
							<a href="tel:<?php echo esc_attr( $asesor->telefono() ); ?>" class="text-center text-capitalize"><i
									class="fas fa-mobile-alt"></i> Teléfono</a>
						</div>
						<div class="col-md-6">
							<a href="mailto:<?php echo esc_attr( antispambot( sanitize_email( $asesor->email() ) ) ); ?>" class="text-center text-capitalize"><i
									class="fas fa-envelope"></i> Correo</a>
						</div>
						<div class="col-md-12 mt-3">
							<a href="https://wa.me/<?php echo rawurlencode( (string) $asesor->telefono() ); ?>?text=Hola%20estoy%20interesado%20acerca%20de%20un%20inmueble"
								class="text-center text-capitalize btn btn-primary btn-block btn-whatsapp-homlity"><i class="fab fa-whatsapp"></i> Whatsapp</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php } ?>
</div>
<style>
	.card-asesor {
		font-size: var(--e-global-typography-e92d54e-font-size);
		font-family: var(--e-global-typography-e92d54e-font-family), Sans-serif;
	}
	.btn-whatsapp-homlity{
		background-color: var(--e-global-color-primary);
		color: #fff !important;
		border:none;
	}
	.card-asesor a {
		color: var(--e-global-color-primary);
	}
</style>
