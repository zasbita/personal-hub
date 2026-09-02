<?php

namespace App\Services;

class SportPrefsService
{
    /** Sport types MatchNotifier actually knows how to check. */
    public const SPORTS = ['football', 'volly', 'motogp', 'moto2', 'moto3', 'baggers', 'mobilelegend', 'futsal'];

    /** Alias → canonical sport_type (ponytail: keep SPORTS unique, normalize at boundary) */
    public const ALIASES = ['mlbb' => 'mobilelegend', 'ml' => 'mobilelegend', 'sepakbola' => 'football', 'sepak-bola' => 'football'];

    public const MOTO_GROUP = ['motogp', 'moto2', 'moto3', 'baggers'];

    public static function normalizeSport(string $sport): string
    {
        $s = strtolower(trim($sport));

        return self::ALIASES[$s] ?? $s;
    }

    public static function isMotoGroup(string $sport): bool
    {
        return in_array(self::normalizeSport($sport), self::MOTO_GROUP, true);
    }

    /** For motogp filter return all 4 classes, else single canonical. */
    public static function expandSport(string $sport): array
    {
        $n = self::normalizeSport($sport);
        if ($n === 'motogp') {
            return self::MOTO_GROUP;
        }

        return [$n];
    }

    private SupabaseService $supabase;

    public function __construct(SupabaseService $s)
    {
        $this->supabase = $s;
    }

    public function addPreference(int $userId, string $sportType, string $entityId, string $entityName): void
    {
        try {
            $this->supabase->insert('user_preferences', ['user_id' => $userId, 'sport_type' => $sportType, 'entity_id' => $entityId, 'entity_name' => $entityName, 'notification_enabled' => true]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), '23505')) {
                $this->supabase->update('user_preferences', ['notification_enabled' => true], ['user_id' => "eq.{$userId}", 'sport_type' => "eq.{$sportType}", 'entity_id' => "eq.{$entityId}"]);
            } else {
                throw $e;
            }
        }
    }

    public function removePreference(int $userId, string $sportType, string $entityId): void
    {
        $this->supabase->delete('user_preferences', ['user_id' => "eq.{$userId}", 'sport_type' => "eq.{$sportType}", 'entity_id' => "eq.{$entityId}"]);
    }

    public function getPreferences(int $userId): array
    {
        return $this->supabase->select('user_preferences', ['user_id' => "eq.{$userId}", 'order' => 'created_at.desc']);
    }

    public function getActivePreferences(): array
    {
        return $this->supabase->select('user_preferences', ['notification_enabled' => 'eq.true']);
    }
}
