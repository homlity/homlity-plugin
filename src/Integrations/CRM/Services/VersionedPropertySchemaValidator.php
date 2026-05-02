<?php

namespace Homlity\PluginInmobiliario\Integrations\CRM\Services;

use Homlity\PluginInmobiliario\Integrations\CRM\DTO\SchemaValidationResult;

if (!defined('ABSPATH')) {
    exit;
}

class VersionedPropertySchemaValidator
{
    public const CURRENT_SCHEMA_VERSION = '1.0';

    /** @param array<string,mixed> $payload */
    public function validateWebhookPayload(array $payload): SchemaValidationResult
    {
        $errors = [];
        $warnings = [];

        $version = sanitize_text_field((string) ($payload['schema_version'] ?? ''));
        if ($version === '') {
            $warnings[] = $this->issue('schema_version', 'missing_recommended', 'schema_version is recommended for forward compatibility.');
        } elseif ($version !== self::CURRENT_SCHEMA_VERSION) {
            $warnings[] = $this->issue('schema_version', 'unsupported_version', 'Unknown schema_version; adapter should verify compatibility.');
        }

        foreach (['source_key', 'event_type'] as $field) {
            if (trim((string) ($payload[$field] ?? '')) === '') {
                $errors[] = $this->issue($field, 'required', $field . ' is required.');
            }
        }

        if (empty($payload['event_id'])) {
            $warnings[] = $this->issue('event_id', 'missing_idempotency_key', 'event_id is required for stable idempotency; core will derive a hash fallback.');
        }

        $property = is_array($payload['property'] ?? null) ? $payload['property'] : [];
        if (!$property) {
            $errors[] = $this->issue('property', 'required', 'property object is required.');
        } else {
            $propertyResult = $this->validateProperty($property);
            $errors = array_merge($errors, $propertyResult->errors());
            $warnings = array_merge($warnings, $propertyResult->warnings());
        }

        return new SchemaValidationResult($errors, $warnings);
    }

    /** @param array<string,mixed> $property */
    public function validateProperty(array $property): SchemaValidationResult
    {
        $errors = [];
        $warnings = [];

        foreach (['external_id', 'title'] as $field) {
            if (trim((string) ($property[$field] ?? '')) === '') {
                $errors[] = $this->issue('property.' . $field, 'required', $field . ' is required.');
            }
        }

        $images = $this->extractImages($property);
        foreach ($images as $index => $image) {
            if (!is_array($image)) {
                $errors[] = $this->issue('property.media.images.' . $index, 'invalid_type', 'Image item must be an object.');
                continue;
            }

            $url = trim((string) ($image['url'] ?? $image['external_url'] ?? ''));
            if ($url === '') {
                $errors[] = $this->issue('property.media.images.' . $index . '.url', 'required', 'Image url is required.');
            } elseif (!wp_http_validate_url($url)) {
                $errors[] = $this->issue('property.media.images.' . $index . '.url', 'invalid_url', 'Image url must be absolute HTTP/HTTPS.');
            }

            if (isset($image['position']) && filter_var($image['position'], FILTER_VALIDATE_INT) === false) {
                $warnings[] = $this->issue('property.media.images.' . $index . '.position', 'invalid_position', 'position should be an integer.');
            }
        }

        return new SchemaValidationResult($errors, $warnings);
    }

    /** @param array<string,mixed> $property */
    private function extractImages(array $property): array
    {
        if (isset($property['media']['images']) && is_array($property['media']['images'])) {
            return $property['media']['images'];
        }

        if (isset($property['images']) && is_array($property['images'])) {
            return $property['images'];
        }

        return [];
    }

    /** @return array<string,string> */
    private function issue(string $field, string $code, string $message): array
    {
        return [
            'field' => $field,
            'code' => $code,
            'message' => $message,
        ];
    }
}
