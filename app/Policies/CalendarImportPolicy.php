<?php
namespace App\Policies;
use App\Models\CalendarImport;
use App\Models\User;
class CalendarImportPolicy {
    public function create(User $user): bool { return $user->hasPermission('calendar.import'); }
    public function confirm(User $user, CalendarImport $import): bool {
        return $user->hasPermission('calendar.confirm')
            && $user->contracting_agency_id === $import->contracting_agency_id;
    }
}
