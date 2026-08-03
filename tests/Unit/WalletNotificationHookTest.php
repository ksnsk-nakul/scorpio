<?php

use App\Models\User;
use App\Notifications\Contracts\NotificationSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

it('calls NotificationSender exactly once when crediting a wallet', function () {
    $this->mock(NotificationSender::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')->once();
    });

    $user = User::factory()->create(['wallet_balance_paise' => 0]);
    $user->creditWallet(50000, 'topup', 'Test credit');

    expect(true)->toBeTrue();
});

it('calls NotificationSender exactly once when debiting a wallet', function () {
    $this->mock(NotificationSender::class, function (MockInterface $mock) {
        $mock->shouldReceive('send')->once();
    });

    $user = User::factory()->create(['wallet_balance_paise' => 100000]);
    $user->debitWallet(50000, 'enrollment', 'Test debit');

    expect(true)->toBeTrue();
});

it('does not call NotificationSender when a debit fails for insufficient balance', function () {
    $this->mock(NotificationSender::class, function (MockInterface $mock) {
        $mock->shouldNotReceive('send');
    });

    $user = User::factory()->create(['wallet_balance_paise' => 100]);

    expect(fn () => $user->debitWallet(50000, 'enrollment', 'Test debit'))
        ->toThrow(RuntimeException::class);
});

it('binds NullNotificationSender by default and it does not throw', function () {
    app()->forgetInstance(NotificationSender::class);
    $sender = app(NotificationSender::class);

    expect($sender)->toBeInstanceOf(\App\Notifications\NullNotificationSender::class);
    $sender->send(new stdClass()); // must not throw
    expect(true)->toBeTrue();
});

it('creditWallet increases the balance and writes a credit transaction row', function () {
    $user = User::factory()->create(['wallet_balance_paise' => 10000]);

    $newBalance = $user->creditWallet(5000, 'topup', 'Test credit', 'Jane', 'jane@example.com');

    expect($newBalance)->toBe(15000)
        ->and($user->fresh()->wallet_balance_paise)->toBe(15000);

    $txn = $user->walletTransactions()->first();
    expect($txn->type)->toBe('credit')
        ->and($txn->amount_paise)->toBe(5000)
        ->and($txn->balance_after_paise)->toBe(15000)
        ->and($txn->category)->toBe('topup');
});

it('debitWallet decreases the balance and writes a debit transaction row', function () {
    $user = User::factory()->create(['wallet_balance_paise' => 10000]);

    $newBalance = $user->debitWallet(3000, 'enrollment', 'Test debit');

    expect($newBalance)->toBe(7000)
        ->and($user->fresh()->wallet_balance_paise)->toBe(7000);

    $txn = $user->walletTransactions()->first();
    expect($txn->type)->toBe('debit')
        ->and($txn->amount_paise)->toBe(3000)
        ->and($txn->balance_after_paise)->toBe(7000)
        ->and($txn->category)->toBe('enrollment');
});
