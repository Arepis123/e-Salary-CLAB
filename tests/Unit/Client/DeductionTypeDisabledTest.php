<?php

use App\Livewire\Client\OTEntry;
use App\Models\User;
use Livewire\Livewire;

/**
 * The generic "Other Deduction" transaction type is no longer accepted.
 *
 * These tests drive the real OTEntry component's validation. They boot the
 * app via Tests\TestCase but deliberately avoid RefreshDatabase / the database:
 * the project's migrations are MySQL-only (raw ALTER ... MODIFY ... ENUM) and
 * cannot run on the sqlite test connection. addTransaction() validates before
 * touching the DB, and with currentWorkerIndex left null the happy path stays
 * entirely in-memory, so an unsaved User is enough to act as.
 */
uses(Tests\TestCase::class);

it('rejects the disabled "deduction" transaction type', function () {
    $user = new User(['contractor_clab_no' => 'TEST123']);

    Livewire::actingAs($user)
        ->test(OTEntry::class)
        ->set('newTransactionType', 'deduction')
        ->set('newTransactionAmount', 50)
        ->set('newTransactionRemarks', 'damaged equipment')
        ->call('addTransaction')
        ->assertHasErrors(['newTransactionType']);
});

it('still accepts a valid transaction type (advance_payment)', function () {
    $user = new User(['contractor_clab_no' => 'TEST123']);

    Livewire::actingAs($user)
        ->test(OTEntry::class)
        ->set('newTransactionType', 'advance_payment')
        ->set('newTransactionAmount', 50)
        ->set('newTransactionRemarks', 'advance for medical')
        ->call('addTransaction')
        ->assertHasNoErrors();
});
