<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\PomodoroDefaults;
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

    public function execute(int $userId, int $expectedEndsAtMs): void
    {
        if (! $this->sender->isConfigured()) {
            return;
        }

        $state = $this->pomodoroStates->findByUserId($userId);
        if ($state === null || ! $state->isRunning || $state->endsAt === null) {
            return;
        }

        if ((int) $state->endsAt !== $expectedEndsAtMs) {
            return;
        }

        $nowMs = (int) floor(microtime(true) * 1000);
        if ($state->endsAt > $nowMs + 1500) {
            // Fired too early — reschedule.
            $this->schedulePomodoroPhasePush->execute($userId, $state->endsAt, true);

            return;
        }

        $fromPhase = $state->phase;
        $advanced = $this->advancePomodoroPhase->advance($state);
        $this->pomodoroStates->save($advanced);
        PomodoroStreamVersion::bump($userId);

        $fromLabel = $this->phaseLabel($fromPhase);
        $toLabel = $this->phaseLabel($advanced->phase);
        $title = $fromPhase === PomodoroDefaults::PHASE_FOCUS
            ? 'Hết phiên tập trung'
            : 'Hết giờ nghỉ';
        $body = "{$fromLabel} → {$toLabel}. Bấm để mở Matrix.";
        $icon = $advanced->phase === PomodoroDefaults::PHASE_FOCUS
            ? '/images/todos/work.gif'
            : '/images/todos/relax.gif';

        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => (string) config('web-push.matrix_url', '/matrix'),
            'tag' => 'todo-pomodoro-phase',
            'icon' => $icon,
        ];

        $focusTtl = max(15, (int) config('web-push.focus_ttl_seconds', 60));
        $cutoff = time() - $focusTtl;

        foreach ($this->subscriptions->listByUserId($userId) as $subscription) {
            if ($subscription->lastFocusedAt !== null
                && $subscription->lastFocusedAt->getTimestamp() >= $cutoff
            ) {
                continue;
            }

            $ok = $this->sender->send($subscription, $payload);
            if (! $ok) {
                $this->subscriptions->deleteByEndpointOnly($subscription->endpoint);
            }
        }

        if ($advanced->isRunning && $advanced->endsAt !== null) {
            $this->schedulePomodoroPhasePush->execute($userId, $advanced->endsAt, true);
        }
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
