<?php

declare(strict_types=1);

namespace AvatarTok\Models;

use AvatarTok\Core\Database;

class User
{
    public string  $id;
    public string  $username;
    public string  $email;
    public string  $password_hash;
    public string  $role;
    public string  $status;
    public int     $trust_score;
    public ?string $display_name;
    public ?string $bio;
    public ?string $country;
    public ?string $stripe_customer_id;
    public ?string $email_verified_at;
    public ?string $phone_verified_at;
    public ?string $last_login_at;
    public ?string $suspended_until;
    public string  $created_at;

    public static function findById(string $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        return $row ? self::hydrate($row) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $row = Database::fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        return $row ? self::hydrate($row) : null;
    }

    public static function findByUsername(string $username): ?self
    {
        $row = Database::fetchOne("SELECT * FROM users WHERE username = ?", [$username]);
        return $row ? self::hydrate($row) : null;
    }

    public static function existsByEmail(string $email): bool
    {
        return (bool) Database::fetchOne("SELECT 1 FROM users WHERE email = ?", [$email]);
    }

    public static function existsByUsername(string $username): bool
    {
        return (bool) Database::fetchOne("SELECT 1 FROM users WHERE username = ?", [$username]);
    }

    public function toPublicArray(): array
    {
        return [
            'id'           => $this->id,
            'username'     => $this->username,
            'display_name' => $this->display_name ?? $this->username,
            'bio'          => $this->bio,
            'country'      => $this->country,
            'role'         => $this->role,
            'created_at'   => $this->created_at,
        ];
    }

    private static function hydrate(object $row): self
    {
        $user = new self();
        foreach (get_object_vars($row) as $key => $val) {
            if (property_exists($user, $key)) {
                $user->$key = $val;
            }
        }
        return $user;
    }
}
