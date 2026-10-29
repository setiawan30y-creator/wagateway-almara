<?php

namespace Tests\Feature;

use App\Http\Controllers\DocsController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Panduan di dalam panel. @2026-Alifiandra */
class DocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_docs_render_with_anchors_and_internal_links(): void
    {
        $user = User::factory()->create(['role' => 'agent']);

        foreach (array_keys(DocsController::DOCS) as $slug) {
            $this->actingAs($user)->get('/panduan/'.$slug)->assertOk();
        }

        $this->actingAs($user)->get('/panduan')->assertOk()
            ->assertSee('id="2-pemasangan-di-windows-laragon"', false)
            ->assertSee('href="#2-pemasangan-di-windows-laragon"', false)
            ->assertSee(route('docs.show', 'api'), false)
            ->assertSee('<table>', false);

        $this->actingAs($user)->get('/panduan/tidak-ada')->assertNotFound();
        auth()->logout();
        $this->get('/panduan')->assertRedirect('/login');
    }
}
