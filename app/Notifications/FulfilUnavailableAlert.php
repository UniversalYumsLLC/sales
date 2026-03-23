<?php

namespace App\Notifications;

use App\Exceptions\FulfilUnavailableException;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class FulfilUnavailableAlert extends Notification
{
    use Queueable;

    public function __construct(
        protected FulfilUnavailableException $exception
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $endpoint = $this->exception->getEndpoint();
        $method = strtoupper($this->exception->getMethod());
        $timeout = $this->exception->getTimeoutSeconds();
        $originalError = $this->exception->getPrevious()?->getMessage() ?? 'Unknown';

        return (new SlackMessage)
            ->to('#engineering')
            ->headerBlock('Fulfil API Unreachable')
            ->sectionBlock(function (SectionBlock $block) {
                $block->text('The application failed to reach the Fulfil API. User-facing operations that depend on Fulfil are currently broken.');
            })
            ->dividerBlock()
            ->sectionBlock(function (SectionBlock $block) use ($method, $endpoint) {
                $block->field("*Request*\n`{$method} {$endpoint}`");
            })
            ->sectionBlock(function (SectionBlock $block) use ($timeout) {
                $block->field("*Timeout*\n{$timeout}s with 0 bytes received");
            })
            ->sectionBlock(function (SectionBlock $block) use ($originalError) {
                $block->field("*Error*\n```{$originalError}```");
            })
            ->sectionBlock(function (SectionBlock $block) {
                $block->field('*Environment*\n`'.config('app.env').'`');
            })
            ->sectionBlock(function (SectionBlock $block) {
                $block->field('*Time*\n'.now()->toDateTimeString().' UTC');
            });
    }
}
