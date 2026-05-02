<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Services;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookPayloadSchema
{
    /**
     * @return array<string,mixed>
     */
    public static function schema(): array
    {
        return [
            'schema_version' => 'string|recommended',
            'source_key' => 'string|required',
            'event_id' => 'string|required_for_idempotency',
            'event_type' => 'string|required: property.created|property.updated|property.deleted|property.unpublished|advisor.created|advisor.updated|advisor.deleted',
            'occurred_at' => 'string|optional ISO-8601',
            'external_id' => 'string|optional when property.external_id exists',
            'property' => CanonicalPropertySchema::definition(),
            'media_policy' => [
                'mode' => 'string|optional: urls_only|download_for_push',
                'delete_missing' => 'bool|optional default false',
                'images' => CanonicalPropertySchema::imageDefinition(),
            ],
        ];
    }
}
