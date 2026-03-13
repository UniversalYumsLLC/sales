<?php

namespace App\Notifications;

use App\Models\GmailSyncHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GmailSyncResult extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public GmailSyncHistory $syncHistory
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Only use database notifications (in-app)
        return ['database'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isSuccess = $this->syncHistory->status === GmailSyncHistory::STATUS_COMPLETED;
        $isDomainSync = $this->syncHistory->isDomainSync();

        return [
            'sync_history_id' => $this->syncHistory->id,
            'sync_type' => $this->syncHistory->sync_type,
            'entity_type' => $this->syncHistory->entity_type,
            'entity_id' => $this->syncHistory->entity_id,
            'domains' => $this->syncHistory->domains,
            'status' => $this->syncHistory->status,
            'emails_fetched' => $this->syncHistory->emails_fetched,
            'emails_matched' => $this->syncHistory->emails_matched,
            'error_message' => $this->syncHistory->error_message,
            'title' => $this->getTitle($isSuccess, $isDomainSync),
            'message' => $this->getMessage($isSuccess, $isDomainSync),
            'type' => $isSuccess ? 'success' : 'error',
        ];
    }

    /**
     * Get the notification title.
     */
    protected function getTitle(bool $isSuccess, bool $isDomainSync): string
    {
        if ($isDomainSync) {
            return $isSuccess ? 'Email Sync Complete' : 'Email Sync Failed';
        }

        return $isSuccess ? 'Gmail Sync Complete' : 'Gmail Sync Failed';
    }

    /**
     * Get the notification message.
     */
    protected function getMessage(bool $isSuccess, bool $isDomainSync): string
    {
        if (! $isSuccess) {
            $error = $this->syncHistory->error_message ?? 'Unknown error';
            if ($isDomainSync) {
                $domains = $this->syncHistory->domains ? implode(', ', $this->syncHistory->domains) : 'unknown';

                return "Failed to sync emails for {$domains}: {$error}";
            }

            return "Gmail sync failed: {$error}";
        }

        $fetched = $this->syncHistory->emails_fetched ?? 0;
        $matched = $this->syncHistory->emails_matched ?? 0;

        if ($isDomainSync) {
            $domains = $this->syncHistory->domains ? implode(', ', $this->syncHistory->domains) : 'unknown';
            $entityType = $this->syncHistory->entity_type === 'prospect' ? 'prospect' : 'customer';

            return "Synced emails for new {$entityType} ({$domains}): {$matched} emails matched.";
        }

        return "Gmail sync completed: {$fetched} emails fetched, {$matched} matched to prospects/customers.";
    }
}
