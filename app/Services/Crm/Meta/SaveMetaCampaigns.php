<?php

namespace App\Services\Crm\Meta;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveMetaCampaigns
{
    public function __construct(private readonly MetaReadService $meta) {}

    /**
     * @param  array<int, string>  $campaignIds
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function save(int $companyId, int $userId, string $accountId, string $currency, string $token, string $since, string $until, array $campaignIds): array
    {
        $campaigns = collect($this->meta->campaigns($accountId, $token))
            ->filter(fn (array $campaign) => (string) ($campaign['account_id'] ?? '') === $accountId)
            ->keyBy('id');
        $wanted = collect($campaignIds)->unique();

        if ($wanted->count() !== $wanted->filter(fn (string $id) => $campaigns->has($id))->count()) {
            throw ValidationException::withMessages(['campaign_ids' => 'La selección incluye una campaña ajena a esta cuenta.']);
        }

        $insights = collect($this->meta->insights($accountId, $token, $since, $until))->keyBy('campaign_id');
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($wanted as $id) {
            $campaign = $campaigns->get($id);
            $metrics = $insights->get($id, []);
            $values = [
                'name' => (string) $campaign['name'],
                'status' => $campaign['status'] ?? null,
                'effective_status' => $campaign['effective_status'] ?? null,
                'objective' => $campaign['objective'] ?? null,
                'currency' => $currency,
                'metrics' => json_encode($metrics, JSON_THROW_ON_ERROR),
            ];
            $key = [
                'company_id' => $companyId,
                'meta_campaign_id' => $id,
                'period_start' => $since,
                'period_end' => $until,
            ];
            $existing = DB::table('meta_saved_campaigns')->where($key)->first();

            if ($existing) {
                $changed = collect($values)->some(fn ($value, $field) => $field === 'metrics'
                    ? json_decode($existing->metrics, true) != $metrics
                    : $existing->{$field} !== $value);

                if (! $changed) {
                    $counts['unchanged']++;

                    continue;
                }

                DB::table('meta_saved_campaigns')->where('id', $existing->id)->update([
                    ...$values,
                    'ad_account_id' => $accountId,
                    'saved_by_auth_user_id' => $userId,
                    'fetched_at' => now(),
                    'updated_at' => now(),
                ]);
                $counts['updated']++;
            } else {
                DB::table('meta_saved_campaigns')->insert([
                    ...$key,
                    ...$values,
                    'ad_account_id' => $accountId,
                    'saved_by_auth_user_id' => $userId,
                    'fetched_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $counts['created']++;
            }
        }

        return $counts;
    }
}
