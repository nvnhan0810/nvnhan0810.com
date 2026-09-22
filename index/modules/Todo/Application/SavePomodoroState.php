<?php

namespace Modules\Todo\Application;

use Modules\Todo\Domain\PomodoroDefaults;
use Modules\Todo\Domain\PomodoroState;
use Modules\Todo\Domain\PomodoroStreamVersion;
use Modules\Todo\Domain\Ports\PomodoroStateRepository;

final class SavePomodoroState
{
    public function __construct(
        private readonly PomodoroStateRepository $repository,
        private readonly SchedulePomodoroPhasePush $schedulePomodoroPhasePush,
    ) {}

    /**
     * @param  array{
     *   settings: array{
     *     focusMinutes: int|float|string,
     *     shortBreakMinutes: int|float|string,
     *     sessionsBeforeLongBreak: int|float|string,
     *     longBreakMinutes: int|float|string
     *   },
     *   runtime: array{
     *     phase: string,
     *     remainingMs: int|float|string,
     *     endsAt: int|float|string|null,
     *     focusCount: int|float|string,
     *     activeTodoId: int|float|string|null,
     *     isRunning: bool,
     *     updatedAt: int|float|string
     *   }
     * }  $payload
     * @return array{
     *   settings: array{
     *     focusMinutes: int,
     *     shortBreakMinutes: int,
     *     sessionsBeforeLongBreak: int,
     *     longBreakMinutes: int
     *   },
     *   runtime: array{
     *     phase: string,
     *     remainingMs: int,
     *     endsAt: int|null,
     *     focusCount: int,
     *     activeTodoId: int|null,
     *     isRunning: bool,
     *     updatedAt: int
     *   },
     *   version: int,
     *   accepted: bool
     * }
     */
    public function execute(int $userId, array $payload): array
    {
        $incoming = $this->fromPayload($userId, $payload);
        $existing = $this->repository->findByUserId($userId);

        if ($existing !== null && $incoming->clientUpdatedAt < $existing->clientUpdatedAt) {
            return [
                ...$existing->toPayload(PomodoroStreamVersion::current($userId)),
                'accepted' => false,
            ];
        }

        if ($existing !== null && $incoming->clientUpdatedAt === $existing->clientUpdatedAt) {
            return [
                ...$existing->toPayload(PomodoroStreamVersion::current($userId)),
                'accepted' => true,
            ];
        }

        $saved = $this->repository->save($incoming);
        $version = PomodoroStreamVersion::bump($userId);

        $this->schedulePomodoroPhasePush->execute(
            $userId,
            $saved->endsAt,
            $saved->isRunning,
        );

        return [
            ...$saved->toPayload($version),
            'accepted' => true,
        ];
    }

    /**
     * @param  array{
     *   settings: array{
     *     focusMinutes: int|float|string,
     *     shortBreakMinutes: int|float|string,
     *     sessionsBeforeLongBreak: int|float|string,
     *     longBreakMinutes: int|float|string
     *   },
     *   runtime: array{
     *     phase: string,
     *     remainingMs: int|float|string,
     *     endsAt: int|float|string|null,
     *     focusCount: int|float|string,
     *     activeTodoId: int|float|string|null,
     *     isRunning: bool,
     *     updatedAt: int|float|string
     *   }
     * }  $payload
     */
    private function fromPayload(int $userId, array $payload): PomodoroState
    {
        $settings = $payload['settings'];
        $runtime = $payload['runtime'];

        $phase = in_array($runtime['phase'], PomodoroDefaults::PHASES, true)
            ? $runtime['phase']
            : PomodoroDefaults::PHASE_FOCUS;

        $endsAt = $runtime['endsAt'];
        $activeTodoId = $runtime['activeTodoId'];

        return new PomodoroState(
            userId: $userId,
            focusMinutes: $this->clampInt($settings['focusMinutes'], 1, 180, PomodoroDefaults::FOCUS_MINUTES),
            shortBreakMinutes: $this->clampInt($settings['shortBreakMinutes'], 1, 60, PomodoroDefaults::SHORT_BREAK_MINUTES),
            sessionsBeforeLongBreak: $this->clampInt(
                $settings['sessionsBeforeLongBreak'],
                1,
                12,
                PomodoroDefaults::SESSIONS_BEFORE_LONG_BREAK,
            ),
            longBreakMinutes: $this->clampInt($settings['longBreakMinutes'], 1, 60, PomodoroDefaults::LONG_BREAK_MINUTES),
            phase: $phase,
            remainingMs: $this->clampInt($runtime['remainingMs'], 0, 24 * 60 * 60_000, PomodoroDefaults::remainingMsForFocus()),
            endsAt: is_numeric($endsAt) ? (int) $endsAt : null,
            focusCount: $this->clampInt($runtime['focusCount'], 0, 12, 0),
            activeTodoId: is_numeric($activeTodoId) ? (int) $activeTodoId : null,
            isRunning: $runtime['isRunning'] === true,
            clientUpdatedAt: $this->clampInt($runtime['updatedAt'], 0, PHP_INT_MAX, 0),
        );
    }

    private function clampInt(mixed $value, int $min, int $max, int $fallback): int
    {
        if (! is_numeric($value)) {
            return $fallback;
        }

        $n = (int) round((float) $value);

        return min($max, max($min, $n));
    }
}
