<?php

namespace Modules\Todo\Application;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Todo\Domain\PomodoroDefaults;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;
use Modules\Todo\Domain\Ports\WebPushSender;
use Modules\Todo\Domain\Ports\WebPushSubscriptionRepository;

final class DeliverPomodoroPhasePush
{
    private const DEDUPE_TTL_SECONDS = 60 * 60 * 6;

    public function __construct(
        private readonly PomodoroStateRepository $pomodoroStates,
        private readonly WebPushSubscriptionRepository $subscriptions,
        private readonly WebPushSender $sender,
        private readonly AdvancePomodoroPhase $advancePomodoroPhase,
        private readonly SchedulePomodoroPhasePush $schedulePomodoroPhasePush,
    ) {}

    public function execute(int $userId, int $expectedEndsAtMs, string $fromPhase): void
    {
        if (! $this->sender->isConfigured()) {
            return;
        }

        $dedupeKey = $this->dedupeKey($userId, $expectedEndsAtMs);
        if (Cache::has($dedupeKey)) {
            return;
        }

        $state = $this->pomodoroStates->findByUserId($userId);
        if ($state === null) {
            return;
        }

        $nowMs = (int) floor(microtime(true) * 1000);
        $matchesCurrent = $state->isRunning
            && $state->endsAt !== null
            && (int) $state->endsAt === $expectedEndsAtMs;

        // Still scheduled in the future for this endsAt — wait.
        if ($matchesCurrent && $expectedEndsAtMs > $nowMs + 1500) {
            $this->schedulePomodoroPhasePush->execute(
                $userId,
                $state->endsAt,
                true,
                $state->phase,
            );

            return;
        }

        // Stale delayed job from an older timer that was replaced before it was due.
        if (! $matchesCurrent && $expectedEndsAtMs > $nowMs + 1500) {
            return;
        }

        $phase = in_array($fromPhase, PomodoroDefaults::PHASES, true)
            ? $fromPhase
            : ($matchesCurrent ? $state->phase : PomodoroDefaults::PHASE_FOCUS);

        // Mark early so concurrent workers / double jobs don't double-notify.
        Cache::put($dedupeKey, 1, self::DEDUPE_TTL_SECONDS);

        $toPhase = $state->phase;
        if ($matchesCurrent) {
            $advanced = $this->advancePomodoroPhase->advance($state);
            $toPhase = $advanced->phase;
            $this->pomodoroStates->save($advanced);
            PomodoroStreamVersion::bump($userId);
            $state = $advanced;
        }

        // Always attempt push for this endsAt — even if a client already advanced
        // the timer (race). Per-device focus decides who receives the banner.
        $this->sendPhaseEndPush($userId, $phase, $toPhase);

        Log::info('web-push.pomodoro.delivered', [
            'user_id' => $userId,
            'expected_ends_at' => $expectedEndsAtMs,
            'from_phase' => $phase,
            'to_phase' => $toPhase,
            'advanced' => $matchesCurrent,
        ]);

        if ($state->isRunning && $state->endsAt !== null) {
            $this->schedulePomodoroPhasePush->execute(
                $userId,
                $state->endsAt,
                true,
                $state->phase,
            );
        }
    }

    private function sendPhaseEndPush(int $userId, string $fromPhase, string $toPhase): void
    {
        $fromLabel = $this->phaseLabel($fromPhase);
        $toLabel = $this->phaseLabel($toPhase);
        $title = $fromPhase === PomodoroDefaults::PHASE_FOCUS
            ? 'Hết phiên tập trung'
            : 'Hết giờ nghỉ';
        $body = "{$fromLabel} → {$toLabel}. Bấm để mở Matrix.";
        $icon = $toPhase === PomodoroDefaults::PHASE_FOCUS
            ? url('/images/todos/work.gif')
            : url('/images/todos/relax.gif');

        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => (string) config('web-push.matrix_url', '/matrix'),
            'tag' => 'todo-pomodoro-phase',
            'icon' => $icon,
        ];

        // Per-device: skip only subscriptions that reported Matrix focus recently
        // (laptop tab OR iOS PWA currently looking). TTL must exceed heartbeat interval.
        $focusTtl = max(15, min(60, (int) config('web-push.focus_ttl_seconds', 30)));
        $cutoff = time() - $focusTtl;
        $sent = 0;
        $skippedFocused = 0;

        foreach ($this->subscriptions->listByUserId($userId) as $subscription) {
            if (
                $subscription->lastFocusedAt !== null
                && $subscription->lastFocusedAt->getTimestamp() >= $cutoff
            ) {
                $skippedFocused++;
                continue;
            }

            $ok = $this->sender->send($subscription, $payload);
            if (! $ok) {
                $this->subscriptions->deleteByEndpointOnly($subscription->endpoint);
                continue;
            }
            $sent++;
        }

        Log::info('web-push.pomodoro.send_summary', [
            'user_id' => $userId,
            'sent' => $sent,
            'skipped_focused' => $skippedFocused,
            'focus_ttl_seconds' => $focusTtl,
        ]);
    }

    private function dedupeKey(int $userId, int $expectedEndsAtMs): string
    {
        return "todo.pomodoro.push.{$userId}.{$expectedEndsAtMs}";
    }

    private function phaseLabel(string $phase): string
    {
        return match ($phase) {
            PomodoroDefaults::PHASE_FOCUS => 'Tập trung',
            PomodoroDefaults::PHASE_SHORT_BREAK => 'Nghỉ ngắn',
            PomodoroDefaults::PHASE_LONG_BREAK => 'Nghỉ dài',
            default => $phase,
        };
    }
}
