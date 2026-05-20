<?php
/**
 * Homlity Consignment Media Handler.
 *
 * Handles secure file uploads from the public consignment form.
 * Validates MIME type (real MIME, not just extension), size, and allowed formats.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Homlity_Consignment_Media_Handler
{
    // Allowed MIME types per upload type
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    private const ALLOWED_BROCHURE_MIMES = [
        'application/pdf' => ['pdf'],
    ];

    /**
     * Upload a file to the WordPress media library.
     *
     * @param array  $file      $_FILES['file'] entry.
     * @param string $type      'image' | 'brochure'
     * @return array|WP_Error   On success: ['url' => string, 'id' => int]. On failure: WP_Error.
     */
    public static function upload(array $file, string $type = 'image'): array|WP_Error
    {
        // Basic sanity checks
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new WP_Error('invalid_upload', 'Archivo no válido.');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', self::phpUploadError($file['error']));
        }

        $opts = Homlity_Consignment_Manager::options();

        // Size limit
        $max_mb   = $type === 'brochure' ? (int) $opts['max_brochure_size'] : (int) $opts['max_image_size'];
        $max_bytes = $max_mb * 1024 * 1024;
        if ($file['size'] > $max_bytes) {
            return new WP_Error(
                'file_too_large',
                sprintf('El archivo excede el tamaño máximo permitido de %d MB.', $max_mb)
            );
        }

        // Validate real MIME type (not just extension or Content-Type header)
        $real_mime = self::getRealMime($file['tmp_name']);
        $allowed   = apply_filters(
            'homlity_consignment_allowed_mime_types',
            $type === 'brochure' ? self::ALLOWED_BROCHURE_MIMES : self::ALLOWED_IMAGE_MIMES
        );

        if (!isset($allowed[$real_mime])) {
            return new WP_Error(
                'invalid_mime',
                sprintf('Tipo de archivo no permitido: %s.', esc_html($real_mime ?: 'desconocido'))
            );
        }

        // Validate extension matches MIME
        $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $valid_exts = $allowed[$real_mime];
        if (!in_array($ext, $valid_exts, true)) {
            return new WP_Error('extension_mismatch', 'La extensión del archivo no corresponde al tipo de contenido.');
        }

        // Override name to a sanitized, predictable one (prevents path traversal)
        $safe_name     = 'consignment-' . time() . '-' . wp_generate_password(8, false, false) . '.' . $ext;
        $file['name']  = $safe_name;

        // Load WordPress media upload functions
        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $overrides = [
            'test_form' => false,
            'test_type' => false,  // We already validated MIME ourselves
        ];

        $moved = wp_handle_upload($file, $overrides);

        if (isset($moved['error'])) {
            return new WP_Error('wp_upload_failed', $moved['error']);
        }

        // Create attachment in media library
        $attachment = [
            'post_mime_type' => $real_mime,
            'post_title'     => sanitize_file_name($safe_name),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => 0,
            'guid'           => $moved['url'],
        ];

        $attach_id = wp_insert_attachment($attachment, $moved['file']);
        if (is_wp_error($attach_id)) {
            return $attach_id;
        }

        $meta = wp_generate_attachment_metadata($attach_id, $moved['file']);
        wp_update_attachment_metadata($attach_id, $meta);

        return [
            'id'  => $attach_id,
            'url' => $moved['url'],
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Get the real MIME type by reading the file's magic bytes.
     * Falls back to finfo if mime_content_type is unavailable.
     */
    private static function getRealMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $path);
            finfo_close($finfo);
            return $mime ?: '';
        }
        if (function_exists('mime_content_type')) {
            return (string) mime_content_type($path);
        }
        // Last resort: read magic bytes manually
        $handle = fopen($path, 'rb'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if (!$handle) {
            return '';
        }
        $bytes = fread($handle, 12); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
        fclose($handle); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

        // JPEG: FF D8
        if (substr($bytes, 0, 2) === "\xFF\xD8") {
            return 'image/jpeg';
        }
        // PNG: 89 50 4E 47
        if (substr($bytes, 0, 4) === "\x89PNG") {
            return 'image/png';
        }
        // WebP: RIFF....WEBP
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        // PDF: %PDF
        if (substr($bytes, 0, 4) === '%PDF') {
            return 'application/pdf';
        }

        return '';
    }

    private static function phpUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió de forma incompleta.',
            UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'Directorio temporal no disponible.',
            UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir el archivo en el servidor.',
            default => 'Error desconocido al subir el archivo.',
        };
    }
}
