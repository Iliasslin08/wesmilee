<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspacePatientLocalTest extends TestCase
{
    use RefreshDatabase;

    public function test_mode_local_ferme_le_qr_et_mon_espace(): void
    {
        config(['cabinet.espace_patient' => false]);
        $this->get('/mon-espace')->assertNotFound()->assertSee('Bientôt disponible');
        $this->get('/c/abc')->assertNotFound()->assertSee('Bientôt disponible');
        $this->get('/accueil')->assertOk(); // la tablette d'accueil reste ouverte
    }

    public function test_mode_en_ligne_ouvre_mon_espace(): void
    {
        config(['cabinet.espace_patient' => true]);
        $this->get('/mon-espace')->assertOk();
    }
}
