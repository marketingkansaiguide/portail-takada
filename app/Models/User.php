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

#[Fillable(['name', 'email', 'password', 'role', 'agency_id'])]
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
     * Relation avec l'agence pour les comptes partenaires B2B.
     */
    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    private ?array $resolvedRoleIds = null;

    /**
     * Profils cumulables (pivot role_user). users.role reste lu en complément
     * (rôle principal, synchronisé à chaque enregistrement) : aucun accès perdu.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }

    /** @return array<int, string> */
    public function roleIds(): array
    {
        if ($this->resolvedRoleIds === null) {
            $ids = $this->exists
                ? DB::table('role_user')->where('user_id', $this->getKey())->pluck('role_id')->all()
                : [];
            if ($this->role) {
                $ids[] = $this->role;
            }
            $this->resolvedRoleIds = array_values(array_unique($ids));
        }

        return $this->resolvedRoleIds;
    }

    /** Rôle principal (colonne users.role) pour un jeu de profils. */
    public static function primaryRoleFor(array $ids): ?string
    {
        foreach ([self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_AGENT, self::ROLE_AGENCY] as $legacy) {
            if (in_array($legacy, $ids, true)) {
                return $legacy;
            }
        }

        return $ids[0] ?? null;
    }

    /** Définit users.role (rôle principal) à partir des profils attribués. */
    public function syncPrimaryRole(): void
    {
        $primary = self::primaryRoleFor(
            DB::table('role_user')->where('user_id', $this->getKey())->pluck('role_id')->all()
        );
        if ($primary && $primary !== $this->role) {
            DB::table('users')->where('id', $this->getKey())->update(['role' => $primary]);
            $this->role = $primary;
            $this->syncOriginalAttribute('role');
        }
        $this->resolvedRoleIds = null;
    }

    /**
     * Vérifie si l'utilisateur possède un rôle spécifique.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roleIds(), true);
    }

    /** Accès au back-office : au moins un profil marqué is_staff. */
    public function isStaff(): bool
    {
        $roles = self::rolesCache();
        foreach ($this->roleIds() as $id) {
            if (! empty($roles[$id]['is_staff'])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array{permissions: array, is_staff: bool}> */
    private static function rolesCache(): array
    {
        static $cache = null;
        $cache ??= DB::table('roles')->get()->mapWithKeys(fn ($r) => [
            $r->id => [
                'permissions' => json_decode($r->permissions ?: '[]', true) ?: [],
                'is_staff' => (bool) ($r->is_staff ?? false),
            ],
        ])->all();

        return $cache;
    }

    /**
     * Permissions = union de celles de tous les profils de l'utilisateur.
     */
    public function hasPermission(string $permission): bool
    {
        // Le Super Admin conserve un droit absolu et permanent sur tout le système
        if ($this->hasRole(self::ROLE_SUPER_ADMIN)) {
            return true;
        }

        $roles = self::rolesCache();
        foreach ($this->roleIds() as $id) {
            if (in_array($permission, $roles[$id]['permissions'] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Segments de dossiers visibles : null = tous (y compris non classés).
     *
     * @return array<int, string>|null
     */
    public function visibleFolderSegments(): ?array
    {
        if ($this->hasPermission('folder.segment.all')) {
            return null;
        }

        return array_values(array_filter(
            ['fit', 'groupe', 'billetterie'],
            fn (string $s) => $this->hasPermission("folder.segment.$s")
        ));
    }

    /** Segment implicite d'un dossier créé par cet utilisateur (null si ambigu). */
    public function defaultFolderSegment(): ?string
    {
        if ($this->hasRole(self::ROLE_AGENCY)) {
            return 'billetterie';
        }
        $map = ['commercial_fit' => 'fit', 'commercial_groupe' => 'groupe', 'billetterie' => 'billetterie'];
        $found = array_unique(array_values(array_intersect_key($map, array_flip($this->roleIds()))));

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * Le "Vigile" de Filament : Détermine qui a le droit d'accéder à chaque panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Panel d'administration interne (Takada) : profils marqués "back-office"
        if ($panel->getId() === 'admin') {
            return $this->isStaff();
        }

        // Cloisonnement strict : seuls les comptes "agency" entrent dans le panel agence.
        if ($panel->getId() === 'agency') {
            return $this->hasRole(self::ROLE_AGENCY);
        }

        return false;
    }

    /**
     * Dossiers où cet utilisateur est le vendeur principal assigné
     */
    public function mainSellerFolders()
    {
        return $this->hasMany(Folder::class, 'main_seller_id');
    }
}