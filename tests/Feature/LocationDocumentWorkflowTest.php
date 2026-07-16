<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Location;
use App\Models\LocationDocument;
use App\Models\User;
use App\Models\VendingRoute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocationDocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_download_and_delete_a_private_location_document(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Alpha Vending');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_OWNER);
        $location = $this->createLocation($account, 'Campus Center');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.show', $location))
            ->assertOk()
            ->assertSeeText('Documents');

        $upload = UploadedFile::fake()->create('location-contract.pdf', 256, 'application/pdf');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('locations.documents.store', $location), [
                'document_type' => 'Contract',
                'title' => 'Location Contract',
                'description' => 'Signed site agreement.',
                'file' => $upload,
            ])
            ->assertRedirect(route('locations.show', $location));

        $document = LocationDocument::query()->firstOrFail();

        $this->assertSame($account->id, $document->account_id);
        $this->assertSame($location->id, $document->location_id);
        $this->assertSame('Contract', $document->document_type);
        $this->assertSame('location-contract.pdf', $document->original_filename);
        $this->assertSame('private', $document->storage_disk);
        $this->assertStringStartsWith('location-documents/'.$account->id.'/'.$location->id.'/', $document->storage_path);
        $this->assertNotSame($document->original_filename, $document->stored_filename);
        $this->assertDatabaseHas('tbl_location_documents', [
            'id' => $document->id,
            'account_id' => $account->id,
            'location_id' => $location->id,
            'uploaded_by_user_id' => $user->id,
        ]);
        Storage::disk('private')->assertExists($document->storage_path);

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.documents.download', [$location, $document]))
            ->assertOk()
            ->assertDownload('location-contract.pdf');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('locations.documents.destroy', [$location, $document]))
            ->assertRedirect(route('locations.show', $location));

        $this->assertDatabaseMissing('tbl_location_documents', [
            'id' => $document->id,
        ]);
        Storage::disk('private')->assertMissing($document->storage_path);
    }

    public function test_viewer_can_download_but_cannot_upload_or_delete_location_documents(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $account = $this->createAccount('Beta Vending');
        $this->attachUserToAccount($user, $account, AccountUser::ROLE_VIEWER);
        $location = $this->createLocation($account, 'Library Hall');
        $document = $this->createDocument($account, $location, 'viewer-manual.pdf');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.documents.download', [$location, $document]))
            ->assertOk()
            ->assertDownload('viewer-manual.pdf');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->get(route('locations.documents.create', $location))
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('locations.documents.destroy', [$location, $document]))
            ->assertForbidden();

        $this->assertDatabaseHas('tbl_location_documents', [
            'id' => $document->id,
        ]);
        Storage::disk('private')->assertExists($document->storage_path);
    }

    public function test_location_documents_cannot_be_accessed_across_accounts_by_url_changes(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $accountA = $this->createAccount('Account A');
        $accountB = $this->createAccount('Account B');
        $this->attachUserToAccount($user, $accountA, AccountUser::ROLE_OWNER);

        $locationB = $this->createLocation($accountB, 'Remote Stop');
        $documentB = $this->createDocument($accountB, $locationB, 'insurance.pdf');

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->get(route('locations.documents.download', [$locationB, $documentB]))
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['current_account_id' => $accountA->id])
            ->delete(route('locations.documents.destroy', [$locationB, $documentB]))
            ->assertNotFound();

        $this->assertDatabaseHas('tbl_location_documents', [
            'id' => $documentB->id,
            'account_id' => $accountB->id,
        ]);
    }

    protected function createAccount(string $name): Account
    {
        return Account::create([
            'account_name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'status' => 'active',
            'billing_email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
    }

    protected function attachUserToAccount(User $user, Account $account, string $role): void
    {
        AccountUser::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => AccountUser::STATUS_ACTIVE,
        ]);
    }

    protected function createLocation(Account $account, string $name): Location
    {
        $route = VendingRoute::create([
            'account_id' => $account->id,
            'route_name' => $name.' Route',
            'description' => $name.' route description',
        ]);

        return Location::create([
            'account_id' => $account->id,
            'route_id' => $route->id,
            'location_name' => $name,
            'address' => '123 Service Road',
            'city' => 'Toronto',
            'state' => 'ON',
            'zip_code' => 'M1M1M1',
            'contact_name' => 'Casey Tech',
        ]);
    }

    protected function createDocument(Account $account, Location $location, string $originalFilename): LocationDocument
    {
        $storedFilename = now()->format('YmdHis').'-'.uniqid().'.pdf';
        $storagePath = 'location-documents/'.$account->id.'/'.$location->id.'/'.$storedFilename;
        Storage::disk('private')->put($storagePath, 'test document body');

        return LocationDocument::create([
            'account_id' => $account->id,
            'location_id' => $location->id,
            'document_type' => 'Insurance',
            'title' => 'Insurance Document',
            'description' => 'Account-scoped document.',
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'storage_disk' => 'private',
            'storage_path' => $storagePath,
            'mime_type' => 'application/pdf',
            'file_size' => 18,
            'uploaded_by_user_id' => null,
        ]);
    }
}
