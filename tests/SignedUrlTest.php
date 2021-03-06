<?php

namespace Tests;

use Carbon\Carbon;
use Faker\Factory as Faker;
use Grosv\LaravelPasswordlessLogin\LoginUrl;
use Grosv\LaravelPasswordlessLogin\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SignedUrlTest extends TestCase
{
    protected $user;
    private $url;
    private $route;
    private $expires;
    private $uid;

    public function setUp(): void
    {
        parent::setUp();

        $faker = Faker::create();
        $this->user = User::create([
            'name'              => $faker->name,
            'email'             => $faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password'          => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token'    => Str::random(10),
        ]);

        Carbon::setTestNow();

        $generator = new LoginUrl($this->user);
        $this->url = $generator->generate();
        list($route, $expires, $uid) = explode('/', ltrim(parse_url($this->url)['path'], '/'));
        $this->route = $route;
        $this->expires = $expires;
        $this->uid = $uid;
    }

    /** @test */
    public function ed_knows_how_to_set_up_phpunit()
    {
        $this->assertTrue(true);
    }

    /** @test */
    public function can_create_default_signed_login_url()
    {
        $this->assertEquals(Carbon::now()->addMinutes(30)->timestamp, $this->expires);
        $this->assertEquals($this->user->id, $this->uid);
        $this->assertEquals(config('laravel-passwordless-login.login_route_name'), $this->route);
    }

    /** @test */
    public function a_signed_request_will_log_user_in_and_redirect()
    {
        $this->assertGuest();
        $response = $this->followingRedirects()->get($this->url);
        $this->assertAuthenticatedAs($this->user);
        $response->assertSuccessful();
        Auth::logout();
        $this->assertGuest();
    }

    /** @test */
    public function an_expired_request_will_not_log_user_in()
    {
        $expired = str_replace($this->expires, $this->expires - 86400, $this->url);
        $this->assertGuest();
        $response = $this->get($expired);
        $response->assertStatus(401);
        $this->assertGuest();
    }

    /** @test */
    public function an_unsigned_request_will_not_log_user_in()
    {
        $unsigned = explode('?', $this->url)[0];
        $this->assertGuest();
        $response = $this->get($unsigned);
        $response->assertStatus(401);
        $this->assertGuest();
    }

    /** @test */
    public function an_invalid_signature_request_will_not_log_user_in()
    {
        $this->assertGuest();
        $response = $this->get($this->url.'tampered');
        $response->assertStatus(401);
        $this->assertGuest();
    }
}
