<?php
// app/Console/Commands/GenerateDailyMemos.php

namespace App\Console\Commands;

use App\Services\MemoGenerationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyMemos extends Command
{
    protected $signature = 'memos:generate-daily 
                            {--date= : Date to generate memos for (default: yesterday)}
                            {--force : Force generation even if memos exist}';
    
    protected $description = 'Generate memos for absent trainees without approved leave';
    
    protected $memoService;
    
    public function __construct(MemoGenerationService $memoService)
    {
        parent::__construct();
        $this->memoService = $memoService;
    }
    
    public function handle()
    {
        $date = $this->option('date') ?? Carbon::yesterday()->format('Y-m-d');
        
        $this->info("==========================================");
        $this->info("Memo Generation for Date: {$date}");
        $this->info("==========================================");
        
        $result = $this->memoService->generateMemosForDate($date);
        
        if ($result['success']) {
            $this->info("✓ " . $result['message']);
            $this->info("Generated: {$result['generated_count']} memo(s)");
            $this->info("Skipped: {$result['skipped_count']} trainee(s)");
            
            if ($result['generated_count'] > 0) {
                $this->newLine();
                $this->info("Generated Memos:");
                $this->table(
                    ['Memo Number', 'Trainee', 'Roll No', 'Absent Sessions', 'Date'],
                    collect($result['generated_memos'])->map(function ($memo) {
                        return [
                            $memo->memo_number,
                            $memo->trainee_name,
                            $memo->trainee_roll_no,
                            count($memo->absent_sessions),
                            $memo->date
                        ];
                    })->toArray()
                );
            }
        } else {
            $this->error("✗ " . $result['message']);
            return 1;
        }
        
        $this->newLine();
        $this->info("Memo generation completed!");
        
        return 0;
    }
}