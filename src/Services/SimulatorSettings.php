<?php
/**
 * Canonical simulator defaults, fields and sanitization.
 */

namespace Homlity\PluginInmobiliario\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class SimulatorSettings
{
    public static function defaults(): array
    {
        return [
            'arriendo' => [
                'comisionMinimaInmobiliaria' => '70000',
                'comisionTotalInmobiliaria' => '9.5',
                'comisionActiva' => '1',
                'comisionModalidad' => 'porcentaje_con_minimo',
                'comisionAplicaIva' => '1',
                'incluirAdministracionEnBaseComision' => '1',
                'incluirAdministracionEnBaseSeguro' => '1',
                'seguroCanonArrendamiento' => '2.5',
                'seguroModalidad' => 'porcentaje',
                'seguroValorFijo' => '0',
                'seguroBase' => 'canon_mas_administracion_mas_iva',
                'seguroActivo' => '1',
                'porcentajeRetencionFuente' => '3.5',
                'retencionFuenteActiva' => '1',
                'porcentajeRetencionIca' => '9.66',
                'retencionIcaActiva' => '1',
                'porcentajeRetencionIVA' => '0',
                'retencionIvaActiva' => '0',
                'porcentajeIva' => '19',
                'costoBancarioFijo' => '',
                'gastosBancariosModalidad' => 'cuatro_por_mil',
                'gastosBancariosActivos' => '1',
                'introConceptos' => '',
                'notaPie' => '',
            ],
            'venta' => [
                'porcentajeGastosNotariales' => '0.30',
                'valorConstitucionAfectacionViviendaFamiliar' => '57600',
                'porcentajeIva' => '19',
                'porcentajeConstitucionHipoteca' => '0.30',
                'portcentajeBeneficiencia' => '1',
                'portcentajeRegistroCompra' => '0.91',
                'valorbeneficienciaConstitucionHipoteca' => '185600',
                'porcentajeTimbre' => '1.5',
                'valorMinimoAplicaImpuestoTimbre' => '941300000',
                'porcentajeRetencionFuente' => '1',
                'otrosGastos' => '200000',
                'labelImpuestoDepartamental' => 'Beneficencia',
                'comisionHabilitada' => '0',
                'porcentajeComision' => '3',
                'comisionMinima' => '0',
                'labelComision' => '',
                'comisionPropiedadUrbanaHabilitada' => '1',
                'comisionPropiedadUrbanaPorcentaje' => '3',
                'comisionPropiedadUrbanaLabel' => 'Comisión propiedad urbana',
                'comisionPropiedadRuralHabilitada' => '1',
                'comisionPropiedadRuralPorcentaje' => '5',
                'comisionPropiedadRuralLabel' => 'Comisión propiedad rural',
                'introConceptos' => '',
                'notaPie' => '',
                'protecciones_familiares' => self::defaultsProteccionesFamiliares(),
            ],
        ];
    }

    public static function defaultsProteccionesFamiliares(): array
    {
        return [
            'vigencia_desde' => '2026-01-01',
            'vigencia_hasta' => '2026-12-31',

            'valores_por_defecto' => [
                'tarifa_notarial_acto_sin_cuantia' => 90600,
                'registro_acto_sin_cuantia_primer_folio' => 29500,
                'registro_acto_sin_cuantia_folio_adicional' => 15300,
                'iva_porcentaje' => 19,
            ],

            'afectacion_vivienda_familiar' => [
                'cancelacion' => [
                    'activo' => true,
                    'derechos_notariales' => 90600,
                    'aplica_iva' => true,
                    'porcentaje_iva' => 19,
                    'requiere_registro' => true,
                    'derechos_registro_primer_folio' => 29500,
                    'derechos_registro_folio_adicional' => 15300,
                    'responsable_por_defecto' => 'vendedor',
                    'permitir_edicion_responsable' => true,
                ],
                'constitucion' => [
                    'activo' => true,
                    'derechos_notariales' => 90600,
                    'aplica_iva' => true,
                    'porcentaje_iva' => 19,
                    'requiere_registro' => true,
                    'derechos_registro_primer_folio' => 29500,
                    'derechos_registro_folio_adicional' => 15300,
                    'responsable_por_defecto' => 'comprador',
                    'permitir_edicion_responsable' => true,
                ],
            ],

            'patrimonio_familia' => [
                'cancelacion' => [
                    'activo' => true,
                    'derechos_notariales' => 90600,
                    'aplica_iva' => true,
                    'porcentaje_iva' => 19,
                    'requiere_registro' => true,
                    'derechos_registro_primer_folio' => 29500,
                    'derechos_registro_folio_adicional' => 15300,
                    'responsable_por_defecto' => 'vendedor',
                    'permitir_edicion_responsable' => true,
                ],
                'constitucion_voluntaria' => [
                    'activo' => true,
                    'derechos_notariales' => 90600,
                    'aplica_iva' => true,
                    'porcentaje_iva' => 19,
                    'requiere_registro' => true,
                    'derechos_registro_primer_folio' => 29500,
                    'derechos_registro_folio_adicional' => 15300,
                    'responsable_por_defecto' => 'comprador',
                    'permitir_edicion_responsable' => true,
                ],
                'constitucion_vis' => [
                    'activo' => true,
                    'modo_calculo' => 'requiere_validacion',
                    'derechos_notariales' => 0,
                    'aplica_iva' => false,
                    'porcentaje_iva' => 0,
                    'requiere_registro' => true,
                    'derechos_registro_primer_folio' => 0,
                    'derechos_registro_folio_adicional' => 0,
                    'responsable_por_defecto' => 'comprador',
                    'permitir_edicion_responsable' => false,
                    'mensaje_validacion' => 'La constitución de patrimonio de familia asociada a VIS debe confirmarse con la notaría, la constructora y las condiciones del subsidio o financiación.',
                ],
            ],
        ];
    }

