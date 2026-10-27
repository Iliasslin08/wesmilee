<?php

namespace App\Models\Concerns;

use App\Models\Journal;
use Illuminate\Database\Eloquent\Model;

/** Enregistre qui a créé / modifié / supprimé, et quand. */
trait Journalise
{
    public static function bootJournalise(): void
    {
        static::created(fn (Model $m) => static::journaliser($m, 'creation', null, $m->getAttributes()));
        static::updated(function (Model $m) {
            $apres = collect($m->getChanges())->except(['updated_at'])->all();
            if ($apres) {
                static::journaliser($m, 'modification', array_intersect_key($m->getOriginal(), $apres), $apres);
            }
        });
        static::deleted(fn (Model $m) => static::journaliser($m, 'suppression', $m->getOriginal(), null));
    }

    protected static function journaliser(Model $m, string $action, ?array $avant, ?array $apres): void
    {
        $cacher = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
        Journal::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'sujet_type' => class_basename($m),
            'sujet_id' => $m->getKey(),
            'avant' => $avant ? array_diff_key($avant, array_flip($cacher)) : null,
            'apres' => $apres ? array_diff_key($apres, array_flip($cacher)) : null,
        ]);
    }
}
