<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Organization\Models\Organization;
use App\Mail\TrialReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTrialReminderEmails extends Command
{
    protected $signature = 'trial:send-reminders';
    protected $description = 'Invia email di promemoria per trial in scadenza (7, 3, 1 giorno)';

    public function handle(): int
    {
        $reminders = [7, 3, 1];

        foreach ($reminders as $days) {
            $from = now()->addDays($days)->startOfDay();
            $to   = now()->addDays($days)->endOfDay();

            $organizations = Organization::where('subscription_status', 'trial')
                ->whereBetween('trial_ends_at', [$from, $to])
                ->with('users')
                ->get();

            foreach ($organizations as $org) {
                foreach ($org->users as $user) {
                    Mail::to($user->email)->queue(new TrialReminderMail($user, $days));
                }
            }

            $this->info("Inviati reminder {$days}gg a {$organizations->count()} organizzazioni.");
        }

        return self::SUCCESS;
    }
}
