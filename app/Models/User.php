<?php

namespace App\Models;

use App\Enums\EtatDocteur;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'telephone', 'actif',
        'specialite', 'couleur', 'salle', 'etat', 'etat_depuis', 'email_verified_at',
        'fait_consultation', 'chef_labo', 'assistante', 'duree_rdv', 'taches_principales', 'taches_secondaires', 'autres_taches',
    ];

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
            'etat' => EtatDocteur::class,
            'etat_depuis' => 'datetime',
            'actif' => 'boolean',
            'fait_consultation' => 'boolean',
            'chef_labo' => 'boolean',
            'duree_rdv' => 'integer',
        ];
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->take(2)
            ->map(fn ($w) => Str::substr($w, 0, 1))->implode('');
    }

    public function hasRole(Role ...$roles): bool
    {
        return $this->isAdmin() || in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [Role::Admin, Role::Developpeur], true);
    }

    /**
     * Docteur de consultation (ex. Dr Nassim) : après la consultation, il n'envoie pas lui-même
     * le patient à un autre docteur ; il écrit le plan, la finance présente le devis au patient
     * (il accepte tout, une partie ou rien) et fixe le docteur et la date.
     */
    public function passeParLeChef(): bool
    {
        return $this->role === Role::Docteur && $this->fait_consultation;
    }

    /** Chef(fe) du labo (ex. Sofia MOSTAFI) : accepte les demandes et les confie. Le chef du centre aussi. */
    public function estChefLabo(): bool
    {
        return $this->isAdmin() || ($this->role === Role::Labo && $this->chef_labo);
    }

    /** Les comptes développeur restent invisibles pour le cabinet. */
    public function scopeVisibles(Builder $q): Builder
    {
        return $q->where('role', '!=', Role::Developpeur->value);
    }

    /** L'équipe du laboratoire, avec la mission de chacun. */
    public function scopeEquipeLabo(Builder $q): Builder
    {
        return $q->where('role', \App\Enums\Role::Labo->value)->where('actif', true)->orderBy('name');
    }

    public function scopeDocteurs(Builder $q): Builder
    {
        return $q->where('role', Role::Docteur->value)->where('actif', true)->orderBy('name');
    }

    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class, 'docteur_id');
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class, 'docteur_id');
    }

    public function changerEtat(EtatDocteur $etat): void
    {
        static::whereKey($this->id)->update(['etat' => $etat->value, 'etat_depuis' => now()]);
        $this->forceFill(['etat' => $etat, 'etat_depuis' => now()])->syncOriginal();
    }

    /** E-mail toujours enregistré sans espace et en minuscules (sinon la connexion échoue). */
    protected function email(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(set: fn (?string $v) => $v === null ? null : \Illuminate\Support\Str::lower(trim($v)));
    }
}
