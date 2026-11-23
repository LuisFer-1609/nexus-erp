<?php

namespace App\Services\Crm\Meta;

use App\Contracts\Crm\Meta\MetaClient;
use App\Models\Nexus\MetaConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportMetaLeads
{
    public function __construct(private readonly MetaClient $meta) {}

    /** @return array{imported: int, failed_pages: int} */
    public function all(): array
    {
        $totals = ['imported' => 0, 'failed_pages' => 0];

        foreach (MetaConnection::query()->get() as $connection) {
            $result = $this->company((int) $connection->company_id);
            $totals['imported'] += $result['imported'];
            $totals['failed_pages'] += $result['failed_pages'];
        }

        return $totals;
    }

    /** @return array{imported: int, failed_pages: int} */
    public function company(int $companyId): array
    {
        $connection = MetaConnection::query()->where('company_id', $companyId)->firstOrFail();
        $totals = ['imported' => 0, 'failed_pages' => 0];

        foreach (DB::table('meta_pages')->where('company_id', $companyId)->get() as $page) {
            try {
                foreach ($this->meta->leadForms($page->external_id, $connection->access_token) as $form) {
                    foreach ($this->meta->formLeads((string) $form['id'], $connection->access_token) as $lead) {
                        $totals['imported'] += $this->insert($companyId, $page->external_id, $lead);
                    }
                }
            } catch (\Throwable $exception) {
                $totals['failed_pages']++;
                Log::warning('No se pudieron importar leads de una página de Meta.', [
                    'company_id' => $companyId,
                    'page_id' => $page->external_id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $totals;
    }

    /** @param array<string, mixed> $lead */
    private function insert(int $companyId, string $pageId, array $lead): int
    {
        $fields = [];
        foreach ($lead['field_data'] ?? [] as $field) {
            $fields[$field['name']] = $field['values'][0] ?? null;
        }

        $fullName = trim((string) ($fields['full_name'] ?? (($fields['first_name'] ?? '').' '.($fields['last_name'] ?? ''))));
        $sourceId = DB::table('lead_sources')->where('code', 'meta')->value('id');
        if (! $sourceId) {
            $sourceId = DB::table('lead_sources')->insertGetId([
                'code' => 'meta', 'name' => 'Meta', 'created_at' => now(),
            ]);
        }

        $stageId = DB::table('lead_stages')->where('code', 'nuevo')->value('id');
        if (! $stageId) {
            $stageId = DB::table('lead_stages')->insertGetId([
                'code' => 'nuevo', 'name' => 'Nuevo', 'position' => 1, 'created_at' => now(),
            ]);
        }

        $createdAt = $lead['created_time'] ?? now();

        return DB::table('leads')->insertOrIgnore([
            'company_id' => $companyId,
            'lead_source_id' => $sourceId,
            'lead_stage_id' => $stageId,
            'responsible_user_id' => null,
            'full_name' => $fullName !== '' ? $fullName : 'Sin nombre',
            'email' => $fields['email'] ?? null,
            'phone' => $fields['phone_number'] ?? ($fields['phone'] ?? null),
            'meta_lead_id' => (string) $lead['id'],
            'meta_page_id' => $pageId,
            'meta_form_id' => (string) ($lead['form_id'] ?? ''),
            'meta_campaign_id' => $lead['campaign_id'] ?? null,
            'meta_ad_id' => $lead['ad_id'] ?? null,
            'meta_platform' => $lead['platform'] ?? null,
            'meta_fields' => json_encode($fields, JSON_THROW_ON_ERROR),
            'created_at' => $createdAt,
        ]);
    }
}
