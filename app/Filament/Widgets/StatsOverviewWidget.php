<?php

namespace App\Filament\Widgets;

use App\Models\Company;
use App\Models\Contact;
use App\Models\OutreachLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Companies', Company::count())
                ->icon('heroicon-o-building-office-2')
                ->color('primary'),

            Stat::make('Total Contacts', Contact::count())
                ->icon('heroicon-o-users')
                ->color('info'),

            Stat::make('Emails Sent', OutreachLog::where('channel', 'email')->where('status', 'sent')->count())
                ->icon('heroicon-o-envelope')
                ->color('success'),

            Stat::make('WhatsApp Sent', OutreachLog::where('channel', 'whatsapp')->where('status', 'sent')->count())
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success'),

            Stat::make('Contacted Today', OutreachLog::where('status', 'sent')->whereDate('sent_at', today())->count())
                ->icon('heroicon-o-paper-airplane')
                ->color('warning'),

            Stat::make('Replies This Week', OutreachLog::where('status', 'replied')
                ->whereBetween('replied_at', [now()->startOfWeek(), now()])
                ->count())
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->color('danger'),

            Stat::make('Failed Jobs (24h)', DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count())
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
