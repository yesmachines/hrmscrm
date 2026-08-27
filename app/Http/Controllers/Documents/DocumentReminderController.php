<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\SalesCrm\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentReminderController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $threeMonthsLater = (clone $today)->addDays(90);

        // Fetch documents expiring in next 90 days or already expired
        $query = EmployeeDocument::query()
            ->with(['documentType.category', 'files' => fn ($q) => $q->orderByDesc('id')])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $threeMonthsLater);

        if ($request->filled('urgency')) {
            if ($request->input('urgency') === 'expired') {
                $query->where('expiry_date', '<', $today);
            } elseif ($request->input('urgency') === 'critical') {
                $query->whereBetween('expiry_date', [$today, (clone $today)->addDays(30)]);
            } elseif ($request->input('urgency') === 'warning') {
                $query->whereBetween('expiry_date', [(clone $today)->addDays(31), $threeMonthsLater]);
            }
        }

        $reminders = $query->orderBy('expiry_date', 'asc')
            ->paginate(15)
            ->withQueryString()
            ->through(function (EmployeeDocument $doc) use ($today) {
                $employee = Employee::query()->with('user:id,name,email')->find($doc->employee_id);
                $daysRemaining = (int) $today->diffInDays($doc->expiry_date, false);

                $urgency = 'normal';
                if ($daysRemaining < 0) {
                    $urgency = 'expired';
                } elseif ($daysRemaining <= 30) {
                    $urgency = 'critical';
                } elseif ($daysRemaining <= 90) {
                    $urgency = 'warning';
                }

                return [
                    'id' => $doc->id,
                    'employee_id' => $doc->employee_id,
                    'employee_name' => $employee?->user?->name ?? "Employee #{$doc->employee_id}",
                    'employee_email' => $employee?->user?->email,
                    'emp_num' => $employee?->emp_num,
                    'document_name' => $doc->documentType?->document_name,
                    'document_number' => $doc->document_number,
                    'expiry_date' => $doc->expiry_date?->format('d M Y'),
                    'days_remaining' => $daysRemaining,
                    'urgency' => $urgency,
                    'status' => $doc->status,
                ];
            });

        return Inertia::render('document-reminders/index', [
            'reminders' => $reminders,
            'filters' => $request->only(['urgency']),
        ]);
    }
}