    public static function merge(array $stored): array
    {
        return array_replace_recursive(self::defaults(), $stored);
    }

    public static function fields(): array
    {
        return [
            'arriendo' => [
                'title' => __('Simulador de arriendo', 'homlity-real-estate'),
                'fields' => [
                    'comisionTotalInmobiliaria' => [
                        'label' => __('Porcentaje base de comisión (%)', 'homlity-real-estate'),
                        'help' => __('Porcentaje que se aplica sobre el canon y la administración si está habilitada.', 'homlity-real-estate'),
                    ],
                    'comisionMinimaInmobiliaria' => [
                        'label' => __('Valor mínimo de comisión', 'homlity-real-estate'),
                        'help' => __('Aplica como piso cuando la modalidad es porcentaje con mínimo o como valor fijo.', 'homlity-real-estate'),
                    ],
                    'comisionActiva' => [
                        'label' => __('Comisión activa', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Activar comisión inmobiliaria por defecto', 'homlity-real-estate'),
                    ],
                    'comisionModalidad' => [
                        'label' => __('Modalidad de comisión', 'homlity-real-estate'),
                        'type' => 'select',
                        'options' => [
                            'porcentaje' => __('Porcentaje sobre la base', 'homlity-real-estate'),
                            'porcentaje_con_minimo' => __('Porcentaje con mínimo', 'homlity-real-estate'),
                            'valor_fijo' => __('Valor fijo', 'homlity-real-estate'),
                        ],
                    ],
                    'comisionAplicaIva' => [
                        'label' => __('IVA sobre comisión', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Aplicar IVA sobre el valor de la comisión', 'homlity-real-estate'),
                    ],
                    'incluirAdministracionEnBaseComision' => [
                        'label' => __('Administración en base de comisión', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Incluir administración en la base de la comisión', 'homlity-real-estate'),
                    ],
                    'incluirAdministracionEnBaseSeguro' => [
                        'label' => __('Administración en base del seguro', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Incluir administración en la base del seguro / póliza', 'homlity-real-estate'),
                    ],
                    'seguroCanonArrendamiento' => [
                        'label' => __('Seguro / póliza (%)', 'homlity-real-estate'),
                    ],
                    'seguroModalidad' => [
                        'label' => __('Modalidad del seguro / póliza', 'homlity-real-estate'),
                        'type' => 'select',
                        'options' => [
                            'porcentaje' => __('Porcentaje', 'homlity-real-estate'),
                            'valor_fijo' => __('Valor fijo', 'homlity-real-estate'),
                        ],
                    ],
                    'seguroValorFijo' => [
                        'label' => __('Valor fijo del seguro / póliza', 'homlity-real-estate'),
                    ],
                    'seguroBase' => [
                        'label' => __('Base de cálculo del seguro', 'homlity-real-estate'),
                        'type' => 'select',
                        'options' => [
                            'canon' => __('Canon', 'homlity-real-estate'),
                            'canon_mas_administracion' => __('Canon + administración', 'homlity-real-estate'),
                            'canon_mas_iva' => __('Canon + IVA sobre canon', 'homlity-real-estate'),
                            'canon_mas_administracion_mas_iva' => __('Canon + administración + IVA sobre canon', 'homlity-real-estate'),
                        ],
                    ],
                    'seguroActivo' => [
                        'label' => __('Póliza activa por defecto', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Mostrar póliza activada al cargar el simulador', 'homlity-real-estate'),
                    ],
                    'porcentajeRetencionFuente' => [
                        'label' => __('Retención en la fuente (%)', 'homlity-real-estate'),
                    ],
                    'retencionFuenteActiva' => [
                        'label' => __('Retención en la fuente activa', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Mostrar retención en la fuente activada al cargar', 'homlity-real-estate'),
                    ],
                    'porcentajeRetencionIca' => [
                        'label' => __('Retención ICA (por mil)', 'homlity-real-estate'),
                    ],
                    'retencionIcaActiva' => [
                        'label' => __('ReteICA activa', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Mostrar reteICA activada al cargar', 'homlity-real-estate'),
                    ],
                    'porcentajeRetencionIVA' => [
                        'label' => __('Retención IVA (%)', 'homlity-real-estate'),
                    ],
                    'retencionIvaActiva' => [
                        'label' => __('Retención IVA activa', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Mostrar retención de IVA activada al cargar', 'homlity-real-estate'),
                    ],
                    'porcentajeIva' => [
                        'label' => __('IVA (%)', 'homlity-real-estate'),
                    ],
                    'costoBancarioFijo' => [
                        'label' => __('Valor fijo gastos bancarios', 'homlity-real-estate'),
                        'help' => __('Déjalo vacío si usarás cuatro por mil o porcentaje.', 'homlity-real-estate'),
                    ],
                    'gastosBancariosModalidad' => [
                        'label' => __('Modalidad de gastos bancarios', 'homlity-real-estate'),
                        'type' => 'select',
                        'options' => [
                            'cuatro_por_mil' => __('Cuatro por mil (4 x 1.000)', 'homlity-real-estate'),
                            'porcentaje' => __('Porcentaje personalizado', 'homlity-real-estate'),
                            'valor_fijo' => __('Valor fijo', 'homlity-real-estate'),
                        ],
                    ],
                    'gastosBancariosActivos' => [
                        'label' => __('Gastos bancarios activos', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Mostrar gastos bancarios activados al cargar', 'homlity-real-estate'),
                    ],
                    'introConceptos' => [
                        'label' => __('Descripción de conceptos', 'homlity-real-estate'),
                        'type' => 'textarea',
                    ],
                    'notaPie' => [
                        'label' => __('Nota al pie', 'homlity-real-estate'),
                        'type' => 'textarea',
                    ],
                ],
            ],
            'venta' => [
                'title' => __('Simulador de venta', 'homlity-real-estate'),
                'fields' => [
                    'porcentajeGastosNotariales' => [
                        'label' => __('Gastos notariales (%)', 'homlity-real-estate'),
                    ],
                    'valorConstitucionAfectacionViviendaFamiliar' => [
                        'label' => __('Valor constitución afectación vivienda familiar (campo heredado)', 'homlity-real-estate'),
                        'help' => __('Valor de referencia para compatibilidad con simulaciones anteriores. Los valores vigentes se configuran en Protecciones Familiares.', 'homlity-real-estate'),
                    ],
                    'porcentajeIva' => [
                        'label' => __('IVA (%)', 'homlity-real-estate'),
                    ],
                    'porcentajeConstitucionHipoteca' => [
                        'label' => __('Constitución hipoteca (%)', 'homlity-real-estate'),
                    ],
                    'portcentajeBeneficiencia' => [
                        'label' => __('Beneficencia (%)', 'homlity-real-estate'),
                    ],
                    'labelImpuestoDepartamental' => [
                        'label' => __('Etiqueta impuesto departamental', 'homlity-real-estate'),
                    ],
                    'portcentajeRegistroCompra' => [
                        'label' => __('Registro compra (%)', 'homlity-real-estate'),
                    ],
                    'valorbeneficienciaConstitucionHipoteca' => [
                        'label' => __('Valor beneficencia constitución hipoteca', 'homlity-real-estate'),
                    ],
                    'porcentajeTimbre' => [
                        'label' => __('Impuesto de timbre (%)', 'homlity-real-estate'),
                    ],
                    'valorMinimoAplicaImpuestoTimbre' => [
                        'label' => __('Valor mínimo aplica impuesto de timbre', 'homlity-real-estate'),
                    ],
                    'porcentajeRetencionFuente' => [
                        'label' => __('Retención en la fuente (%)', 'homlity-real-estate'),
                    ],
                    'otrosGastos' => [
                        'label' => __('Otros gastos', 'homlity-real-estate'),
                    ],
                    'comisionHabilitada' => [
                        'label' => __('Comisión inmobiliaria / asesor', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Activar comisión en el simulador de venta', 'homlity-real-estate'),
                    ],
                    'porcentajeComision' => [
                        'label' => __('Comisión (%)', 'homlity-real-estate'),
                    ],
                    'comisionMinima' => [
                        'label' => __('Comisión mínima', 'homlity-real-estate'),
                    ],
                    'labelComision' => [
                        'label' => __('Etiqueta de la comisión asesor', 'homlity-real-estate'),
                    ],
                    'comisionPropiedadUrbanaHabilitada' => [
                        'label' => __('Comisión propiedad urbana', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Habilitar opción de comisión para propiedad urbana en el simulador', 'homlity-real-estate'),
                    ],
                    'comisionPropiedadUrbanaPorcentaje' => [
                        'label' => __('Porcentaje comisión propiedad urbana (%)', 'homlity-real-estate'),
                        'help' => __('Se aplica sobre el valor del inmueble. Por defecto 3%.', 'homlity-real-estate'),
                    ],
                    'comisionPropiedadUrbanaLabel' => [
                        'label' => __('Etiqueta comisión propiedad urbana', 'homlity-real-estate'),
                    ],
                    'comisionPropiedadRuralHabilitada' => [
                        'label' => __('Comisión propiedad rural', 'homlity-real-estate'),
                        'type' => 'checkbox',
                        'checkboxLabel' => __('Habilitar opción de comisión para propiedad rural en el simulador', 'homlity-real-estate'),
                    ],
                    'comisionPropiedadRuralPorcentaje' => [
                        'label' => __('Porcentaje comisión propiedad rural (%)', 'homlity-real-estate'),
                        'help' => __('Se aplica sobre el valor del inmueble. Por defecto 5%.', 'homlity-real-estate'),
                    ],
                    'comisionPropiedadRuralLabel' => [
                        'label' => __('Etiqueta comisión propiedad rural', 'homlity-real-estate'),
                    ],
                    'introConceptos' => [
                        'label' => __('Descripción de conceptos', 'homlity-real-estate'),
                        'type' => 'textarea',
                    ],
                    'notaPie' => [
                        'label' => __('Nota al pie', 'homlity-real-estate'),
                        'type' => 'textarea',
                    ],
                ],
            ],
        ];
    }

    public static function sanitize($input): array
    {
        $defaults = self::defaults();
        $sections = self::fields();
        $sanitized = $defaults;

        if (!is_array($input)) {
            return $sanitized;
        }

        foreach ($sections as $sectionKey => $section) {
            foreach ($section['fields'] as $fieldKey => $field) {
                $type = $field['type'] ?? 'text';

                if ($type === 'checkbox') {
                    $sanitized[$sectionKey][$fieldKey] = isset($input[$sectionKey][$fieldKey]) && (string) $input[$sectionKey][$fieldKey] === '1' ? '1' : '0';
                    continue;
                }

                $raw = $input[$sectionKey][$fieldKey] ?? $defaults[$sectionKey][$fieldKey];
                $raw = is_string($raw) ? wp_unslash($raw) : $raw;

                if ($type === 'textarea') {
                    $sanitized[$sectionKey][$fieldKey] = wp_kses_post((string) $raw);
                } else {
                    $sanitized[$sectionKey][$fieldKey] = sanitize_text_field((string) $raw);
                }
            }
        }

        // Sanitize nested protecciones_familiares for venta.
        $rawPf = $input['venta']['protecciones_familiares'] ?? null;
        $sanitized['venta']['protecciones_familiares'] = self::sanitizeProteccionesFamiliares(
            is_array($rawPf) ? $rawPf : []
        );

        return $sanitized;
    }

    /**
     * Sanitize the nested protecciones_familiares configuration block.
     * Merges incoming values over defaults so missing keys always fall back safely.
     */
    public static function sanitizeProteccionesFamiliares(array $input): array
    {
        $defaults = self::defaultsProteccionesFamiliares();

        $sanitized = $defaults;

        // Vigencias.
        if (!empty($input['vigencia_desde'])) {
            $sanitized['vigencia_desde'] = sanitize_text_field($input['vigencia_desde']);
        }
        if (!empty($input['vigencia_hasta'])) {
            $sanitized['vigencia_hasta'] = sanitize_text_field($input['vigencia_hasta']);
        }

        // Valores por defecto.
        $numericKeys = ['tarifa_notarial_acto_sin_cuantia', 'registro_acto_sin_cuantia_primer_folio', 'registro_acto_sin_cuantia_folio_adicional', 'iva_porcentaje'];
        foreach ($numericKeys as $key) {
            if (isset($input['valores_por_defecto'][$key])) {
                $sanitized['valores_por_defecto'][$key] = (int) $input['valores_por_defecto'][$key];
            }
        }

        // Actos de afectación vivienda familiar (cancelacion / constitucion).
        $afectacionSubtipos = ['cancelacion', 'constitucion'];
        foreach ($afectacionSubtipos as $subtipo) {
            $sanitized['afectacion_vivienda_familiar'][$subtipo] = self::sanitizeActoSinCuantia(
                $input['afectacion_vivienda_familiar'][$subtipo] ?? [],
                $defaults['afectacion_vivienda_familiar'][$subtipo]
            );
        }

        // Actos de patrimonio de familia (cancelacion / constitucion_voluntaria / constitucion_vis).
        $patrimonioSubtipos = ['cancelacion', 'constitucion_voluntaria'];
        foreach ($patrimonioSubtipos as $subtipo) {
            $sanitized['patrimonio_familia'][$subtipo] = self::sanitizeActoSinCuantia(
                $input['patrimonio_familia'][$subtipo] ?? [],
                $defaults['patrimonio_familia'][$subtipo]
            );
        }

        // constitucion_vis has an extra text field.
        $rawVis = $input['patrimonio_familia']['constitucion_vis'] ?? [];
        $sanitized['patrimonio_familia']['constitucion_vis'] = self::sanitizeActoSinCuantia(
            $rawVis,
            $defaults['patrimonio_familia']['constitucion_vis']
        );
        $rawModo = $rawVis['modo_calculo'] ?? $defaults['patrimonio_familia']['constitucion_vis']['modo_calculo'];
        $allowedModos = ['requiere_validacion', 'cobrado', 'exento', 'incluido'];
        $sanitized['patrimonio_familia']['constitucion_vis']['modo_calculo'] = in_array($rawModo, $allowedModos, true) ? $rawModo : 'requiere_validacion';
        if (!empty($rawVis['mensaje_validacion'])) {
            $sanitized['patrimonio_familia']['constitucion_vis']['mensaje_validacion'] = sanitize_textarea_field($rawVis['mensaje_validacion']);
        }

        return $sanitized;
    }

    /**
     * Sanitize a single acto-sin-cuantia configuration block.
     */
    private static function sanitizeActoSinCuantia(array $input, array $defaults): array
    {
        $sanitized = $defaults;

        $boolKeys = ['activo', 'aplica_iva', 'requiere_registro', 'permitir_edicion_responsable'];
        foreach ($boolKeys as $key) {
            if (array_key_exists($key, $input)) {
                $sanitized[$key] = (bool) $input[$key];
            }
        }

        $intKeys = ['derechos_notariales', 'porcentaje_iva', 'derechos_registro_primer_folio', 'derechos_registro_folio_adicional'];
        foreach ($intKeys as $key) {
            if (isset($input[$key])) {
                $sanitized[$key] = (int) $input[$key];
            }
        }

        $allowedResponsables = ['vendedor', 'comprador'];
        if (!empty($input['responsable_por_defecto']) && in_array($input['responsable_por_defecto'], $allowedResponsables, true)) {
            $sanitized['responsable_por_defecto'] = $input['responsable_por_defecto'];
        }

        return $sanitized;
    }

    /**
     * Resolve the protecciones_familiares config with safe fallbacks.
     * Used by SimulatorService when delivering config to the frontend.
     */
    public static function resolveProteccionesFamiliares(array $ventaConfig): array
    {
        $stored = $ventaConfig['protecciones_familiares'] ?? [];
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_replace_recursive(self::defaultsProteccionesFamiliares(), $stored);
    }

    public static function resolveLogo(): string
    {
        $customLogoId = (int) get_theme_mod('custom_logo');
        if ($customLogoId > 0) {
            $customLogoUrl = wp_get_attachment_image_url($customLogoId, 'full');
            if (is_string($customLogoUrl) && $customLogoUrl !== '') {
                return $customLogoUrl;
            }
        }

        $siteIcon = get_site_icon_url(192);
        if (is_string($siteIcon) && $siteIcon !== '') {
            return $siteIcon;
        }

        return HOMLITY_PLUGIN_URL . 'icono.png';
    }
}
