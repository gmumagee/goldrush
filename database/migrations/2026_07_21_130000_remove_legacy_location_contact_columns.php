<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();

        foreach ($this->legacyLocations() as $location) {
            $locationContacts = $this->locationContacts((int) $location->account_id, (int) $location->id);
            $matchingLocationContact = $this->matchingLocationContact($location, $locationContacts);

            if ($matchingLocationContact === null) {
                $contactId = $this->matchingAccountContactId($location)
                    ?? DB::table('tbl_contacts')->insertGetId([
                        'account_id' => $location->account_id,
                        'first_name' => null,
                        'last_name' => null,
                        'organization' => $this->nullableString($location->contact_name),
                        'title' => null,
                        'email' => $this->nullableString($location->contact_email),
                        'phone' => $this->nullableString($location->contact_phone),
                        'mobile_phone' => null,
                        'notes' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                $locationContactId = DB::table('tbl_location_contacts')->insertGetId([
                    'account_id' => $location->account_id,
                    'location_id' => $location->id,
                    'contact_id' => $contactId,
                    'contact_role' => null,
                    'is_primary' => false,
                    'notes' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            } else {
                $locationContactId = $matchingLocationContact->location_contact_id;
            }

            DB::table('tbl_location_contacts')
                ->where('account_id', $location->account_id)
                ->where('location_id', $location->id)
                ->update(['is_primary' => false]);

            DB::table('tbl_location_contacts')
                ->where('id', $locationContactId)
                ->update([
                    'is_primary' => true,
                    'updated_at' => $timestamp,
                ]);
        }

        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'contact_phone', 'contact_email']);
        });
    }

    public function down(): void
    {
        Schema::table('tbl_locations', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('zip_code');
            $table->string('contact_phone', 50)->nullable()->after('contact_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
        });

        foreach (DB::table('tbl_locations')->select(['id', 'account_id'])->orderBy('id')->cursor() as $location) {
            $primaryContact = DB::table('tbl_location_contacts')
                ->join('tbl_contacts', 'tbl_contacts.id', '=', 'tbl_location_contacts.contact_id')
                ->where('tbl_location_contacts.account_id', $location->account_id)
                ->where('tbl_location_contacts.location_id', $location->id)
                ->orderByDesc('tbl_location_contacts.is_primary')
                ->orderBy('tbl_location_contacts.id')
                ->select([
                    'tbl_contacts.first_name',
                    'tbl_contacts.last_name',
                    'tbl_contacts.organization',
                    'tbl_contacts.email',
                    'tbl_contacts.phone',
                    'tbl_contacts.mobile_phone',
                ])
                ->first();

            DB::table('tbl_locations')
                ->where('id', $location->id)
                ->update([
                    'contact_name' => $this->contactDisplayName($primaryContact),
                    'contact_phone' => $primaryContact?->phone ?: $primaryContact?->mobile_phone,
                    'contact_email' => $primaryContact?->email,
                ]);
        }
    }

    protected function legacyLocations(): \Generator
    {
        yield from DB::table('tbl_locations')
            ->select(['id', 'account_id', 'contact_name', 'contact_phone', 'contact_email'])
            ->where(function ($query) {
                $query
                    ->whereNotNull('contact_name')
                    ->orWhereNotNull('contact_phone')
                    ->orWhereNotNull('contact_email');
            })
            ->orderBy('id')
            ->cursor();
    }

    protected function locationContacts(int $accountId, int $locationId): Collection
    {
        return DB::table('tbl_location_contacts')
            ->join('tbl_contacts', 'tbl_contacts.id', '=', 'tbl_location_contacts.contact_id')
            ->where('tbl_location_contacts.account_id', $accountId)
            ->where('tbl_location_contacts.location_id', $locationId)
            ->orderByDesc('tbl_location_contacts.is_primary')
            ->orderBy('tbl_location_contacts.id')
            ->select([
                'tbl_location_contacts.id as location_contact_id',
                'tbl_contacts.id as contact_id',
                'tbl_contacts.first_name',
                'tbl_contacts.last_name',
                'tbl_contacts.organization',
                'tbl_contacts.email',
                'tbl_contacts.phone',
                'tbl_contacts.mobile_phone',
            ])
            ->get();
    }

    protected function matchingLocationContact(object $location, Collection $locationContacts): ?object
    {
        $legacyEmail = $this->normalizedLower($location->contact_email);

        if ($legacyEmail !== null) {
            $match = $locationContacts->first(fn (object $contact) => $this->normalizedLower($contact->email) === $legacyEmail);

            if ($match !== null) {
                return $match;
            }
        }

        $legacyPhone = $this->normalizedPhone($location->contact_phone);

        if ($legacyPhone !== null) {
            $match = $locationContacts->first(function (object $contact) use ($legacyPhone) {
                return $this->normalizedPhone($contact->phone) === $legacyPhone
                    || $this->normalizedPhone($contact->mobile_phone) === $legacyPhone;
            });

            if ($match !== null) {
                return $match;
            }
        }

        $legacyName = $this->nullableString($location->contact_name);

        if ($legacyName !== null) {
            return $locationContacts->first(
                fn (object $contact) => $this->contactDisplayName($contact) === $legacyName
            );
        }

        return null;
    }

    protected function matchingAccountContactId(object $location): ?int
    {
        $legacyEmail = $this->normalizedLower($location->contact_email);

        if ($legacyEmail !== null) {
            $contactId = DB::table('tbl_contacts')
                ->where('account_id', $location->account_id)
                ->whereRaw('LOWER(TRIM(email)) = ?', [$legacyEmail])
                ->value('id');

            if ($contactId !== null) {
                return (int) $contactId;
            }
        }

        $legacyPhone = $this->normalizedPhone($location->contact_phone);

        if ($legacyPhone === null) {
            return null;
        }

        return DB::table('tbl_contacts')
            ->where('account_id', $location->account_id)
            ->where(function ($query) use ($legacyPhone) {
                $query
                    ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), '(', ''), ')', ''), ' ', ''), '.', '') = ?", [$legacyPhone])
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(mobile_phone, '-', ''), '(', ''), ')', ''), ' ', ''), '.', '') = ?", [$legacyPhone]);
            })
            ->value('id');
    }

    protected function contactDisplayName(?object $contact): ?string
    {
        if ($contact === null) {
            return null;
        }

        $name = trim(($contact->first_name ?? '').' '.($contact->last_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        foreach ([$contact->organization ?? null, $contact->email ?? null, $contact->phone ?? null] as $value) {
            $value = $this->nullableString($value);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    protected function normalizedLower(?string $value): ?string
    {
        $value = $this->nullableString($value);

        return $value !== null ? mb_strtolower($value) : null;
    }

    protected function normalizedPhone(?string $value): ?string
    {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    protected function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
};
