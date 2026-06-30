<?php

namespace App\Http\Controllers;

use App\Models\DtrEditRequest;
use App\Models\DtrUser;
use App\Models\Office;
use App\Models\Section;
use Illuminate\Http\Request;

class SupervisorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function pending()
    {
        $user = auth()->user();

        if ($user->is_super) {
            $requests = DtrEditRequest::with('employee', 'deletionOf')
                ->pending()
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(function ($r) {
                    return $r->employee->full_name . ' (' . $r->employee->emp_code . ')';
                });
        } else {
            $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
            if (!$dtrUser) {
                return view('supervisor.pending', ['grouped' => collect()]);
            }

            $sectionIds = Section::where('supervisor_id', $dtrUser->id)->pluck('id');
            $sectionOicIds = Section::where('oic_id', $dtrUser->id)->pluck('id');
            $officeIds = Office::where('supervisor_id', $dtrUser->id)->pluck('id');
            $seniorManagerOfficeIds = Office::where('senior_manager_id', $dtrUser->id)->pluck('id');
            $seniorManagerOicOfficeIds = Office::where('senior_manager_oic_id', $dtrUser->id)->pluck('id');
            $oicOfficeIds = Office::where('oic_id', $dtrUser->id)->pluck('id');

            $requests = DtrEditRequest::with('employee', 'deletionOf')
                ->where(function ($q) use ($sectionIds, $sectionOicIds, $officeIds, $seniorManagerOfficeIds, $seniorManagerOicOfficeIds, $oicOfficeIds) {
                    if ($sectionIds->isNotEmpty()) {
                        $q->whereHas('employee', function ($q) use ($sectionIds) {
                            $q->whereIn('section_id', $sectionIds);
                        });
                    }
                    if ($sectionOicIds->isNotEmpty()) {
                        $q->orWhereHas('employee', function ($q) use ($sectionOicIds) {
                            $q->whereIn('section_id', $sectionOicIds);
                        });
                    }
                    if ($officeIds->isNotEmpty()) {
                        $q->orWhereHas('employee', function ($q) use ($officeIds) {
                            $q->whereIn('office_id', $officeIds);
                        });
                    }
                    if ($seniorManagerOfficeIds->isNotEmpty()) {
                        $q->orWhereHas('employee', function ($q) use ($seniorManagerOfficeIds) {
                            $q->whereIn('office_id', $seniorManagerOfficeIds);
                        });
                    }
                    if ($seniorManagerOicOfficeIds->isNotEmpty()) {
                        $q->orWhereHas('employee', function ($q) use ($seniorManagerOicOfficeIds) {
                            $q->whereIn('office_id', $seniorManagerOicOfficeIds);
                        });
                    }
                    if ($oicOfficeIds->isNotEmpty()) {
                        $q->orWhereHas('employee', function ($q) use ($oicOfficeIds) {
                            $q->whereIn('office_id', $oicOfficeIds);
                        });
                    }
                })
                ->pending()
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(function ($r) {
                    return $r->employee->full_name . ' (' . $r->employee->emp_code . ')';
                });
        }

        return view('supervisor.pending', ['grouped' => $requests]);
    }

    public function getSupervisorSectionIds()
    {
        $user = auth()->user();
        if ($user->is_super) return null;

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        if (!$dtrUser) return collect();

        return Section::where('supervisor_id', $dtrUser->id)->pluck('id');
    }
}
