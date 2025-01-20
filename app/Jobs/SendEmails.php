<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $subject;
    protected $content;
    protected $emails;

    /**
     * Create a new job instance.
     */
    public function __construct($subject, $content, $emails)
    {
        $this->subject = $subject;
        $this->content = $content;
        $this->emails = $emails;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $subject = $this->subject;
        $content = $this->content;
        $emails = $this->emails;

        Mail::raw($content, function ($message) use ($emails, $subject) {
            $message->to($emails)->subject($subject);
        });
    }
}
