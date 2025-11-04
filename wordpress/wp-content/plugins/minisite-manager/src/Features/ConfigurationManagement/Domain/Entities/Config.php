<?php

namespace Minisite\Features\ConfigurationManagement\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use Minisite\Infrastructure\Security\ConfigEncryption;

#[ORM\Entity(repositoryClass: \Minisite\Features\ConfigurationManagement\Repositories\ConfigRepository::class)]
#[ORM\Table(name: 'minisite_config')]
class Config
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    public ?int $id = null;

    #[ORM\Column(name: 'config_key', type: 'string', length: 100, unique: true)]
    public string $key; // lowercase with underscores, e.g., 'whatsapp_access_token'

    #[ORM\Column(name: 'config_value', type: 'text', nullable: true)]
    public ?string $value = null;

    #[ORM\Column(name: 'config_type', type: 'string', length: 20)]
    public string $type = 'string'; // 'string' | 'integer' | 'boolean' | 'json' | 'encrypted' | 'secret'

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $description = null;

    #[ORM\Column(name: 'is_sensitive', type: 'boolean')]
    public bool $isSensitive = false;

    #[ORM\Column(name: 'is_required', type: 'boolean')]
    public bool $isRequired = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    public \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Get typed value based on config type
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => (bool) $this->value,
            'json' => json_decode($this->value ?? '{}', true),
            'encrypted' => $this->decryptValue(),
            'secret' => $this->value, // Never decrypt, only compare
            default => $this->value,
        };
    }

    /**
     * Set typed value (encrypt if needed)
     */
    public function setTypedValue(mixed $value): void
    {
        $this->value = match ($this->type) {
            'integer' => (string) $value,
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            'encrypted' => ConfigEncryption::encrypt((string) $value),
            'secret' => hash('sha256', (string) $value),
            default => (string) $value,
        };

        $this->updatedAt = new \DateTimeImmutable();
    }

    private function decryptValue(): ?string
    {
        if (!$this->value) {
            return null;
        }

        return ConfigEncryption::decrypt($this->value);
    }
}
