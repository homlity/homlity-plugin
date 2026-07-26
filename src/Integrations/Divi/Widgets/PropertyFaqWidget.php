<?php

namespace Homlity\PluginInmobiliario\Integrations\Divi\Widgets;

use Homlity\PluginInmobiliario\Integrations\Divi\Compatibility\Controls_Manager;
use Homlity\PluginInmobiliario\Services\IconRenderer;

if (!defined('ABSPATH')) {
    exit;
}

class PropertyFaqWidget extends BasePropertyWidget
{
    private static int $instanceCounter = 0;

    public function get_name(): string
    {
        return 'property_faq';
    }

    public function get_title(): string
    {
        return __('Preguntas frecuentes del inmueble', 'homlity-real-estate');
    }

    public function get_icon(): string
    {
        return 'eicon-help-o';
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => __('Contenido', 'homlity-real-estate'),
        ]);
        $this->register_property_control();
        $this->add_control('enable_auto_faqs', [
            'label' => __('Activar preguntas automáticas', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $questions = [
            'price' => __('Precio', 'homlity-real-estate'),
            'operation' => __('Operación', 'homlity-real-estate'),
            'location' => __('Ubicación', 'homlity-real-estate'),
            'type' => __('Tipo de inmueble', 'homlity-real-estate'),
            'area' => __('Área', 'homlity-real-estate'),
            'bedrooms' => __('Habitaciones', 'homlity-real-estate'),
            'bathrooms' => __('Baños', 'homlity-real-estate'),
            'parking' => __('Parqueaderos', 'homlity-real-estate'),
            'admin' => __('Administración', 'homlity-real-estate'),
            'stratum' => __('Estrato', 'homlity-real-estate'),
            'features' => __('Características', 'homlity-real-estate'),
            'code' => __('Código', 'homlity-real-estate'),
            'contact' => __('Contacto', 'homlity-real-estate'),
        ];
        foreach ($questions as $key => $label) {
            $this->add_control('show_' . $key, [
                'label' => $label,
                'type' => Controls_Manager::SWITCHER,
                'default' => 'yes',
                'condition' => ['enable_auto_faqs' => 'yes'],
            ]);
        }
        $this->add_control('auto_limit', [
            'label' => __('Límite de preguntas (0 = todas)', 'homlity-real-estate'),
            'type' => Controls_Manager::NUMBER,
            'default' => 0,
        ]);
        $this->add_control('include_global_faqs', [
            'label' => __('Incluir preguntas globales configuradas', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);
        $this->end_controls_section();

        $this->start_controls_section('general', [
            'label' => __('Presentación', 'homlity-real-estate'),
        ]);
        $this->add_control('block_title', [
            'label' => __('Título', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXT,
            'default' => __('Preguntas frecuentes del inmueble', 'homlity-real-estate'),
        ]);
        $this->add_control('block_description', [
            'label' => __('Descripción', 'homlity-real-estate'),
            'type' => Controls_Manager::TEXTAREA,
            'default' => __('Resuelve las dudas más comunes sobre este inmueble antes de solicitar más información o agendar una visita.', 'homlity-real-estate'),
        ]);
        $this->add_control('layout', [
            'label' => __('Presentación', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'accordion',
            'options' => [
                'accordion' => __('Acordeón', 'homlity-real-estate'),
                'open' => __('Lista abierta', 'homlity-real-estate'),
                'cards' => __('Tarjetas', 'homlity-real-estate'),
            ],
        ]);
        $this->add_control('first_open', [
            'label' => __('Primera pregunta abierta', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => 'yes',
            'condition' => ['layout' => 'accordion'],
        ]);
        $this->add_control('allow_multiple', [
            'label' => __('Permitir varias preguntas abiertas', 'homlity-real-estate'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
            'condition' => ['layout' => 'accordion'],
        ]);
        $this->add_control('icon_closed', [
            'label' => __('Icono al cerrar', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-chevron-down', 'library' => 'fa-solid'],
        ]);
        $this->add_control('icon_open', [
            'label' => __('Icono al abrir', 'homlity-real-estate'),
            'type' => Controls_Manager::ICONS,
            'default' => ['value' => 'fas fa-chevron-up', 'library' => 'fa-solid'],
        ]);
        $this->end_controls_section();

        $this->start_controls_section('style', [
            'label' => __('Estilos', 'homlity-real-estate'),
            'tab' => Controls_Manager::TAB_STYLE,
        ]);
        $this->add_control('title_align', [
            'label' => __('Alineación del título', 'homlity-real-estate'),
            'type' => Controls_Manager::SELECT,
            'default' => 'left',
            'options' => [
                'left' => __('Izquierda', 'homlity-real-estate'),
                'center' => __('Centro', 'homlity-real-estate'),
                'right' => __('Derecha', 'homlity-real-estate'),
            ],
            'selectors' => [
                '{{WRAPPER}} .homlity-property-faq-header' => 'text-align: {{VALUE}};',
            ],
        ]);
        $this->add_control('title_color', [
            'label' => __('Color del título', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-property-faq-title' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('question_color', [
            'label' => __('Color de las preguntas', 'homlity-real-estate'),
            'type' => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .homlity-property-faq-question' => 'color: {{VALUE}};',
            ],
        ]);
        $this->end_controls_section();
    }

    protected function render(): void
    {
        wp_enqueue_style(
            'homlity-property-faq',
            HOMLITY_PLUGIN_URL . 'assets/css/property-faq.css',
            [],
            HOMLITY_PLUGIN_VERSION
        );
        wp_enqueue_script(
            'homlity-property-faq',
            HOMLITY_PLUGIN_URL . 'assets/js/property-faq.js',
            [],
            HOMLITY_PLUGIN_VERSION,
            true
        );

        if (!class_exists('Homlity_Property_FAQ_Generator')) {
            require_once HOMLITY_PLUGIN_PATH . 'includes/elementor/helpers/class-homlity-property-faq-generator.php';
        }

        $settings = $this->get_settings_for_display();
        $postId = $this->current_property_id();
        if ($postId <= 0 || !class_exists('Homlity_Property_FAQ_Generator')) {
            return;
        }

        $generator = new \Homlity_Property_FAQ_Generator($postId);
        $faqs = [];
        if (($settings['enable_auto_faqs'] ?? 'yes') === 'yes') {
            $faqs = $generator->generate_auto_faqs($settings);
            $limit = (int) ($settings['auto_limit'] ?? 0);
            if ($limit > 0) {
                $faqs = array_slice($faqs, 0, $limit);
            }
        }

        if (($settings['include_global_faqs'] ?? 'yes') === 'yes' && function_exists('homlity_get_global_faqs')) {
            $globalFaqs = [];
            foreach (homlity_get_global_faqs('property') as $faq) {
                $question = sanitize_text_field($faq['question'] ?? '');
                $answer = wp_kses_post($faq['answer'] ?? '');
                if ($question !== '' && $answer !== '') {
                    $globalFaqs[] = [
                        'key' => 'global_' . sanitize_key($question),
                        'question' => $question,
                        'answer' => $answer,
                    ];
                }
            }
            $faqs = $generator->deduplicate_faqs(array_merge($faqs, $globalFaqs));
        }

        $faqs = apply_filters('homlity_faq_final_questions', $faqs, $postId, $settings);
        if (empty($faqs)) {
            return;
        }

        $layout = in_array($settings['layout'] ?? '', ['accordion', 'open', 'cards'], true)
            ? $settings['layout']
            : 'accordion';
        $firstOpen = $layout === 'accordion' && ($settings['first_open'] ?? 'yes') === 'yes';
        $allowMultiple = ($settings['allow_multiple'] ?? '') === 'yes';
        $blockTitle = sanitize_text_field($settings['block_title'] ?? '');
        $description = wp_kses_post($settings['block_description'] ?? '');
        $iconOpen = is_array($settings['icon_open'] ?? null)
            ? $settings['icon_open']
            : ['value' => 'fas fa-chevron-up', 'library' => 'fa-solid'];
        $iconClosed = is_array($settings['icon_closed'] ?? null)
            ? $settings['icon_closed']
            : ['value' => 'fas fa-chevron-down', 'library' => 'fa-solid'];

        self::$instanceCounter++;
        $widgetUid = 'hfaq-divi-' . $postId . '-' . self::$instanceCounter;
        ?>
        <div class="homlity-property-faq-widget homlity-property-faq--<?php echo esc_attr($layout); ?>" id="<?php echo esc_attr($widgetUid); ?>">
            <?php if ($blockTitle !== '' || $description !== ''): ?>
                <div class="homlity-property-faq-header">
                    <?php if ($blockTitle !== ''): ?>
                        <h2 class="homlity-property-faq-title"><?php echo esc_html($blockTitle); ?></h2>
                    <?php endif; ?>
                    <?php if ($description !== ''): ?>
                        <div class="homlity-property-faq-description"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="homlity-property-faq-list"
                 data-layout="<?php echo esc_attr($layout); ?>"
                 data-first-open="<?php echo $firstOpen ? 'true' : 'false'; ?>"
                 data-allow-multiple="<?php echo $allowMultiple ? 'true' : 'false'; ?>">
                <?php foreach ($faqs as $index => $faq):
                    $answerId = $widgetUid . '-answer-' . $index;
                    $buttonId = $widgetUid . '-button-' . $index;
                    $isOpen = $layout !== 'accordion' || ($firstOpen && $index === 0);
                    ?>
                    <div class="homlity-property-faq-item<?php echo $isOpen ? ' is-active' : ''; ?>">
                        <button class="homlity-property-faq-question"
                                id="<?php echo esc_attr($buttonId); ?>"
                                aria-expanded="<?php echo $isOpen ? 'true' : 'false'; ?>"
                                aria-controls="<?php echo esc_attr($answerId); ?>"
                                type="button">
                            <span class="homlity-property-faq-question-text"><?php echo esc_html($faq['question']); ?></span>
                            <span class="homlity-property-faq-icon homlity-property-faq-icon--open" aria-hidden="true">
                                <?php IconRenderer::render($iconOpen, ['aria-hidden' => 'true']); ?>
                            </span>
                            <span class="homlity-property-faq-icon homlity-property-faq-icon--closed" aria-hidden="true">
                                <?php IconRenderer::render($iconClosed, ['aria-hidden' => 'true']); ?>
                            </span>
                        </button>
                        <div class="homlity-property-faq-answer"
                             id="<?php echo esc_attr($answerId); ?>"
                             role="region"
                             aria-labelledby="<?php echo esc_attr($buttonId); ?>"
                             <?php echo $isOpen ? '' : 'hidden'; ?>>
                            <div class="homlity-property-faq-answer-inner">
                                <?php echo wp_kses_post($faq['answer']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
}
