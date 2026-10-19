<?php

namespace App\Http\Controllers\Api;

use App\Models\{CalendarImport, Evidence, ScheduledLoad, ScheduledLoadDeliverable, User};
use App\Services\{AccessScopeService, CalendarPresentationService};

/** Explicit response fields: never serialize database records or physical repository paths. */
final class ApiData
{
    public static function user(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => $user->role?->code, 'contracting_agency_id' => $user->contracting_agency_id,
            'organizational_unit_id' => $user->organizational_unit_id,
            'permissions' => $user->role->permissions()->orderBy('code')->pluck('code')->all()];
    }

    public static function load(ScheduledLoad $load, User $user, bool $details = false): array
    {
        $data = ['id' => $load->id, 'title' => $load->title, 'period' => $load->period_label,
            'agency' => ['id' => $load->contracting_agency_id, 'code' => $load->agency?->code, 'name' => $load->agency?->name],
            'calendar_import_id' => $load->calendar_import_id,
            'opens_at' => $load->effective_open_at?->toIso8601String(),
            'closes_at' => $load->effective_close_at?->toIso8601String(),
            'status' => $load->status->value, 'completion_percentage' => (float) $load->completion_percentage,
            'blocked' => $load->is_blocked, 'block_reason' => $load->block_reason,
            'appearance' => app(CalendarPresentationService::class)->forLoad($load, now())];
        if ($details) {
            $data['deliverables'] = app(AccessScopeService::class)->scopeDeliverables(
                $load->deliverables()->with(['templateRequirement', 'organizationalUnit', 'evidences']), $user
            )->orderBy('id')->get()->map(fn ($item) => self::deliverable($item))->all();
        }
        return $data;
    }

    public static function managedUser(User $user): array
    {
        return self::user($user) + ['status' => $user->status,
            'general_profile' => ($user->metadata['standard_profile'] ?? null) === $user->role?->code,
            'scopes' => $user->scopes()->get()->map(fn ($scope) => $scope->only([
                'contracting_agency_id', 'organizational_unit_id', 'can_read', 'can_write']))->all()];
    }

    public static function deliverable(ScheduledLoadDeliverable $item): array
    {
        return ['id' => $item->id, 'scheduled_load_id' => $item->scheduled_load_id,
            'requirement' => $item->templateRequirement?->only(['code', 'name', 'min_files', 'max_files', 'max_size_mb', 'allowed_extensions']),
            'unit' => $item->organizationalUnit?->only(['id', 'code', 'name']),
            'responsible_user_id' => $item->responsible_user_id, 'status' => $item->status->value,
            'due_at' => $item->due_at?->toIso8601String(),
            'evidences' => $item->evidences->map(fn ($e) => ['id' => $e->id, 'title' => $e->title,
                'status' => $e->status->value, 'version' => $e->current_version])->all()];
    }

    public static function evidence(Evidence $evidence): array
    {
        return ['id' => $evidence->id, 'scheduled_load_id' => $evidence->scheduled_load_id,
            'deliverable_id' => $evidence->deliverable_id, 'title' => $evidence->title,
            'status' => $evidence->status->value, 'version' => $evidence->current_version,
            'submitted_at' => $evidence->submitted_at?->toIso8601String(),
            'validated_at' => $evidence->validated_at?->toIso8601String(),
            'files' => $evidence->files()->orderBy('id')->get()->map(fn ($file) => [
                'id' => $file->id, 'name' => $file->original_name, 'size_bytes' => $file->size_bytes,
                'sha256' => $file->sha256, 'version' => $file->version,
                'download_url' => route('api.v1.files.download', $file),
            ])->all(),
            'reviews' => $evidence->reviews()->orderBy('id')->get()->map(fn ($review) =>
                $review->only(['id', 'review_type', 'decision', 'comments', 'evidence_version', 'created_at']))->all()];
    }

    public static function import(CalendarImport $import): array
    {
        return $import->only(['id', 'contracting_agency_id', 'original_filename', 'sha256', 'status',
            'workbook_version', 'total_rows', 'valid_rows', 'error_rows', 'warnings', 'errors', 'confirmed_at']);
    }
}
