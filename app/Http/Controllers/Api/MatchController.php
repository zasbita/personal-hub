<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SportPrefsService;
use App\Services\SupabaseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $s = new SupabaseService;
            $params = ['select' => '*', 'match_time' => 'gte.'.now()->toIso8601String(), 'order' => 'match_time.asc', 'limit' => 10];
            $sport = $request->query('sport_type');
            if ($sport !== null && $sport !== '') {
                $norm = SportPrefsService::normalizeSport((string) $sport);
                if (! in_array($norm, SportPrefsService::SPORTS, true)) {
                    return response()->json(['error' => 'invalid sport_type'], 400);
                }
                $expanded = SportPrefsService::expandSport($norm);
                if (count($expanded) > 1) {
                    $params['sport_type'] = 'in.('.implode(',', $expanded).')';
                } else {
                    $params['sport_type'] = 'eq.'.$expanded[0];
                }
            }

            return response()->json($s->select('match_schedule', $params) ?? []);
        } catch (\Exception $e) {
            // An empty list and a broken Supabase must not look alike.
            Log::error("Match list failed: {$e->getMessage()}");

            return response()->json(['error' => 'Failed to fetch matches'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $b = $request->json()->all();
            if (empty($b['sport_type']) || empty($b['home_team']) || empty($b['match_time'])) {
                return response()->json(['error' => 'sport_type, home_team, match_time required'], 400);
            }
            $s = new SupabaseService;
            // No entity_name column exists on match_schedule; sending one made every
            // manual entry fail with a Postgres 42703.
            $r = $s->insert('match_schedule', ['sport_type' => $b['sport_type'], 'match_time' => Carbon::parse($b['match_time'])->toIso8601String(), 'competition' => $b['tournament'] ?? null, 'home_team' => $b['home_team'] ?? null, 'away_team' => $b['away_team'] ?? null]);

            return response()->json($r[0] ?? $r, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function exportIcs(): StreamedResponse
    {
        $rows = [];
        try {
            $s = new SupabaseService;
            $rows = $s->select('match_schedule', ['select' => '*', 'match_time' => 'gte.'.now()->subDays(1)->toIso8601String(), 'order' => 'match_time.asc', 'limit' => 50]) ?? [];
        } catch (\Exception $e) {
            $rows = [];
        }

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//PersonalHub//Matches//EN\r\nCALSCALE:GREGORIAN\r\n";
        foreach ($rows as $r) {
            $start = Carbon::parse($r['match_time'] ?? now());
            $end = (clone $start)->addHours(2);
            $summary = $this->icsEscape(trim(($r['home_team'] ?? $r['competition'] ?? $r['sport_type'] ?? 'Match').(isset($r['away_team']) && $r['away_team'] ? ' vs '.$r['away_team'] : '')));
            $desc = $this->icsEscape(($r['competition'] ?? '').' '.($r['sport_type'] ?? ''));
            $uid = ($r['id'] ?? uniqid()).'@personal-hub';
            $ics .= "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:".now()->format('Ymd\THis\Z')."\r\nDTSTART:".$start->format('Ymd\THis\Z')."\r\nDTEND:".$end->format('Ymd\THis\Z')."\r\nSUMMARY:{$summary}\r\nDESCRIPTION:{$desc}\r\nEND:VEVENT\r\n";
        }
        $ics .= "END:VCALENDAR\r\n";

        return response()->streamDownload(function () use ($ics) {
            echo $ics;
        }, 'matches.ics', ['Content-Type' => 'text/calendar; charset=utf-8']);
    }

    private function icsEscape(string $s): string
    {
        return str_replace([',', ';', "\n", "\r"], ['\\,', '\\;', '\\n', ''], $s);
    }
}
