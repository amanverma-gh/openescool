<?php

namespace App\Services\Brevo;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Brevo v3 REST API. Transactional email goes through
 * Laravel Mail (Brevo SMTP relay); this client handles contacts and lists.
 */
class BrevoClient
{
    public function enabled(): bool
    {
        return filled(config('services.brevo.key'));
    }

    /**
     * Create or update a contact and add it to the given lists.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $listIds
     */
    public function upsertContact(string $email, array $attributes = [], array $listIds = []): void
    {
        $this->request()->post('contacts', array_filter([
            'email' => $email,
            'attributes' => $attributes ?: null,
            'listIds' => $listIds ?: null,
            'updateEnabled' => true,
        ], fn ($value) => $value !== null))->throw();
    }

    /**
     * @param  list<int>  $listIds
     */
    public function removeFromLists(string $email, array $listIds): void
    {
        foreach ($listIds as $listId) {
            $this->request()
                ->post("contacts/lists/{$listId}/contacts/remove", ['emails' => [$email]])
                ->throwUnlessStatus(fn (int $status) => $status < 500);
        }
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl('https://api.brevo.com/v3/')
            ->withHeaders(['api-key' => config('services.brevo.key')])
            ->acceptJson()
            ->timeout(10)
            ->retry(2, 500, throw: false);
    }
}
