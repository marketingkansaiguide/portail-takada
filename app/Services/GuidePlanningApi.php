<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GuidePlanningApi
{
    private const CACHE_KEY = 'guide-planning-api.employees';

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function syncMission(array $payload): array
    {
        $apiKey = (string) config('services.guide_planning.api_key');
        $endpoint = $this->missionsEndpoint();

        if ($endpoint === '' || $apiKey === '') {
            throw new \RuntimeException('Configuration de l’API Planning Guides manquante.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['X-PM-Api-Key' => $apiKey])
            ->timeout(15)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("Planning Guides a répondu HTTP {$response->status()}.");
        }

        $data = $response->json('data');
        if (! $response->json('success') || ! is_array($data) || empty($data['id'])) {
            throw new \UnexpectedValueException('Réponse invalide de Planning Guides.');
        }

        return $data;
    }

    public function cancelMission(int $missionId, int $folderItemId): void
    {
        $apiKey = (string) config('services.guide_planning.api_key');
        $endpoint = $this->missionsEndpoint();

        if ($endpoint === '' || $apiKey === '') {
            throw new \RuntimeException('Configuration de l’API Planning Guides manquante.');
        }

        $response = Http::acceptJson()
            ->withHeaders(['X-PM-Api-Key' => $apiKey])
            ->timeout(15)
            ->post("{$endpoint}/{$missionId}/cancel", [
                'ticketing_order_item_id' => $folderItemId,
            ]);

        if (! $response->successful() || ! $response->json('success')) {
            throw new \RuntimeException("Planning Guides a répondu HTTP {$response->status()} lors de l’annulation.");
        }
    }

    /** @return array<int, array{id:int,name:string,email:?string,phone:?string,dispatch_address:?string}> */
    public function employees(): array
    {
        $baseUrl = rtrim((string) config('services.guide_planning.base_url'), '/');
        $apiKey = (string) config('services.guide_planning.api_key');

        if ($baseUrl === '' || $apiKey === '') {
            return [];
        }

        $endpoint = str_ends_with(mb_strtolower($baseUrl), '/employees')
            ? $baseUrl
            : "{$baseUrl}/employees";

        return Cache::remember(self::CACHE_KEY.'.'.sha1($endpoint), now()->addMinutes(15), function () use ($apiKey, $endpoint): array {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(['X-PM-Api-Key' => $apiKey])
                    ->timeout(10)
                    ->get($endpoint);

                if (! $response->successful() || ! $response->json('success')) {
                    return [];
                }

                return collect($response->json('data', []))
                    ->filter(fn ($employee): bool => is_array($employee) && filled($employee['id'] ?? null) && filled($employee['name'] ?? null))
                    ->map(fn (array $employee): array => $this->normaliseEmployee($employee))
                    ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }

    /** @return array<int, string> */
    public function search(string $search): array
    {
        $needle = mb_strtolower(trim($search));

        return collect($this->employees())
            ->filter(fn (array $employee): bool => $needle === '' || str_contains(mb_strtolower($employee['name']), $needle))
            ->take(50)
            ->mapWithKeys(fn (array $employee): array => [
                (string) $employee['id'] => $employee['name'] . ($employee['phone'] ? " — {$employee['phone']}" : ''),
            ])
            ->all();
    }

    /** @return array{id:int,name:string,email:?string,phone:?string,dispatch_address:?string}|null */
    public function find(int|string|null $id): ?array
    {
        if (! filled($id)) {
            return null;
        }

        return collect($this->employees())->firstWhere('id', (int) $id);
    }

    /** @param array<string, mixed> $employee */
    private function normaliseEmployee(array $employee): array
    {
        $address = Arr::first([
            $employee['address'] ?? null,
            $employee['postal_address'] ?? null,
            $employee['dispatch_address'] ?? null,
        ], fn ($value): bool => filled($value));

        if (! $address) {
            $address = collect(array_merge(
                Arr::wrap($employee['characteristics'] ?? []),
                Arr::wrap($employee['free_characteristics'] ?? []),
            ))
                ->first(function ($characteristic): bool {
                    if (! is_array($characteristic)) {
                        return false;
                    }

                    $family = mb_strtolower((string) ($characteristic['family'] ?? ''));

                    return preg_match('/adresse|address|coordonn|livraison|envoi/u', $family) === 1 && filled($characteristic['value'] ?? null);
                });

            $address = is_array($address) ? $address['value'] : null;
        }

        return [
            'id' => (int) $employee['id'],
            'name' => trim((string) $employee['name']),
            'email' => filled($employee['email'] ?? null) ? trim((string) $employee['email']) : null,
            'phone' => filled($employee['phone'] ?? null) ? trim((string) $employee['phone']) : null,
            'dispatch_address' => filled($address) ? trim((string) $address) : null,
        ];
    }

    private function missionsEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.guide_planning.base_url'), '/');
        $lowerBaseUrl = mb_strtolower($baseUrl);

        return str_ends_with($lowerBaseUrl, '/missions')
            ? $baseUrl
            : (str_ends_with($lowerBaseUrl, '/employees') ? substr($baseUrl, 0, -10).'/missions' : "{$baseUrl}/missions");
    }
}
