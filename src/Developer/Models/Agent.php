<?php

declare(strict_types=1);

/**
 * @package Homlity\Developer
 * @since   2.8.0
 */

namespace Homlity\Developer\Models;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The advisor responsible for a property.
 *
 * These are the agency's own public contact details — the ones already shown
 * on the property page. The *owner's* contact data captured by the consignment
 * form is deliberately not part of this model and is never exposed through the
 * Developer API.
 *
 * @since 2.8.0
 */
final class Agent
{
    private int $userId;
    private string $name;
    private string $email;
    private string $phone;
    private string $role;
    private string $photoUrl;
    private string $externalId;

    /**
     * @internal Built by {@see \Homlity\Developer\Services\PropertyRepository}.
     *
     * @param array<string,mixed> $data
     */
    public function __construct(array $data)
    {
        $this->userId     = (int) ($data['user_id'] ?? 0);
        $this->name       = (string) ($data['name'] ?? '');
        $this->email      = (string) ($data['email'] ?? '');
        $this->phone      = (string) ($data['phone'] ?? '');
        $this->role       = (string) ($data['role'] ?? '');
        $this->photoUrl   = (string) ($data['photo'] ?? '');
        $this->externalId = (string) ($data['external_id'] ?? '');
    }

    /**
     * WordPress user ID, or 0 when the advisor has no user account.
     *
     * @since 2.8.0
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /** @since 2.8.0 */
    public function getName(): string
    {
        return $this->name;
    }

    /** @since 2.8.0 */
    public function getEmail(): string
    {
        return $this->email;
    }

    /** @since 2.8.0 */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /** @since 2.8.0 */
    public function getRole(): string
    {
        return $this->role;
    }

    /** @since 2.8.0 */
    public function getPhotoUrl(): string
    {
        return $this->photoUrl;
    }

    /**
     * Identifier of the advisor in the CRM the property came from.
     *
     * @since 2.8.0
     */
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * @since 2.8.0
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'     => $this->userId,
            'name'        => $this->name,
            'email'       => $this->email,
            'phone'       => $this->phone,
            'role'        => $this->role,
            'photo'       => $this->photoUrl,
            'external_id' => $this->externalId,
        ];
    }
}
