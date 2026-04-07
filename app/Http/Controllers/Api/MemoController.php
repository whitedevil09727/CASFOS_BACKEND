<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use App\Services\MemoGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MemoController extends Controller
{

    public function __construct(
        protected MemoGenerationService $memoService
    ) {
    }

    public function index(Request $request)
    {
        $query = Memo::with(['trainee', 'approver']);

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        if ($request->has('trainee_id')) {
            $query->where('trainee_id', $request->trainee_id);
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        $memos = $query->orderBy('generated_at', 'desc')->get();

        $summary = [
            'total' => Memo::count(),
            'pending' => Memo::where('status', 'pending_approval')->count(),
            'approved' => Memo::where('status', 'approved')->count(),
            'rejected' => Memo::where('status', 'rejected')->count()
        ];

        return response()->json([
            'success' => true,
            'memos' => $memos,
            'summary' => $summary
        ]);
    }


    public function show($id)
    {
        $memo = Memo::with(['trainee', 'approver'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'memo' => $memo
        ]);
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'approved_by_name' => 'required|string|max:255'
        ]);

        $memo = Memo::findOrFail($id);

        if ($memo->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Memo is already processed'
            ], 400);
        }

        $memo->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_by_name' => $request->approved_by_name,
            'approved_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Memo approved successfully',
            'memo' => $memo->fresh()
        ]);
    }

 
    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|min:5|max:500'
        ]);

        $memo = Memo::findOrFail($id);

        if ($memo->status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Memo is already processed'
            ], 400);
        }

        $memo->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_by_name' => auth()->user()->name,
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Memo rejected successfully',
            'memo' => $memo->fresh()
        ]);
    }

    public function generateForDate(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $result = $this->memoService->generateMemosForDate($request->date);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 500);
    }


    public function generateForDateRange(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        $result = $this->memoService->generateMemosForDateRange(
            $request->start_date,
            $request->end_date
        );

        return response()->json($result);
    }


    public function generateForTrainee(Request $request)
    {
        $request->validate([
            'trainee_id' => 'required|exists:users,id',
            'date' => 'required|date'
        ]);

        $result = $this->memoService->checkAndGenerateMemo(
            $request->trainee_id,
            $request->date
        );

        if ($result['generated']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'memo' => $result['memo']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['reason'] ?? 'Failed to generate memo',
            'error' => $result['error'] ?? null
        ], 400);
    }

    public function preview(Request $request)
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $absentTrainees = $this->memoService->getAbsentTraineesWithoutLeave($request->date);

        return response()->json([
            'success' => true,
            'date' => $request->date,
            'trainees_count' => count($absentTrainees),
            'trainees' => $absentTrainees
        ]);
    }


    public function destroy($id)
    {
        $memo = Memo::findOrFail($id);

        if ($memo->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete approved memos'
            ], 400);
        }

        $memo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Memo deleted successfully'
        ]);
    }


    public function statistics(Request $request): JsonResponse
    {
        
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

        $stats = [
            'total' => Memo::whereBetween('date', [$startDate, $endDate])->count(),
            'by_status' => [
                'pending' => Memo::whereBetween('date', [$startDate, $endDate])->where('status', 'pending_approval')->count(),
                'approved' => Memo::whereBetween('date', [$startDate, $endDate])->where('status', 'approved')->count(),
                'rejected' => Memo::whereBetween('date', [$startDate, $endDate])->where('status', 'rejected')->count(),
            ],
            'by_date' => Memo::whereBetween('date', [$startDate, $endDate])
                ->selectRaw('date, count(*) as total')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'statistics' => $stats
        ]);
    }


    public function getTraineeMemos(Request $request)
    {
        $user = $request->user();

       
        if ($user->role !== 'trainee') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Trainee role required.'
            ], 403);
        }

        $traineeId = DB::table('trainees')
            ->where('user_id', $user->id)
            ->value('id');

        if (!$traineeId) {
            return response()->json([
                'success' => true,
                'memos' => [],
                'message' => 'No trainee record found for this user'
            ]);
        }

        $memos = DB::table('memos as m')
            ->leftJoin('trainees as t', 'm.trainee_id', '=', 't.id')
            ->leftJoin('users as a', 'm.approved_by', '=', 'a.id')
            ->where('m.trainee_id', $traineeId)
            ->orderBy('m.generated_at', 'desc')
            ->select(
                'm.*',
                't.name as trainee_name',
                DB::raw('COALESCE(a.name, m.approved_by_name) as approver_name')
            )
            ->get();

        $memos = $memos->map(function ($memo) {
            $memo->absent_sessions = is_string($memo->absent_sessions)
                ? json_decode($memo->absent_sessions, true)
                : ($memo->absent_sessions ?? []);

            return $memo;
        });

        return response()->json([
            'success' => true,
            'memos' => $memos
        ]);
    }


    public function getMyMemos(Request $request)
    {
        return $this->getTraineeMemos($request);
    }
}