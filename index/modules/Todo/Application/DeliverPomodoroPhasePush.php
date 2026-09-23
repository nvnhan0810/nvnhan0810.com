<?php

namespace Modules\Todo\Application;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Todo\Domain\PomodoroDefaults;
use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;
use Modules\Todo\Domain\Ports\WebPushSender;
use Modules\Todo\Domain\Ports\WebPushSubscriptionRepository;

final class DeliverPomodoroPhasePush
{
    public function __construct(
        private readonly PomodoroStateRepository $pomodoroStates,
        private readonly WebPushSubscriptionRepository $subscriptions,
        private readonly WebPushSender $sender,
        private readonly AdvancePomodoroPhase $advancePomodoroPhase,
        private readonly SchedulePomodoroPhasePush $schedulePomodoroPhasePush,
    ) {}

    public function execute(
        int $userId,
        string $sessionUuid,
        int $expectedEndsAtMs,
        string $fromPhase,
    ): void {
        $state = $this->pomodoroStates->findByUserId($userId);
        if ($state === null) {
            return;
        }

        // Stale / cancelled / replaced session — no-op (no push, no advance).
        if (
            ! $state->isRunning
            || $state->sessionUuid === null
            || $state->sessionUuid !== $sessionUuid
            || $state->endsAt === null
            || (int) $state->endsAt !== $expectedEndsAtMs
        ) {
            Log::info('web-push.pomodoro.job_stale', [
                'user_id' => $userId,
                'job_session' => $sessionUuid,
                'state_session' => $state->sessionUuid,
                'expected_ends_at' => $expectedEndsAtMs,
                'state_ends_at' => $state->endsAt,
            ]);

            return;
        }

        $nowMs = PomodoroState::nowMs();
        // Fired early — reschedule for the same session.
        if ($expectedEndsAtMs > $nowMs + 1500) {
            $this->schedulePomodoroPhasePush->execute(
                $userId,
                $state->sessionUuid,
                $state->endsAt,
                true,
                $state->phase,
            );

            return;
        }

        $phase = in_array($fromPhase, PomodoroDefaults::PHASES, true)
            ? $fromPhase
            : $state->phase;

        $advanced = $this->advancePomodoroPhase->advance($state);
        $nextUuid = (string) Str::uuid();
        $next = new PomodoroState(
            userId: $advanced->userId,
            focusMinutes: $advanced->focusMinutes,
            shortBreakMinutes: $advanced->shortBreakMinutes,
            sessionsBeforeLongBreak: $advanced->sessionsBeforeLongBreak,
            longBreakMinutes: $advanced->longBreakMinutes,
            phase: $advanced->phase,
            remainingMs: $advanced->remainingMs,
            endsAt: $advanced->endsAt,
            focusCount: $advanced->focusCount,
            activeTodoId: $advanced->activeTodoId,
            isRunning: $advanced->isRunning,
            clientUpdatedAt: PomodoroState::nowMs(),
            sessionUuid: $advanced->isRunning ? $nextUuid : null,
            lastFocusedAt: $advanced->lastFocusedAt,
        );

        $this->pomodoroStates->save($next);
        PomodoroStreamVersion::bump($userId);

        // Push is optional; phase change + SSE always happen.
        $pushed = false;
        if ($this->sender->isConfigured() && $this->shouldPush($next)) {
            $this->sendPhaseEndPush($userId, $phase, $next->phase, $sessionUuid);
            $pushed = true;
        } else {
            Log::info('web-push.pomodoro.push_skipped', [
                'user_id' => $userId,
                'session_uuid' => $sessionUuid,
                'reason' => $this->sender->isConfigured() ? 'user_focused' : 'vapid_unconfigured',
            ]);
        }

        Log::info('web-push.pomodoro.delivered', [
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
            'next_session_uuid' => $next->sessionUuid,
            'from_phase' => $phase,
            'to_phase' => $next->phase,
            'pushed' => $pushed,
        ]);

        if ($next->isRunning && $next->endsAt !== null && $next->sessionUuid !== null) {
            $this->schedulePomodoroPhasePush->execute(
                $userId,
                $next->sessionUuid,
                $next->endsAt,
                true,
                $next->phase,
                $sessionUuid,
            );
        }
    }

    private function shouldPush(PomodoroState $state): bool
    {
        $ttl = max(5, min(60, (int) config('web-push.focus_ttl_seconds', 15)));
        if ($state->lastFocusedAt === null) {
            return true;
        }

        $age = time() - $state->lastFocusedAt->getTimestamp();

        return $age >= $ttl;
    }

    private function sendPhaseEndPush(
        int $userId,
        string $fromPhase,
        string $toPhase,
        string $sessionUuid,
    ): void {
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
            // Apple: Topic max 32 chars, URL-safe Base64 alphabet only (a-zA-Z0-9_-).
            // UUID without hyphens is exactly 32 hex chars — unique per phase session.
            'topic' => str_replace('-', '', $sessionUuid),
        ];

        $sent = 0;
        foreach ($this->subscriptions->listByUserId($userId) as $subscription) {
            $ok = $this->sender->send($subscription, $payload);
            if (! $ok) {
                $this->subscriptions->deleteByEndpointOnly($subscription->endpoint);
                continue;
            }
            $sent++;
        }

        Log::info('web-push.pomodoro.send_summary', [
            'user_id' => $userId,
            'session_uuid' => $sessionUuid,
            'sent' => $sent,
        ]);
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
