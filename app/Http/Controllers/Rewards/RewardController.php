<?php

namespace App\Http\Controllers\Rewards;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardCategory;
use App\Models\SalesCrm\Employee;
use App\Support\SalesCrmRoles;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RewardController extends Controller
{
    /**
     * Display a listing of reward claims with dashboard metrics and filters.
     */
    public function index(Request $request): Response
    {
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        // Dashboard Summary Metrics
        $summary = [
            'total_claims' => Reward::query()->count(),
            'paid_this_month' => (float) Reward::query()
                ->where('status', 'paid')
                ->whereBetween('updated_at', [$currentMonthStart, $currentMonthEnd])
                ->sum('amount'),
            'pending_count' => Reward::query()->where('status', 'pending')->count(),
            'pending_amount' => (float) Reward::query()->where('status', 'pending')->sum('amount'),
            'approved_count' => Reward::query()->where('status', 'approved')->count(),
            'approved_amount' => (float) Reward::query()->where('status', 'approved')->sum('amount'),
        ];

        $query = Reward::query()
            ->with([
                'category:id,reward_name,short_code',
                'employee:id,user_id,emp_num,designation',
                'employee.user:id,name,email',
                'approvalStatuses.doneByUser:id,name',
            ]);

        // Filter by Status Tab
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        // Filter by Reward Category / Type
        if ($request->filled('category_id') && $request->input('category_id') !== 'all') {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by Search Query (claim_no, employee name, description)
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $q) use ($search): void {
                $q->where('claim_no', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('employee.user', function (Builder $userQ) use ($search): void {
                        $userQ->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('category', function (Builder $catQ) use ($search): void {
                        $catQ->where('reward_name', 'like', "%{$search}%");
                    });
            });
        }

        // Flexible Date Filter (today, this_month, last_month, or YYYY-MM)
        if ($request->filled('date_filter') && $request->input('date_filter') !== 'all') {
            $dateFilter = strtolower(trim((string) $request->input('date_filter')));

            if ($dateFilter === 'today') {
                $query->whereDate('submitted_date', now()->toDateString());
            } elseif ($dateFilter === 'this_month') {
                $query->whereBetween('submitted_date', [now()->startOfMonth(), now()->endOfMonth()]);
            } elseif ($dateFilter === 'last_month') {
                $query->whereBetween('submitted_date', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()]);
            } elseif (preg_match('/^(\d{4})[-_\/](0?[1-9]|1[0-2])$/', $dateFilter, $m)) {
                $start = Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();
                $end = (clone $start)->endOfMonth();
                $query->whereBetween('submitted_date', [$start, $end]);
            } else {
                try {
                    $exact = Carbon::parse($dateFilter)->toDateString();
                    $query->whereDate('submitted_date', $exact);
                } catch (\Throwable) {
                    // ignore invalid date string
                }
            }
        }

        $rewards = $query->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $categories = RewardCategory::query()
            ->active()
            ->orderBy('reward_name')
            ->get(['id', 'reward_name', 'short_code']);

        $employees = Employee::query()
            ->with('user:id,name')
            ->where('status', 1)
            ->orderBy('emp_num')
            ->get(['id', 'user_id', 'emp_num', 'designation']);

        return Inertia::render('rewards/index', [
            'summary' => $summary,
            'rewards' => $rewards,
            'categories' => $categories,
            'employees' => $employees,
            'filters' => $request->only(['status', 'category_id', 'date_filter', 'search']),
        ]);
    }

    /**
     * Store a newly submitted reward claim.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:reward_categories,id'],
            'submitted_by' => ['nullable', 'exists:salescrm.employees,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        // Resolve employee ID
        $employeeId = $validated['submitted_by'] ?? null;
        if (! $employeeId && $request->user()) {
            $employeeId = Employee::query()->where('user_id', $request->user()->id)->value('id');
        }

        if (! $employeeId) {
            $employeeId = Employee::query()->where('status', 1)->value('id');
        }

        // Generate unique claim reference: REW-YYYY-XXXX
        $year = date('Y');
        $prefix = "REW-{$year}-";
        $lastClaim = Reward::withTrashed()->where('claim_no', 'like', "{$prefix}%")->orderByDesc('id')->value('claim_no');
        $nextSeq = 1;
        if ($lastClaim && preg_match('/REW-\d{4}-(\d+)/', $lastClaim, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }
        $claimNo = $prefix.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);

        $filePath = null;
        if ($request->hasFile('document')) {
            $filePath = $request->file('document')->store('rewards', 'public');
        }

        $reward = Reward::query()->create([
            'category_id' => $validated['category_id'],
            'submitted_by' => $employeeId,
            'claim_no' => $claimNo,
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'document_file' => $filePath,
            'submitted_date' => now(),
            'status' => 'pending',
        ]);

        // Create initial history log
        $reward->approvalStatuses()->create([
            'done_by' => $request->user()?->id ?? 1,
            'done_on' => now(),
            'comments' => 'Claim applied and submitted for approval.',
            'status' => 'pending',
        ]);

        return redirect()->route('rewards.index')
            ->with('success', "Reward claim {$claimNo} submitted successfully.");
    }

    /**
     * Update claim approval status (Approve, Mark as Paid, Reject).
     */
    public function updateStatus(Request $request, Reward $reward): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'paid', 'rejected'])],
            'comments' => ['nullable', 'string', 'max:1000'],
        ]);

        $newStatus = $validated['status'];
        $comments = $validated['comments'] ?? null;

        // Only finance role can approve or mark as paid
        if (in_array($newStatus, ['approved', 'paid'], true)) {
            $user = $request->user();
            if (! $user || ! SalesCrmRoles::userHasAnyRole((int) $user->id, ['finance'])) {
                abort(403, 'Only Finance role users can approve or pay reward claims.');
            }
        }

        if ($newStatus === 'rejected' && empty($comments)) {
            return back()->withErrors(['comments' => 'Please provide a reason for rejecting this claim.']);
        }

        $reward->update([
            'status' => $newStatus,
        ]);

        $reward->approvalStatuses()->create([
            'done_by' => $request->user()?->id ?? 1,
            'done_on' => now(),
            'comments' => $comments ?: ucfirst($newStatus).' by HR/Management.',
            'status' => $newStatus,
        ]);

        return redirect()->route('rewards.index')
            ->with('success', "Reward claim {$reward->claim_no} updated to {$newStatus}.");
    }

    /**
     * Download or print the formatted Reward Claim Form Voucher.
     */
    public function downloadForm(Reward $reward): HttpResponse
    {
        $reward->load([
            'category',
            'employee.user',
            'approvalStatuses.doneByUser',
        ]);

        return response()->view('rewards.claim-voucher', [
            'reward' => $reward,
        ]);
    }

    /**
     * Download the uploaded receipt/certificate attachment.
     */
    public function downloadAttachment(Reward $reward): BinaryFileResponse|RedirectResponse
    {
        if (! $reward->document_file || ! Storage::disk('public')->exists($reward->document_file)) {
            return back()->with('error', 'Attachment file not found.');
        }

        return response()->download(
            Storage::disk('public')->path($reward->document_file),
            $reward->claim_no.'-attachment.'.pathinfo($reward->document_file, PATHINFO_EXTENSION)
        );
    }

    /**
     * Remove the specified reward claim (soft delete).
     */
    public function destroy(Reward $reward): RedirectResponse
    {
        $reward->delete();

        return redirect()->route('rewards.index')
            ->with('success', 'Reward claim removed successfully.');
    }
}
