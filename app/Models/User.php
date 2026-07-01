<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // --- Profils Internes (Takada) ---
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_AGENT = 'agent';

    // --- Profils Externes (Clients B2B) ---
    public const ROLE_AGENCY = 'agency';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Vérifie si l'utilisateur possède un rôle spécifique.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Vérification dynamique et instantanée des permissions stockées en Base de Données
     */
    public function hasPermission(string $permission): bool
    {
        // Le Super Admin conserve un droit absolu et permanent sur tout le système
        if ($this->hasRole(self::ROLE_SUPER_ADMIN)) {
            return true;
        }

        // Système de cache en mémoire vive par cycle de requête (Évite le spam de requêtes SQL)
        static $permissionsCache = null;

        if ($permissionsCache === null) {
            $permissionsCache = DB::table('roles')->pluck('permissions', 'id')->toArray();
        }

        $rawJson = $permissionsCache[$this->role] ?? '[]';
        $myPermissions = json_decode($rawJson, true) ?: [];

        return in_array($permission, $myPermissions);
    }

    /**
     * Le "Vigile" de Filament : Détermine qui a le droit de voir la page de connexion /admin
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_AGENT,
        ]);
    }
}