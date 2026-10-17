<?php

namespace App\Services;

use App\Models\EvidenceReview;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/** Current deliverable states, distinct from cumulative submission counts. */
final class DashboardSnapshotService
{
    public const GROUPS = [
        'pending' => ['PENDIENTE', 'EN_CAPTURA', 'CORREGIDO'],
        'review' => ['ENVIADO', 'EN_REVISION'],
        'observed' => ['OBSERVADO', 'RECHAZADO'],
        'validated' => ['VALIDADO', 'CERRADO'],
    ];

    public static function group($row): string
    {
        foreach (self::GROUPS as $group => $statuses) {
            if (in_array($row->status->value, $statuses, true)) return $group;
        }
        throw new \LogicException('Estado de entregable sin clasificación.');
    }

    public static function deadline($row): ?\Carbon\CarbonInterface
    {
        return $row->due_at ?? $row->scheduledLoad?->effective_close_at;
    }

    public static function overdue($row): bool
    {
        $load = $row->scheduledLoad;
        return in_array(self::group($row), ['pending', 'observed'], true)
            && !$load?->is_blocked
            && !in_array($load?->status?->value, ['SUSPENDIDA', 'REPROGRAMADA', 'CANCELADA'], true)
            && self::deadline($row)?->lt(now());
    }

    public function summarize(Collection $rows): array
    {
        $result = ['expected' => $rows->count()];
        foreach (array_keys(self::GROUPS) as $group) {
            $result[$group] = $rows->filter(fn ($d) => self::group($d) === $group)->count();
        }
        $result['overdue'] = $rows->filter(fn ($d) => self::overdue($d))->count();
        return $result;
    }

    public function forUser(Collection $rows, User $user): array
    {
        $byAgency = $rows->groupBy(fn ($d) => $d->scheduledLoad->contracting_agency_id)
            ->map(fn ($items, $id) => ['id' => (int) $id, 'name' => $items->first()->scheduledLoad->agency?->name ?? 'Sin dependencia'] + $this->summarize($items))
            ->sortByDesc(fn ($r) => $r['pending'] + $r['observed'])->values();
        $byResponsible = $rows->groupBy(fn ($d) => $d->responsible_user_id ?? 'unassigned')
            ->map(fn ($items, $id) => ['id' => $id === 'unassigned' ? null : (int) $id, 'name' => $items->first()->responsibleUser?->name ?? 'Sin asignar'] + $this->summarize($items))
            ->sortByDesc(fn ($r) => $r['pending'] + $r['observed'] + $r['review'])->values();
        $queue = $rows->sortBy(fn ($d) => [
            self::overdue($d) ? 0 : (self::group($d) === 'observed' ? 1 : (self::group($d) === 'review' ? 2 : 3)),
            self::deadline($d)?->timestamp ?? PHP_INT_MAX, $d->id,
        ]);

        $fiscal = null;
        $fiscalAge = [];
        if ($user->role?->code === 'FISCALIZADOR') {
            $assignments = ReviewAssignment::query()->where('fiscalizador_id', $user->id)->where('active', true)
                ->whereIn('scheduled_load_id', $rows->pluck('scheduled_load_id'))->get()->keyBy('scheduled_load_id');
            $reviews = EvidenceReview::query()->where('reviewer_id', $user->id)->where('review_type', 'FISCALIZATION')
                ->whereIn('evidence_id', $rows->flatMap->evidences->pluck('id'))->orderByDesc('id')->get()
                ->groupBy(fn ($r) => $r->evidence_id.':'.$r->evidence_version);
            $queue = $queue->sortBy(function ($d) use ($reviews) {
                $e = $d->evidences->sortByDesc('id')->first();
                return $e && $reviews->has($e->id.':'.$e->current_version) ? 1 : 0;
            });
            $fiscal = ['pending' => 0, 'approved' => 0, 'observed' => 0, 'not_submitted' => 0];
            foreach ($rows as $d) {
                $e = $d->evidences->sortByDesc('id')->first();
                $review = $e ? $reviews->get($e->id.':'.$e->current_version)?->first() : null;
                $key = $review ? ($review->decision === 'APROBADO' ? 'approved' : 'observed')
                    : (in_array($d->status->value, ['ENVIADO', 'EN_REVISION', 'VALIDADO', 'CERRADO'], true) ? 'pending' : 'not_submitted');
                $d->setAttribute('dashboard_fiscal_state', $key);
                $fiscal[$key]++;
                if ($key === 'pending') {
                    $assignedAt = $assignments->get($d->scheduled_load_id)?->created_at;
                    $since = $e?->submitted_at;
                    if ($assignedAt && (!$since || $assignedAt->gt($since))) $since = $assignedAt;
                    $days = $since ? max(0, (int) $since->diffInDays(now())) : 0;
                    $bucket = $days === 0 ? 'Menos de 24 horas' : ($days <= 3 ? '1 a 3 días' : ($days <= 7 ? '4 a 7 días' : 'Más de 7 días'));
                    $fiscalAge[$bucket] = ($fiscalAge[$bucket] ?? 0) + 1;
                }
            }
            $queue = $queue->sortBy(fn ($d) => match ($d->dashboard_fiscal_state) {
                'pending' => 0, 'observed' => 1, 'not_submitted' => 2, default => 3,
            });
        }

        return ['summary' => $this->summarize($rows), 'by_agency' => $byAgency->all(), 'by_responsible' => $byResponsible->all(),
            'queue' => $queue->take(15)->values(), 'queue_total' => $rows->count(), 'fiscal' => $fiscal, 'fiscal_age' => $fiscalAge];
    }
}
