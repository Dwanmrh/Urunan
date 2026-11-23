<?php

use App\Actions\Telegram\TanganiUpdateTelegram;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\TagihanBaru;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.telegram.token' => 'TOKEN-UJI',
        'services.telegram.username' => 'urunan_bot',
    ]);
});

/**
 * Bentuk update Telegram untuk pesan teks dari sebuah chat.
 *
 * @return array<string, mixed>
 */
function updateTelegram(int $idUpdate, int $chatId, string $teks): array
{
    return [
        'update_id' => $idUpdate,
        'message' => ['chat' => ['id' => $chatId], 'text' => $teks],
    ];
}

test('menekan Start dengan kode yang benar menghubungkan akun', function () {
    Http::fake();
    $user = User::factory()->create(['name' => 'Sinta']);
    $user->forceFill(['telegram_token' => 'kodebenar123'])->save();

    TanganiUpdateTelegram::tangani(updateTelegram(1, 555, '/start kodebenar123'));

    $user->refresh();

    expect($user->telegram_chat_id)->toBe('555');
    expect($user->telegram_token)->toBeNull();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'sendMessage')
        && $request['chat_id'] === '555'
        && str_contains($request['text'], 'Halo Sinta'));
});

test('kode yang salah tidak menghubungkan akun mana pun', function () {
    Http::fake();
    $user = User::factory()->create();
    $user->forceFill(['telegram_token' => 'kodebenar123'])->save();

    TanganiUpdateTelegram::tangani(updateTelegram(1, 555, '/start kodesalah'));

    expect($user->fresh()->telegram_chat_id)->toBeNull();
    Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'tidak dikenal'));
});

test('perintah dengarkan mengambil pesan dari Telegram lalu menghubungkan akun', function () {
    $user = User::factory()->create();
    $user->forceFill(['telegram_token' => 'kodebenar123'])->save();

    Http::fake([
        'api.telegram.org/*getUpdates*' => Http::response(['ok' => true, 'result' => [updateTelegram(7, 777, '/start kodebenar123')]]),
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $this->artisan('telegram:dengarkan --sekali')->assertSuccessful();

    expect($user->fresh()->telegram_chat_id)->toBe('777');
});

test('notifikasi juga dikirim ke Telegram kalau akun sudah terhubung', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $user = User::factory()->create();
    $user->forceFill(['telegram_chat_id' => '555'])->save();
    $tagihan = Tagihan::factory()->create(['judul' => 'Ngegrill']);

    $user->notify(new TagihanBaru($tagihan, 22000));

    expect($user->notifications()->count())->toBe(1);
    Http::assertSent(fn (Request $request): bool => $request['chat_id'] === '555'
        && str_contains($request['text'], 'Ngegrill')
        && str_contains($request['text'], 'Rp 22.000'));
});

test('notifikasi tidak dikirim ke Telegram kalau akun belum terhubung', function () {
    Http::fake();
    $user = User::factory()->create();
    $tagihan = Tagihan::factory()->create();

    $user->notify(new TagihanBaru($tagihan, 22000));

    expect($user->notifications()->count())->toBe(1);
    Http::assertNothingSent();
});

test('tombol hubungkan membuat kode lalu membuka bot di Telegram', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $this->get(route('telegram.edit'))->assertOk()->assertSee('Hubungkan Telegram');

    $respons = Livewire::test('pages::settings.telegram')->call('hubungkan');

    $token = $user->fresh()->telegram_token;

    expect($token)->not->toBeNull();
    $respons->assertRedirect('https://t.me/urunan_bot?start='.$token);
});

test('user bisa memutuskan Telegram', function () {
    $user = User::factory()->create();
    $user->forceFill(['telegram_chat_id' => '555'])->save();

    $this->actingAs($user);

    Livewire::test('pages::settings.telegram')->call('putuskan');

    expect($user->fresh()->telegram_chat_id)->toBeNull();
});
