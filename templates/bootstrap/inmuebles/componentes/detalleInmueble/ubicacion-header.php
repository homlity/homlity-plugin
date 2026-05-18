<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="row d-flex align-items-stretch visualinmu-ubicacion-header">
    <div class="col-md-4 col-sm-12">
        <div class="location-title">
            <h2 class="h2">Ubicación</h2>
            <p class="">Explora el mapa para ver el inmueble y descubre lugares cercanos de interés.
            </p>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="h5 card-title">Como llegar!</h6>
                <p class="card-text">Calcula la mejor ruta para llegar fácilmente al inmueble.</p>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="h6 card-title text-center">Calcular con</h6>
                <div class="d-flex flex-wrap flex-md-row flex-sm-column justify-content-evenly" id="vi-btns-calcroute">
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr( $mapa['latitud'] . ',' . $mapa['longitud'] ); ?> "
                        target="_blank" class="btn google-maps btn-outline-primary" onclick="gtag('event', 'prop_dir_maps', {
    'origin': 'property_ubicacion',
    'label': 'Ubicación inmueble' // Este valor puede ser un número
  });">
                        <svg width="30" height="20" class="svg-inline--fa fa-map-location-dot" aria-hidden="true"
                            focusable="false" data-prefix="fas" data-icon="map-location-dot" role="img"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" data-fa-i2svg="">
                            <path fill="currentColor"
                                d="M408 120c0 54.6-73.1 151.9-105.2 192c-7.7 9.6-22 9.6-29.6 0C241.1 271.9 168 174.6 168 120C168 53.7 221.7 0 288 0s120 53.7 120 120zm8 80.4c3.5-6.9 6.7-13.8 9.6-20.6c.5-1.2 1-2.5 1.5-3.7l116-46.4C558.9 123.4 576 135 576 152V422.8c0 9.8-6 18.6-15.1 22.3L416 503V200.4zM137.6 138.3c2.4 14.1 7.2 28.3 12.8 41.5c2.9 6.8 6.1 13.7 9.6 20.6V451.8L32.9 502.7C17.1 509 0 497.4 0 480.4V209.6c0-9.8 6-18.6 15.1-22.3l122.6-49zM327.8 332c13.9-17.4 35.7-45.7 56.2-77V504.3L192 449.4V255c20.5 31.3 42.3 59.6 56.2 77c20.5 25.6 59.1 25.6 79.6 0zM288 152a40 40 0 1 0 0-80 40 40 0 1 0 0 80z">
                            </path>
                        </svg>
                        <br>Maps
                    </a>
                    <a href="https://waze.com/ul?ll=<?php echo esc_attr( $mapa['latitud'] . ',' . $mapa['longitud'] ); ?>&navigate=yes"
                        target="_blank" class="btn waze btn-outline-primary" onclick="gtag('event', 'prop_dir_waze', {
                                'origin': 'property_ubicacion',
                                'label': 'Ubicación inmueble' // Este valor puede ser un número
                            });">
                        <svg width="30" height="20" class="svg-inline--fa fa-waze" aria-hidden="true" focusable="false"
                            data-prefix="fab" data-icon="waze" role="img" xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 512 512" data-fa-i2svg="">
                            <path fill="currentColor"
                                d="M502.17 201.67C516.69 287.53 471.23 369.59 389 409.8c13 34.1-12.4 70.2-48.32 70.2a51.68 51.68 0 0 1-51.57-49c-6.44.19-64.2 0-76.33-.64A51.69 51.69 0 0 1 159 479.92c-33.86-1.36-57.95-34.84-47-67.92-37.21-13.11-72.54-34.87-99.62-70.8-13-17.28-.48-41.8 20.84-41.8 46.31 0 32.22-54.17 43.15-110.26C94.8 95.2 193.12 32 288.09 32c102.48 0 197.15 70.67 214.08 169.67zM373.51 388.28c42-19.18 81.33-56.71 96.29-102.14 40.48-123.09-64.15-228-181.71-228-83.45 0-170.32 55.42-186.07 136-9.53 48.91 5 131.35-68.75 131.35C58.21 358.6 91.6 378.11 127 389.54c24.66-21.8 63.87-15.47 79.83 14.34 14.22 1 79.19 1.18 87.9.82a51.69 51.69 0 0 1 78.78-16.42zM205.12 187.13c0-34.74 50.84-34.75 50.84 0s-50.84 34.74-50.84 0zm116.57 0c0-34.74 50.86-34.75 50.86 0s-50.86 34.75-50.86 0zm-122.61 70.69c-3.44-16.94 22.18-22.18 25.62-5.21l.06.28c4.14 21.42 29.85 44 64.12 43.07 35.68-.94 59.25-22.21 64.11-42.77 4.46-16.05 28.6-10.36 25.47 6-5.23 22.18-31.21 62-91.46 62.9-42.55 0-80.88-27.84-87.9-64.25z">
                            </path>
                        </svg>
                        <br>Waze
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
