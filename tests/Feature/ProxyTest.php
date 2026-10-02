<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxyTest extends TestCase
{
    public function test_urls_are_https_behind_the_platform_proxy(): void
    {
        Route::get('/_test/url', fn () => route('home'));

        $this->get('/_test/url', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'farm2home.example'])
            ->assertOk()
            ->assertSee('https://farm2home.example');
    }
}
