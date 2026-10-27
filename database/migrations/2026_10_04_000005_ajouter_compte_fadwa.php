<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

// Fadwa EL JADID : explique au patient sa facture (devis, payé, reste) → rôle Finance
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $u = User::withTrashed()->firstOrNew(['email' => 'eljadidfadwa23@gmail.com']);
        if ($u->trashed()) {
            $u->restore();
        }
        $u->fill(['name' => 'Fadwa EL JADID', 'role' => Role::Finance, 'actif' => true, 'email_verified_at' => $u->email_verified_at ?? now()]);
        if (! $u->exists) {
            $u->password = 'password';
        }
        $u->save();
        echo "Compte Fadwa EL JADID (Finance) : eljadidfadwa23@gmail.com / password\n";
    }

    public function down(): void {}
};
