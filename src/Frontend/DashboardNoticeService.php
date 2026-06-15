<?php

namespace HabitTracker\Frontend;

if (! defined('ABSPATH')) {
    exit;
}

final class DashboardNoticeService
{
    public function getMessages(): array
    {
        return [
            'checkin-checked' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--success',
                'text' => __('Habit checked for today.', 'habit-tracker'),
            ],
            'checkin-unchecked' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--info',
                'text' => __('Habit unchecked for today.', 'habit-tracker'),
            ],
            'checkin-invalid' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--info',
                'text' => __('This habit is not available for check-in.', 'habit-tracker'),
            ],
            'checkin-failed' => [
                'class' => 'habit-tracker-notice habit-tracker-notice--error',
                'text' => __('Could not update check status.', 'habit-tracker'),
            ],
        ];
    }

    public function getPayload(string $notice): array
    {
        $messages = $this->getMessages();
        $fallback = $messages['checkin-failed'];
        $resolved = $messages[$notice] ?? $fallback;

        return [
            'key' => $notice,
            'class' => (string) ($resolved['class'] ?? $fallback['class']),
            'text' => (string) ($resolved['text'] ?? $fallback['text']),
        ];
    }
}
